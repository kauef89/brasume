<?php

namespace App\Filament\Central\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class SystemStatusWidget extends BaseWidget
{
    // Força o widget a ficar no topo do Dashboard
    protected static ?int $sort = -10;

    // Atualiza os dados a cada 10 segundos automaticamente na tela!
    protected ?string $pollingInterval = '10s';

    protected function getStats(): array
    {
        return [
            $this->getWorkerStatus(),
            $this->getReverbStatus(),
        ];
    }

    private function getWorkerStatus(): Stat
    {
        $lastHeartbeat = Cache::store('file')->get('system_worker_heartbeat', 0);

        // Se o último batimento foi há menos de 120 segundos (2 minutos), está vivo.
        $isOnline = (time() - $lastHeartbeat) <= 120;

        return Stat::make('Filas & Workers (Supervisor)', $isOnline ? 'Online' : 'Offline / Falha')
            ->description($isOnline ? 'Tarefas processadas em tempo real.' : 'As filas estão travadas! Reinicie o Supervisor.')
            ->descriptionIcon($isOnline ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-triangle')
            ->color($isOnline ? 'success' : 'danger')
            ->chart($isOnline ? [1, 2, 3, 4, 5, 4, 3, 4, 5] : [1, 0, 0, 0, 0]) // Um gráfico visual bonitinho
            ->extraAttributes([
                'class' => $isOnline ? '' : 'animate-pulse ring-2 ring-danger-500',
            ]);
    }

    private function getReverbStatus(): Stat
    {
        // Lê as configurações de host/porta originais do Reverb (.env)
        $host = config('reverb.servers.reverb.host', '127.0.0.1');
        $port = config('reverb.servers.reverb.port', 8080);

        // PING RÁPIDO: Tenta abrir a porta do WebSockets localmente com 1 segundo de Timeout
        $isOnline = false;
        try {
            $fp = @fsockopen($host, $port, $errno, $errstr, 1);
            if ($fp) {
                $isOnline = true;
                fclose($fp);
            }
        } catch (\Exception $e) {
            $isOnline = false;
        }

        return Stat::make('Reverb (WebSockets)', $isOnline ? 'Online' : 'Offline / Falha')
            ->description($isOnline ? 'Conexões bidirecionais ativas.' : 'Servidor WebSockets caiu! Reinicie o Reverb.')
            ->descriptionIcon($isOnline ? 'heroicon-m-bolt' : 'heroicon-m-x-circle')
            ->color($isOnline ? 'success' : 'danger')
            ->chart($isOnline ? [5, 4, 5, 4, 5, 4, 5, 4] : [1, 0, 0, 0, 0])
            ->extraAttributes([
                'class' => $isOnline ? '' : 'animate-pulse ring-2 ring-danger-500',
            ]);
    }
}
