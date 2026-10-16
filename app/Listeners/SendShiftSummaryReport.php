<?php

namespace App\Listeners;

use App\Events\ShiftClosed;
use App\Models\Tenant;
use App\Models\CompanySetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendShiftSummaryReport implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(ShiftClosed $event): void
    {
        try {
            if ($event->tenantId !== 'central') {
                $tenant = Tenant::find($event->tenantId);
                if ($tenant) tenancy()->initialize($tenant);
            }

            $shift = $event->shift;
            $settings = CompanySetting::getCached();

            // 🚀 Aqui você pode plugar a sua API do WhatsApp (Evolution/Z-API) ou geração de PDF
            // Exemplo de payload preparado:
            $message = "📊 *Fechamento de Caixa*\n";
            $message .= "Operador: " . ($shift->closer->name ?? 'Sistema') . "\n";
            $message .= "Apurado: R$ " . number_format($shift->closing_balance_actual, 2, ',', '.') . "\n";
            $message .= "Esperado: R$ " . number_format($shift->closing_balance_expected, 2, ',', '.');

            if ($shift->closing_balance_actual < $shift->closing_balance_expected) {
                $message .= "\n⚠️ *Furo no Caixa Detectado!*";
            }

            Log::info("Enviando relatório do turno {$shift->id} para a gestão: \n" . $message);

            if ($event->tenantId !== 'central') tenancy()->end();

        } catch (\Exception $e) {
            Log::error("Erro ao enviar relatório de fechamento: " . $e->getMessage());
        }
    }
}
