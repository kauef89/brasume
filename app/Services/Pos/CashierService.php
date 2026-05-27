<?php

namespace App\Services\Pos;

use App\Models\CashShift;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class CashierService
{
    public function openShift(float $amount, int $userId): void
    {
        CashShift::create([
            'opened_at' => now(),
            'status' => 'open',
            'opened_by' => $userId,
            'opening_balance' => $amount,
        ]);
    }

    public function closeShift(float $actualAmount, int $userId): void
    {
        $shift = CashShift::where('status', 'open')->latest()->firstOrFail();

        // Fundo Inicial
        $expected = $shift->opening_balance;

        // (+) Suprimentos (-) Sangrias
        $transactions = Transaction::where('created_at', '>=', $shift->opened_at)->get();
        $expected += $transactions->where('type', 'in')->sum('amount');
        $expected -= $transactions->where('type', 'out')->sum('amount');

        // (+) Faturamento em Dinheiro Vivo
        $cashSales = DB::table('payments')
            ->join('orders', 'payments.order_id', '=', 'orders.id')
            ->where('orders.status', 'completed')
            ->where('orders.updated_at', '>=', $shift->opened_at)
            ->where('payments.method', 'like', '%Dinheiro%')
            ->sum('payments.amount');

        $expected += $cashSales;

        $shift->update([
            'closed_at' => now(),
            'status' => 'closed',
            'closed_by' => $userId,
            'closing_balance_expected' => $expected,
            'closing_balance_actual' => $actualAmount,
        ]);
    }

    public function recordTransaction(array $data, string $operatorName): void
    {
        Transaction::create([
            'type' => $data['type'],
            'amount' => $data['amount'],
            'description' => ($data['type'] === 'in' ? 'Suprimento: ' : 'Sangria: ') . $data['description'],
            'operator_name' => $operatorName,
        ]);
    }

    public function refundAndClone(Order $order, array $data): void
    {
        $user = User::where("pin", $data['pin'])->first();
        if (!$user || !$user->hasAnyRole(["admin", "gerente", "manager"])) {
            throw new Exception("PIN inválido ou sem permissão de Gerente.");
        }

        DB::transaction(function () use ($order, $data, $user) {
            // Reclona a estrutura para deixá-lo em aberto no Salão
            $newOrder = $order->replicate();
            $newOrder->status = 'pending';
            $newOrder->payment_status = 'pending';
            $newOrder->save();

            foreach ($order->items as $item) {
                $newItem = $item->replicate();
                $newItem->order_id = $newOrder->id;
                $newItem->paid_qty = 0;
                $newItem->status = 'pending';
                $newItem->save();
            }

            $order->update(['status' => 'cancelled']);

            if (!empty($data['is_loss'])) {
                Transaction::create([
                    "type" => "out",
                    "amount" => $order->total,
                    "description" => "Perda Operacional ({$data['reason']}) - Ref Pedido Antigo #{$order->id}",
                    "operator_name" => $user->name,
                    "order_id" => $order->id,
                ]);
            }
        });
    }
}
