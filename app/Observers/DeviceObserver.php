<?php

namespace App\Observers;

use App\Models\Device;
use App\Services\Auth\RemoteUnlockService;
use App\Services\Financial\Acquirers\AcquirerReconciliationEngine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DeviceObserver
{
    public function created(Device $device): void
    {
        if ($device->is_active && filled($device->terminal_code)) {
            $this->reprocessTerminalTransactions($device);
        }
    }

    public function updated(Device $device): void
    {
        // 1. Bloqueio remoto imediato quando o terminal é desativado
        if ($device->wasChanged('is_active') && !$device->is_active) {
            $this->revokeTerminalAccess($device, 'desativado');
            return;
        }

        // 2. Reprocessa pendências da adquirente se TID, escopo, adquirente ou reativação mudaram
        if (
            $device->is_active
            && filled($device->terminal_code)
            && $device->wasChanged(['terminal_code', 'assignment_scope', 'acquirer_name', 'is_active'])
        ) {
            $this->reprocessTerminalTransactions($device);
        }
    }

    public function deleted(Device $device): void
    {
        $this->revokeTerminalAccess($device, 'removido');
    }

    protected function revokeTerminalAccess(Device $device, string $reason): void
    {
        if (empty($device->device_uuid)) {
            return;
        }

        try {
            Cache::forget("device_waiting_{$device->device_uuid}");
            app(RemoteUnlockService::class)->lockDevice($device->device_uuid);

            Log::warning("🔒 [DeviceObserver] Terminal '{$device->name}' ({$device->device_uuid}) {$reason} e bloqueado via WebSocket.");
        } catch (\Throwable $e) {
            Log::error("Erro ao bloquear remotamente o dispositivo {$device->device_uuid}: " . $e->getMessage());
        }
    }

    protected function reprocessTerminalTransactions(Device $device): void
    {
        try {
            $promoted = app(AcquirerReconciliationEngine::class)->reprocessPending();

            if ($promoted > 0) {
                Log::info("💳 [DeviceObserver] Terminal '{$device->name}' (TID: {$device->terminal_code}) atualizado: {$promoted} venda(s) conciliada(s) automaticamente.");
            }
        } catch (\Throwable $e) {
            Log::error("Erro ao reprocessar conciliação após alteração do dispositivo {$device->id}: " . $e->getMessage());
        }
    }
}
