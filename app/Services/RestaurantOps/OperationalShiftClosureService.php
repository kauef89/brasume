<?php

namespace App\Services\RestaurantOps;

use App\Models\CashShift;
use App\Models\CompanySetting;
use App\Models\PrintJob;
use App\Events\ShiftClosed;
use App\Events\StoreStatusUpdated;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OperationalShiftClosureService
{
    public function __construct(
        protected WmsRestockService $wmsService
    ) {}

    public function closeShift(?int $shiftId = null, bool $closeStore = true): array
    {
        Log::info("🛑 [OperationalShiftClosure] Iniciando encerramento operacional do turno #" . ($shiftId ?? 'ativo') . "...");

        $results = [
            'store_closed'      => false,
            'wms_rules_synced'  => 0,
            'print_jobs_purged' => 0,
        ];

        $shift = $shiftId
            ? CashShift::find($shiftId)
            : CashShift::where('status', 'open')->latest('opened_at')->first();

        // 🚀 ACÚMULO DE DÍVIDAS: Lança diárias não pagas no extrato (Ledger) dos colaboradores
        if ($shift) {
            try {
                $attendanceService = app(\App\Services\RestaurantOps\ShiftAttendanceService::class);
                
                $unpaidAttendees = $shift->attendees()
                    ->wherePivot('status', 'active')
                    ->wherePivot('attendance_status', 'present')
                    ->wherePivotNull('account_payable_id')
                    ->wherePivot('daily_rate', '>', 0)
                    ->get();

                foreach ($unpaidAttendees as $user) {
                    if (!$user->employee) continue;

                    // Evita duplicidade caso o turno seja reaberto e fechado novamente
                    $alreadyProcessed = $user->employee->ledgers()
                        ->where('description', 'like', "%Turno #{$shift->id}%")
                        ->where('type', 'credit')
                        ->exists();

                    if ($alreadyProcessed) continue;

                    $employee = $user->employee;
                    $isClt = $employee && ($employee->employment_type === \App\Enums\EmploymentType::CLT || (is_string($employee->employment_type) && $employee->employment_type === 'clt'));
                    $grossRate = $isClt ? 0.00 : (float) ($user->pivot->daily_rate ?? 0);

                    $latestLedger = $employee ? $employee->ledgers()->latest('id')->first() : null;
                    $balance = $latestLedger ? (float) $latestLedger->balance : 0.00;

                    // 1. Crédito da Diária (Apenas para Freelancers/Diaristas — CLT recebe via Folha Mensal)
                    if (!$isClt && $grossRate > 0) {
                        $employee->ledgers()->create([
                            'type'        => 'credit',
                            'description' => "Diária de Trabalho (Turno #{$shift->id}) - Acumulada",
                            'amount'      => $grossRate,
                            'balance'     => $balance + $grossRate,
                        ]);
                        $balance += $grossRate;
                    }

                    // 2. Abatimento de Consumo Interno do Turno
                    $consumptionData = $attendanceService->getFreelancerShiftConsumption($user, $shift);
                    $consumptionAmount = (float) $consumptionData['total'];

                    if ($consumptionAmount > 0) {
                        $user->employee->ledgers()->create([
                            'type'        => 'debit',
                            'description' => "Abatimento de Consumo Interno (Turno #{$shift->id})",
                            'amount'      => $consumptionAmount,
                            'balance'     => $balance - $consumptionAmount,
                        ]);
                    }
                    
                    \Illuminate\Support\Facades\Log::info("💰 Diária do garçom {$user->name} (R$ {$grossRate}) acumulada no extrato.");
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Erro ao acumular dívidas no fechamento do turno: " . $e->getMessage());
            }
        }

        // 1. GESTÃO DO STATUS DA LOJA NO APP DO CLIENTE
        if ($closeStore && $shift) {
            $shift->update([
                'accepting_orders' => false,
            ]);

            Cache::forget('store_availability_status');
            Cache::forget('customer_catalog_data');

            if (method_exists(CompanySetting::class, 'purgeCache')) {
                CompanySetting::purgeCache();
            }

            $tenantId = function_exists('tenant') && tenant() ? tenant('id') : 'central';
            if (class_exists(StoreStatusUpdated::class)) {
                broadcast(new StoreStatusUpdated($tenantId));
            }

            $results['store_closed'] = true;
            Log::info("🚪 [OperationalShiftClosure] Loja fechada no app do cliente.");
        }

        // 2. CONSOLIDAÇÃO DO WMS (Baixa de Prateleira por Consumo)
        try {
            $affected = $this->wmsService->syncPendingConsumptionToShelf($shift?->id);
            $results['wms_rules_synced'] = $affected;
            Log::info("📦 [OperationalShiftClosure] Consumo de prateleira consolidado ({$affected} regras).");
        } catch (\Throwable $e) {
            Log::error("🚨 [OperationalShiftClosure] Falha ao sincronizar WMS: " . $e->getMessage());
        }

        // 🚀 3. EXPURGO DO SPOOLER DE IMPRESSÃO (Remove impressões antigas do turno que fechou)
        try {
            $purged = PrintJob::where('created_at', '<=', now())->delete();
            $results['print_jobs_purged'] = $purged;
            Log::info("🖨️ [OperationalShiftClosure] Spooler limpo ({$purged} trabalhos de impressão removidos).");
        } catch (\Throwable $e) {
            Log::error("🚨 [OperationalShiftClosure] Falha ao limpar spooler de impressão: " . $e->getMessage());
        }

        // 🚀 4. AVISO EM TEMPO REAL PARA LIMPEZA DE RASCUNHOS NOS APPS
        if ($shift) {
            $tenantId = function_exists('tenant') && tenant() ? tenant('id') : null;
            broadcast(new \App\Events\ShiftClosed($shift));
        }

        Log::info("🏁 [OperationalShiftClosure] Turno finalizado com sucesso.", $results);

        return $results;
    }
}
