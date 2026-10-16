<?php

namespace App\Http\Controllers\Tenant\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\Auth\QrAuthService;

class QrAuthController extends Controller
{
    public function authorizeLogin(Request $request, QrAuthService $qrService)
    {
        Log::info('--- 📲 INICIANDO AUTORIZAÇÃO DE QR CODE (MOBILE) ---');

        $userTeam = auth('team')->user();
        $userWeb  = auth('web')->user();
        $user     = $userTeam ?? $userWeb ?? auth()->user();

        if (!$user) {
            return response()->json(['error' => 'Sessão expirada. Faça login novamente no aplicativo.'], 401);
        }

        try {
            $token = $request->input('token') ?? $request->input('uuid');

            if (!$token) {
                return response()->json(['error' => 'Código do QR Code inválido ou em formato incorreto.'], 422);
            }

            $qrService->authorizeTerminal($token, $user->id);

            return response()->json([
                'success' => true,
                'message' => 'Terminal autorizado com sucesso!'
            ]);

        } catch (\Exception $e) {
            Log::error('🔥 ERRO CRÍTICO no authorizeLogin: ' . $e->getMessage());
            return response()->json(['error' => 'Falha interna ao autorizar terminal.'], 500);
        }
    }

    public function exchangeToken(Request $request, QrAuthService $qrService)
    {
        $request->validate([
            'device_uuid' => 'required|string',
            'token'       => 'required|string',
        ]);

        try {
            $userId = $qrService->exchangeToken($request->device_uuid, $request->token);

            if ($userId) {
                auth('team')->loginUsingId($userId);
                $request->session()->regenerate();

                return response()->json([
                    'success'  => true,
                    'redirect' => '/team' // 🚀 CORRIGIDO AQUI!
                ]);
            }

            return response()->json(['success' => false, 'status' => 'pending']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    public function validateDevice(Request $request, \App\Services\Auth\QrAuthService $qrService)
        {
            \Illuminate\Support\Facades\Log::info('📡 [Sonda QR Validate] Recebendo pedido de validação. UUID enviado pelo App: ' . $request->device_uuid);

            $request->validate(['device_uuid' => 'required|string']);

            try {
                $device = $qrService->validateAndTouchDevice($request->device_uuid);

                \Illuminate\Support\Facades\Log::info('✅ [Sonda QR Validate] Máquina encontrada: ' . $device->name);

                return response()->json([
                    'valid' => true,
                    'device' => [
                        'name' => $device->name,
                        'type' => $device->type,
                        'uuid' => $device->device_uuid,
                    ]
                ]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('❌ [Sonda QR Validate] Falha na validação: ' . $e->getMessage());
                return response()->json(['valid' => false, 'error' => $e->getMessage()], $e->getCode() ?: 400);
            }
        }
}
