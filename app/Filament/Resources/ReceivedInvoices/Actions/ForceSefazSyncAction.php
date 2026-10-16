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
                \App\Jobs\SyncTenantSefazJob::dispatch($tenantId);

                Notification::make()
                    ->title("Sincronização Iniciada")
                    ->body("A varredura está acontecendo nos bastidores. Se houver novas notas, elas aparecerão aqui na tabela em breve.")
                    ->success()
                    ->send();
            }

            $setting = CompanySetting::first();
            if ($setting) {
                // 🚀 CORREÇÃO: Marca apenas a coluna de Sefaz, não afeta o update geral
                $setting->sefaz_last_sync_at = now();
                $setting->saveQuietly();
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
