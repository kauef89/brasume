<?php

namespace App\Http\Controllers\Tenant\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\Auth\TwoFactorService;

class TwoFactorController extends Controller
{
    public function __construct(
        protected TwoFactorService $twoFactorService
    ) {}

    /**
     * Exibe o ecrã apropriado: Leitura do QR Code (Setup) ou Apenas Código (Challenge)
     */
    public function showChallenge()
    {
        $user = auth()->user();

        // CENÁRIO 1: Configuração Inédita
        if (empty($user->two_factor_secret)) {
            $setupData = $this->twoFactorService->getSetupData($user);

            return Inertia::render('Auth/TwoFactorSetup', [
                'qrCodeUrl' => $setupData['qrCodeUrl'],
                'secret' => $setupData['secret']
            ]);
        }

        // CENÁRIO 2: Desafio de Rotina
        return Inertia::render('Auth/TwoFactorChallenge');
    }

    /**
     * Recebe o código de 6 dígitos do Vue e processa a libertação.
     */
    public function verifyChallenge(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6'
        ]);

        $isValid = $this->twoFactorService->verifyAndSave(auth()->user(), $request->code);

        if ($isValid) {
            // Marca a sessão como duplamente segura
            session(['2fa_verified' => true]);

            // Redireciona para o painel de gestão ou origem
            return redirect()->intended('/admin');
        }

        return back()->withErrors(['code' => 'O código de 6 dígitos está incorreto ou expirou.']);
    }
}
