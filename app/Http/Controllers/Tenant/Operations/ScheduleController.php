<?php

namespace App\Http\Controllers\Tenant\Operations;

use App\Http\Controllers\Controller;
use App\Services\RestaurantOps\ScheduleService;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function batchSave(Request $request, ScheduleService $scheduleService)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'schedules' => 'present|array',
            'slots' => 'nullable|array'
        ]);

        $scheduleService->saveWeeklySchedule(
            $validated['start_date'],
            $validated['schedules'],
            $validated['slots'] ?? null
        );

        return redirect()->to('/pos/schedules?start_date=' . $validated['start_date'])
            ->with('success', 'Escala semanal e slots salvos com sucesso!');
    }

    public function cloneWeek(Request $request, ScheduleService $scheduleService)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
        ]);

        $scheduleService->clonePreviousWeek($validated['start_date']);

        return redirect()->to('/pos/schedules?start_date=' . $validated['start_date'])
            ->with('success', 'Escala da semana anterior clonada com sucesso!');
    }
}
