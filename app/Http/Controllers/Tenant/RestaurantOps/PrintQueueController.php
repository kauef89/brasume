<?php

namespace App\Http\Controllers\Tenant\RestaurantOps;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Printer;
use App\Services\Printing\PrintDispatcher;
use App\Services\Printing\PrintQueueService;

class PrintQueueController extends Controller
{
    public function index(PrintQueueService $service)
    {
        return Inertia::render('Operations/Printers/Printers', $service->getQueueDashboardData());
    }

    public function retryJobs(Request $request, PrintDispatcher $dispatcher)
    {
        $request->validate(['job_ids' => 'required|array']);
        $count = $dispatcher->retryJobs($request->job_ids);

        return back()->with('success', "{$count} trabalhos reenviados para a fila!");
    }

    public function retryAllFailed(PrintQueueService $service, PrintDispatcher $dispatcher)
    {
        $count = $service->retryAllFailed($dispatcher);

        if ($count === 0) {
            return back()->with('info', 'Não há impressões falhas recentes.');
        }

        return back()->with('success', "{$count} trabalhos falhos foram reenviados!");
    }

    public function setFallback(Request $request, Printer $printer, PrintQueueService $service)
    {
        $request->validate(['fallback_printer_id' => 'nullable|exists:printers,id']);

        $service->updateFallback($printer, $request->fallback_printer_id);

        return back()->with('success', 'Regra de desvio atualizada com sucesso!');
    }

    public function dismissJobs(Request $request, PrintQueueService $service)
    {
        $request->validate(['job_ids' => 'required|array']);

        $service->dismissJobs($request->job_ids);

        return back()->with('success', count($request->job_ids) . ' trabalhos descartados do histórico.');
    }
}
