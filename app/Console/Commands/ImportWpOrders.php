<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant;
use App\Models\LegacyOrder;
use App\Models\LegacyOrderItem;
use App\Models\LegacyOrderPayment;
use App\Models\LegacyProduct;
use App\Models\LegacyPaymentMethod;
use App\Models\LegacyImportLog;
use App\Models\LegacyModifier;
use App\Models\LegacyOrderItemModifier;
use Carbon\Carbon;

class ImportWpOrders extends Command
{
    // 🚀 1. Adicionamos a opção --days com 30 como padrão
    protected $signature = 'import:wp-orders {tenant=nativa} {--days=30}';
    protected $description = 'Suga os pedidos da V1 (Limitado por dias), agrupa modificadores, e faz match com XML.';

    public function handle()
    {
        $tenantId = $this->argument('tenant');
        $tenant = Tenant::find($tenantId);
        $days = (int) $this->option('days'); // 🚀 2. Resgata a quantidade de dias
        $cutoffDate = Carbon::now()->subDays($days); // 🚀 3. Define a data de corte

        if (!$tenant) {
            $this->error("❌ Lanchonete '{$tenantId}' não encontrada!");
            return;
        }

        tenancy()->initialize($tenant);
        $this->info("🔌 Conectando ao Banco V1 (WordPress) - Puxando últimos {$days} dias...");

        $wp = DB::connection('wordpress');
        $tag = 'WP_DB_' . now()->format('Ymd_His');

        // 🚀 4. Aplicamos o filtro ->where('created_at', '>=', $cutoffDate)
        $sessions = $wp->table('nativa_order_items')
            ->selectRaw('session_id, MIN(created_at) as issue_date, SUM(line_total) as total_items')
            ->whereNotNull('session_id')
            ->where('created_at', '>=', $cutoffDate)
            ->groupBy('session_id')
            ->get();

        $totalSessions = $sessions->count();
        $this->info("📦 {$totalSessions} sessões (pedidos) encontradas na V1.");

        $matchedCount = 0;
        $createdCount = 0;

        if ($totalSessions === 0) {
            $this->warn("Nenhum pedido recente encontrado para importar.");
            tenancy()->end();
            return;
        }

        $bar = $this->output->createProgressBar($totalSessions);

        foreach ($sessions as $s) {
            $sessionId = $s->session_id;
            try { $issueDate = Carbon::parse($s->issue_date); } catch (\Exception $e) { $issueDate = now(); }

            $totalItems = (float) $s->total_items;
            $totalPaid = $wp->table('nativa_transactions')->where('session_id', $sessionId)->where('amount', '>', 0)->sum('amount');
            $finalTotal = $totalPaid > 0 ? $totalPaid : $totalItems;

            if ($finalTotal <= 0) {
                $bar->advance(); continue;
            }

            $v3Order = LegacyOrder::whereNotNull('nfe_key')
                ->whereBetween('issue_date', [
                    $issueDate->copy()->subDay()->startOfDay(),
                    $issueDate->copy()->addDay()->endOfDay()
                ])
                ->where(function($q) use ($finalTotal, $totalItems) {
                    $q->whereBetween('total_value', [$finalTotal - 3.0, $finalTotal + 3.0])
                      ->orWhereBetween('total_value', [$totalItems - 3.0, $totalItems + 3.0]);
                })
                ->where(function($q) {
                    $q->whereNull('import_tag')
                      ->orWhere('import_tag', 'not like', '%WP_DB_%');
                })
                ->first();

            DB::beginTransaction();
            try {
                if ($v3Order) {
                    $v3Order->update([
                        'number' => $sessionId,
                        'order_type' => 'counter',
                        'import_tag' => $v3Order->import_tag . ' | ' . $tag
                    ]);
                    $orderModel = $v3Order;
                    $matchedCount++;
                } else {
                    $orderModel = LegacyOrder::create([
                        'import_tag' => $tag,
                        'number' => $sessionId,
                        'issue_date' => $issueDate,
                        'order_type' => 'counter',
                        'status' => 'completed',
                        'total_value' => $finalTotal,
                        'discount' => 0,
                    ]);
                    $createdCount++;
                }

                DB::table('legacy_order_items')->where('legacy_order_id', $orderModel->id)->delete();
                DB::table('legacy_order_payments')->where('legacy_order_id', $orderModel->id)->delete();

                $items = $wp->table('nativa_order_items')->where('session_id', $sessionId)->get();
                foreach ($items as $item) {
                    if (empty(trim($item->product_name))) continue;

                    $lProd = LegacyProduct::firstOrCreate(
                        ['name' => trim($item->product_name)],
                        ['legacy_code' => $item->product_id]
                    );

                    if ($lProd->is_ignored) continue;

                    $createdItem = LegacyOrderItem::create([
                        'legacy_order_id' => $orderModel->id,
                        'legacy_product_id' => $lProd->id,
                        'quantity' => (float) $item->quantity,
                        'unit_price' => (float) $item->unit_price,
                        'total_price' => (float) $item->line_total,
                    ]);

                    if (!empty($item->modifiers_json) && $item->modifiers_json !== '[]') {
                        $modifiersArray = json_decode($item->modifiers_json, true);
                        if (is_array($modifiersArray)) {
                            $groupedModifiers = [];
                            foreach ($modifiersArray as $mod) {
                                if (empty($mod['name'])) continue;
                                $modName = trim($mod['name']);
                                $modPrice = isset($mod['price']) ? (float) $mod['price'] : 0.0;
                                $modQty = isset($mod['qty']) ? (float) $mod['qty'] : 1.0;

                                if (!isset($groupedModifiers[$modName])) {
                                    $groupedModifiers[$modName] = ['price' => $modPrice, 'qty' => 0];
                                }
                                $groupedModifiers[$modName]['qty'] += $modQty;
                            }

                            foreach ($groupedModifiers as $modName => $data) {
                                $lMod = LegacyModifier::firstOrCreate(['name' => $modName]);
                                if (!$lMod->is_ignored) {
                                    LegacyOrderItemModifier::create([
                                        'legacy_order_item_id' => $createdItem->id,
                                        'legacy_modifier_id' => $lMod->id,
                                        'quantity' => $data['qty'],
                                        'price' => $data['price']
                                    ]);
                                }
                            }
                        }
                    }
                }

                $payments = $wp->table('nativa_transactions')->where('session_id', $sessionId)->where('amount', '>', 0)->get();
                foreach ($payments as $pay) {
                    if (empty(trim($pay->method))) continue;

                    $lPay = LegacyPaymentMethod::firstOrCreate(['name' => trim($pay->method)]);
                    LegacyOrderPayment::create([
                        'legacy_order_id' => $orderModel->id,
                        'legacy_payment_method_id' => $lPay->id,
                        'amount' => (float) $pay->amount,
                    ]);
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("\nErro na sessão {$sessionId}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();

        LegacyImportLog::create([
            "import_tag" => $tag,
            "type" => "v1_database",
            "items_count" => $matchedCount + $createdCount,
        ]);

        $this->info("\n\n🎉 Importação Híbrida salva no log: {$tag}");
        $this->info("=================================================");
        $this->info("Pedidos XML Enriquecidos (MATCH MADRUGADA): {$matchedCount}");
        $this->info("Novos Pedidos V1 Criados:                   {$createdCount}");
        $this->info("=================================================\n");

        tenancy()->end();
    }
}
