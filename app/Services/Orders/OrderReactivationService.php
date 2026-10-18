<?php

namespace App\Services\Orders;

use App\Models\CompanySetting;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\TeamMember;
use App\Services\Fiscal\SpedDocumentService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderReactivationService
{
    public function __construct(
        protected SpedDocumentService $spedService
    ) {}

    /**
     * Executa a reativação de uma comanda com estorno de NFC-e e registros contábeis.
     *
     * @throws ValidationException|Exception
     */
    public function reactivate(Order $order, string $mode, ?string $reason = null, ?string $pin = null): void
    {
        $settings = CompanySetting::getCached() ?? CompanySetting::first();

        // 1. Validação de PIN de Gerente se exigido
        if ($settings && $settings->require_pin_for_voids) {
            if (!$pin || !TeamMember::validatePinAuth($pin)) {
                throw ValidationException::withMessages(['pin' => 'PIN de Gerente inválido ou ausente.']);
            }
        }

        $rawReason = trim((string) $reason);
        $fiscalJustification = strlen($rawReason) >= 15
            ? $rawReason
            : "Reabertura e correcao operacional da conta do pedido #{$order->id}";

        DB::transaction(function () use ($order, $mode, $fiscalJustification) {
            // 2. Cancelamento Fiscal de NFC-e com verificação do limite de 30 minutos
            $authorizedDocs = $order->fiscalDocuments()->where('status', 'authorized')->get();
            $fiscalErrors = [];

            foreach ($authorizedDocs as $doc) {
                $minutesSinceIssue = $doc->issue_date ? $doc->issue_date->diffInMinutes(now()) : 999;

                if ($minutesSinceIssue > 30) {
                    $fiscalErrors[] = "A NFC-e #{$doc->number} foi emitida há mais de 30 minutos (limite da SEFAZ excedido). O documento precisará de estorno contábil manual.";
                    continue;
                }

                try {
                    $this->spedService->cancelNfce($doc, $fiscalJustification);
                } catch (Exception $e) {
                    $fiscalErrors[] = "Falha ao cancelar NFC-e #{$doc->number} na SEFAZ: " . $e->getMessage();
                }
            }

            if (!empty($fiscalErrors)) {
                throw new Exception(implode("\n", $fiscalErrors));
            }

            // 3. Estorno contábil de pagamentos se for correção
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

            // 4. Reversão do status operacional
            $order->status = ($order->type === 'counter') ? 'ready' : 'pending';
            $order->closed_at = null;
            $order->save();

            // 5. Reocupação física da mesa
            if ($order->type === 'table') {
                $table = $order->diningTable ?? ($order->dining_table_id ? DiningTable::find($order->dining_table_id) : null);
                if ($table) {
                    $table->update(['status' => 'occupied']);
                }
            }
        });
    }
}
