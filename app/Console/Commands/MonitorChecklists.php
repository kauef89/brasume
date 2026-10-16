<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use App\Models\CashShift;
use App\Models\Checklist;
use App\Models\CompanySetting;
use App\Models\User;
use App\Notifications\SystemPushNotification; // 🚀 Import limpo e correto!
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class MonitorChecklists extends Command
{
    protected $signature = 'erp:monitor-checklists';
    protected $description = 'Monitora atrasos nos checklists de abertura e fechamento para disparar alertas Push.';

    public function handle()
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);

            $shift = CashShift::current();
            if (!$shift) {
                tenancy()->end();
                continue;
            }

            $now = now();
            $settings = CompanySetting::getCached();

            // ==============================================================
            // REGRA 1: 30 MINUTOS APÓS ABERTURA (Tarefas Pendentes)
            // ==============================================================
            $shiftOpenedAt = Carbon::parse($shift->opened_at);

            if ($now->diffInMinutes($shiftOpenedAt) >= 30 && $now->diffInMinutes($shiftOpenedAt) < 35) {

                $missingStartTasks = Checklist::where('trigger_type', 'shift_start')
                    ->where('is_active', true)
                    ->whereHas('tasks', function($q) use ($shift) {
                        $q->whereDoesntHave('logs', function($logQuery) use ($shift) {
                            $logQuery->where('cash_shift_id', $shift->id);
                        });
                    })->count();

                if ($missingStartTasks > 0) {
                    $this->notifyTeam(
                        "⚠️ Checklist Atrasado",
                        "Atenção Equipe: O turno foi aberto há 30 min e existem checklists de ABERTURA pendentes!",
                        $shift
                    );
                    Log::info("[Tenant {$tenant->id}] Push de Abertura Atrasada disparado.");
                }
            }

            // ==============================================================
            // REGRA 2: 30 MINUTOS ANTES DO FECHAMENTO
            // ==============================================================
            if ($settings && $settings->business_hours) {
                $todayName = strtolower($now->englishDayOfWeek);
                $hours = is_string($settings->business_hours) ? json_decode($settings->business_hours, true) : $settings->business_hours;

                if (isset($hours[$todayName]['close'])) {
                    $closeTime = Carbon::createFromTimeString($hours[$todayName]['close']);

                    if ($now->diffInMinutes($closeTime, false) >= 30 && $now->diffInMinutes($closeTime, false) < 35) {
                        $this->notifyTeam(
                            "🧹 Fechamento Próximo",
                            "Faltam 30 minutos para encerrar! Não esqueçam de iniciar os checklists de FECHAMENTO.",
                            $shift
                        );
                        Log::info("[Tenant {$tenant->id}] Push de Fechamento disparado.");
                    }
                }
            }

            tenancy()->end();
        }
    }

    /**
     * Envia Push para quem está no turno
     */
    private function notifyTeam(string $title, string $message, CashShift $shift)
    {
        $userIds = $shift->attendees()->pluck('users.id');
        $users = User::whereIn('id', $userIds)->get();

        foreach ($users as $user) {
            try {
                // 🚀 Dispara a notificação direcionando para o App da Equipe
                $user->notify(new SystemPushNotification($title, $message, '/team'));
            } catch (\Exception $e) {
                Log::error("Erro ao enviar Push de Checklist para o utilizador {$user->id}: " . $e->getMessage());
            }
        }
    }
}
