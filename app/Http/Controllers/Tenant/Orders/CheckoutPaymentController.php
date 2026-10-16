<?php

namespace App\Http\Controllers\Tenant\Orders;

use App\Events\OrderUpdated;
use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Orders\OrderPaymentService;
use App\Services\Marketing\CouponService;
use App\Services\RestaurantOps\TablesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckoutPaymentController extends Controller
{
    public function processPayment(Order $order, Request $request, OrderPaymentService $paymentService, CouponService $couponService)
    {
        // 🚀 Sanitiza a lista de itens removendo qualquer item com quantidade zerada ou não selecionado
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

        // 🚀 Aplica o cupom de desconto se ele vier na requisição
        if (!empty($data['coupon_code'])) {
            $baseTotal = (float) $order->subtotal + (float) $order->delivery_fee;

            $couponResult = $couponService->applyCoupon(
                $data['coupon_code'],
                $baseTotal,
                $order->customer_id,
                true // isPos
            );

            if ($couponResult['success']) {
                $coupon = \App\Models\Coupon::find($couponResult['coupon_id']);

                // Calcula quanto de desconto esse cupom gera em dinheiro
                $discountValue = $couponService->calculateDiscountValue(
                    $coupon,
                    (float) $order->subtotal,
                    (float) $order->delivery_fee,
                    isset($data['service_fee']) ? (float) $data['service_fee'] : (float) $order->service_fee
                );

                $order->coupon_id = $coupon->id;
                $order->discount = round($discountValue, 2);
                $order->save();

                // Incrementa a contagem de uso do cupom globalmente
                $coupon->increment('usage_count');
            }
        }

        try {
            $paymentService->processPayment($order, $data);

            $order->refresh();

            // 🚀 Hidratação completa dos pagamentos e itens anexados
            $order->load([
                'items.modifiers',
                'payments.items.modifiers',
                'payments.transaction',
                'customer',
                'diningTable'
            ]);

            $shouldClose = ($order->status === 'dispatched' && $order->payment_status === 'paid')
                || ($order->type === 'counter' && in_array($order->status, ['ready', 'dispatched']) && $order->payment_status === 'paid')
                || ($order->type === 'table' && $order->payment_status === 'paid');

            if ($shouldClose) {
                $order->status = 'closed';
                if (true) {
                    $order->closed_at = now();
                }
                $order->save();

                if ($order->type === 'table') {
                    $table = $order->diningTable
                        ?? ($order->dining_table_id ? DiningTable::find($order->dining_table_id) : null)
                        ?? (!empty($order->table_number) ? DiningTable::where('name', $order->table_number)->first() : null);

                    if ($table) {
                        // Verifica se ainda existem pedidos pendentes em qualquer mesa do grupo
                        $hasOtherActiveOrders = $table->hasActiveOrdersInGroup($order->id);

                        if (!$hasOtherActiveOrders) {
                            $primaryTable = $table->parent_table_id ? $table->parentTable : $table;
                            if ($primaryTable) {
                                // O split já atualiza os status para 'available' e faz o broadcast via afterCommit
                                app(TablesService::class)->split($primaryTable);
                            }
                        }
                    }
                }

                broadcast(new OrderUpdated($order->fresh()));
            }

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
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function destroyPayment(Order $order, Payment $payment, Request $request)
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
            } else {
                $totalPaid = (float) $order->payments()->sum('amount');
                $totalOrder = (float) $order->total;

                if ($totalPaid <= 0) {
                    $order->update(['payment_status' => 'pending']);
                } elseif ($totalPaid < ($totalOrder - 0.01)) {
                    $order->update(['payment_status' => 'partial']);
                } else {
                    $order->update(['payment_status' => 'paid']);
                }
            }
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Pagamento estornado com sucesso.'
            ]);
        }

        return back()->with('success', 'Pagamento estornado com sucesso.');
    }

    public function generatePix(Order $order, Request $request, \App\Services\Financial\Gateways\SicrediPixGateway $pixGateway)
    {
        $settings = \App\Models\CompanySetting::getCached() ?? \App\Models\CompanySetting::first();

        if (!$settings || !$settings->sicredi_is_active) {
            return response()->json([
                'success' => false,
                'message' => 'A integração PIX Sicredi não está ativa nas configurações.'
            ], 422);
        }

        // Calcula saldo devedor real abatendo pagamentos já parciais
        $alreadyPaid = (float) $order->payments()->sum('amount');
        $balanceDue = max(0, round((float) $order->total - $alreadyPaid, 2));

        if ($balanceDue <= 0.01) {
            return response()->json([
                'success' => false,
                'message' => 'Este pedido já está quitado.'
            ], 422);
        }

        try {
            $pixData = $pixGateway->generatePayment($order, $balanceDue);

            $order->update([
                'txid' => $pixData['txid'],
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
            \Illuminate\Support\Facades\Log::error("🚨 [SicrediPix] Erro ao gerar PIX para pedido #{$order->id}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Falha ao comunicar com o Sicredi: ' . $e->getMessage()
            ], 500);
        }
    }
}
