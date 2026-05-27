<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Action;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;
use App\Models\ReceivedInvoice;
use App\Filament\Resources\ReceivedInvoices\ReceivedInvoiceResource;
use App\Filament\Pages\ImportInvoice\Schemas\ImportInvoiceForm;
use App\Services\InvoiceImportService;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Facades\Blade;

// 🚀 IMPORTS CORRIGIDOS (Sempre no topo do arquivo!)
use App\Filament\Resources\ReceivedInvoices\Actions\ViewDanfeAction;
use App\Filament\Traits\HasWelcomeModal;

class ImportInvoice extends Page implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;
    use HasWelcomeModal;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-arrow-up';
    protected static string | \UnitEnum | null $navigationGroup = 'Estoque & Compras';
    protected static ?string $title = 'Importar Compras';
    protected static bool $shouldRegisterNavigation = false;
    protected string $view = 'filament.pages.import-invoice';

    public ?array $data = [];
    public ?string $xmlContent = null;

    // 🚀 Propriedade para guardarmos a nota
    public ?ReceivedInvoice $invoiceRecord = null;

    public function mount(): void
    {
        $this->checkWelcomeModal();

        $invoiceId = request()->query('invoice') ?? request()->query('nfe') ?? request()->query('record');
        $initialData = [];

        if ($invoiceId) {
            $this->invoiceRecord = ReceivedInvoice::find($invoiceId);

            if ($this->invoiceRecord && $this->invoiceRecord->xml_content) {
                $processor = app(InvoiceImportService::class);
                $initialData = $processor->extractDataFromXml($this->invoiceRecord->xml_content, $this->invoiceRecord->id);
            }
        }

        $this->data = $initialData;
        $this->form->fill($this->data);

        FilamentView::registerRenderHook(
            'tables::toolbar.search.before',
            function () {
                $invoiceKey = $this->data['invoice_key'] ?? 'none';

                return Blade::render('@include("filament.components.tabs-render-hook", [
                    "stockCount" => \App\Models\ProductCostHistory::where("nfe_key", $invoiceKey)->count(),
                    "personalCount" => \App\Models\PersonalPurchase::where("nfe_key", $invoiceKey)->count(),
                ])', ['invoiceKey' => $invoiceKey]);
            },
            scopes: [
                \App\Livewire\ProcessedStockTable::class,
                \App\Livewire\ProcessedPersonalTable::class,
            ],
        );
    }

    // 🚀 AQUI ENTRAM OS BOTÕES DO CABEÇALHO
    protected function getHeaderActions(): array
    {
        return [
            // Botão da DANFE
            ViewDanfeAction::make()
                ->record($this->invoiceRecord) // Injeta a nota atual na Action
                ->button()
                ->label('Visualizar DANFE')
                ->outlined()
                ->color('gray')
                ->visible(fn () =>
                    $this->invoiceRecord !== null &&
                    !empty($this->invoiceRecord->xml_content) &&
                    !str_contains($this->invoiceRecord->xml_content, 'resNFe')
                ),

            // Botão de Ajuda gerado pelo Trait
            $this->getWelcomeAction(),
        ];
    }

    protected function getViewData(): array
    {
        $invoiceKey = $this->data['invoice_key'] ?? 'none';

        return [
            'stockCount' => \App\Models\ProductCostHistory::where('nfe_key', $invoiceKey)->count(),
            'personalCount' => \App\Models\PersonalPurchase::where('nfe_key', $invoiceKey)->count(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components(ImportInvoiceForm::schema());
    }

    public function submit(): void
    {
        $items = $this->data['xml_items'] ?? [];

        if (count($items) > 0) {
            $this->mountAction('warningModal');
            return;
        }

        app(InvoiceImportService::class)->finalizeInvoice($this->form->getState());

        Notification::make()->title('Nota Fiscal Finalizada!')->success()->send();
        $this->redirect(ReceivedInvoiceResource::getUrl('index'));
    }

    public function warningModalAction(): Action
    {
        return Action::make('warningModal')
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalHeading('Atenção: Itens Pendentes')
            ->modalDescription(new HtmlString(
                'Ainda existem itens na nota aguardando processamento.<br><br>Você deve processar <strong>todos os itens</strong> para poder finalizar a importação de vez.'
            ))
            ->modalSubmitActionLabel('Sair e Voltar Para a Lista')
            ->modalCancelActionLabel('Continuar Processando')
            ->color('warning')
            ->action(fn() => redirect()->to(ReceivedInvoiceResource::getUrl('index')));
    }

    public function restoreToPendingQueue(array $itemData, bool $isPersonal): void
    {
        $xmlItems = $this->data['xml_items'] ?? [];
        $xmlItems[] = [
            'xml_name' => $itemData['xml_name'],
            'xml_qty' => $itemData['xml_qty'] ?? $itemData['available_qty'] ?? 1,
            'available_qty' => $itemData['xml_qty'] ?? $itemData['available_qty'] ?? 1,
            'xml_cost' => $itemData['xml_cost'],
            'xml_uom' => $itemData['xml_uom'],
            'conversion_factor' => $itemData['conversion_factor'] ?? 1,
            'product_id' => $isPersonal ? null : ($itemData['product_id'] ?? null),
            'is_matched' => $isPersonal ? 0 : 1,
            'item_uuid' => Str::uuid()->toString(),
            'confirmation_step' => 0,
            'factor_source' => 'manual',
        ];

        $this->data['xml_items'] = collect($xmlItems)->sortByDesc('is_matched')->values()->toArray();
    }

    #[On('items-restored')]
    public function handleRestoredItems(array $items, bool $isPersonal): void
    {
        foreach ($items as $itemData) {
            $this->restoreToPendingQueue($itemData, $isPersonal);
        }
        $this->form->fill($this->data);
    }

    protected function getWelcomeMessage(): HtmlString
    {
        return new HtmlString('
            <div class="wm-container">
                <p>
                    Você está na mesa de operação! Aqui transformamos o XML do fornecedor em <strong>atualização de Estoque</strong> e <strong>Contas a Pagar</strong>.
                </p>

                <div class="wm-step-list">
                    <div class="wm-step-item">
                        <div class="wm-step-icon">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244"></path></svg>
                        </div>
                        <div>
                            <p class="wm-step-title">1. Amarração de Insumos</p>
                            <p class="wm-step-description">Vincule os produtos da nota aos insumos do seu estoque. O sistema aprenderá essas relações e as preencherá automaticamente nas próximas compras!</p>
                        </div>
                    </div>

                    <div class="wm-step-item">
                        <div class="wm-step-icon">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        </div>
                        <div>
                            <p class="wm-step-title">2. Fator de Conversão</p>
                            <p class="wm-step-description">Se o fornecedor vende em CAIXA e você controla em UNIDADE, use a engrenagem para configurar a conversão (ex: 1 CX = 12 UN) e manter seu estoque correto.</p>
                        </div>
                    </div>

                    <div class="wm-step-item">
                        <div class="wm-step-icon">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        </div>
                        <div>
                            <p class="wm-step-title">3. Uso Pessoal ou Consumo</p>
                            <p class="wm-step-description">Itens que não pertencem ao estoque da loja devem ser marcados como "Uso Pessoal". Isso separa esses custos das suas fichas técnicas automaticamente.</p>
                        </div>
                    </div>
                </div>

                <div class="wm-alert-box">
                    <h4 class="wm-alert-title">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        Errou alguma coisa? Não se preocupe!
                    </h4>
                    <p class="wm-alert-text">
                        Nas tabelas inferiores de <strong>Itens Processados</strong>, você pode clicar em "Desfazer". O item retornará para a lista de pendências acima para você corrigir amarrações ou fatores.
                    </p>
                </div>

                <div class="wm-tip-box">
                    <p class="wm-tip-text">
                        <strong>Dica:</strong> A nota só pode ser finalizada após todos os itens serem processados ou movidos para Uso Pessoal.
                    </p>
                </div>
            </div>
        ');
    }
}
