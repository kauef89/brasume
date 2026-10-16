<?php

namespace App\Services\Printing;

use App\Models\Printer;
use App\Models\PrintJob;

class PrintQueueService
{
    public function getQueueDashboardData(): array
    {
        $printers = Printer::where('is_active', true)->get()->map(function ($printer) {
            $lastJob = PrintJob::where('printer_id', $printer->id)->latest()->first();

            return [
                'id' => $printer->id,
                'name' => $printer->name,
                'type' => $printer->type,
                'is_cashier' => $printer->is_cashier,
                'connection_type' => $printer->connection_type,
                'fallback_printer_id' => $printer->fallback_printer_id,
                'last_status' => $lastJob ? $lastJob->status : 'success',
                'last_error' => $lastJob && $lastJob->status === 'failed' ? $lastJob->error_message : null,
            ];
        });

        $jobs = PrintJob::with(['printer:id,name', 'order:id,customer_name'])
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(function ($job) {
                return [
                    'id' => $job->id,
                    'printer_name' => $job->printer->name ?? 'Desconhecida',
                    'order_id' => $job->order_id,
                    'customer_name' => $job->order->customer_name ?? '-',
                    'job_type' => $job->job_type,
                    'status' => $job->status,
                    'error_message' => $job->error_message,
                    'created_at' => $job->created_at->format('H:i:s'),
                ];
            });

        return ['printers' => $printers, 'printJobs' => $jobs];
    }

    public function retryAllFailed(PrintDispatcher $dispatcher): int
    {
        $failedJobs = PrintJob::where('status', 'failed')
            ->where('created_at', '>=', now()->subHours(12))
            ->pluck('id')
            ->toArray();

        if (empty($failedJobs)) return 0;

        return $dispatcher->retryJobs($failedJobs);
    }

    // 🚀 MÉTODOS ADICIONADOS AQUI
    public function updateFallback(Printer $printer, ?int $fallbackPrinterId): void
    {
        $printer->update(['fallback_printer_id' => $fallbackPrinterId]);
    }

    public function dismissJobs(array $jobIds): int
    {
        return PrintJob::whereIn('id', $jobIds)->delete();
    }
}
