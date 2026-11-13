<?php

namespace App\Services\Orders\Actions;

use App\Enums\AcquirerMatchStatus;
use App\Models\AcquirerTransaction;
use App\Models\CashShift;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Services\Fiscal\SpedDocumentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateRetroactiveOrderAction
{
    /**
     * Executa a criação do pedido avulso retroativo conciliado com a adquirente
     * e dispara a emissão fiscal respeitando auto_fiscal_emission.
     */
    public function execute(
        AcquirerTransaction $acqTx,
        CashShift $shift,
        array $items,
        ?string $customerName = null,
        ?string $notes = null
    ): Order {
        $order = DB::transaction(function () use ($acqTx, $shift, $items, $customerName, $notes) {
            $grossAmount = (float) ($acqTx->gross_amount ?: ($acqTx->amount ?? 0));
            $txDate = $acqTx->transaction_date ?: now();

            // 1. Criação do Pedido Retroativo
            $order = Order::create([
                'cash_shift_id'  => $shift->id,
                'type'           => 'counter',
                'source'         => Order::SOURCE_BACKOFFICE,
                'status'         => 'closed',
                'payment_status' => 'paid',
                'subtotal'       => $grossAmount,
                'total'          => $grossAmount,
                'discount'       => 0,
                'delivery_fee'   => 0,
                'service_fee'    => 0,
                'customer_name'  => $customerName ?: "Consumidor Avulso (NSU: {$acqTx->nsu})",
                'notes'          => $notes ?: "Venda avulsa gerada na auditoria do Turno #{$shift->id} a partir do NSU {$acqTx->nsu}",
                'created_at'     => $txDate,
                'closed_at'      => $txDate,
            ]);

            // 2. Criação dos Itens do Pedido
            if (empty($items)) {
                $order->items()->create([
                    'product_id'        => null,
                    'kitchen_id'        => null,
                    'product_name'      => "Venda Avulsa de Balcão (NSU {$acqTx->nsu})",
                    'quantity'          => 1,
                    'paid_qty'          => 1,
                    'unit_price'        => $grossAmount,
                    'total_price'       => $grossAmount,
                    'status'            => 'completed',
                    'production_status' => 'completed',
                    'created_at'        => $txDate,
                ]);
            } else {
                foreach ($items as $item) {
                    $product = !empty($item['product_id']) ? Product::find($item['product_id']) : null;
                    $qty = (int) ($item['quantity'] ?? 1);
                    $unitPrice = (float) ($item['unit_price'] ?? ($product?->dine_in_price ?: $product?->delivery_price ?: 0));
                    $itemTotal = round($unitPrice * $qty, 2);

                    $order->items()->create([
                        'product_id'        => $product?->id,
                        'kitchen_id'        => $product?->kitchen_id,
                        'product_name'      => $item['name'] ?? ($product?->name ?? 'Item Diverso'),
                        'quantity'          => $qty,
                        'paid_qty'          => $qty,
                        'unit_price'        => $unitPrice,
                        'total_price'       => $itemTotal,
                        'status'            => 'completed',
                        'production_status' => 'completed',
                        'created_at'        => $txDate,
                    ]);
                }
            }

            // 3. Resolução da Forma de Pagamento
            $modality = mb_strtolower($acqTx->modality ?? '');
            $paymentMethod = null;

            if (str_contains($modality, 'pix')) {
                $paymentMethod = PaymentMethod::where('engine_type', 'pix_machine')->first()
                    ?? PaymentMethod::where('name', 'LIKE', '%Pix%')->first();
            } elseif (str_contains($modality, 'créd') || str_contains($modality, 'cred')) {
                $paymentMethod = PaymentMethod::where('name', 'LIKE', '%Crédito%')->first()
                    ?? PaymentMethod::where('engine_type', 'pos_manual')->first();
            } else {
                $paymentMethod = PaymentMethod::where('name', 'LIKE', '%Débito%')->first()
                    ?? PaymentMethod::where('engine_type', 'pos_manual')->first();
            }

            // 4. Criação do Registro de Pagamento
            $payment = Payment::create([
                'order_id'          => $order->id,
                'cash_shift_id'     => $shift->id,
                'payment_method_id' => $paymentMethod?->id,
                'method'            => $paymentMethod?->name ?? ($acqTx->modality ?: 'Cartão/Pix Máq'),
                'amount'            => $grossAmount,
                'is_paid'           => true,
                'created_at'        => $txDate,
            ]);

            // 5. Conciliação com a Transação da Maquininha
            $acqTx->update([
                'order_id'   => $order->id,
                'payment_id' => $payment->id,
                'status'     => AcquirerMatchStatus::MATCHED,
                'notes'      => "Conciliado automaticamente com Pedido retroativo #{$order->id}"
            ]);

            Log::info("✔ [Auditoria Turno #{$shift->id}] Venda avulsa #{$order->id} (R$ {$grossAmount}) gerada para NSU {$acqTx->nsu}.");

            return $order;
        });

        // 6. Avaliação e Disparo Fiscal (fora da transação financeira para não reverter a conciliação)
        $this->processFiscalEmission($order);

        return $order->fresh();
    }

    protected function processFiscalEmission(Order $order): void
    {
        $settings = CompanySetting::first();
        if (!$settings || !$settings->is_fiscal_emitter) {
            return;
        }

        $order->loadMissing(['items.product', 'payments.paymentMethod']);
        $spedService = app(SpedDocumentService::class);

        if (!$spedService->shouldEmitAutomatically($order)) {
            $order->update([
                'fiscal_status' => 'skipped',
                'fiscal_error'  => null,
            ]);
            return;
        }

        $hasUnlinkedProduct = $order->items->contains(fn ($item) => empty($item->product_id));
        if ($hasUnlinkedProduct) {
            $order->update([
                'fiscal_status' => 'pending',
                'fiscal_error'  => 'Item avulso sem produto vinculado (NCM ausente). Vincule um produto do catálogo para emitir NFC-e.',
            ]);
            return;
        }

        try {
            $spedService->emitNfce($order, null, false, null, false);
        } catch (\Throwable $e) {
            $order->update([
                'fiscal_status' => 'failed',
                'fiscal_error'  => $e->getMessage(),
            ]);

            Log::warning("⚠ [Auditoria Fiscal] Falha ao emitir NFC-e do pedido retroativo #{$order->id}: {$e->getMessage()}");
        }
    }
}
