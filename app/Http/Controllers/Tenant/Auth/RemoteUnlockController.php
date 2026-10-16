<?php

namespace App\Http\Controllers\Tenant\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\Auth\RemoteUnlockService;

class RemoteUnlockController extends Controller
{
    public function __construct(
        protected RemoteUnlockService $unlockService
    ) {}

    public function fleet()
    {
        return response()->json($this->unlockService->getDeviceFleet());
    }

    public function requestUnlock(Request $request)
    {
        $request->validate(['device_uuid' => 'required|string']);
        $this->unlockService->setDeviceWaiting($request->device_uuid);
        return response()->json(['success' => true]);
    }

    public function registerQrSession(Request $request)
    {
        $request->validate([
            'device_uuid' => 'required|string',
            'session_token' => 'required|string'
        ]);

        \Illuminate\Support\Facades\Cache::put(
            "qr_session_rev_{$request->session_token}",
            $request->device_uuid,
            65
        );

        return response()->json(['success' => true]);
    }

    public function handleQuickUnlockScan(Request $request, string $token)
    {
        $user = auth('team')->user() ?? auth('web')->user();

        if (!$user || !$user->hasAnyRole(['admin', 'gerente', 'manager', 'pos', 'kitchen'])) {
            // 🚀 Atualizado para a nova rota do WaiterApp
            return redirect()->route('waiter.index', ['tab' => 'menu'])
                             ->withErrors(['acesso' => 'Você não possui permissão para autorizar terminais.']);
        }

        $deviceUuid = \Illuminate\Support\Facades\Cache::get("qr_session_rev_{$token}");

        if (!$deviceUuid) {
            return redirect()->route('waiter.index', ['tab' => 'menu'])
                             ->withErrors(['acesso' => 'QR Code expirado por segurança. Escaneie novamente.']);
        }

        return redirect()->route('waiter.index', [
            'tab' => 'menu',
            'quick_unlock_token' => $deviceUuid,
        ]);
    }

    public function approve(Request $request)
    {
        try {
            $request->validate(['device_uuid' => 'required|string']);
            $user = auth('team')->user() ?? auth()->user();

            $this->unlockService->approveUnlock($user, $request->device_uuid);

            if ($request->wantsJson() || $request->ajax()) return response()->json(['success' => true]);
            return back()->with('success', 'Terminal liberado com sucesso!');

        } catch (\Exception $e) {
            if ($request->wantsJson() || $request->ajax()) return response()->json(['error' => $e->getMessage()], 400);
            return back()->with('error', $e->getMessage());
        }
    }

    public function exchange(Request $request)
    {
        $request->validate(['token' => 'required|string']);

        try {
            $userId = $this->unlockService->exchangeToken($request->token);
            auth('team')->loginUsingId($userId);
            $request->session()->regenerate();

            if ($request->wantsJson()) return response()->json(['success' => true]);
            return back()->with('success', 'Acesso liberado.');
        } catch (\Exception $e) {
            if ($request->wantsJson()) return response()->json(['error' => $e->getMessage()], 403);
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function lock(Request $request)
    {
        $request->validate(['device_uuid' => 'required|string']);
        $this->unlockService->lockDevice($request->device_uuid);
        return response()->json(['success' => true]);
    }
}
