<?php

namespace App\Http\Controllers\Tenant\Orders;

use Illuminate\Support\Facades\DB;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Services\RestaurantOps\OperationsDashboardService;
use App\Services\Orders\OrderCancellationService;

class OrderStatusController extends Controller
{
    public function update(Order $order, Request $request, OperationsDashboardService $operationsService)
    {
        $validated = $request->validate([
            'status'              => 'required|string',
            'stamp_intermediates' => 'nullable|boolean',
        ]);

        $operationsService->updateOrderStatus(
            $order,
            $validated['status'],
            $request->boolean('stamp_intermediates')
        );

        return redirect()->back()->with('success', 'Status do pedido atualizado!');
    }

    public function cancel(Order $order, Request $request, OrderCancellationService $cancellationService)
    {
        $validated = $request->validate([
            'reason'     => 'nullable|string',
            'is_loss'    => 'nullable|boolean',
            'lost_items' => 'nullable|array',
            'pin'        => 'nullable|string',
        ]);

        $cancellationService->cancelOrder($order, $validated);

        return back()->with('success', 'Pedido cancelado e estornado com sucesso.');
    }

    public function cancelItem(Order $order, Request $request, OrderCancellationService $cancellationService)
    {
        $validated = $request->validate([
            'item_id' => 'required|integer|exists:order_items,id',
            'qty'     => 'nullable',
            'is_loss' => 'nullable|boolean',
            'reason'  => 'nullable|string',
            'pin'     => 'nullable|string',
        ]);

        try {
            $cancellationService->cancelItem($order, $validated);

            return back()->with('success', 'Item estornado com sucesso!');
        } catch (\Exception $e) {
            return back()->withErrors(['pin' => $e->getMessage()]);
        }
    }

    public function reactivate(Order $order, Request $request)
    {
        $request->validate([
            'pin'    => 'nullable|string',
            'reason' => 'nullable|string|min:15|max:255',
            'mode'   => 'required|string|in:reactivate,correct_payment'
        ], [
            'reason.min' => 'A justificativa para a SEFAZ deve conter no mínimo 15 caracteres.'
        ]);

        $settings = \App\Models\CompanySetting::getCached() ?? \App\Models\CompanySetting::first();
        if ($settings && $settings->require_pin_for_voids) {
            if (!$request->pin || !\App\Models\TeamMember::validatePinAuth($request->pin)) {
                return back()->withErrors(['pin' => 'PIN de Gerente inválido ou ausente.']);
            }
        }

        // Garante justificativa válida com mais de 15 caracteres para o Fisco
        $rawReason = trim((string) $request->input('reason', ''));
        $fiscalJustification = strlen($rawReason) >= 15
            ? $rawReason
            : "Reabertura e correcao operacional da conta do pedido #{$order->id}";

        $fiscalErrors = [];

        DB::transaction(function () use ($order, $request, $fiscalJustification, &$fiscalErrors) {
            $mode = $request->input('mode');

            // 🧾 TRATAMENTO FISCAL: Cancelamento de NFC-e Autorizada
            $authorizedDocs = $order->fiscalDocuments()->where('status', 'authorized')->get();

            foreach ($authorizedDocs as $doc) {
                // Checa o prazo legal de 30 minutos da NFC-e
                $minutesSinceIssue = $doc->issue_date ? $doc->issue_date->diffInMinutes(now()) : 999;

                if ($minutesSinceIssue > 30) {
                    $fiscalErrors[] = "A NFC-e #{$doc->number} foi emitida há mais de 30 minutos (limite da SEFAZ excedido). O documento não pôde ser cancelado automaticamente e precisará de estorno contábil.";
                    continue;
                }

                try {
                    app(\App\Services\Fiscal\SpedDocumentService::class)->cancelNfce($doc, $fiscalJustification);
                } catch (\Exception $e) {
                    $fiscalErrors[] = "Falha ao cancelar NFC-e #{$doc->number} na SEFAZ: " . $e->getMessage();
                }
            }

            // Se o cancelamento fiscal falhou, interrompe para não gerar inconsistência com a Receita
            if (!empty($fiscalErrors)) {
                throw new \Exception(implode("\n", $fiscalErrors));
            }

            // Se for 'correct_payment', estorna os registros financeiros para nova cobrança
            if ($mode === 'correct_payment') {
                foreach ($order->payments as $payment) {
                    if ($payment->transaction) {
                        $payment->transaction->delete();
                    }
                    $payment->delete();
                }

                foreach ($order->items as $item) {
                    $item->update(['paid_qty' => 0]);
                }

                $order->payment_status = 'pending';
            }

            // Reverte o status operacional
            $order->status = ($order->type === 'counter') ? 'ready' : 'pending';
            $order->closed_at = null;
            $order->save();

            // Reocupa a mesa física
            if ($order->type === 'table') {
                $table = $order->diningTable ?? ($order->dining_table_id ? \App\Models\DiningTable::find($order->dining_table_id) : null);
                if ($table) {
                    $table->update(['status' => 'occupied']);
                }
            }
        });

        return back()->with('success', 'Conta reativada com sucesso.');
    }
}
