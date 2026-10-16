<?php

namespace App\Http\Controllers\Tenant\RestaurantOps;

use App\Http\Controllers\Controller;
use App\Models\Checklist;
use App\Models\ChecklistTask;
use App\Models\Product;
use App\Models\Supply;
use App\Services\RestaurantOps\ChecklistService;
use Illuminate\Http\Request;

class ChecklistApiController extends Controller
{
    public function __construct(
        protected ChecklistService $checklistService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user() ?? auth('team')->user();
        $checklists = $this->checklistService->getChecklistsForUser($user);

        return response()->json(['checklists' => $checklists]);
    }

    public function overview()
    {
        $data = $this->checklistService->getShiftOverview();
        return response()->json($data);
    }

    public function managementIndex()
    {
        $data = $this->checklistService->getManagementData();
        return response()->json($data);
    }

    public function syncTask(Request $request)
    {
        $data = $request->validate([
            'task_id' => 'required|exists:checklist_tasks,id',
            'status'  => 'required|in:done,issue,pending',
            'notes'   => 'nullable|string',
        ]);

        try {
            $userId = $request->user()?->id ?? auth('team')->id();
            $this->checklistService->syncTask($userId, $data);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function storeChecklist(Request $request)
    {
        $data = $request->validate([
            'title'     => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        $checklist = $this->checklistService->createChecklist($data);
        return response()->json(['success' => true, 'checklist' => $checklist]);
    }

    public function updateChecklist(Request $request, Checklist $checklist)
    {
        $data = $request->validate([
            'title'     => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        $checklist = $this->checklistService->updateChecklist($checklist, $data);
        return response()->json(['success' => true, 'checklist' => $checklist]);
    }

    public function destroyChecklist(Checklist $checklist)
    {
        $this->checklistService->deleteChecklist($checklist);
        return response()->json(['success' => true]);
    }

    public function storeTask(Request $request, Checklist $checklist)
    {
        $data = $request->validate([
            'description'         => 'required|string|max:255',
            'instructions'        => 'nullable|string',
            'assignment_type'     => 'required|in:all,roles,users',
            'target_roles'        => 'nullable|array',
            'target_users'        => 'nullable|array',
            'completion_type'     => 'required|in:shared,individual',
            'trigger_type'        => 'required|in:manual,shift_start,shift_end,scheduled',
            'trigger_time'        => 'nullable',
            'deadline_minutes'    => 'nullable|numeric',
            'recurrence_type'     => 'required|in:daily,weekly,monthly,once',
            'recurrence_days'     => 'nullable|array',
            'deadline_date'       => 'nullable',
            'is_inventory_task'   => 'nullable|boolean',
            'inventory_item_type' => 'nullable|string',
            'inventory_item_id'   => 'nullable|integer',
        ]);

        $task = $this->checklistService->createTask($checklist, $data);
        return response()->json(['success' => true, 'task' => $task]);
    }

    public function updateTask(Request $request, ChecklistTask $task)
    {
        $data = $request->validate([
            'description'         => 'required|string|max:255',
            'instructions'        => 'nullable|string',
            'assignment_type'     => 'required|in:all,roles,users',
            'target_roles'        => 'nullable|array',
            'target_users'        => 'nullable|array',
            'completion_type'     => 'required|in:shared,individual',
            'trigger_type'        => 'required|in:manual,shift_start,shift_end,scheduled',
            'trigger_time'        => 'nullable',
            'deadline_minutes'    => 'nullable|numeric',
            'recurrence_type'     => 'required|in:daily,weekly,monthly,once',
            'recurrence_days'     => 'nullable|array',
            'deadline_date'       => 'nullable',
            'is_inventory_task'   => 'nullable|boolean',
            'inventory_item_type' => 'nullable|string',
            'inventory_item_id'   => 'nullable|integer',
        ]);

        $task = $this->checklistService->updateTask($task, $data);
        return response()->json(['success' => true, 'task' => $task]);
    }

    public function destroyTask(ChecklistTask $task)
    {
        $this->checklistService->deleteTask($task);
        return response()->json(['success' => true]);
    }

    public function searchInventoryItems(Request $request)
    {
        $type = $request->input('type', 'App\\Models\\Product');
        $query = $request->input('q', '');

        if ($type === 'App\\Models\\Supply') {
            $items = Supply::where('name', 'like', "%{$query}%")
                ->limit(15)
                ->get(['id', 'name']);
        } else {
            $items = Product::where('name', 'like', "%{$query}%")
                ->limit(15)
                ->get(['id', 'name', 'type', 'current_stock', 'unit_of_measure', 'track_inventory']);
        }

        return response()->json($items);
    }
}
