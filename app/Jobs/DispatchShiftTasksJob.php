<?php

namespace App\Jobs;

use App\Models\ChecklistTask;
use App\Models\User;
use App\Events\ChecklistsUpdated;
use App\Notifications\NewTasksNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class DispatchShiftTasksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $triggerType;

    /**
     * Recebe 'shift_start' (abertura) ou 'shift_end' (fechamento)
     */
    public function __construct(string $triggerType)
    {
        $this->triggerType = $triggerType;
    }

    public function handle(): void
    {
        $dayOfWeek = now()->dayOfWeek;
        $dayOfMonth = now()->day;

        // 1. Busca as tarefas vinculadas a este momento do turno e que devem rodar hoje
        $tasks = ChecklistTask::where('trigger_type', $this->triggerType)
            ->where(function ($query) use ($dayOfWeek, $dayOfMonth) {
                $query->where('recurrence_type', 'daily')
                    ->orWhere(function ($qWeekly) use ($dayOfWeek) {
                        $qWeekly->where('recurrence_type', 'weekly')
                            ->where(function ($jsonQ) use ($dayOfWeek) {
                                $jsonQ->whereJsonContains('recurrence_days', $dayOfWeek)
                                    ->orWhereJsonContains('recurrence_days', (string)$dayOfWeek);
                            });
                    })
                    ->orWhere(function ($qMonthly) use ($dayOfMonth) {
                        $qMonthly->where('recurrence_type', 'monthly')
                            ->where(function ($jsonQ) use ($dayOfMonth) {
                                $jsonQ->whereJsonContains('recurrence_days', $dayOfMonth)
                                    ->orWhereJsonContains('recurrence_days', (string)$dayOfMonth);
                            });
                    })
                    ->orWhere('recurrence_type', 'once');
            })
            ->get();

        if ($tasks->isEmpty()) {
            return;
        }

        // 2. Dispara a atualização em tempo real para os PDVs abertos
        event(new ChecklistsUpdated());

        // 3. Monta a lista de usuários que precisam ser notificados via Push
        $usersToNotify = collect();

        foreach ($tasks as $task) {
            if ($task->assignment_type === 'all') {
                $usersToNotify = $usersToNotify->merge(User::where('is_active', true)->get());
            }
            elseif ($task->assignment_type === 'roles' && !empty($task->target_roles)) {
                $users = User::where('is_active', true)
                    ->whereHas('roles', function ($q) use ($task) {
                        $q->whereIn('name', $task->target_roles);
                    })->get();
                $usersToNotify = $usersToNotify->merge($users);
            }
            elseif ($task->assignment_type === 'users' && !empty($task->target_users)) {
                $users = User::where('is_active', true)
                    ->whereIn('id', $task->target_users)
                    ->get();
                $usersToNotify = $usersToNotify->merge($users);
            }
        }

        $usersToNotify = $usersToNotify->unique('id');

        // 4. Envia o alerta Push
        if ($usersToNotify->isNotEmpty()) {
            $msg = $this->triggerType === 'shift_start'
                ? 'O turno foi aberto! Verifique as suas tarefas iniciais.'
                : 'O turno está a fechar! Conclua as rotinas de fechamento.';

            Notification::send($usersToNotify, new NewTasksNotification($msg));
        }
    }
}
