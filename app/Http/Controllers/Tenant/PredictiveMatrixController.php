<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Jobs\TrainPredictiveAiJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class PredictiveMatrixController extends Controller
{
    public function show(): JsonResponse
    {
        $matrix = Cache::store('file')->get('predictive_modifier_matrix', []);

        if (empty($matrix)) {
            $tenantId = function_exists('tenant') ? tenant('id') : null;

            // 🚀 Despacha o Job para a fila de background e não trava o servidor!
            if (class_exists(TrainPredictiveAiJob::class)) {
                TrainPredictiveAiJob::dispatch($tenantId);
            }

            return response()->json([]);
        }

        return response()->json($matrix);
    }
}
