<?php

namespace App\Filament\Resources\ReceivedInvoices\Pages;

use App\Filament\Resources\ReceivedInvoices\ReceivedInvoiceResource;
use App\Filament\Resources\ReceivedInvoices\Actions\ForceSefazSyncAction;
use App\Filament\Pages\ImportInvoice;
use App\Models\CompanySetting;
use App\Models\ReceivedInvoice;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Artisan;
use Filament\Notifications\Notification;
use Filament\Schemas\Components as SchemaComponents;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use App\Filament\Traits\TeleportsTabs;
use App\Filament\Traits\HasWelcomeModal;
use Illuminate\Support\HtmlString;

class ManageReceivedInvoices extends ManageRecords
{
    protected static string $resource = ReceivedInvoiceResource::class;

    use TeleportsTabs;
    use HasWelcomeModal; // 🚀 Injetamos o Trait!

    public function mount(): void
    {
        parent::mount();
        $this->checkWelcomeModal();
    }

    public function getTabs(): array
    {
        return [
            "pendentes" => SchemaComponents\Tabs\Tab::make("Pendências")
                ->icon("heroicon-m-clock")
                ->modifyQueryUsing(
                    fn(Builder $query) => $query->whereIn("status", [
                        "pending",
                        "manifested",
                        "ready",
                        "return_pending",
                        "expired_manifestation",
                    ]),
                )
                ->badge(
                    fn() => ReceivedInvoice::whereIn("status", [
                        "pending",
                        "manifested",
                        "ready",
                        "return_pending",
                        "expired_manifestation",
                    ])->count(),
                )
                ->badgeColor("warning"),

            "historico" => SchemaComponents\Tabs\Tab::make("Histórico")
                ->icon("heroicon-m-check-badge")
                ->modifyQueryUsing(
                    fn(Builder $query) => $query->whereIn("status", [
                        "processed",
                        "rejected",
                        "canceled_by_supplier",
                        "returned",       // 🚀 A nota de compra após você clicar em processar
                        "return_receipt", // 🚀 O documento XML da devolução (escondido das pendências)
                    ]),
                ),
        ];
    }

    protected function getHeaderActions(): array
        {
            $setting = CompanySetting::getCached();
            $hasCertificate = $setting && !empty($setting->certificate_path);

            return [
                $this->getWelcomeAction(),

                Action::make("upload_manual")
                    ->label("Subir Manualmente")
                    ->icon("heroicon-o-arrow-up-tray")
                    ->color("gray")
                    ->url(fn() => ImportInvoice::getUrl())
                    ->disabled(! $hasCertificate),

                ForceSefazSyncAction::make(),
            ];
        }

    protected function getWelcomeMessage(): HtmlString
    {
        return new HtmlString('
            <div class="wm-container">
                <p>
                    Esta é a sua central de entrada de mercadorias. Aqui você gerencia todas as Notas Fiscais emitidas contra o seu CNPJ e alimenta o seu estoque de forma automatizada.
                </p>

                <div class="wm-alert-box">
                    <h4 class="wm-alert-title">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                        Atenção: Certificado Digital A1
                    </h4>
                    <p class="wm-alert-text">
                        Para que a busca automática funcione, o sistema precisa se autenticar na Receita Federal. Acesse <strong>Configurações da Empresa</strong> e certifique-se de que o seu Certificado A1 (.pfx) e a senha foram preenchidos corretamente.
                    </p>
                </div>

                <div class="wm-step-list">
                    <div class="wm-step-item">
                        <div class="wm-step-icon">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        </div>
                        <div>
                            <p class="wm-step-title">1. Sincronizar SEFAZ</p>
                            <p class="wm-step-description">Busca novas notas diretamente da Receita Federal. Por regras da própria SEFAZ, só é possível realizar essa varredura <strong>1 vez por hora</strong>.</p>
                        </div>
                    </div>

                    <div class="wm-step-item">
                        <div class="wm-step-icon">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <div>
                            <p class="wm-step-title">2. Dar Ciência e Importar</p>
                            <p class="wm-step-description">As notas novas aparecem como <strong>Pendentes</strong>. Você deve clicar em "Dar Ciência" para liberar o XML e, depois, em "Importar" para processar os itens.</p>
                        </div>
                    </div>
                </div>

                <div class="wm-tip-box">
                    <p class="wm-tip-text">
                        <strong>Dica:</strong> Se uma nota estiver errada, você pode estornar o lote na aba "Histórico" para devolvê-la à fila de entrada.
                    </p>
                </div>
            </div>
        ');
    }
}
