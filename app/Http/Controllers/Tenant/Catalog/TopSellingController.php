<?php

namespace App\Http\Controllers\Tenant\Catalog;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Services\Catalog\TopSellingCombinationsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TopSellingController extends Controller
{
    public function index(TopSellingCombinationsService $topSellingService): JsonResponse
    {
        try {
            $combinations = Cache::store('file')->remember('tenant_top_combinations', 3600, function () use ($topSellingService) {
                return $topSellingService->getTopCombinations(10);
            });

            return response()->json(['data' => $combinations]);
        } catch (\Throwable $e) {
            Log::error('Erro ao consultar combinações mais vendidas: ' . $e->getMessage());

            return response()->json([
                'error' => 'Ocorreu um erro ao carregar os dados do catálogo.'
            ], 500);
        }
    }
}
