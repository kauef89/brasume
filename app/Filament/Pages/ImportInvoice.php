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
use Illuminate\Support\Facades\Blade;
use Livewire\Attributes\On;

use App\Models\ReceivedInvoice;
use App\Models\ProductCostHistory;
use App\Models\PersonalPurchase;
use App\Filament\Resources\ReceivedInvoices\ReceivedInvoiceResource;
use App\Filament\Pages\ImportInvoice\Schemas\ImportInvoiceForm;
use App\Filament\Pages\ImportInvoice\Actions\ApproveSmartMatchesAction;
use App\Filament\Traits\HasWelcomeModal;
use App\Services\Purchases\InvoiceImportService;
use App\Services\Purchases\InvoiceItemProcessor;
use Filament\Support\Facades\FilamentView;

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
    public ?int $invoiceId = null;
    public ?ReceivedInvoice $invoiceRecord = null;

    public function mount(): void
    {
        $this->checkWelcomeModal();

        $rawInvoiceId = request()->query('invoice') ?? request()->query('nfe') ?? request()->query('record');
        $initialData = [];

        if ($rawInvoiceId) {
            $this->invoiceId = (int) $rawInvoiceId;
            $this->invoiceRecord = ReceivedInvoice::find($this->invoiceId);

            // Carrega o XML limpo apenas na primeira montagem da página
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
            ]
        );
    }

    public function getInvoiceRecord(): ?ReceivedInvoice
    {
        if ($this->invoiceRecord) {
            return $this->invoiceRecord;
        }

        $id = $this->invoiceId
            ?? ($this->data['received_invoice_id'] ?? null)
            ?? request()->query('invoice')
            ?? request()->query('nfe')
            ?? request()->query('record');

        return $this->invoiceRecord = $id ? ReceivedInvoice::find($id) : null;
    }

    public function getInvoiceProperty(): ?ReceivedInvoice
    {
        return $this->getInvoiceRecord();
    }

    public function getRecordProperty(): ?ReceivedInvoice
    {
        return $this->getInvoiceRecord();
    }

    /**
     * Processa a confirmação de um item e atualiza estritamente a chave xml_items.
     */
    public function confirmItem(array $payload): array
    {
        $processor = app(\App\Services\Purchases\InvoiceItemProcessor::class);
        $currentItems = $this->data['xml_items'] ?? [];

        $result = $processor->processConfirmedItems($payload, (array) $currentItems);

        $this->data['xml_items'] = array_values($result['updated_items'] ?? []);
        $this->form->fill($this->data);

        return $result;
    }

    protected function getHeaderActions(): array
    {
        return [
            ApproveSmartMatchesAction::make('approveSmartMatches'),
            $this->getWelcomeAction(),
        ];
    }

    protected function getViewData(): array
    {
        $invoiceKey = $this->data['invoice_key'] ?? 'none';
        return [
            'stockCount' => ProductCostHistory::where('nfe_key', $invoiceKey)->count(),
            'personalCount' => PersonalPurchase::where('nfe_key', $invoiceKey)->count(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components(ImportInvoiceForm::schema());
    }

    /**
     * Ação Nativa de Finalização (Lê diretamente do estado fresco do Livewire)
     */
    public function finishImportAction(): Action
    {
        return Action::make('finishImport')
            ->label('Finalizar Importação')
            ->color('primary')
            // Removemos o requiresConfirmation condicional daqui para forçar a execução direta
            ->action(function () {
                $state = $this->form->getState();
                $items = $state['xml_items'] ?? [];

                $pendingItems = collect($items)->where('confirmation_step', 0);

                // 🚀 SONDA: Se o sistema encontrar pendentes, trava tudo e cospe os dados!
                if ($pendingItems->isNotEmpty()) {
                    dd([
                        'ALERTA' => 'O SISTEMA ENCONTROU ITENS FANTASMAS!',
                        'QTD_PENDENTES' => $pendingItems->count(),
                        'QTD_TOTAL_NO_FORM' => count($items),
                        'ITENS_PENDENTES' => $pendingItems->toArray(),
                        'ESTADO_CRU_DO_LIVEWIRE' => $this->data['xml_items'] ?? []
                    ]);
                }

                // Se passar da sonda, é porque realmente estava vazio e deve finalizar
                app(\App\Services\Purchases\InvoiceImportService::class)->finalizeInvoice($state);

                \Filament\Notifications\Notification::make()
                    ->title('Nota Fiscal Finalizada!')
                    ->success()
                    ->send();

                return redirect()->to(\App\Filament\Resources\ReceivedInvoices\ReceivedInvoiceResource::getUrl('index'));
            });
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
                <p>Você está na mesa de operação! Aqui transformamos o XML do fornecedor em <strong>atualização de Estoque</strong> e <strong>Contas a Pagar</strong>.</p>
                <div class="wm-step-list">
                    <div class="wm-step-item">
                        <div class="wm-step-icon"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg></div>
                        <div><p class="wm-step-title">1. Aprovação Inteligente</p><p class="wm-step-description">Se o sistema encontrar produtos que você já vinculou antes, um botão aparecerá no topo para aprová-los em lote.</p></div>
                    </div>
                    <div class="wm-step-item">
                        <div class="wm-step-icon"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244"></path></svg></div>
                        <div><p class="wm-step-title">2. Amarração de Insumos</p><p class="wm-step-description">Vincule os produtos desconhecidos aos insumos do estoque. O sistema memorizará essas relações para as próximas notas.</p></div>
                    </div>
                    <div class="wm-step-item">
                        <div class="wm-step-icon"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg></div>
                        <div><p class="wm-step-title">3. Uso Pessoal ou Consumo</p><p class="wm-step-description">Itens fora da operação comercial devem ser marcados como "Uso Pessoal", preservando as fichas técnicas de CMV.</p></div>
                    </div>
                </div>
            </div>
        ');
    }
}
