<?php

namespace App\Http\Controllers\Tenant\Orders;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\CompanySetting;
use App\Services\Printing\OrderPrintService;
use App\Events\OrderUpdated;
use Throwable;

class CheckoutFiscalController extends Controller
{
    public function printConference(Order $order, OrderPrintService $printService)
    {
        try {
            $printService->printConferenceBill($order);
            return back()->with('success', 'Conta enviada para a impressora!');
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function emitNfce(Order $order, Request $request)
    {
        $data = $request->validate([
            'document' => 'nullable|string',
            'force'    => 'nullable|boolean',
        ]);

        $settings = CompanySetting::first();

        if (!$settings || !$settings->is_fiscal_emitter) {
            $msg = 'Empresa não está configurada como emissora fiscal.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return back()->with('error', $msg);
        }

        // 🚀 GUARDA: Se já possui NFC-e autorizada, retorna sucesso imediatamente
        if ($order->fiscalDocument && $order->fiscalDocument->status === 'authorized') {
            $msg = "NFC-e do pedido #{$order->id} já está emitida e autorizada (NF nº {$order->fiscalDocument->number})!";
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'status'  => 'authorized',
                    'message' => $msg,
                ]);
            }
            return back()->with('success', $msg);
        }

        // Se o operador clicou para forçar OU se o pedido já estava 'skipped', trata como ação manual deliberada
        $isManualForce = $request->boolean('force') || $order->fiscal_status === 'skipped';

        try {
            $spedService = app(\App\Services\Fiscal\SpedDocumentService::class);
            $result = $spedService->emitNfce(
                $order,
                $data['document'] ?? null,
                false,
                null,
                $isManualForce
            );

            $order->load('fiscalDocument');

            if (!$request->wantsJson()) {
                try {
                    broadcast(new OrderUpdated($order));
                } catch (Throwable $e) {}
            }

            // Caso tenha sido dispensada pela forma de pagamento (auto_fiscal_emission = false)
            if (is_array($result) && ($result['status'] ?? null) === 'skipped') {
                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => true,
                        'status'  => 'skipped',
                        'message' => $result['message'] ?? "Emissão de NFC-e dispensada para o pedido #{$order->id}."
                    ]);
                }
                return back()->with('info', "Emissão de NFC-e dispensada para o pedido #{$order->id}.");
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'status'  => 'authorized',
                    'message' => "NFC-e do pedido #{$order->id} autorizada com sucesso na SEFAZ!"
                ]);
            }

            return back()->with('success', 'NFC-e autorizada com sucesso na Sefaz!');
        } catch (Throwable $e) {
            $order->update([
                'fiscal_status' => 'failed',
                'fiscal_error'  => $e->getMessage(),
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'status'  => 'failed',
                    'message' => $e->getMessage()
                ], 422);
            }

            return back()->with('error', $e->getMessage());
        }
    }

    public function finalizeReceipt(Order $order, Request $request, OrderPrintService $printService)
    {
        $data = $request->validate([
            'print_action' => 'required|string',
        ]);

        $printService->dispatchCheckoutReceipts($order, $data['print_action']);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Comandos enviados à impressora!'
            ]);
        }

        return back()->with('success', 'Comandos enviados à impressora!');
    }
}
