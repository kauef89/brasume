<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ImportWpCatalog extends Command
{
    protected $signature = 'tenant:import-wp-catalog
                            {--tenant=nativa : Identificador do Tenant destino}
                            {--dry-run : Simula a importação sem persistir alterações}
                            {--rollback : Restaura o catálogo para o estado anterior ao último import}';

    protected $description = 'Importa catálogo do MVP WordPress com suporte a snapshot e rollback automático.';

    public function handle()
    {
        $tenantId = $this->option('tenant');
        $isDryRun = $this->option('dry-run');
        $isRollback = $this->option('rollback');

        if (!tenancy()->initialized) {
            tenancy()->initialize($tenantId);
        }

        $backupDir = storage_path("app/catalog_backups/{$tenantId}");

        // =========================================================================
        // MODO ROLLBACK: RESTAURA O ÚLTIMO SNAPSHOT
        // =========================================================================
        if ($isRollback) {
            return $this->executeRollback($backupDir);
        }

        if ($isDryRun) {
            $this->warn("⚠️  MODO DRY-RUN: Nenhuma alteração será feita no banco.");
        }

        // =========================================================================
        // SNAPSHOT DE SEGURANÇA (Antes de tocar no banco)
        // =========================================================================
        if (!$isDryRun) {
            $this->createSnapshot($backupDir, $tenantId);
        }

        try {
            DB::connection('wordpress')->getPdo();
        } catch (\Throwable $e) {
            $this->error("Erro ao conectar no banco legado 'wordpress': " . $e->getMessage());
            return 1;
        }

        // =========================================================================
        // 1. IMPORTAÇÃO DE CATEGORIAS
        // =========================================================================
        $this->info("1. Sincronizando Categorias...");

        $wpCategories = DB::connection('wordpress')->table('terms')
            ->join('term_taxonomy', 'terms.term_id', '=', 'term_taxonomy.term_id')
            ->whereIn('term_taxonomy.taxonomy', ['category', 'product_cat'])
            ->whereNotIn('terms.slug', ['sem-categoria', 'uncategorized'])
            ->select('terms.term_id', 'terms.name', 'terms.slug', 'term_taxonomy.description', 'term_taxonomy.parent')
            ->get();

        $categoryMap = [];
        $createdCats = 0;
        $updatedCats = 0;

        foreach ($wpCategories as $wpCat) {
            $name = html_entity_decode(trim($wpCat->name), ENT_QUOTES, 'UTF-8');
            $slug = Str::slug($wpCat->slug ?: $name);

            if (!$isDryRun) {
                $category = Category::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'name'        => $name,
                        'description' => $wpCat->description ?: null,
                        'is_active'   => true,
                        'status'      => 'active',
                        'visibility'  => 'both',
                        'type'        => 'food',
                    ]
                );
                $categoryMap[$wpCat->term_id] = $category->id;
                $category->wasRecentlyCreated ? $createdCats++ : $updatedCats++;
            } else {
                $categoryMap[$wpCat->term_id] = 1;
                $createdCats++;
            }
        }

        if (!$isDryRun) {
            foreach ($wpCategories as $wpCat) {
                if (!empty($wpCat->parent) && isset($categoryMap[$wpCat->parent], $categoryMap[$wpCat->term_id])) {
                    Category::where('id', $categoryMap[$wpCat->term_id])->update([
                        'parent_id' => $categoryMap[$wpCat->parent]
                    ]);
                }
            }
        }

        $this->line("Categorias: <comment>{$createdCats} criadas</comment>, <comment>{$updatedCats} atualizadas</comment>.");

        // =========================================================================
        // 2. IMPORTAÇÃO DE PRODUTOS
        // =========================================================================
        $this->info("2. Carregando Produtos Legados...");

        $wpPosts = DB::connection('wordpress')->table('posts')
            ->whereIn('post_type', ['nativa_produto', 'product'])
            ->whereIn('post_status', ['publish', 'draft', 'pending', 'private'])
            ->select('ID', 'post_title', 'post_content', 'post_excerpt', 'post_status')
            ->get();

        if ($wpPosts->isEmpty()) {
            $this->warn("Nenhum produto encontrado.");
            return 0;
        }

        $postIds = $wpPosts->pluck('ID')->toArray();

        $metaCollection = DB::connection('wordpress')->table('postmeta')
            ->whereIn('post_id', $postIds)
            ->get()
            ->groupBy('post_id');

        $catRelationships = DB::connection('wordpress')->table('term_relationships')
            ->join('term_taxonomy', 'term_relationships.term_taxonomy_id', '=', 'term_taxonomy.term_taxonomy_id')
            ->whereIn('term_taxonomy.taxonomy', ['category', 'product_cat'])
            ->whereIn('term_relationships.object_id', $postIds)
            ->select('term_relationships.object_id', 'term_taxonomy.term_id')
            ->get()
            ->groupBy('object_id');

        $unitRelationships = DB::connection('wordpress')->table('term_relationships')
            ->join('term_taxonomy', 'term_relationships.term_taxonomy_id', '=', 'term_taxonomy.term_taxonomy_id')
            ->join('terms', 'term_taxonomy.term_id', '=', 'terms.term_id')
            ->where('term_taxonomy.taxonomy', 'nativa_unidade_medida')
            ->whereIn('term_relationships.object_id', $postIds)
            ->select('term_relationships.object_id', 'terms.name')
            ->get()
            ->groupBy('object_id');

        $thumbIds = [];
        foreach ($metaCollection as $metas) {
            $tId = $metas->firstWhere('meta_key', '_thumbnail_id')?->meta_value;
            if ($tId) $thumbIds[] = (int) $tId;
        }

        $imagesMap = [];
        if (!empty($thumbIds)) {
            $imagesMap = DB::connection('wordpress')->table('posts')
                ->whereIn('ID', array_unique($thumbIds))
                ->pluck('guid', 'ID')
                ->toArray();
        }

        $bar = $this->output->createProgressBar($wpPosts->count());
        $bar->start();

        $created = 0;
        $updated = 0;

        foreach ($wpPosts as $wpPost) {
            $metas = $metaCollection->get($wpPost->ID, collect());

            $getMeta = fn($key, $default = null) => $metas->firstWhere('meta_key', $key)?->meta_value ?? $default;

            $price = (float) ($getMeta('produto_preco') ?: $getMeta('_regular_price') ?: 0);
            $promoPrice = (float) ($getMeta('produto_preco_promocional') ?: $getMeta('_sale_price') ?: 0);

            $isControlled = ($getMeta('nativa_stock_controlled') == '1' || $getMeta('nativa_estoque_ativo') == '1');
            $currentStock = (float) ($getMeta('nativa_estoque_qtd') ?: 0);
            $minStock = (float) ($getMeta('nativa_stock_min') ?: 0);

            $requiresPrep = ($getMeta('nativa_requires_preparation') !== '0');
            $stationId = is_numeric($getMeta('nativa_estacao_impressao')) ? (int) $getMeta('nativa_estacao_impressao') : null;
            $showDelivery = ($getMeta('nativa_exibir_delivery') !== '0');
            $showTable = ($getMeta('nativa_exibir_mesa') !== '0');
            $is18Plus = ($getMeta('nativa_apenas_maiores') == '1');
            $isLoyalty = ($getMeta('nativa_is_loyalty_reward') == '1');

            $baseCost = (float) ($getMeta('nativa_base_cost') ?: 0);
            $conversion = (float) ($getMeta('nativa_purchase_conversion') ?: 1);

            $unitTerm = $unitRelationships->get($wpPost->ID)?->first();
            $unit = $unitTerm ? $unitTerm->name : 'un';

            $fichaCount = (int) $getMeta('nativa_ficha_tecnica', 0);
            $ficha = [];
            for ($i = 0; $i < $fichaCount; $i++) {
                $itemId = (int) $getMeta("nativa_ficha_tecnica_{$i}_item_id");
                $quantity = (float) $getMeta("nativa_ficha_tecnica_{$i}_quantity");
                if ($itemId > 0) {
                    $ficha[] = ['item_id' => $itemId, 'quantity' => $quantity];
                }
            }

            $rawGroups = $getMeta('produto_grupos_adicionais');
            $groups = [];
            if ($rawGroups) {
                if (is_string($rawGroups) && str_starts_with($rawGroups, 'a:')) {
                    $unserialized = @unserialize($rawGroups);
                    $groups = is_array($unserialized) ? array_values($unserialized) : [];
                } elseif (is_string($rawGroups)) {
                    $groups = array_filter(array_map('trim', explode(',', $rawGroups)));
                }
            }

            $catRel = $catRelationships->get($wpPost->ID)?->first();
            $localCategoryId = $catRel ? ($categoryMap[$catRel->term_id] ?? null) : null;

            $thumbId = $getMeta('_thumbnail_id');
            $imagePath = $thumbId && isset($imagesMap[$thumbId]) ? $imagesMap[$thumbId] : null;

            $availability = $getMeta('produto_disponibilidade', 'disponivel');
            $isActive = ($wpPost->post_status === 'publish' && $availability === 'disponivel');

            $name = html_entity_decode(trim($wpPost->post_title), ENT_QUOTES, 'UTF-8');
            $rawDesc = !empty($wpPost->post_excerpt) ? $wpPost->post_excerpt : $wpPost->post_content;
            $cleanDesc = trim(strip_tags(html_entity_decode($rawDesc, ENT_QUOTES, 'UTF-8')));

            $sku = str_pad($wpPost->ID, 4, '0', STR_PAD_LEFT);

            $productData = [
                'category_id'             => $localCategoryId,
                'name'                    => $name,
                'description'             => $cleanDesc ?: null,
                'type'                    => 'simple',
                'unit_measure'            => $unit,
                'unit_of_measure'         => $unit,
                'sku'                     => $sku,
                'base_cost'               => $baseCost,
                'dine_in_price'           => $price,
                'delivery_price'          => $price,
                'promo_price'             => ($promoPrice > 0 && $promoPrice < $price) ? $promoPrice : null,
                'conversion_factor'       => $conversion,
                'track_inventory'         => $isControlled,
                'current_stock'           => $currentStock,
                'min_stock'               => $minStock,
                'status'                  => $isActive ? 'active' : 'inactive',
                'show_on_menu'            => $showTable,
                'show_on_delivery'        => $showDelivery,
                'is_age_restricted'       => $is18Plus,
                'requires_preparation'    => $requiresPrep,
                'print_station_id'        => $stationId,
                'loyalty_reward'          => $isLoyalty,
                'fiscal_ncm'              => $getMeta('nativa_prod_ncm'),
                'fiscal_cest'             => $getMeta('nativa_prod_cest'),
                'fiscal_cfop'             => $getMeta('nativa_prod_cfop'),
                'fiscal_origin'           => $getMeta('nativa_prod_origem'),
                'fiscal_csosn'            => $getMeta('nativa_prod_csosn'),
                'legacy_modifier_groups'  => !empty($groups) ? json_encode($groups) : null,
                'legacy_recipe'           => !empty($ficha) ? json_encode($ficha) : null,
            ];

            if ($imagePath) {
                $productData['image_path'] = $imagePath;
            }

            if (!$isDryRun) {
                $existing = Product::where('sku', $sku)->orWhere('name', $name)->first();

                if ($existing) {
                    $existing->update($productData);
                    $updated++;
                } else {
                    Product::create($productData);
                    $created++;
                }
            } else {
                $created++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Catálogo sincronizado com sucesso!");
        $this->line("Produtos Criados: <comment>{$created}</comment>");
        $this->line("Produtos Atualizados: <comment>{$updated}</comment>");
        $this->line("Dica: Em caso de inconsistência, reverta executando: <comment>php artisan tenant:import-wp-catalog --tenant={$tenantId} --rollback</comment>");

        return 0;
    }

    protected function createSnapshot(string $backupDir, string $tenantId): void
    {
        if (!File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $snapshotData = [
            'created_at' => now()->toDateTimeString(),
            'tenant_id'  => $tenantId,
            'categories' => Category::all()->toArray(),
            'products'   => Product::all()->toArray(),
        ];

        $filename = "snapshot_" . date('Y_m_d_His') . ".json";
        $filepath = "{$backupDir}/{$filename}";

        File::put($filepath, json_encode($snapshotData, JSON_PRETTY_PRINT));
        $this->info("📸 Snapshot do catálogo salvo em: {$filepath}");
    }

    protected function executeRollback(string $backupDir): int
    {
        if (!File::exists($backupDir)) {
            $this->error("Nenhum diretório de backup encontrado em: {$backupDir}");
            return 1;
        }

        $files = collect(File::files($backupDir))
            ->filter(fn($f) => str_starts_with($f->getFilename(), 'snapshot_') && $f->getExtension() === 'json')
            ->sortByDesc(fn($f) => $f->getMTime());

        if ($files->isEmpty()) {
            $this->error("Nenhum snapshot de backup disponível para rollback.");
            return 1;
        }

        $latestBackup = $files->first();
        $this->warn("Carregando snapshot: {$latestBackup->getFilename()}");

        $data = json_decode(File::get($latestBackup->getRealPath()), true);

        if (!$data || !isset($data['categories'], $data['products'])) {
            $this->error("Arquivo de snapshot corrompido ou inválido.");
            return 1;
        }

        if (!$this->confirm("Deseja realmente restaurar o catálogo gravado em [{$data['created_at']}]? O estado atual será substituído.", true)) {
            $this->line("Rollback cancelado pelo usuário.");
            return 0;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Restaura Categorias
        Category::truncate();
        foreach ($data['categories'] as $cat) {
            Category::create($cat);
        }

        // Restaura Produtos
        Product::truncate();
        foreach ($data['products'] as $prod) {
            Product::create($prod);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->info("✅ Rollback concluído com sucesso!");
        $this->line("Categorias restauradas: " . count($data['categories']));
        $this->line("Produtos restaurados: " . count($data['products']));

        return 0;
    }
}
