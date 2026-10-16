<?php

namespace App\Filament\Resources\BankStatements\Actions;

use Filament\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use App\Services\Accounting\BankReconciliationService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Utilities\Get;
use App\Models\Employee;
use App\Models\AccountPayable;
use App\Models\Transaction;
use App\Services\Accounting\AccountingService;
use Filament\Notifications\Notification;

class BulkEmployeeAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'bulk_employee';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Lançar Colaborador em Lote')
            ->icon('heroicon-o-users')
            ->color('info')
            ->form([
                Select::make('employee_id')
                    ->label('Colaborador')
                    ->options(\App\Models\Employee::where('is_active', true)->pluck('name', 'id'))
                    ->searchable()
                    ->required(),

                TextInput::make('description')
                    ->label('Descrição Padrão')
                    ->placeholder('Ex: Adiantamento Quinzenal, Salário')
                    ->helperText('Se deixar em branco, usaremos a descrição do extrato.'),

                Select::make("accounting_category")
                    ->label("Categoria DRE")
                    ->options(AccountingService::getCategories())
                    ->live()
                    ->afterStateUpdated(fn(Set $set) => $set("accounting_subcategory", null))
                    ->required(),

                Select::make("accounting_subcategory")
                    ->label("Subcategoria")
                    ->options(fn(Get $get) => AccountingService::getSubcategories($get("accounting_category")))
                    ->required()
                    ->disabled(fn(Get $get) => !$get("accounting_category")),
            ])
            ->action(function (Collection $records, array $data) {
                            $employee = \App\Models\Employee::find($data['employee_id']);
                            $count = 0;

                            foreach ($records as $stmt) {
                                if ($stmt->status !== 'pending' || $stmt->amount >= 0) continue;

                                $desc = !empty($data['description'])
                                    ? $data['description'] . " - " . ($stmt->counterparty_name ?: '')
                                    : $stmt->description;

                                $payable = AccountPayable::create([
                                    "description" => $desc,
                                    "employee_id" => $employee->id,
                                    "amount" => abs((float) $stmt->amount),
                                    "due_date" => $stmt->bank_date,
                                    "accounting_category" => $data['accounting_category'],
                                    "accounting_subcategory" => $data['accounting_subcategory'],
                                ]);

                                // 🚀 O MOTOR FAZ A MÁGICA (Passamos null no Supplier)
                                BankReconciliationService::finalize($stmt, $payable, null, $desc);
                                $count++;
                            }

                            Notification::make()->title("{$count} pagamentos a colaboradores conciliados!")->success()->send();
                        });
    }
}
