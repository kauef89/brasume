<?php

namespace App\Http\Controllers\Tenant\RestaurantOps;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\RestaurantOps\BridgeService;

class BridgeApiController extends Controller
{
    public function handshake(Request $request, BridgeService $bridgeService): JsonResponse
    {
        try {
            $version = $request->input('version', '0.0.0');
            $tenantId = tenant('id') ?? 'central';

            $response = $bridgeService->processHandshake($request->header('X-Bridge-Token', ''), $version, $tenantId);

            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    public function updateJobStatus(Request $request, $id, BridgeService $bridgeService): JsonResponse
    {
        try {
            $bridgeService->updateJob(
                $request->header('X-Bridge-Token', ''),
                $id,
                $request->input('status'),
                $request->input('error_message', null)
            );
            return response()->json(['message' => 'Recebido']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    public function getPrinters(BridgeService $bridgeService): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'printers' => $bridgeService->getActivePrinters()
        ]);
    }
}
