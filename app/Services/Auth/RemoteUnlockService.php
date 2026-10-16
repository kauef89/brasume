<?php

namespace App\Services\Auth;

use App\Models\Device;
use App\Models\User;
use App\Events\DeviceRemoteUnlocked;
use App\Events\DeviceRemoteLocked;
use App\Notifications\DeviceWaitingNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Exception;

class RemoteUnlockService
{
    /**
     * Retorna a "Frota" de hardware do restaurante e verifica quem está pedindo acesso.
     */
    public function getDeviceFleet()
    {
        $devices = Device::where('is_active', true)->get();

        return $devices->map(function ($device) {
            $isWaiting = Cache::has("device_waiting_{$device->device_uuid}");
            $operator = Cache::get("device_operator_{$device->device_uuid}");

            $status = 'locked';
            if ($operator && !$isWaiting) {
                $status = 'operating';
            }

            return [
                'id'         => $device->id,
                'uuid'       => $device->device_uuid,
                'name'       => $device->name,
                'type'       => $device->type,
                'is_waiting' => $isWaiting,
                'status'     => $status,
                'operator'   => $operator, // Retorna ['name' => 'Kauê', 'role' => 'Gerente'] ou null
            ];
        });
    }

    /**
     * O Terminal avisa que foi ligado e está aguardando liberação.
     */
    public function setDeviceWaiting(string $deviceUuid): void
    {
        $device = Device::where('device_uuid', $deviceUuid)->first();

        if ($device) {
            Cache::put("device_waiting_{$deviceUuid}", true, now()->addMinutes(10));

            $managers = User::whereHas('roles', function ($q) {
                $q->whereIn('name', ['admin', 'gerente', 'manager']);
            })->get();

            if ($managers->isNotEmpty()) {
                Notification::send(
                    $managers,
                    new DeviceWaitingNotification($device->name, $device->type)
                );
            }
        }
    }

    /**
     * O Usuário (Gestor ou Operador) aciona a autorização remota.
     */
    public function approveUnlock(User $authorizer, string $deviceUuid): void
    {
        $device = Device::where('device_uuid', $deviceUuid)->firstOrFail();

        $token = Str::random(40);

        Cache::put("device_unlock_{$token}", $authorizer->id, now()->addMinutes(1));
        Cache::forget("device_waiting_{$deviceUuid}");

        $roleName = 'Operador';
        if (method_exists($authorizer, 'roles') && $authorizer->roles->isNotEmpty()) {
            $roleName = ucfirst($authorizer->roles->first()->name);
        } elseif (isset($authorizer->role)) {
            $roleName = ucfirst($authorizer->role);
        }

        Cache::put("device_operator_{$deviceUuid}", [
            'name' => $authorizer->name,
            'role' => $roleName,
        ], now()->addHours(12));

        // Roteamento pós-login respeitando o hardware físico (KDS ou PDV Caixa)
        $redirectUrl = $device->type === 'kds'
            ? route('kds.index')
            : route('pos.index', ['tab' => 'pos']);

        broadcast(new DeviceRemoteUnlocked($deviceUuid, $token, $redirectUrl));
    }

    /**
     * Interrompe ativamente a sessão do terminal e fecha a tela via WebSocket.
     */
    public function lockDevice(string $deviceUuid): void
    {
        Cache::forget("device_operator_{$deviceUuid}");

        broadcast(new DeviceRemoteLocked($deviceUuid));
    }

    /**
     * O Terminal recebe o token temporário via WebSocket e troca pelo ID do usuário.
     */
    public function exchangeToken(string $token): int
    {
        $userId = Cache::pull("device_unlock_{$token}");

        if (!$userId) {
            throw new Exception('Token inválido ou expirado. Peça ao gestor para autorizar novamente.');
        }

        return (int) $userId;
    }
}
