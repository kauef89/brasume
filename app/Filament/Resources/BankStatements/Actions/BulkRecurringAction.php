<?php

namespace App\Filament\Resources\BankStatements\Actions;


use App\Enums\RecurringExpenseValueType;use Filament\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use App\Models\RecurringExpense;
use App\Models\AccountPayable;
use App\Models\Transaction;
use App\Models\Supplier;
use App\Services\Accounting\AccountingService;
use App\Filament\Resources\Suppliers\Schemas\SupplierForm; // 🚀 IMPORT DO SEU FORMULÁRIO OFICIAL
use Filament\Notifications\Notification;
use App\Services\Accounting\BankReconciliationService;

class BulkRecurringAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'bulk_recurring';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Vincular a Conta Recorrente')
            ->icon('heroicon-o-arrow-path-rounded-square')
            ->color('warning')
            ->modalHeading('Vincular a Conta Fixa em Lote')
            ->modalDescription('Selecione o contrato. O sistema gerará as contas a pagar para as transações selecionadas puxando a DRE correta automaticamente.')
            ->form(function (Collection $records) {

                // 🚀 A MÁGICA DE LEITURA DO OFX: Pega o primeiro extrato selecionado
                $stmt = $records->first();
                $defaultDoc = $stmt ? preg_replace('/[^0-9]/', '', (string) $stmt->document) : null;
                $defaultName = $stmt ? ($stmt->counterparty_name ?: $stmt->description) : null;

                return [
                    Select::make('recurring_expense_id')
                        ->label('Contrato / Conta Fixa')
                        ->options(fn() => RecurringExpense::where('is_active', true)->get()->mapWithKeys(fn($f) => [$f->id => ($f->supplier->name ?? "S/F") . " - " . $f->name]))
                        ->searchable()
                        ->required()
                        // 🚀 1ª CAMADA: Criar o Contrato/Conta Recorrente na hora
                        ->createOptionForm([
                            TextInput::make('name')
                                ->label('Nome do Contrato / Conta')
                                ->placeholder('Ex: Internet Fibra Vivo')
                                ->default($defaultName) // Já sugere o nome do banco como nome do contrato
                                ->required(),

                            Select::make('supplier_id')
                                ->label('Fornecedor Vinculado')
                                ->options(fn() => Supplier::where('is_active', true)->pluck('name', 'id'))
                                ->searchable()
                                ->required()
                                // 🚀 2ª CAMADA: RESTAURAMOS O SEU QUICK CREATE OFICIAL AQUI!
                                ->createOptionForm(SupplierForm::getFormSchema())
                                ->createOptionUsing(fn (array $data) => Supplier::create($data)->id)
                                // 🚀 A MÁGICA DA INJEÇÃO: Preenchemos o modal do fornecedor antes de ele abrir
                                ->createOptionAction(
                                    fn (Action $action) => $action->mountUsing(function (Schema $form) use ($defaultDoc, $defaultName) {
                                        $form->fill([
                                            'document' => $defaultDoc,
                                            'name' => $defaultName,
                                            'document_name' => $defaultName,
                                            'type' => 'physical',
                                            'is_active' => true,
                                            'is_formal' => true,
                                        ]);
                                    })
                                ),

                            Grid::make(2)->schema([
                                Select::make('accounting_category')
                                    ->label('Categoria DRE')
                                    ->options(AccountingService::getCategories())
                                    ->live()
                                    ->afterStateUpdated(fn($set) => $set('accounting_subcategory', null))
                                    ->required(),

                                Select::make('accounting_subcategory')
                                    ->label('Subcategoria')
                                    ->options(fn($get) => AccountingService::getSubcategories($get('accounting_category')))
                                    ->required()
                                    ->disabled(fn($get) => !$get('accounting_category')),
                            ]),

                            Grid::make(2)->schema([
                                TextInput::make('due_day')
                                    ->label('Dia de Vencimento')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(31)
                                    ->required(),

                                Select::make('value_type')
                                    ->label('Tipo de Valor')
                                    ->options(RecurringExpenseValueType::class)
                                    ->default('variable')
                                    ->required(),
                            ]),
                        ])
                        ->createOptionUsing(function (array $data) {
                            $data['is_auto_debit'] = false;
                            $data['account_holder'] = 'company';
                            $data['paid_by'] = 'company';
                            $data['is_personal'] = false;
                            $data['is_active'] = true;
                            return RecurringExpense::create($data)->id;
                        }),
                ];
            })
            ->action(function (Collection $records, array $data) {
                            $fixed = RecurringExpense::find($data['recurring_expense_id']);
                            $count = 0;

                            foreach ($records as $stmt) {
                                if ($stmt->status !== 'pending' || $stmt->amount >= 0) continue;

                                $desc = $fixed->name . " (" . $stmt->bank_date->format("M/Y") . ")";

                                $payable = AccountPayable::create([
                                    "description" => $desc,
                                    "amount" => abs((float) $stmt->amount),
                                    "supplier_id" => $fixed->supplier_id,
                                    "recurring_expense_id" => $fixed->id,
                                    "accounting_category" => $fixed->accounting_category,
                                    "accounting_subcategory" => $fixed->accounting_subcategory,
                                    "due_date" => $stmt->bank_date,
                                ]);

                                // 🚀 O MOTOR FAZ A MÁGICA
                                BankReconciliationService::finalize($stmt, $payable, $fixed->supplier_id);
                                $count++;
                            }

                            Notification::make()->title("{$count} transações vinculadas à conta fixa!")->success()->send();
                        });
    }
}
