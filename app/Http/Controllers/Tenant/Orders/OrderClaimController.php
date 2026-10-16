<?php

namespace App\Http\Controllers\Tenant\Orders;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Marketing\OrderClaimService;
use Exception;

class OrderClaimController extends Controller
{
    public function __construct(
        protected OrderClaimService $claimService
    ) {}

    /**
     * Ponto de entrada do QR Code: /resgate/{hash}
     */
    public function handleScan(string $hash)
    {
        $hash = strtoupper(trim($hash));
        $user = auth()->user();

        // 1. Se o cliente já estiver autenticado e com perfil Customer criado
        if ($user && $user->customer) {
            try {
                $order = $this->claimService->claimOrder($hash, $user->customer);
                $cashbackFormatted = number_format($order->cashback_earned, 2, ',', '.');

                return redirect()->route('customer.app', ['tab' => 'wallet'])
                    ->with('success', "🎉 Incrível! Você resgatou R$ {$cashbackFormatted} de Cashback do Pedido #{$order->id}!");
            } catch (Exception $e) {
                return redirect()->route('customer.app', ['tab' => 'wallet'])
                    ->with('error', $e->getMessage());
            }
        }

        // 2. Se for um cliente visitante / não autenticado:
        // Guarda a intenção de resgate na sessão e abre o app solicitando identificação
        session(['pending_claim_hash' => $hash]);

        return redirect()->route('customer.app', [
            'claim' => $hash,
            'tab'   => 'account',
        ]);
    }

    /**
     * Processamento da pendência após login ou cadastro no CustomerApp
     */
    public function processPendingClaim(Request $request)
    {
        $user = auth()->user();
        if (!$user || !$user->customer) {
            return response()->json(['success' => false, 'message' => 'Usuário não autenticado.'], 401);
        }

        $hash = $request->input('hash') ?: session('pending_claim_hash');
        if (!$hash) {
            return response()->json(['success' => false, 'message' => 'Nenhum comprovante pendente.'], 400);
        }

        try {
            $order = $this->claimService->claimOrder($hash, $user->customer);
            session()->forget('pending_claim_hash');

            return response()->json([
                'success'         => true,
                'message'         => "Parabéns! R$ " . number_format($order->cashback_earned, 2, ',', '.') . " creditados na sua conta!",
                'points'          => $order->loyalty_points_earned,
                'cashback_earned' => $order->cashback_earned,
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
