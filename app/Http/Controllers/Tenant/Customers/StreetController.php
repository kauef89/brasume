<?php

namespace App\Http\Controllers\Tenant\Customers;

use App\Http\Controllers\Controller;
use App\Models\Street;
use Illuminate\Http\Request;
use Exception;

class StreetController extends Controller
{
    /**
     * Retorna todas as ruas do Tenant mapeando suas faixas de numeração e bairros
     */
    public function index()
    {
        try {
            // 🚀 Buscamos a rua carregando o relacionamento do Repeater (segments) e do Select (neighborhood)
            return Street::with('segments.neighborhood')
                ->get()
                ->map(function ($street) {
                    return [
                        'id' => $street->id,
                        'name' => $street->name,
                        'segments' => $street->segments->map(function ($seg) {
                            return [
                                'start' => (int) $seg->start_number,
                                'end' => $seg->end_number ? (int) $seg->end_number : null,
                                'neighborhood' => $seg->neighborhood->name ?? 'Sem Bairro'
                            ];
                        })->values()->toArray()
                    ];
                });
        } catch (Exception $e) {
            return response()->json([
                'dev_error' => true,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
