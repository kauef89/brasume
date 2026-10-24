<?php

namespace App\Http\Controllers\Tenant;

use App\Models\PushSubscription;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PushSubscriptionController extends Controller
{
    public function subscribe(Request $request)
    {
        Log::info("📍 [SONDA PUSH BACK] Inscrição Push recebida:", $request->all());

        $request->validate([
            'endpoint'    => 'required',
            'keys.auth'   => 'required',
            'keys.p256dh' => 'required'
        ]);

        $subscribableType = null;
        $subscribableId = null;

        if (auth('team')->check()) {
            $subscribableType = \App\Models\User::class;
            $subscribableId = auth('team')->id();
        } elseif (auth('web')->check()) {
            $subscribableType = \App\Models\User::class;
            $subscribableId = auth('web')->id();
        } elseif (session()->has('guest_customer_id')) {
            $subscribableType = \App\Models\Customer::class;
            $subscribableId = session('guest_customer_id');
        } elseif ($request->filled('customer_id')) {
            $subscribableType = \App\Models\Customer::class;
            $subscribableId = $request->customer_id;
        } else {
            return response()->json(['error' => 'Identificador do usuário não encontrado'], 401);
        }

        // Salva diretamente na tabela do banco do Tenant ativo
        PushSubscription::where('endpoint', $request->endpoint)->delete();

        PushSubscription::create([
            'subscribable_type' => $subscribableType,
            'subscribable_id'   => $subscribableId,
            'endpoint'          => $request->endpoint,
            'public_key'        => $request->keys['p256dh'],
            'auth_token'        => $request->keys['auth'],
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        Log::info("📍 [SONDA PUSH BACK] Inscrição salva no banco do Tenant para {$subscribableType} #{$subscribableId}!");

        return response()->json(['success' => true]);
    }

    public function unsubscribe(Request $request)
    {
        $request->validate(['endpoint' => 'required']);
        PushSubscription::where('endpoint', $request->endpoint)->delete();
        return response()->json(['success' => true]);
    }
}