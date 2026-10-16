<?php

namespace App\Services\Printing;

use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\Order;
use App\Events\PrintJobCreated;
use App\Events\PrintAlertTriggered;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PrintDispatcher
{
    public function dispatch(Printer $printer, $document): void
    {
        Log::info("🚀 [PrintDispatcher] Iniciando formatação do documento para a impressora: {$printer->name}");

        if ($printer->fallback_printer_id) {
            $printer = Printer::find($printer->fallback_printer_id) ?? $printer;
            Log::info("🔄 [PrintDispatcher] Rota de Contingência ativada! Destino final: {$printer->name}");
        }

        $orderId = null;
        if (method_exists($document, 'getOrderId')) {
            $orderId = $document->getOrderId();
        } elseif (property_exists($document, 'order') && isset($document->order->id)) {
            $orderId = $document->order->id;
        }

        $order = $orderId ? Order::find($orderId) : null;

        if (!$printer->is_active) {
            Log::warning("🛑 [PrintDispatcher] A impressora de destino está inativa. Disparo cancelado.");
            $this->firePrintAlert($printer, $order, 'Impressora de destino está desativada.');
            return;
        }

        $blueprint = $document->build();

        // 1. Grava no Banco de Dados o "Log" SEMPRE como pending, confiando que a Bridge puxará o evento
        try {
            $job = PrintJob::create([
                'printer_id'    => $printer->id,
                'order_id'      => $orderId,
                'job_type'      => $document->getJobType(),
                'payload'       => $blueprint,
                'status'        => 'pending',
                'error_message' => null,
                'attempts'      => 0
            ]);
            Log::info("💾 [PrintDispatcher] PrintJob #{$job->id} salvo com sucesso!");
        } catch (\Exception $e) {
            Log::error("❌ [PrintDispatcher] Erro ao salvar na tabela 'print_jobs': " . $e->getMessage());
            $this->firePrintAlert($printer, $order, 'Erro interno ao salvar ordem de impressão.');
            return;
        }

        // 2. Prepara o Pacote para a Bridge Node.js
        $payload = [
            'print_job_id' => $job->id,
            'printer_type' => $printer->connection_type,
            'os_name'      => $printer->os_name,
            'paper_width'  => $printer->paper_width,
            'job_type'     => $document->getJobType(),
            'blueprint'    => $blueprint
        ];

        // 3. Dispara para o Reverb (WebSocket) IMEDIATAMENTE
        try {
            $tenantId = tenant('id') ?? 'central';
            broadcast(new PrintJobCreated($tenantId, $payload));
        } catch (\Exception $e) {
            $job->update(['status' => 'failed', 'error_message' => 'Falha no WebSocket: ' . $e->getMessage()]);
            Log::error("❌ [PrintDispatcher] Erro ao tentar disparar o WebSocket: " . $e->getMessage());
            $this->firePrintAlert($printer, $order, 'Falha ao transmitir ordem para a fila.');
        }
    }

    public function retryJobs(array $jobIds): int
    {
        $jobs = PrintJob::whereIn('id', $jobIds)->get();
        $count = 0;

        foreach ($jobs as $job) {
            $job->update(['status' => 'pending', 'attempts' => $job->attempts + 1]);

            $printer = Printer::find($job->printer_id);
            if ($printer && $printer->fallback_printer_id) {
                $printer = Printer::find($printer->fallback_printer_id) ?? $printer;
            }

            if (!$printer || !$printer->is_active) continue;

            $payload = [
                'print_job_id' => $job->id,
                'printer_type' => $printer->connection_type,
                'os_name'      => $printer->os_name,
                'paper_width'  => $printer->paper_width,
                'job_type'     => $job->job_type,
                'blueprint'    => $job->payload,
            ];

            try {
                $tenantId = tenant('id') ?? 'central';
                broadcast(new PrintJobCreated($tenantId, $payload));
                $count++;
            } catch (\Exception $e) {
                Log::error("Falha ao disparar Retry Print Job: " . $e->getMessage());
            }
        }

        return $count;
    }

    public function notifyPrintFailure(Printer $printer, ?Order $order, string $reason, string $status = 'failed'): void
    {
        $this->firePrintAlert($printer, $order, $reason, $status);
    }

    protected function firePrintAlert(Printer $printer, ?Order $order, string $reason, string $status = 'failed'): void
    {
        if (!$order) return;

        $tableName = $order->table_number
            ?? $order->table?->name
            ?? $order->table?->number
            ?? null;

        $orderTitle = $order->type === 'table'
            ? ($tableName ? "Mesa {$tableName}" : "Mesa (Pedido #{$order->id})")
            : ($order->customer_name ?? "Pedido #{$order->id}");

        $source = $order->source ?? ($order->type === 'delivery' ? 'customer_app' : 'team_app');

        event(new PrintAlertTriggered([
            'id'           => (string) Str::uuid(),
            'order_id'     => $order->id,
            'order_title'  => trim($orderTitle),
            'source'       => $source,
            'printer_name' => $printer->name ?? 'Impressora',
            'status'       => $status,
            'reason'       => $reason,
            'created_at'   => now()->toIso8601String(),
        ]));
    }
}
