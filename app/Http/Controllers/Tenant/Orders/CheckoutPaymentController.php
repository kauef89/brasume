<?php

namespace App\Http\Controllers\Tenant\Orders;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Financial\Gateways\SicrediPixGateway;
use App\Services\Orders\OrderPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CheckoutPaymentController extends Controller
{
    public function processPayment(
        Order $order,
        Request $request,
        OrderPaymentService $paymentService
    ): JsonResponse|RedirectResponse {
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
            'idempotency_key'      => 'nullable|string|max:64',
        ]);

        $idempotencyKey = $data['idempotency_key'] ?? null;
        $idempotencyCacheKey = $idempotencyKey ? "order_payment_idempotency:{$order->id}:{$idempotencyKey}" : null;

        // Se a mesma submissão já foi concluída recentemente, responde imediatamente sem reprocessar
        if ($idempotencyCacheKey && \Illuminate\Support\Facades\Cache::has($idempotencyCacheKey)) {
            $order->refresh()->load([
                'items.modifiers',
                'payments.items.modifiers',
                'payments.transaction',
                'customer',
                'diningTable'
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Pagamento já processado com sucesso!',
                    'order'   => $order,
                ]);
            }
            return redirect()->back()->with('success', 'Pagamento já processado!');
        }

        // Trava atómica de 15 segundos por encomenda para serializar pedidos concorrentes
        $lockKey = "order_payment_lock:{$order->id}";
        $lock = \Illuminate\Support\Facades\Cache::lock($lockKey, 15);

        try {
            return $lock->block(5, function () use ($order, $data, $request, $paymentService, $idempotencyCacheKey) {
                $paymentService->attachActiveShiftIfMissing($order);

                if (!empty($data['coupon_code'])) {
                    $paymentService->applyCheckoutCoupon(
                        $order,
                        $data['coupon_code'],
                        isset($data['service_fee']) ? (float) $data['service_fee'] : null
                    );
                }

                $paymentService->processPayment($order, $data);

                $order->refresh()->load([
                    'items.modifiers',
                    'payments.items.modifiers',
                    'payments.transaction',
                    'customer',
                    'diningTable'
                ]);

                $paymentService->resolveOrderCompletion($order);

                if ($idempotencyCacheKey) {
                    \Illuminate\Support\Facades\Cache::put($idempotencyCacheKey, true, now()->addMinutes(2));
                }

                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Pagamento processado com sucesso!',
                        'order'   => $order,
                    ]);
                }

                return redirect()->back()->with('success', 'Pagamento processado!');
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            $msg = 'Existe outro pagamento a ser processado simultaneamente para esta comanda. Aguarde um instante.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 409);
            }
            return redirect()->back()->with('error', $msg);
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function destroyPayment(
        Order $order,
        Payment $payment,
        Request $request,
        OrderPaymentService $paymentService
    ): JsonResponse|RedirectResponse {
        try {
            $paymentService->destroyPayment($order, $payment);

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Pagamento removido com sucesso.']);
            }

            return back()->with('success', 'Pagamento removido com sucesso.');
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function generatePix(
        Order $order,
        Request $request,
        OrderPaymentService $paymentService,
        SicrediPixGateway $pixGateway
    ): JsonResponse {
        try {
            $amount = $request->input('amount') ? (float) $request->input('amount') : null;
            $pixData = $paymentService->generatePixPayment($order, $amount, $pixGateway);

            return response()->json(array_merge(['success' => true], $pixData));
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function cancelPixIntent(
        Order $order,
        Request $request,
        OrderPaymentService $paymentService
    ): JsonResponse|RedirectResponse {
        $paymentService->cancelPixPayment($order);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Cobrança PIX cancelada com sucesso.',
                'order'   => $order,
            ]);
        }

        return back()->with('success', 'Cobrança PIX cancelada.');
    }
}
