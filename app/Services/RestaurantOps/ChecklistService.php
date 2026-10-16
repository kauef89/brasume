<?php

namespace App\Services\RestaurantOps;

use App\Models\Checklist;
use App\Models\ChecklistLog;
use App\Models\ChecklistTask;
use App\Models\CashShift;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class ChecklistService
{
    private function getTodayRecurrenceFilter(): \Closure
    {
        $dayOfWeek = now()->dayOfWeek;
        $dayOfMonth = now()->day;

        return function ($query) use ($dayOfWeek, $dayOfMonth) {
            $query->where(function ($q) use ($dayOfWeek, $dayOfMonth) {
                $q->where('recurrence_type', 'daily')
                  ->orWhere(function ($qWeekly) use ($dayOfWeek) {
                      $qWeekly->where('recurrence_type', 'weekly')
                              ->where(function($jsonQ) use ($dayOfWeek) {
                                  $jsonQ->whereJsonContains('recurrence_days', $dayOfWeek)
                                        ->orWhereJsonContains('recurrence_days', (string)$dayOfWeek);
                              });
                  })
                  ->orWhere(function ($qMonthly) use ($dayOfMonth) {
                      $qMonthly->where('recurrence_type', 'monthly')
                               ->where(function($jsonQ) use ($dayOfMonth) {
                                  $jsonQ->whereJsonContains('recurrence_days', $dayOfMonth)
                                        ->orWhereJsonContains('recurrence_days', (string)$dayOfMonth);
                               });
                  })
                  ->orWhere('recurrence_type', 'once');
            });
        };
    }

    public function getChecklistsForUser(User $user): array
    {
        $shift = CashShift::where('status', 'open')->latest()->first();
        if (!$shift) {
            return [];
        }

        $roles = $user->roles->pluck('name')->toArray();
        if (empty($roles)) {
            $roles = ['waiter'];
        }
        $userId = $user->id;

        $todayFilter = $this->getTodayRecurrenceFilter();

        $taskFilter = function ($query) use ($roles, $userId, $todayFilter, $shift) {
            $query->with('inventoryItem');

            $query->where(function ($q) use ($roles, $userId) {
                $q->where('assignment_type', 'all')
                  ->orWhere(function ($qRoles) use ($roles) {
                      $qRoles->where('assignment_type', 'roles');
                      $qRoles->where(function ($qInJson) use ($roles) {
                          foreach ($roles as $r) {
                              $qInJson->orWhereJsonContains('target_roles', $r)
                                      ->orWhereJsonContains('target_roles', (string)$r);
                          }
                      });
                  })
                  ->orWhere(function ($qUsers) use ($userId) {
                      $qUsers->where('assignment_type', 'users')
                              ->where(function($qInJson) use ($userId) {
                                  $qInJson->whereJsonContains('target_users', $userId)
                                          ->orWhereJsonContains('target_users', (string)$userId);
                              });
                  });
            });

            $todayFilter($query);

            $query->whereNotExists(function ($qDone) use ($shift) {
                $qDone->select(DB::raw(1))
                        ->from('checklist_logs')
                        ->whereColumn('checklist_logs.checklist_task_id', 'checklist_tasks.id')
                        ->where('checklist_tasks.recurrence_type', 'once')
                        ->where('checklist_logs.status', 'done')
                        ->where('checklist_logs.cash_shift_id', '!=', $shift->id);
            });

            $query->orderBy('sort_order');
        };

        return Checklist::with(['tasks' => $taskFilter])
            ->where('is_active', true)
            ->whereHas('tasks', $taskFilter)
            ->get()
            ->map(function ($checklist) use ($shift) {
                $tasks = $checklist->tasks->map(function ($task) use ($shift) {
                    $log = ChecklistLog::where('checklist_task_id', $task->id)
                        ->where('cash_shift_id', $shift->id)
                        ->first();

                    return [
                        'id'                => $task->id,
                        'description'       => $task->description,
                        'instructions'      => $task->instructions,
                        'status'            => $log ? $log->status : 'pending',
                        'notes'             => $log ? $log->notes : null,
                        'trigger_type'      => $task->trigger_type,
                        'trigger_time'      => $task->trigger_time,
                        'recurrence_type'   => $task->recurrence_type,
                        'deadline_minutes'  => $task->deadline_minutes,
                        'deadline_date'     => $task->deadline_date,
                        'is_inventory_task' => (bool) $task->is_inventory_task,
                        'inventory_item'    => $task->is_inventory_task ? $task->inventoryItem : null,
                    ];
                });

                return [
                    'id'          => $checklist->id,
                    'title'       => $checklist->title,
                    'progress'    => $tasks->where('status', '!=', 'pending')->count(),
                    'total_tasks' => $tasks->count(),
                    'tasks'       => $tasks->values(),
                ];
            })->toArray();
    }

    public function syncTask(int $userId, array $data): void
    {
        $shift = CashShift::where('status', 'open')->latest()->first();
        if (!$shift) {
            throw new Exception('Não existe nenhum turno aberto para registrar esta tarefa.');
        }

        ChecklistLog::updateOrCreate(
            [
                'checklist_task_id' => $data['task_id'],
                'cash_shift_id'     => $shift->id,
            ],
            [
                'user_id' => $userId,
                'status'  => $data['status'],
                'notes'   => $data['notes'] ?? null,
            ]
        );
    }

    public function getShiftOverview(): array
    {
        $shift = CashShift::where('status', 'open')->latest()->first();
        if (!$shift) {
            return [
                'has_open_shift'        => false,
                'total_tasks'           => 0,
                'completed_tasks'       => 0,
                'issues_count'          => 0,
                'completion_percentage' => 0,
                'issues'                => [],
            ];
        }

        $todayFilter = $this->getTodayRecurrenceFilter();

        $activeChecklists = Checklist::with(['tasks' => $todayFilter])
            ->where('is_active', true)
            ->whereHas('tasks', $todayFilter)
            ->get();

        $allTasks = $activeChecklists->pluck('tasks')->flatten();
        $totalTasksCount = $allTasks->count();

        if ($totalTasksCount === 0) {
            return [
                'has_open_shift'        => true,
                'total_tasks'           => 0,
                'completed_tasks'       => 0,
                'issues_count'          => 0,
                'completion_percentage' => 100,
                'issues'                => [],
            ];
        }

        $logs = ChecklistLog::with(['task.checklist', 'user'])
            ->where('cash_shift_id', $shift->id)
            ->get();

        $completedCount = $logs->where('status', 'done')->count();
        $issues = $logs->where('status', 'issue');

        $completionPercentage = round((($completedCount + $issues->count()) / $totalTasksCount) * 100);

        return [
            'has_open_shift'        => true,
            'total_tasks'           => $totalTasksCount,
            'completed_tasks'       => $completedCount,
            'issues_count'          => $issues->count(),
            'completion_percentage' => min(100, $completionPercentage),
            'issues'                => $issues->map(function ($log) {
                return [
                    'id'              => $log->id,
                    'task_name'       => $log->task->description ?? 'Tarefa',
                    'checklist_title' => $log->task->checklist->title ?? 'Rotina',
                    'reported_by'     => $log->user->name ?? 'Colaborador',
                    'notes'           => $log->notes,
                    'time'            => $log->updated_at ? $log->updated_at->format('H:i') : '--:--',
                ];
            })->values()->toArray(),
        ];
    }

    public function getManagementData(): array
    {
        $checklists = Checklist::with(['tasks' => function ($q) {
            $q->with('inventoryItem')->orderBy('sort_order')->orderBy('id');
        }])->orderBy('title')->get();

        $users = User::where('is_active', true)->select('id', 'name')->get();

        $roles = [
            ['value' => 'kitchen', 'label' => 'Cozinha (KDS)'],
            ['value' => 'waiter',  'label' => 'Garçons / Salão'],
            ['value' => 'cashier', 'label' => 'Caixa / Atendimento'],
            ['value' => 'driver',  'label' => 'Entregadores'],
            ['value' => 'admin',   'label' => 'Gestão'],
        ];

        return [
            'checklists' => $checklists,
            'users'      => $users,
            'roles'      => $roles,
            'overview'   => $this->getShiftOverview(),
        ];
    }

    public function createChecklist(array $data): Checklist
    {
        return Checklist::create([
            'title'     => $data['title'],
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function updateChecklist(Checklist $checklist, array $data): Checklist
    {
        $checklist->update([
            'title'     => $data['title'] ?? $checklist->title,
            'is_active' => $data['is_active'] ?? $checklist->is_active,
        ]);
        return $checklist;
    }

    public function deleteChecklist(Checklist $checklist): void
    {
        $checklist->tasks()->delete();
        $checklist->delete();
    }

    public function createTask(Checklist $checklist, array $data): ChecklistTask
    {
        return $checklist->tasks()->create([
            'description'         => $data['description'],
            'instructions'        => $data['instructions'] ?? null,
            'assignment_type'     => $data['assignment_type'] ?? 'all',
            'target_roles'        => $data['target_roles'] ?? null,
            'target_users'        => $data['target_users'] ?? null,
            'completion_type'     => $data['completion_type'] ?? 'shared',
            'trigger_type'        => $data['trigger_type'] ?? 'manual',
            'trigger_time'        => $data['trigger_time'] ?? null,
            'deadline_minutes'    => $data['deadline_minutes'] ?? 60,
            'recurrence_type'     => $data['recurrence_type'] ?? 'daily',
            'recurrence_days'     => $data['recurrence_days'] ?? null,
            'deadline_date'       => $data['deadline_date'] ?? null,
            'sort_order'          => $checklist->tasks()->max('sort_order') + 1,
            'is_inventory_task'   => (bool) ($data['is_inventory_task'] ?? false),
            'inventory_item_type' => $data['inventory_item_type'] ?? null,
            'inventory_item_id'   => $data['inventory_item_id'] ?? null,
        ]);
    }

    public function updateTask(ChecklistTask $task, array $data): ChecklistTask
    {
        $task->update([
            'description'         => $data['description'] ?? $task->description,
            'instructions'        => $data['instructions'] ?? $task->instructions,
            'assignment_type'     => $data['assignment_type'] ?? $task->assignment_type,
            'target_roles'        => $data['target_roles'] ?? $task->target_roles,
            'target_users'        => $data['target_users'] ?? $task->target_users,
            'completion_type'     => $data['completion_type'] ?? $task->completion_type,
            'trigger_type'        => $data['trigger_type'] ?? $task->trigger_type,
            'trigger_time'        => $data['trigger_time'] ?? $task->trigger_time,
            'deadline_minutes'    => $data['deadline_minutes'] ?? $task->deadline_minutes,
            'recurrence_type'     => $data['recurrence_type'] ?? $task->recurrence_type,
            'recurrence_days'     => $data['recurrence_days'] ?? $task->recurrence_days,
            'deadline_date'       => $data['deadline_date'] ?? $task->deadline_date,
            'is_inventory_task'   => (bool) ($data['is_inventory_task'] ?? false),
            'inventory_item_type' => $data['inventory_item_type'] ?? null,
            'inventory_item_id'   => $data['inventory_item_id'] ?? null,
        ]);
        return $task;
    }

    public function deleteTask(ChecklistTask $task): void
    {
        $task->delete();
    }
}
