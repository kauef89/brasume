<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use App\Models\AccountPayable;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditEmployee extends EditRecord
{
    use \App\Filament\Traits\RedirectsToIndex;

    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add_service')
                ->label('Lançar Serviço')
                ->icon('heroicon-o-briefcase')
                ->color('success')
                ->form([
                    TextInput::make('description')
                        ->label('Descrição do Serviço')
                        ->placeholder('Ex: Diária extra, bônus de performance')
                        ->required(),
                    TextInput::make('amount')
                        ->label('Valor do Crédito')
                        ->numeric()
                        ->prefix('R$')
                        ->required(),
                    DatePicker::make('due_date')
                        ->label('Data Prevista de Pagamento')
                        ->default(now())
                        ->required(),
                ])
                ->action(function (array $data, $record) {
                    AccountPayable::create([
                        'employee_id' => $record->id, 
                        'description' => "Serviço: {$data['description']}",
                        'total_value' => $data['amount'],
                        'due_date' => $data['due_date'],
                        'status' => 'pending',
                    ]);

                    Notification::make()
                        ->title('Serviço lançado no extrato!')
                        ->success()
                        ->send();
                }),

            DeleteAction::make(),
        ];
    }
}