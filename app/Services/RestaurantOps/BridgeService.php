<?php

namespace App\Services\RestaurantOps;

use App\Models\CompanySetting;
use App\Models\PrintJob;
use App\Models\Printer;
use App\Models\Order;
use App\Events\BridgeStatusUpdated;
use App\Events\PrintAlertTriggered;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Exception;

class BridgeService
{
    const REQUIRED_VERSION = '1.0.0';

    /**
     * Avalia se a bridge física local está ativa e comunicando.
     */
    public function isOnline(): bool
    {
        $settings = CompanySetting::getCached() ?? CompanySetting::first();
        if (!$settings || empty($settings->print_bridge_connected_at)) {
            return false;
        }

        // Considera online se comunicou nos últimos 180 segundos (3 minutos)
        return Carbon::parse($settings->print_bridge_connected_at)->diffInSeconds(now()) <= 180;
    }

    public function processHandshake(string $providedToken, string $bridgeVersion, string $tenantId): array
    {
        $settings = CompanySetting::getCached();

        if (empty($settings->print_bridge_token)) {
            $settings->update(['print_bridge_token' => Str::random(32)]);
        }

        if ($providedToken !== $settings->print_bridge_token) {
            throw new Exception('Acesso Negado. Token da Bridge inválido.', 403);
        }

        $status = version_compare($bridgeVersion, self::REQUIRED_VERSION, '>=') ? 'online' : 'outdated';

        $settings->update([
            'print_bridge_version' => $bridgeVersion,
            'print_bridge_connected_at' => now(),
        ]);

        broadcast(new BridgeStatusUpdated($tenantId, $status, $bridgeVersion));

        return [
            'status' => $status,
            'tenant_id' => $tenantId,
            'required_version' => self::REQUIRED_VERSION,
            'message' => $status === 'outdated' ? 'Versão obsoleta. Atualização necessária.' : 'Conectado!',
        ];
    }

    public function updateJob(string $providedToken, $jobId, string $status, ?string $errorMessage): void
    {
        $settings = CompanySetting::getCached();

        if ($providedToken !== $settings->print_bridge_token) {
            throw new Exception('Acesso Negado.', 403);
        }

        // ⏱️ Atualiza a presença da Bridge
        $settings->update(['print_bridge_connected_at' => now()]);

        $job = PrintJob::find($jobId);
        if ($job) {
            $job->update([
                'status' => $status,
                'error_message' => $errorMessage,
            ]);

            // 🚀 SE A BRIDGE REPORTAR ERRO / FALHA FÍSICA NO SPOOLER OU IMPRESSORA:
            if ($status === 'failed') {
                $order = $job->order_id ? Order::find($job->order_id) : null;
                $printer = $job->printer_id ? Printer::find($job->printer_id) : null;

                if ($order && $printer) {
                    $tableName = $order->table_number
                        ?? $order->table?->name
                        ?? $order->table?->number
                        ?? null;

                    $orderTitle = $order->type === 'table'
                        ? ($tableName ? "Mesa {$tableName}" : "Mesa (Pedido #{$order->id})")
                        : ($order->customer_name ?? "Pedido #{$order->id}");

                    $rawSource = strtolower((string) ($order->source ?? ''));
                    if (in_array($rawSource, ['customer_app', 'customer', 'b2c', 'online', 'web', 'cardapio'], true)) {
                        $source = 'customer_app';
                    } elseif (in_array($rawSource, ['team_app', 'team', 'waiter', 'garcom', 'pos'], true)) {
                        $source = 'team_app';
                    } else {
                        $source = in_array($order->type, ['delivery'], true) ? 'customer_app' : 'team_app';
                    }

                    event(new PrintAlertTriggered([
                        'id'           => (string) Str::uuid(),
                        'order_id'     => $order->id,
                        'order_title'  => trim($orderTitle),
                        'source'       => $source,
                        'printer_name' => $printer->name ?? 'Impressora',
                        'status'       => 'failed',
                        'reason'       => $errorMessage ?? 'Falha na impressora física (sem papel ou desconectada).',
                        'created_at'   => now()->toIso8601String(),
                    ]));
                }
            }
        }
    }

    public function getActivePrinters()
    {
        return Printer::where('is_active', true)
            ->select('id', 'name', 'type', 'is_cashier', 'paper_width')
            ->get();
    }
}
