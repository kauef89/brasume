<?php

namespace App\Http\Controllers\Tenant\Terminals;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\RestaurantOps\PosSettingsService;

class PosSettingsController extends Controller
{
    public function index(PosSettingsService $service)
    {
        return Inertia::render('Settings/Settings', $service->getSettingsData());
    }

    public function reorder(Request $request, \App\Services\RestaurantOps\PosSettingsService $service)
    {
        // 🚀 SONDA LARAVEL 1: O que chegou no Controller antes de validar?
        \Illuminate\Support\Facades\Log::info('📦 CONTROLLER RECEBEU:', $request->input('items', []));

        $validated = $request->validate([
            'type' => 'required|in:categories,products',
            'items' => 'required|array', // Valida apenas que é um array
        ]);

        try {
            // Passamos o input direto para evitar que o validate() corte nós filhos não declarados
            $service->reorderItems($validated['type'], $request->input('items'));

            return redirect()->back()->with('success', 'Ordem atualizada!');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('🚨 ERRO NO REORDER: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Erro: ' . $e->getMessage());
        }
    }
}
