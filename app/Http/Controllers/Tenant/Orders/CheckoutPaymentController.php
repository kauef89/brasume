<?php

namespace App\Http\Controllers\Tenant\Orders;

use App\Events\OrderUpdated;
use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Financial\Gateways\SicrediPixGateway;
use App\Services\Orders\OrderPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckoutPaymentController extends Controller
{
    public function processPayment(Order $order, Request $request, OrderPaymentService $paymentService): JsonResponse|RedirectResponse
    {
        if ($request->has('items') && is_array($request->input('items'))) {
            $filteredItems = array_values(array_filter($request->input('items'), function ($item) {
                return isset($item['pay_qty']) && (float) $item['pay_qty'] > 0;
            }));
            $request->merge(['items' => $filteredItems]);
        }

        $data = $request->validate([
            'payments'             => 'required|array|min:1',
            'payments.*.method_id' => 'required',
            'payments.*.amount'    => 'required|numeric|gt:0',
            'items'                => 'nullable|array',
            'items.*.id'           => 'required_with:items|integer',
            'items.*.pay_qty'      => 'required_with:items|numeric|gt:0',
            'items.*.amount'       => 'nullable|numeric',
            'service_fee'          => 'nullable|numeric',
            'cashback_used'        => 'nullable|numeric',
            'coupon_code'          => 'nullable|string',
        ]);

        if (!empty($data['coupon_code'])) {
            $paymentService->applyCheckoutCoupon(
                $order,
                $data['coupon_code'],
                isset($data['service_fee']) ? (float) $data['service_fee'] : null
            );
        }

        try {
            $paymentService->processPayment($order, $data);
            $order->refresh();

            $order->load([
                'items.modifiers',
                'payments.items.modifiers',
                'payments.transaction',
                'customer',
                'diningTable'
            ]);

            // Resolução de status e liberação de mesa delegada ao Service
            $paymentService->resolveOrderCompletion($order);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Pagamento processado com sucesso!',
                    'order'   => $order,
                ]);
            }

            return redirect()->back()->with('success', 'Pagamento processado!');
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function destroyPayment(Order $order, Payment $payment, Request $request): JsonResponse|RedirectResponse
    {
        if ((int) $payment->order_id !== (int) $order->id) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Este pagamento não pertence ao pedido.'], 403);
            }
            abort(403, 'Este pagamento não pertence ao pedido.');
        }

        DB::transaction(function () use ($payment, $order) {
            $payment->loadMissing('transaction');
            if ($payment->transaction) {
                $payment->transaction->delete();
            }

            $payment->delete();

            if (method_exists($order, 'recalculatePaymentStatus')) {
                $order->recalculatePaymentStatus();
            }
        });

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Pagamento estornado com sucesso.']);
        }

        return back()->with('success', 'Pagamento estornado com sucesso.');
    }

    public function generatePix(Order $order, \Illuminate\Http\Request $request, SicrediPixGateway $pixGateway): JsonResponse
    {
        $settings = CompanySetting::getCached() ?? CompanySetting::first();

        if (!$settings || !$settings->sicredi_is_active) {
            return response()->json(['success' => false, 'message' => 'A integração PIX Sicredi não está ativa.'], 422);
        }

        $alreadyPaid = (float) $order->payments()->sum('amount');
        $totalOutstanding = max(0, round((float) $order->total - $alreadyPaid, 2));

        if ($totalOutstanding <= 0.01) {
            return response()->json(['success' => false, 'message' => 'Este pedido já está quitado.'], 422);
        }

        // 🚀 Permite que o Frontend solicite o pagamento de apenas uma fração da conta (PIX Parcial)
        $requestedAmount = $request->input('amount') ? (float) $request->input('amount') : $totalOutstanding;
        $balanceDue = min($requestedAmount, $totalOutstanding);
        
        if ($balanceDue < 0.01) {
            return response()->json(['success' => false, 'message' => 'O valor solicitado é inválido.'], 422);
        }

        try {
            $pixData = $pixGateway->generatePayment($order, $balanceDue);

            $order->update([
                'txid'                  => $pixData['txid'],
                'payment_intent_status' => 'pending_pix',
            ]);

            broadcast(new OrderUpdated($order->fresh()));

            return response()->json([
                'success'  => true,
                'txid'     => $pixData['txid'],
                'qr_code'  => $pixData['qr_code'],
                'amount'   => $balanceDue,
                'order_id' => $order->id,
            ]);
        } catch (\Throwable $e) {
            Log::error("🚨 [SicrediPix] Erro no pedido #{$order->id}: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Falha ao comunicar com o Sicredi.'], 500);
        }
    }
}
