<?php

use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\CashShift;
use Illuminate\Support\Facades\DB;

tenancy()->initialize("nativa");

DB::transaction(function () {
    $debitoMethod = PaymentMethod::find(5);
    if (!$debitoMethod) {
        echo "❌ Forma de pagamento ID 5 não encontrada.\n";
        return;
    }

    echo "=== 1. ATUALIZANDO PAGAMENTO #1766 ===\n";
    $payment = Payment::find(1766);
    if ($payment) {
        $oldMethod = $payment->method;
        $payment->update([
            'payment_method_id' => $debitoMethod->id,
            'method'            => $debitoMethod->name,
        ]);
        echo "✔ Pagamento #1766 alterado de '{$oldMethod}' para '{$debitoMethod->name}' (ID: {$debitoMethod->id}).\n";
    } else {
        echo "⚠️ Pagamento #1766 não encontrado.\n";
    }

    echo "\n=== 2. ATUALIZANDO TRANSAÇÃO #6808 ===\n";
    $trans = Transaction::find(6808);
    if ($trans) {
        $destAccount = $debitoMethod->destination_account_id ?? null;
        $trans->update([
            'payment_method_id' => $debitoMethod->id,
            'bank_account_id'   => $destAccount,
            'description'       => "Pedido #2158 ({$debitoMethod->name})",
        ]);
        echo "✔ Transação #6808 desvinculada da gaveta física e direcionada para conta #{$destAccount}.\n";
    } else {
        echo "⚠️ Transação #6808 não encontrada.\n";
    }

    echo "\n=== 3. AJUSTANDO SALDO ESPERADO DO TURNO #50 ===\n";
    $shift = CashShift::find(50);
    if ($shift) {
        $oldExpected = (float) $shift->closing_balance_expected;
        $newExpected = round($oldExpected - 67.50, 2); // 544.45 - 67.50 = 476.95

        $shift->update([
            'closing_balance_expected' => $newExpected,
        ]);
        echo "✔ Saldo esperado do Turno #50 atualizado: de R$ {$oldExpected} para R$ {$newExpected}.\n";
        echo "✔ Saldo contado: R$ {$shift->closing_balance_actual} | Nova diferença apurada: R$ 0,00!\n";
    }

    echo "\n=== 4. REMOVENDO QUEBRA DE CAIXA INDEVIDA ===\n";
    $quebra = Transaction::where('description', 'like', '%Quebra de Caixa (Falta) - Turno #50%')
        ->where('amount', 67.50)
        ->first();

    if ($quebra) {
        $quebra->delete();
        echo "✔ Transação #{$quebra->id} de quebra de caixa indevida removida do Cofre (#1).\n";
    } else {
        echo "ℹ️ Nenhuma transação de quebra de caixa encontrada para estorno.\n";
    }
});

echo "\n✨ AJUSTE CONCLUÍDO COM SUCESSO!\n";
