<?php

namespace App\Http\Controllers\Tenant\Terminals;

use App\Http\Controllers\Controller;
use App\Services\Auth\WhatsAppAuthService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppAuthController extends Controller
{
    public function __construct(
        protected WhatsAppAuthService $whatsAppService
    ) {}

    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string'],
        ]);

        try {
            $this->whatsAppService->sendOtp($request->input('phone'));

            return response()->json([
                'success' => true,
                'message' => 'Código de verificação enviado com sucesso.'
            ], 200);
        } catch (Exception $e) {
            $status = ($e->getCode() >= 400 && $e->getCode() <= 504) ? $e->getCode() : 500;
            return response()->json(['message' => $e->getMessage()], $status);
        }
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string'],
            'code'  => ['required', 'string', 'size:6'],
        ]);

        $isValid = $this->whatsAppService->verifyOtp(
            $request->input('phone'),
            $request->input('code')
        );

        if (!$isValid) {
            return response()->json(['message' => 'Código inválido ou expirado.'], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Telefone autenticado com sucesso.'
        ], 200);
    }
}
