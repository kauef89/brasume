<?php

namespace App\Services\Customers;

use App\Models\Street;

class StreetService
{
    /**
     * Retorna todas as ruas do Tenant mapeando suas faixas de numeração e bairros
     */
    public function getAllStreetsWithSegments(): array
    {
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
                            'neighborhood' => $seg->neighborhood->name ?? 'Sem Bairro',
                            // 🚀 AQUI: Adicionamos o ID do bairro para o frontend usar!
                            'neighborhood_id' => $seg->neighborhood_id ?? ($seg->neighborhood->id ?? null)
                        ];
                    })->values()->toArray()
                ];
            })->toArray();
    }
}
