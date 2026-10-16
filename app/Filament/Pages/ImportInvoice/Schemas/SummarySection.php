<?php

namespace App\Filament\Pages\ImportInvoice\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Actions\Action;
use App\Models\Supplier;
use Illuminate\Support\HtmlString;

class SummarySection
{
    public static function make(): Section
    {
        return Section::make('Resumo da Compra')
            ->description('Confirme o fornecedor vinculado a esta nota.')
            ->icon('heroicon-m-building-storefront')
            ->columns(['md' => 3])
            ->headerActions([self::getDanfeAction()])
            ->schema([
                Placeholder::make('xml_info')
                    ->label('Dados no XML')
                    ->content(fn (Get $get) => new HtmlString('<span class="font-medium text-gray-900 dark:text-white">' . $get('xml_supplier_name') . '</span><br><span class="text-sm text-gray-500">CNPJ: ' . $get('xml_supplier_cnpj') . '</span>'))
                    ->columnSpan(1),

                Placeholder::make('xml_total')
                    ->label('Valor Total da Nota')
                    ->content(fn (Get $get) => new HtmlString('<span class="font-black text-lg text-primary-600">R$ ' . number_format((float) $get('xml_total_amount'), 2, ',', '.') . '</span>'))
                    ->columnSpan(1),

                self::getSupplierSelectField(),
            ]);
    }

    private static function getSupplierSelectField(): Select
    {
        return Select::make("supplier_id")
            ->label("Fornecedor no Sistema")
            ->hint("Vínculo Interno")
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => Supplier::where('name', 'like', "%{$search}%")->orWhere('document', 'like', "%{$search}%")->limit(50)->where('is_active', true)->pluck('name', 'id')->toArray())
            ->getOptionLabelUsing(fn ($value): ?string => Supplier::find($value)?->name)
            // 🚀 AQUI: Substitui o formulário cheio de Tabs pelo modelo direto
            ->createOptionForm(\App\Filament\Resources\Suppliers\Schemas\SupplierForm::getQuickCreateSchema())
            ->createOptionAction(function ($action) {
                return $action
                    ->modalHeading('Cadastro Rápido de Fornecedor')
                    ->modalWidth('md')
                    ->mountUsing(function ($form, $livewire) {
                        $form->fill([
                            "document" => $livewire->data["xml_supplier_cnpj"] ?? null,
                            "document_name" => $livewire->data["xml_supplier_name"] ?? null,
                            // Extraímos o nome fantasia caso a nota traga a SEFAZ suja
                            "name" => $livewire->data["xml_supplier_trade_name"] ?? $livewire->data["xml_supplier_name"] ?? null,
                        ]);
                    });
            })
            ->createOptionUsing(function (array $data, $livewire) {
                return Supplier::create([
                    // Pega estritamente o que foi digitado no modal, recaindo no XML caso o usuário deixe o campo nome em branco (incomum pois é required)
                    "name" => $data["name"] ?? $livewire->data["xml_supplier_trade_name"] ?? $livewire->data["xml_supplier_name"] ?? "S/ Fornecedor",
                    "document_name" => $data["document_name"] ?? $livewire->data["xml_supplier_name"] ?? null,
                    "document" => $data["document"] ?? $livewire->data["xml_supplier_cnpj"] ?? null,
                    "type" => $data["type"] ?? "physical",
                    "is_personal" => $data["is_personal"] ?? false,
                ])->id;
            })
            ->required()
            ->columnSpan(1);
    }

    private static function getDanfeAction(): Action
    {
        return Action::make('view_danfe')
            ->label('Visualizar DANFE')
            ->icon('heroicon-o-document-text')
            ->color('gray')
            ->modalHeading('Documento Auxiliar da Nota Fiscal (DANFE)')
            ->modalWidth('7xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->modalContent(function (Get $get) {
                $invoice = \App\Models\ReceivedInvoice::find($get('received_invoice_id'));
                if (!$invoice || empty($invoice->xml_content) || str_contains($invoice->xml_content, 'resNFe')) {
                    return new HtmlString('<div class="p-4 text-center text-gray-500">O XML completo ainda não está disponível para esta nota.</div>');
                }
                try {
                    $danfe = new \NFePHP\DA\NFe\Danfe($invoice->xml_content);
                    $pdf = $danfe->render();
                    $base64 = base64_encode($pdf);
                    return new HtmlString("<iframe src='data:application/pdf;base64,{$base64}#toolbar=0' width='100%' height='700px' style='border: none; border-radius: 8px;'></iframe>");
                } catch (\Exception $e) {
                    return new HtmlString("<div class='p-4 text-red-500'>Erro ao gerar PDF: {$e->getMessage()}</div>");
                }
            });
    }
}
