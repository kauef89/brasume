<?php

namespace App\Filament\Central\Resources\FailedJobs\Pages;

use App\Filament\Central\Resources\FailedJobs\FailedJobResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Artisan;
use Filament\Notifications\Notification;

class ListFailedJobs extends ListRecords
{
    protected static string $resource = FailedJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retry_all')
                ->label('Reprocessar Todos os Erros')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Reprocessar todos os Jobs com falha?')
                ->modalDescription('O Laravel tentará re-executar todas as tarefas que falharam.')
                ->action(function () {
                    Artisan::call('queue:retry', ['id' => ['all']]);

                    Notification::make()
                        ->title('Todas as tarefas foram reenviadas para a fila!')
                        ->success()
                        ->send();
                }),
        ];
    }
}
