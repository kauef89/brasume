<?php

namespace App\Filament\Resources\ReceivedInvoices\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use App\Models\CompanySetting;
use Carbon\Carbon;

class ForceSefazSyncAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'sync_sefaz';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(function () {
            extract($this->getSefazState());
            if (! $hasCertificate) return "Sincronia Indisponível";
            return $isOnCooldown ? "Sincronia Bloqueada ({$minutosRestantes}m)" : "Sincronizar SEFAZ";
        });

        $this->icon(function () {
            extract($this->getSefazState());
            if (! $hasCertificate) return "heroicon-m-exclamation-triangle";
            return $isOnCooldown ? "heroicon-m-lock-closed" : "heroicon-o-arrow-path";
        });

        $this->color(function () {
            extract($this->getSefazState());
            return (! $hasCertificate || $isOnCooldown) ? "gray" : "primary";
        });

        $this->disabled(function () {
            extract($this->getSefazState());
            return (! $hasCertificate || $isOnCooldown);
        });

        $this->action(function ($livewire) {
            $tenantId = tenant('id');

            if ($tenantId) {
                try {
                    // Executa sincronamente forçando o bypass de cooldown
                    \App\Jobs\SyncTenantSefazJob::dispatchSync($tenantId, true);

                    Notification::make()
                        ->title("Sincronização Concluída!")
                        ->body("A consulta na SEFAZ foi finalizada. As novas notas foram importadas para a tabela.")
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title("Erro na Sincronização SEFAZ")
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            }

            $livewire->dispatch('$refresh');
        });
    }

    private function getSefazState(): array
    {
        $setting = CompanySetting::getCached();
        $hasCertificate = $setting && !empty($setting->certificate_path);
        $isOnCooldown = false;
        $minutosRestantes = 0;

        // 🚀 CORREÇÃO: Ler exclusivamente sefaz_last_sync_at
        if ($setting && $setting->sefaz_last_sync_at) {
            $liberaEm = Carbon::parse($setting->sefaz_last_sync_at)->addMinutes(60);

            if (now()->lessThan($liberaEm)) {
                $isOnCooldown = true;
                $minutosRestantes = (int) ceil(now()->floatDiffInMinutes($liberaEm));
                if ($minutosRestantes <= 0) {
                    $minutosRestantes = 1;
                }
            }
        }

        return [
            'hasCertificate' => $hasCertificate,
            'isOnCooldown' => $isOnCooldown,
            'minutosRestantes' => $minutosRestantes,
        ];
    }
}
