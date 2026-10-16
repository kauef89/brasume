<?php

namespace App\Services\RestaurantOps;

use App\Models\User;
use App\Models\PosAudit;
use App\Models\CompanySetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Exception;

class PosSecurityService
{
    public function validateAndLog(string $action, ?string $pin, ?string $reason, array $context = []): User
    {
        $settings = CompanySetting::getCached();
        $mode = $settings->pos_security_mode ?? 'standard';
        $customActions = $settings->pos_secure_actions ?? [];

        $requiresAuth = false;

        if ($mode === 'strict') {
            $requiresAuth = true;
        } elseif ($mode === 'standard') {
            $requiresAuth = in_array($action, ['cancel_item', 'cancel_order', 'apply_discount', 'cash_out', 'close_shift_override']);
        } elseif ($mode === 'custom') {
            $requiresAuth = in_array($action, $customActions);
        }

        $operator = Auth::user();
        $authorizer = $operator;

        if ($requiresAuth) {
            if (empty($pin)) {
                throw new Exception("Esta ação exige a senha (PIN) de um gestor.");
            }

            // 🚀 1. Busca imediata O(1) para PINs legados em texto puro
            $authorizer = User::where('pin', $pin)->where('is_active', true)->first();

            // 🚀 2. Se não achou, filtra apenas os usuários que usam Hash bcrypt ($2y$) e faz o check
            if (!$authorizer) {
                $hashedUsers = User::whereNotNull('pin')
                    ->where('is_active', true)
                    ->where('pin', 'like', '$2y$%')
                    ->get();

                $authorizer = $hashedUsers->first(function ($user) use ($pin) {
                    return Hash::check($pin, $user->pin);
                });
            }

            if (!$authorizer) {
                throw new Exception("PIN inválido ou não encontrado.");
            }

            $isAuthorized = $authorizer->hasAnyRole(['admin', 'manager', 'gerente']);

            if (!$isAuthorized) {
                try {
                    $isAuthorized = $authorizer->can('pos.authorize_operations') 
                        || $authorizer->can('pos.autorizar_operacoes');
                } catch (\Throwable) {
                    $isAuthorized = false;
                }
            }

            if (!$isAuthorized) {
                throw new Exception("A sua senha está correta, mas você não tem permissão para autorizar esta operação.");
            }
        }

        PosAudit::create([
            'action' => $action,
            'operator_id' => $operator->id ?? null,
            'authorizer_id' => $authorizer->id ?? null,
            'reason' => $reason,
            'context' => $context,
        ]);

        return $authorizer;
    }
}

class_alias(PosSecurityService::class, 'App\Services\Terminals\PosSecurityService');
