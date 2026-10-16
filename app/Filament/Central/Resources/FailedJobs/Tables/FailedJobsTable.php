<?php

namespace App\Filament\Central\Resources\FailedJobs\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Illuminate\Support\Facades\Artisan;
use Filament\Notifications\Notification;

class FailedJobsTable
{
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('failed_at')
                    ->label('Data / Hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                TextColumn::make('tenant_identifier')
                    ->label('Tenant / Origem')
                    ->badge()
                    ->color('warning')
                    ->searchable(false),

                TextColumn::make('job_name')
                    ->label('Tarefa (Job)')
                    ->weight('bold')
                    ->searchable(false),

                TextColumn::make('queue')
                    ->label('Fila')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('exception')
                    ->label('Erro')
                    ->limit(50)
                    ->tooltip(fn ($record) => substr($record->exception, 0, 250)),
            ])
            ->defaultSort('failed_at', 'desc')
            ->actions([
                // Ver Stack Trace Completo
                Action::make('view_error')
                    ->tooltip('Ver Erro')
                    ->hiddenLabel()
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->form([
                        Textarea::make('exception')
                            ->label('Detalhes da Exceção / Stack Trace')
                            ->rows(12)
                            ->readOnly(),
                    ])
                    ->fillForm(fn ($record) => ['exception' => $record->exception]),

                // Re-executar o Job
                Action::make('retry')
                    ->tooltip('Tentar Novamente')
                    ->hiddenLabel()
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        Artisan::call('queue:retry', ['id' => [$record->uuid ?? $record->id]]);

                        Notification::make()
                            ->title('Job reenviado para a fila!')
                            ->success()
                            ->send();
                    }),

                DeleteAction::make()
                ->tooltip('Excluir')
                ->hiddenLabel(),

            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
