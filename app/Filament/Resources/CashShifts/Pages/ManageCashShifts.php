<?php

namespace App\Filament\Resources\CashShifts\Pages;

use App\Filament\Resources\CashShifts\CashShiftResource;
use App\Models\BankAccount;
use App\Models\CashShift;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ManageRecords;
use Filament\Notifications\Notification;

class ManageCashShifts extends ManageRecords
{
    protected static string $resource = CashShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('abrir_caixa')
                ->label('Abrir Novo Turno')
                ->icon('heroicon-o-play')
                ->color('success')
                ->form([
                    Select::make('bank_account_id')
                        ->label('Selecione a Gaveta / Cofre')
                        ->options(BankAccount::where('type', 'vault')->pluck('name', 'id'))
                        ->required()
                        ->searchable()
                        ->preload(),
                        
                    TextInput::make('opening_balance')
                        ->label('Fundo de Troco Inicial')
                        ->numeric()
                        ->prefix('R$')
                        ->default(0)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    // Impede de abrir se a gaveta já estiver aberta
                    $hasOpenShift = CashShift::where('bank_account_id', $data['bank_account_id'])
                        ->where('status', 'open')
                        ->exists();
                        
                    if ($hasOpenShift) {
                        Notification::make()
                            ->title('Atenção!')
                            ->body('Esta gaveta já possui um turno aberto. Feche-o primeiro.')
                            ->warning()
                            ->send();
                        return;
                    }

                    CashShift::create([
                        'bank_account_id' => $data['bank_account_id'],
                        'opened_by' => auth()->id(),
                        'opened_at' => now(),
                        'opening_balance' => $data['opening_balance'],
                        'status' => 'open',
                    ]);

                    Notification::make()
                        ->title('Turno Aberto!')
                        ->body('O caixa está pronto para operar.')
                        ->success()
                        ->send();
                }),
        ];
    }
}