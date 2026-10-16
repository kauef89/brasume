<?php

namespace App\Services\Auth;

use App\Models\Device;
use App\Events\DeviceQrAuthorized;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class QrAuthService
{
    /**
     * Armazena a autorização e dispara o WebSocket para a máquina
     */
    public function authorizeTerminal(string $token, int $userId): void
    {
        Cache::put('qr_auth_' . $token, $userId, 120);
        event(new DeviceQrAuthorized($token, $userId));
        Log::info("✅ SUCESSO! Evento WebSocket disparado para a chave qr-auth.{$token}");
    }

    /**
     * Valida o dispositivo e consome o token do cache
     */
    public function exchangeToken(string $deviceUuid, string $token): ?int
    {
        $device = Device::where('device_uuid', $deviceUuid)->where('is_active', true)->first();

        if (!$device) {
            Log::warning("⚠️ Polling QR: Dispositivo {$deviceUuid} não homologado.");
            throw new Exception('Dispositivo não autorizado.', 403);
        }

        return Cache::pull('qr_auth_' . $token);
    }

    /**
     * Verifica se a máquina é confiável
     */
    public function validateAndTouchDevice(string $deviceUuid): Device
    {
        $device = Device::where('device_uuid', $deviceUuid)->where('is_active', true)->first();

        if (!$device) {
            throw new Exception('Máquina não autorizada.', 404);
        }

        $device->update(['last_active_at' => now()]);

        return $device;
    }
}
