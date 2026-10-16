<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// 🛡️ Canal restrito à equipe interna (POS, KDS, Garçom, Gestor)
Broadcast::channel('tenant.{tenantId}.operations', function ($user, $tenantId) {
    return $user !== null && (string) (tenant('id') ?? 'central') === (string) $tenantId;
});

// 🛡️ Canal restrito a impressoras e hardware do salão
Broadcast::channel('tenant.{tenantId}.hardware', function ($user, $tenantId) {
    return $user !== null && (string) (tenant('id') ?? 'central') === (string) $tenantId;
});

// 🛡️ Canal restrito a um cliente específico (autenticado ou convidado)
Broadcast::channel('tenant.{tenantId}.customer.{customerId}', function ($user, $tenantId, $customerId) {
    // Se o cliente for usuário autenticado
    if ($user && method_exists($user, 'customer') && (int) $user->customer?->id === (int) $customerId) {
        return true;
    }
    // Se for convidado navegando com cookie de sessão
    return (int) session('guest_customer_id') === (int) $customerId;
});
