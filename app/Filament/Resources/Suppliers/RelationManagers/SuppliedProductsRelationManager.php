<?php

namespace App\Filament\Resources\Suppliers\RelationManagers;

use App\Models\Product;
use App\Models\ProductCostHistory;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class SuppliedProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'suppliedProducts';

    protected static ?string $title = 'Itens Fornecidos & Inteligência de Compras';

    protected static ?string $modelLabel = 'Insumo / Suprimento';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-shopping-bag';

    public function table(Table $table): Table
    {
        $supplier = $this->getOwnerRecord();
        $familyIds = !empty($supplier->family_ids) ? $supplier->family_ids : [$supplier->id];
        $familyPlaceholders = implode(',', array_map('intval', $familyIds));

        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('total_spent', 'desc')
            ->modifyQueryUsing(function (Builder $query) use ($familyPlaceholders) {
                // 🚀 Garante select limpo e agrupa pelo ID do produto para eliminar repetições
                return $query
                    ->select('products.*')
                    ->whereHas('costHistories', fn ($q) => $q->whereRaw("supplier_id IN ({$familyPlaceholders})"))
                    ->groupBy('products.id')
                    ->addSelect([
                        'total_spent' => DB::table('product_cost_histories')
                            ->selectRaw('COALESCE(SUM(unit_cost * quantity), 0)')
                            ->whereColumn('product_cost_histories.product_id', 'products.id')
                            ->whereRaw("supplier_id IN ({$familyPlaceholders})"),

                        'total_qty' => DB::table('product_cost_histories')
                            ->selectRaw('COALESCE(SUM(quantity), 0)')
                            ->whereColumn('product_cost_histories.product_id', 'products.id')
                            ->whereRaw("supplier_id IN ({$familyPlaceholders})"),

                        'avg_purchase_cost' => DB::table('product_cost_histories')
                            ->selectRaw('COALESCE(SUM(unit_cost * quantity) / NULLIF(SUM(quantity), 0), 0)')
                            ->whereColumn('product_cost_histories.product_id', 'products.id')
                            ->whereRaw("supplier_id IN ({$familyPlaceholders})"),

                        'last_purchase_cost' => DB::table('product_cost_histories')
                            ->select('unit_cost')
                            ->whereColumn('product_cost_histories.product_id', 'products.id')
                            ->whereRaw("supplier_id IN ({$familyPlaceholders})")
                            ->orderByDesc('id')
                            ->limit(1),

                        'last_purchase_date' => DB::table('product_cost_histories')
                            ->select('created_at')
                            ->whereColumn('product_cost_histories.product_id', 'products.id')
                            ->whereRaw("supplier_id IN ({$familyPlaceholders})")
                            ->orderByDesc('id')
                            ->limit(1),

                        'first_purchase_date' => DB::table('product_cost_histories')
                            ->select('created_at')
                            ->whereColumn('product_cost_histories.product_id', 'products.id')
                            ->whereRaw("supplier_id IN ({$familyPlaceholders})")
                            ->orderBy('id', 'asc')
                            ->limit(1),

                        'other_suppliers_count' => DB::table('product_cost_histories')
                            ->selectRaw('COUNT(DISTINCT supplier_id)')
                            ->whereColumn('product_cost_histories.product_id', 'products.id')
                            ->whereRaw("supplier_id NOT IN ({$familyPlaceholders})"),
                    ]);
            })
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Insumo / Suprimento')
                    ->weight('bold')
                    ->searchable()
                    ->sortable()
                    ->description(function (Product $record) {
                        $uom = strtoupper($record->unit_of_measure ?? 'UN');
                        $cat = $record->category?->name ?? 'Geral';
                        return "{$cat} • Estoque Atual: " . number_format((float)$record->current_stock, 2, ',', '.') . " {$uom}";
                    }),

                Tables\Columns\TextColumn::make('monthly_avg')
                    ->label('Média Mensal')
                    ->state(function (Product $record) {
                        $totalQty = (float) ($record->total_qty ?? 0);
                        if ($totalQty <= 0) return '-';

                        $firstDate = $record->first_purchase_date ? Carbon::parse($record->first_purchase_date) : null;
                        $months = $firstDate ? max(1, (int) ceil($firstDate->diffInDays(now()) / 30.4)) : 1;
                        $monthlyQty = $totalQty / $months;

                        $uom = strtoupper($record->unit_of_measure ?? 'UN');
                        return number_format($monthlyQty, 1, ',', '.') . " {$uom}/mês";
                    })
                    ->badge()
                    ->color('info')
                    ->description(fn (Product $record) => "Total: " . number_format((float) ($record->total_qty ?? 0), 1, ',', '.') . " " . strtoupper($record->unit_of_measure ?? 'UN')),

                Tables\Columns\TextColumn::make('avg_purchase_cost')
                    ->label('Custo Médio')
                    ->state(function (Product $record) {
                        $cost = (float) ($record->avg_purchase_cost ?? 0);
                        $uom = strtolower($record->unit_of_measure ?? 'un');
                        return 'R$ ' . number_format($cost, 4, ',', '.') . " /{$uom}";
                    })
                    ->sortable()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('last_purchase_cost')
                    ->label('Última Compra')
                    ->state(function (Product $record) {
                        $cost = (float) ($record->last_purchase_cost ?? 0);
                        $uom = strtolower($record->unit_of_measure ?? 'un');
                        return 'R$ ' . number_format($cost, 4, ',', '.') . " /{$uom}";
                    })
                    ->weight('bold')
                    ->sortable()
                    ->description(fn (Product $record) => $record->last_purchase_date ? Carbon::parse($record->last_purchase_date)->format('d/m/Y') : null),

                Tables\Columns\TextColumn::make('cost_inflation')
                    ->label('Oscilação')
                    ->state(function (Product $record) {
                        $last = (float) ($record->last_purchase_cost ?? 0);
                        $avg = (float) ($record->avg_purchase_cost ?? 0);
                        if ($last <= 0 || $avg <= 0) return 'Estável';

                        $diff = (($last - $avg) / $avg) * 100;
                        $prefix = $diff > 0 ? '+' : '';
                        return $prefix . number_format($diff, 1, ',', '.') . '%';
                    })
                    ->badge()
                    ->color(function (Product $record) {
                        $last = (float) ($record->last_purchase_cost ?? 0);
                        $avg = (float) ($record->avg_purchase_cost ?? 0);
                        if ($last <= 0 || $avg <= 0) return 'gray';

                        $diff = (($last - $avg) / $avg) * 100;
                        if ($diff > 8) return 'danger';
                        if ($diff < -3) return 'success';
                        return 'gray';
                    })
                    ->icon(function (Product $record) {
                        $last = (float) ($record->last_purchase_cost ?? 0);
                        $avg = (float) ($record->avg_purchase_cost ?? 0);
                        if ($last <= 0 || $avg <= 0) return null;

                        $diff = (($last - $avg) / $avg) * 100;
                        if ($diff > 8) return 'heroicon-m-arrow-trending-up';
                        if ($diff < -3) return 'heroicon-m-arrow-trending-down';
                        return null;
                    }),

                Tables\Columns\TextColumn::make('total_spent')
                    ->label('Gasto Total')
                    ->money('BRL')
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('exclusivity')
                    ->label('Dependência')
                    ->state(function (Product $record) {
                        $others = (int) ($record->other_suppliers_count ?? 0);
                        return $others === 0 ? 'Exclusivo' : "+{$others} fornecedor(es)";
                    })
                    ->badge()
                    ->color(fn (Product $record) => ((int) ($record->other_suppliers_count ?? 0)) === 0 ? 'danger' : 'gray')
                    ->tooltip(fn (Product $record) => ((int) ($record->other_suppliers_count ?? 0)) === 0
                        ? 'Atenção: Você não possui outro fornecedor cadastrado para este insumo!'
                        : 'Você já comprou este item de outros fornecedores alternativos.'
                    ),
            ])
            ->filters([
                Tables\Filters\Filter::make('inflated_only')
                    ->label('🚨 Preço Subiu na Última Compra')
                    ->query(fn (Builder $query) => $query->whereRaw("
                        (SELECT unit_cost FROM product_cost_histories WHERE product_id = products.id AND supplier_id IN ({$familyPlaceholders}) ORDER BY id DESC LIMIT 1)
                        >
                        (SELECT SUM(unit_cost * quantity) / NULLIF(SUM(quantity), 0) FROM product_cost_histories WHERE product_id = products.id AND supplier_id IN ({$familyPlaceholders})) * 1.05
                    ")),

                Tables\Filters\Filter::make('exclusive_only')
                    ->label('⚠️ Apenas Fornecedores Exclusivos')
                    ->query(fn (Builder $query) => $query->whereRaw("
                        (SELECT COUNT(DISTINCT supplier_id) FROM product_cost_histories WHERE product_id = products.id AND supplier_id NOT IN ({$familyPlaceholders})) = 0
                    ")),
            ])
            ->actions([
                Action::make('compare_market')
                    ->label('Comparar Preços')
                    ->icon('heroicon-m-scale')
                    ->color('info')
                    ->modalHeading(fn (Product $record) => "Benchmark de Preços: {$record->name}")
                    ->modalDescription('Preço praticado neste fornecedor comparado às últimas notas fiscais de concorrentes.')
                    ->modalWidth('2xl')
                    ->modalContent(function (Product $record) use ($familyIds) {
                        $quotes = ProductCostHistory::where('product_id', $record->id)
                            ->with('supplier')
                            ->orderByDesc('id')
                            ->get()
                            ->unique('supplier_id');

                        $uom = strtolower($record->unit_of_measure ?? 'un');
                        $currentSupplierCost = (float) ($record->last_purchase_cost ?? 0);

                        $rowsHtml = '';
                        foreach ($quotes as $quote) {
                            $isCurrent = in_array((int) $quote->supplier_id, $familyIds, true);
                            $supplierName = $quote->supplier?->name ?? 'Fornecedor Avulso';
                            $cost = (float) $quote->unit_cost;
                            $diff = $currentSupplierCost > 0 ? (($cost - $currentSupplierCost) / $currentSupplierCost) * 100 : 0;
                            $date = Carbon::parse($quote->created_at)->format('d/m/Y');

                            $tag = $isCurrent
                                ? "<span class='px-2 py-0.5 text-[10px] rounded font-bold bg-primary-100 text-primary-800'>Este Fornecedor</span>"
                                : "<span class='px-2 py-0.5 text-[10px] rounded font-bold bg-gray-100 text-gray-700'>Concorrente</span>";

                            $diffBadge = '';
                            if (!$isCurrent && $currentSupplierCost > 0) {
                                if ($diff < -1) {
                                    $diffBadge = "<span class='text-emerald-600 font-bold'>" . number_format(abs($diff), 1, ',', '.') . "% mais barato</span>";
                                } elseif ($diff > 1) {
                                    $diffBadge = "<span class='text-rose-600 font-bold'>+" . number_format($diff, 1, ',', '.') . "% mais caro</span>";
                                } else {
                                    $diffBadge = "<span class='text-gray-500 font-medium'>Preço igual</span>";
                                }
                            }

                            $rowsHtml .= "
                                <tr class='border-b border-gray-100 dark:border-gray-800 " . ($isCurrent ? "bg-primary-50/40 dark:bg-primary-950/20" : "") . "'>
                                    <td class='px-3 py-2.5'>
                                        <div class='font-bold text-gray-900 dark:text-white'>{$supplierName}</div>
                                        <div class='mt-0.5'>{$tag}</div>
                                    </td>
                                    <td class='px-3 py-2.5 text-gray-600 dark:text-gray-400'>{$date}</td>
                                    <td class='px-3 py-2.5 font-bold font-mono text-gray-900 dark:text-white'>
                                        R$ " . number_format($cost, 4, ',', '.') . " /{$uom}
                                    </td>
                                    <td class='px-3 py-2.5 text-right'>{$diffBadge}</td>
                                </tr>
                            ";
                        }

                        return new HtmlString("
                            <div class='rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden text-xs'>
                                <table class='w-full text-left'>
                                    <thead class='bg-gray-50 dark:bg-gray-900 text-gray-500 uppercase'>
                                        <tr>
                                            <th class='px-3 py-2'>Fornecedor</th>
                                            <th class='px-3 py-2'>Última Nota</th>
                                            <th class='px-3 py-2'>Preço Unitário</th>
                                            <th class='px-3 py-2 text-right'>Variação</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {$rowsHtml}
                                    </tbody>
                                </table>
                            </div>
                        ");
                    }),
            ]);
    }
}
