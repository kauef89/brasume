<?php

namespace App\Services\RestaurantOps;

use App\Models\CashShift;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShiftAuditService
{
    public function __construct(
        protected ShiftCashierService $cashierService
    ) {}

    /**
     * Realiza o diagnóstico completo do fechamento do turno:
     * 1. Gaveta física (Dinheiro declarado vs. Esperado)
     * 2. Meios eletrônicos (Fitas SiTef declaradas vs. Pedidos do sistema)
     * 3. Busca de trocas simples 1x1 (Dinheiro x Cartão/Pix ou Cartão x Pix)
     * 4. Identificação de pedidos e terminais suspeitos em caso de quebra real
     */
    public function diagnose(CashShift $shift, float $actualCash, array $terminalSettlements = []): array
    {
        $expectedCash = $this->cashierService->calculateExpectedCash($shift);
        $cashDiff = round($actualCash - $expectedCash, 2);

        // 1. Apuração eletrônica declarada nas maquininhas
        $declaredCards = 0.0;
        $declaredPix = 0.0;
        $declaredVoucher = 0.0;

        foreach ($terminalSettlements as $tid => $term) {
            $declaredCards   += (float) ($term['cards'] ?? 0);
            $declaredPix     += (float) ($term['pix'] ?? 0);
            $declaredVoucher += (float) ($term['voucher'] ?? 0);
        }
        $declaredElectronicTotal = $declaredCards + $declaredPix + $declaredVoucher;

        // 2. Apuração eletrônica registrada nos pedidos do sistema
        $payments = Payment::with('paymentMethod')
            ->where('cash_shift_id', $shift->id)
            ->where('is_paid', true)
            ->get();

        $sysCards = 0.0;
        $sysPix = 0.0;
        $sysVoucher = 0.0;

        foreach ($payments as $p) {
            $engine = $p->paymentMethod?->engine_type;
            $engineVal = is_object($engine) ? $engine->value : (string) $engine;
            $name = mb_strtolower($p->method ?? '');

            if ($engineVal === 'money' || $engineVal === 'loss' || str_contains($name, 'dinheiro') || str_contains($name, 'cash') || str_contains($name, 'consumo') || str_contains($name, 'interno')) {
                continue;
            }

            if (str_contains($name, 'voucher') || str_contains($name, 'refei') || str_contains($name, 'alimenta') || $engineVal === 'voucher') {
                $sysVoucher += (float) $p->amount;
            } elseif (str_contains($name, 'pix') && (str_contains($name, 'm') || str_contains($name, 'pos') || $engineVal === 'pix_machine')) {
                $sysPix += (float) $p->amount;
            } else {
                $sysCards += (float) $p->amount;
            }
        }

        $sysElectronicTotal = $sysCards + $sysPix + $sysVoucher;

        $cardsDiff = round($declaredCards - $sysCards, 2);
        $pixDiff = round($declaredPix - $sysPix, 2);
        $voucherDiff = round($declaredVoucher - $sysVoucher, 2);
        $electronicDiff = round($declaredElectronicTotal - $sysElectronicTotal, 2);

        $hasCashDiscrepancy = abs($cashDiff) >= 0.05;
        $hasElecDiscrepancy = abs($electronicDiff) >= 0.05;
        $hasModalDiscrepancy = abs($cardsDiff) >= 0.05 || abs($pixDiff) >= 0.05;

        $hasDiscrepancy = $hasCashDiscrepancy || $hasElecDiscrepancy || $hasModalDiscrepancy;

        // Se tudo confere perfeitamente
        if (!$hasDiscrepancy) {
            return [
                'has_discrepancy'       => false,
                'difference'            => 0.00,
                'cash_difference'       => 0.00,
                'electronic_difference' => 0.00,
                'suggestion'            => null,
                'suspects'              => [],
            ];
        }

        // 3. Procura troca simples 1x1
        $suggestion = $this->findSingleSwapSuggestion(
            $shift,
            $cashDiff,
            $cardsDiff,
            $pixDiff,
            $payments
        );

        // 4. Se não há troca óbvia, identifica pedidos suspeitos para auditoria
        $suspects = [];
        if (!$suggestion) {
            $suspects = $this->findSuspectOrders(
                $shift,
                $cashDiff,
                $electronicDiff,
                $cardsDiff,
                $pixDiff,
                $payments,
                $terminalSettlements
            );
        }

        return [
            'has_discrepancy'       => true,
            'difference'            => $cashDiff != 0 ? $cashDiff : $electronicDiff,
            'cash_difference'       => $cashDiff,
            'electronic_difference' => $electronicDiff,
            'cards_difference'      => $cardsDiff,
            'pix_difference'        => $pixDiff,
            'voucher_difference'    => $voucherDiff,
            'suggestion'            => $suggestion,
            'suspects'              => $suspects,
        ];
    }

    /**
     * Detecta trocas 1x1 diretas:
     * - Gaveta vs. Eletrônico (ex: falta R$ 56 em dinheiro e sobra R$ 56 na máquina)
     * - Entre modalidades eletrônicas (ex: total da máquina bateu, mas Débito tem +R$ 56 e Pix tem -R$ 56)
     */
    protected function findSingleSwapSuggestion(
        CashShift $shift,
        float $cashDiff,
        float $cardsDiff,
        float $pixDiff,
        $payments
    ): ?array {
        // CENÁRIO 1: Troca Físico x Eletrônico
        // Falta na gaveta (-X) e sobra na máquina (+X) => Operador registrou Dinheiro no PDV, mas cliente passou na máquina
        if ($cashDiff < -0.05 && ($cardsDiff > 0.05 || $pixDiff > 0.05)) {
            $target = abs($cashDiff);
            $candidate = $payments->first(function ($p) use ($target) {
                $name = mb_strtolower($p->method ?? '');
                $isCash = str_contains($name, 'dinheiro') || str_contains($name, 'cash');
                return $isCash && abs((float) $p->amount - $target) <= 0.05;
            });

            if ($candidate) {
                $isPixSurplus = abs($pixDiff - $target) <= 0.05;
                $targetMethod = $isPixSurplus
                    ? (PaymentMethod::where('engine_type', 'pix_machine')->first() ?? PaymentMethod::where('name', 'LIKE', '%Pix%')->first())
                    : (PaymentMethod::where('name', 'LIKE', '%Débito%')->first() ?? PaymentMethod::find(5));

                return [
                    'payment_id'            => $candidate->id,
                    'order_id'              => $candidate->order_id,
                    'current_method_name'   => $candidate->method,
                    'suggested_method_id'   => $targetMethod?->id ?? 5,
                    'suggested_method_name' => $targetMethod?->name ?? 'Débito',
                    'amount'                => (float) $candidate->amount,
                    'reason'                => "O Pedido #{$candidate->order_id} (R$ " . number_format($candidate->amount, 2, ',', '.') . ") foi lançado como Dinheiro, mas faltou na gaveta e sobrou na maquininha.",
                    'type'                  => 'cash_to_electronic'
                ];
            }
        }

        // Sobra na gaveta (+X) e falta na máquina (-X) => Operador registrou Cartão no PDV, mas cliente pagou em cédulas
        if ($cashDiff > 0.05 && ($cardsDiff < -0.05 || $pixDiff < -0.05)) {
            $target = abs($cashDiff);
            $candidate = $payments->first(function ($p) use ($target) {
                $name = mb_strtolower($p->method ?? '');
                $isElec = !str_contains($name, 'dinheiro') && !str_contains($name, 'cash');
                return $isElec && abs((float) $p->amount - $target) <= 0.05;
            });

            if ($candidate) {
                $cashMethod = PaymentMethod::where('engine_type', 'money')->first() ?? PaymentMethod::find(1);

                return [
                    'payment_id'            => $candidate->id,
                    'order_id'              => $candidate->order_id,
                    'current_method_name'   => $candidate->method,
                    'suggested_method_id'   => $cashMethod->id,
                    'suggested_method_name' => $cashMethod->name,
                    'amount'                => (float) $candidate->amount,
                    'reason'                => "O Pedido #{$candidate->order_id} (R$ " . number_format($candidate->amount, 2, ',', '.') . ") foi registrado como '{$candidate->method}', mas há um excedente idêntico na gaveta.",
                    'type'                  => 'electronic_to_cash'
                ];
            }
        }

        // CENÁRIO 2: Troca entre modalidades eletrônicas (Cartão vs. Pix Máquina)
        if (abs($cardsDiff + $pixDiff) <= 0.05 && abs($cardsDiff) >= 0.05) {
            $target = abs($cardsDiff);
            $fromPixToCard = $cardsDiff > 0; // Sobrou cartão na máquina, faltou pix

            $candidate = $payments->first(function ($p) use ($target, $fromPixToCard) {
                $name = mb_strtolower($p->method ?? '');
                $matches = $fromPixToCard ? str_contains($name, 'pix') : (str_contains($name, 'débito') || str_contains($name, 'crédito'));
                return $matches && abs((float) $p->amount - $target) <= 0.05;
            });

            if ($candidate) {
                $targetMethod = $fromPixToCard
                    ? (PaymentMethod::where('name', 'LIKE', '%Débito%')->first() ?? PaymentMethod::find(5))
                    : (PaymentMethod::where('engine_type', 'pix_machine')->first() ?? PaymentMethod::where('name', 'LIKE', '%Pix%')->first());

                return [
                    'payment_id'            => $candidate->id,
                    'order_id'              => $candidate->order_id,
                    'current_method_name'   => $candidate->method,
                    'suggested_method_id'   => $targetMethod?->id,
                    'suggested_method_name' => $targetMethod?->name,
                    'amount'                => (float) $candidate->amount,
                    'reason'                => "O Pedido #{$candidate->order_id} foi registrado como '{$candidate->method}', mas na fita da maquininha consta como {$targetMethod->name}.",
                    'type'                  => 'electronic_modal_swap'
                ];
            }
        }

        return null;
    }

    /**
     * Identifica pedidos ou terminais suspeitos associados ao valor da divergência
     */
    public function findSuspectOrders(
        CashShift $shift,
        float $cashDiff,
        float $electronicDiff,
        float $cardsDiff,
        float $pixDiff,
        $payments,
        array $terminalSettlements
    ): array {
        $suspects = [];
        $targetDiff = abs($cashDiff) >= 0.02 ? abs($cashDiff) : abs($electronicDiff);

        if ($targetDiff < 0.02) {
            return [];
        }

        // Busca pedidos do turno cujo pagamento coincide com o valor faltante ou excedente
        $matchingPayments = $payments->filter(function ($p) use ($targetDiff) {
            return abs((float) $p->amount - $targetDiff) <= 0.05;
        });

        foreach ($matchingPayments as $p) {
            $order = $p->order;
            $origin = 'Balcão';
            if ($order) {
                if ($order->type === 'table') {
                    $origin = "Mesa #{$order->table_number}";
                } elseif ($order->type === 'delivery') {
                    $origin = "Delivery";
                }
            }

            // Mapeia se o terminal suspeito pode ser inferido pelo valor
            $suspectTerminal = null;
            foreach ($terminalSettlements as $tid => $t) {
                $termSubtotal = (float) ($t['total'] ?? 0);
                if ($termSubtotal > 0 && abs($termSubtotal - $targetDiff) <= 0.05) {
                    $suspectTerminal = "{$t['name']} (TID: {$tid})";
                    break;
                }
            }

            $suspects[] = [
                'order_id'        => $p->order_id,
                'payment_id'      => $p->id,
                'amount'          => (float) $p->amount,
                'method'          => $p->method,
                'origin'          => $origin,
                'terminal'        => $suspectTerminal,
                'time'            => $p->created_at?->format('H:i'),
            ];
        }

        return $suspects;
    }

    /**
     * Aplica a correção de forma de pagamento solicitada pelo operador e audita no turno
     */
    public function applyCorrection(CashShift $shift, int $paymentId, int $newMethodId, string $operatorName): array
    {
        return DB::transaction(function () use ($shift, $paymentId, $newMethodId, $operatorName) {
            $payment = Payment::with('order')->findOrFail($paymentId);
            $newMethod = PaymentMethod::findOrFail($newMethodId);

            $oldMethodName = $payment->method;
            $amount = (float) $payment->amount;

            $payment->update([
                'payment_method_id' => $newMethod->id,
                'method'            => $newMethod->name,
            ]);

            $trans = Transaction::where('payment_id', $payment->id)->first();
            if ($trans) {
                $isCash = $newMethod->engine_type === 'money';
                $destAccount = $newMethod->destination_account_id ?? ($isCash ? 1 : null);

                $trans->update([
                    'payment_method_id' => $newMethod->id,
                    'bank_account_id'   => $destAccount,
                    'description'       => "Pedido #{$payment->order_id} ({$newMethod->name})",
                ]);
            }

            $details = $shift->corrected_errors_details ?? [];
            $details[] = [
                'timestamp'        => now()->toDateTimeString(),
                'payment_id'       => $payment->id,
                'order_id'         => $payment->order_id,
                'amount'           => $amount,
                'from_method'      => $oldMethodName,
                'to_method'        => $newMethod->name,
                'operator_at_fix'  => $operatorName,
            ];

            $shift->increment('corrected_errors_count', 1);
            $shift->update([
                'corrected_errors_amount'  => (float) ($shift->corrected_errors_amount + $amount),
                'corrected_errors_details' => $details,
            ]);

            Log::warning("⚠️ [Auditoria Turno #{$shift->id}] Pagamento corrigido: Pedido #{$payment->order_id} de '{$oldMethodName}' para '{$newMethod->name}' (R$ {$amount}) por [{$operatorName}].");

            $newExpected = $this->cashierService->calculateExpectedCash($shift);

            return [
                'success'      => true,
                'new_expected' => $newExpected,
                'message'      => "Pagamento corrigido para {$newMethod->name} com sucesso."
            ];
        });
    }
}
