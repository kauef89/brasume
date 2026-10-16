<?php

namespace App\Filament\Central\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Artisan;
use Filament\Notifications\Notification;

class Dashboard extends BaseDashboard
{
    // Adicionamos os botões no cabeçalho do Dashboard
    protected function getHeaderActions(): array
    {
        return [
            // 1. Botão para reiniciar os Workers (Supervisor)
            Action::make('restart_queue')
                ->label('Reiniciar Filas (Workers)')
                ->icon('heroicon-o-cpu-chip')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Reiniciar os Workers?')
                ->modalDescription('Isto enviará um sinal para o Supervisor reiniciar os processos em background. Nenhuma tarefa será perdida.')
                ->action(function () {
                    // O comando nativo do Laravel para reiniciar as filas
                    Artisan::call('queue:restart');

                    Notification::make()
                        ->title('Sinal de reinício enviado!')
                        ->body('O Supervisor reiniciará os workers em instantes. O widget ficará verde logo a seguir.')
                        ->success()
                        ->send();
                }),

            // 2. Botão para reiniciar os WebSockets (Reverb)
            Action::make('restart_reverb')
                ->label('Reiniciar WebSockets (Reverb)')
                ->icon('heroicon-o-bolt')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Reiniciar o Reverb?')
                ->modalDescription('Isto desconectará todos os PDVs e KDS momentaneamente. Eles reconectarão em cerca de 3 segundos.')
                ->action(function () {
                    // O comando nativo para reiniciar o Reverb
                    Artisan::call('reverb:restart');

                    Notification::make()
                        ->title('Reverb reiniciado!')
                        ->body('O serviço de tempo real foi reiniciado com sucesso.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
