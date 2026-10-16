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

class DispatchScheduledTasksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // 🚀 CORREÇÃO 2: Garante o relógio correto do Brasil independentemente do servidor Cloud
        $now = now()->setTimezone('America/Sao_Paulo');
        $currentTime = $now->format('H:i') . ':00';
        $dayOfWeek = $now->dayOfWeek;
        $dayOfMonth = $now->day;

        $tasks = ChecklistTask::where('trigger_type', 'scheduled')
            ->whereTime('trigger_time', $currentTime)
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

        event(new ChecklistsUpdated());

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

        if ($usersToNotify->isNotEmpty()) {
            Notification::send(
                $usersToNotify,
                new NewTasksNotification('Novas tarefas agendadas acabaram de ser liberadas para você!')
            );
        }
    }
}
