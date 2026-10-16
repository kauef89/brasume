<?php

namespace App\Filament\Pages\ImportInvoice\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Illuminate\Support\HtmlString;
use App\Models\Product;
use Illuminate\Support\Number;
use App\Services\Purchases\InvoiceItemProcessor;

class ApproveSmartMatchesAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'approve_smart_matches';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Automação')
            ->color('warning')
            ->icon('heroicon-m-bolt')
            ->button()
            ->visible(function ($livewire) {
                $items = $livewire->data['xml_items'] ?? [];
                return collect($items)->where('is_matched', 1)->where('confirmation_step', 0)->count() > 0;
            })
            ->modalHeading('Aprovação Rápida de Itens')
            ->modalDescription('A nossa inteligência encontrou vínculos no seu histórico. Desmarque os que não fizerem sentido, ou aprove todos de uma só vez.')
            ->modalSubmitActionLabel('Aprovar')
            ->modalWidth('5xl')
            ->schema(function ($livewire) {
                $items = $livewire->data['xml_items'] ?? [];
                $options = [];

                foreach ($items as $index => $item) {
                    if ($item['is_matched'] == 1 && $item['confirmation_step'] == 0) {
                        $product = Product::find($item['product_id']);
                        $productName = $product ? $product->name : 'Insumo Desconhecido';

                        // Busca a unidade de medida do sistema
                        $productUnit = $product ? strtoupper($product->unit_of_measure ?? '') : '';

                        // Multiplica para achar a Entrada Real
                        $totalEntryFloat = (float)($item['xml_qty']) * (float)($item['conversion_factor']);

                        // Formata para PT-BR usando o Laravel Number
                        $qtyBr = Number::format((float) $item['xml_qty'], maxPrecision: 4);
                        $totalEntryBr = Number::format($totalEntryFloat, maxPrecision: 4);

                        $label = "
                            <div style='display: flex; width: 100%; gap: 1rem; align-items: center;' class='text-sm py-1.5 border-b border-gray-100 dark:border-gray-800 border-dashed'>
                                <div style='flex: 5; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;' class='font-semibold' title='{$item['xml_name']}'>
                                    {$item['xml_name']}
                                </div>
                                <div style='flex: 4; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;' class='font-medium' title='{$productName}'>
                                    {$productName}
                                </div>
                                <div style='flex: 2; min-width: 0;'>
                                    {$qtyBr} {$item['xml_uom']}
                                </div>
                                <div style='flex: 1; text-align: right; min-width: 0;'>
                                    {$totalEntryBr} <span class='text-gray-400 dark:text-gray-500 text-xs ml-1 font-medium'>{$productUnit}</span>
                                </div>
                            </div>
                        ";

                        $options[$index] = new HtmlString($label);
                    }
                }

                return [
                    Grid::make(6)->schema([

                        // 🚀 COLUNAS 1 A 5: A TABELA DE ITENS
                        Group::make([
                            Placeholder::make('table_header')
                                ->hiddenLabel()
                                ->content(new HtmlString("
                                    <style>
                                        .smart-match-list { width: 100% !important; }
                                        .smart-match-list > div { width: 100% !important; display: block !important; }
                                        .smart-match-list label.fi-checkbox-list-option { width: 100% !important; display: flex !important; max-width: none !important; }
                                        .smart-match-list .fi-checkbox-list-option-label { width: 100% !important; flex: 1 1 auto !important; max-width: none !important; }
                                        .fi-fo-checkbox-list .fi-fo-checkbox-list-option .fi-fo-checkbox-list-option-text { width: 100%;}
                                    </style>

                                    <div style='display: flex; width: 100%; gap: 1rem; padding-bottom: 0.5rem; padding-left: 2.25rem;' class='text-xs font-medium uppercase tracking-wider border-b border-gray-300 dark:border-gray-700'>
                                        <div style='flex: 5;'>Produto na Nota (XML)</div>
                                        <div style='flex: 4;'>Insumo Vinculado</div>
                                        <div style='flex: 2;'>Qtd. na Nota</div>
                                        <div style='flex: 1; text-align: right;'>Entrada</div>
                                    </div>
                                ")),

                            CheckboxList::make('approved_indexes')
                                ->hiddenLabel()
                                ->options($options)
                                ->default(array_keys($options))
                                ->bulkToggleable()
                                ->columns(1)
                                ->extraAttributes([
                                    'class' => 'smart-match-list max-h-[60vh] overflow-y-auto pr-2',
                                ]),
                        ])->columnSpan(5),

                        // 🚀 COLUNA 6: O PAINEL DE AUTOMAÇÃO
                        Group::make([
                            Placeholder::make('auto_approve_header')
                                ->hiddenLabel()
                                ->content(new HtmlString("
                                    <div class='flex flex-col items-center justify-center text-center'>
                                        <div class='w-12 h-12 rounded-full bg-white dark:bg-gray-800 shadow-sm flex items-center justify-center mb-3'>
                                            <svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='2' stroke='currentColor' class='w-6 h-6 text-amber-600 dark:text-amber-400'>
                                                <path stroke-linecap='round' stroke-linejoin='round' d='m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z' />
                                            </svg>
                                        </div>
                                        <h3 class='font-medium text-[13px]'>Auto Aprovar</h3>
                                    </div>
                                ")),

                            Toggle::make('auto_approve_future')
                                ->hiddenLabel()
                                 ->extraFieldWrapperAttributes(['class' => 'h-full flex flex-col items-center'])
                                ->default(false),

                            Placeholder::make('auto_approve_helper')
                                ->hiddenLabel()
                                ->content(new HtmlString("
                                    <div class='text-[11px] text-center'>
                                        Ative esta opção para o sistema processar automaticamente estes itens na próxima nota fiscal recebida.
                                    </div>
                                ")),

                            \Filament\Schemas\Components\Actions::make([
                                Action::make('manage_automations')
                                    ->label('Gerenciar Regras')
                                    ->icon('heroicon-m-cog-6-tooth')
                                    ->color('primary')
                                    ->link()
                                    ->size('xs')
                                    ->url(fn() => \App\Filament\Pages\ManageAutomations::getUrl(), shouldOpenInNewTab: true)
                            ])
                            ->alignCenter()
                            ->extraAttributes(['class' => 'mt-2 opacity-70 hover:opacity-100 transition-opacity']),
                        ])
                        ->columnSpan(1)
                        ->extraAttributes([
                            'class' => 'bg-amber-50 dark:bg-amber-500/10 rounded-2xl p-4 flex flex-col items-center justify-center h-full',
                            'style' => 'border: none; min-height: 100%;'
                        ]),

                    ]),
                ];
            })
            ->action(function (array $data, $livewire) {
                $approvedIndexes = $data['approved_indexes'] ?? [];

                // 🚀 A VACINA APLICADA NO MODAL: Lemos direto da variável pública do Livewire
                $items = $livewire->data['xml_items'] ?? [];

                $invoiceKey = $livewire->data['invoice_key'] ?? '';
                $supplierId = $livewire->data['supplier_id'] ?? null;

                $autoApprove = $data['auto_approve_future'] ?? false;
                $processor = app(InvoiceItemProcessor::class);

                $processedCount = 0;

                // Aqui $uuid é exatamente o índice que vem da CheckboxList
                foreach ($approvedIndexes as $uuid) {
                    if (isset($items[$uuid])) {
                        // Processa o item e salva no banco
                        $processor->processDirectItem($items[$uuid], $invoiceKey, $supplierId, $autoApprove);

                        // Remove o item preservando o UUID e sem usar array_values
                        unset($items[$uuid]);
                        $processedCount++;
                    }
                }

                if ($processedCount > 0) {
                    // 🚀 Devolvemos a array para o componente sem usar o form->fill()
                    $livewire->data['xml_items'] = $items;

                    \Filament\Notifications\Notification::make()
                        ->title($processedCount . ' itens processados com sucesso!')
                        ->success()
                        ->send();

                    $livewire->dispatch('items-processed');
                }
            });
    }
}
