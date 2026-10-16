<?php

namespace App\Filament\Resources\BankStatements\Actions;

use Filament\Actions\BulkAction;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Collection;
use App\Services\Accounting\BankReconciliationService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use App\Models\Supplier;
use App\Models\AccountPayable;
use App\Models\Transaction;
use App\Services\Accounting\AccountingService;
use App\Filament\Resources\Suppliers\Schemas\SupplierForm; // 🚀 IMPORT DO SEU FORMULÁRIO OFICIAL
use Filament\Notifications\Notification;

class BulkExpenseAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'bulk_expense';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Lançar Despesas em Lote')
            ->icon('heroicon-o-document-duplicate')
            ->color('primary')
            // 🚀 1. Transformamos num Closure para ler os extratos selecionados
            ->form(function (Collection $records) {

                // 🚀 2. A MÁGICA: Extrai os dados do banco
                $stmt = $records->first();
                $defaultDoc = $stmt ? preg_replace('/[^0-9]/', '', (string) $stmt->document) : null;
                $defaultName = $stmt ? ($stmt->counterparty_name ?: $stmt->description) : null;

                return [
                    Select::make('supplier_id')
                        ->label('Fornecedor')
                        ->options(Supplier::where('is_active', true)->pluck('name', 'id'))
                        ->searchable()
                        ->required()
                        // 🚀 3. O SEU FORMULÁRIO OFICIAL COMPLETO
                        ->createOptionForm(SupplierForm::getFormSchema())
                        ->createOptionUsing(
                            fn(array $data) => Supplier::create($data)->id
                        )
                        // 🚀 4. A INJEÇÃO DE DADOS (Preenche o modal antes de abrir)
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
                        )
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set) {
                            if ($state && ($sup = Supplier::find($state))) {
                                $set("accounting_category", $sup->accounting_category);
                                $set("accounting_subcategory", $sup->accounting_subcategory);
                            }
                        }),

                    TextInput::make('description')
                        ->label('Descrição Padrão')
                        ->placeholder('Deixe em branco para usar o nome do extrato'),

                    Select::make("accounting_category")
                        ->label("Categoria DRE")
                        ->options(AccountingService::getCategories())
                        ->live()
                        ->afterStateUpdated(fn($set) => $set("accounting_subcategory", null))
                        ->required(),

                    Select::make("accounting_subcategory")
                        ->label("Subcategoria")
                        ->options(fn($get) => AccountingService::getSubcategories($get("accounting_category")))
                        ->required()
                        ->disabled(fn($get) => !$get("accounting_category")),
                ];
            })
            ->action(function (Collection $records, array $data) {
                            $supplier = Supplier::find($data['supplier_id']);
                            $count = 0;

                            foreach ($records as $stmt) {
                                if ($stmt->status !== 'pending' || $stmt->amount >= 0) continue;

                                $desc = !empty($data['description']) ? $data['description'] . " - " . $stmt->counterparty_name : $stmt->description;

                                $payable = AccountPayable::create([
                                    "description" => $desc,
                                    "supplier_id" => $supplier->id,
                                    "amount" => abs((float) $stmt->amount),
                                    "due_date" => $stmt->bank_date,
                                    "accounting_category" => $data['accounting_category'],
                                    "accounting_subcategory" => $data['accounting_subcategory'],
                                ]);

                                // 🚀 O MOTOR FAZ A MÁGICA
                                BankReconciliationService::finalize($stmt, $payable, $supplier->id);

                                if (!empty($stmt->counterparty_name)) {
                                    $supplier->update(['document_name' => $stmt->counterparty_name]);
                                }
                                $count++;
                            }

                            Notification::make()->title("{$count} despesas conciliadas em lote!")->success()->send();
                        });
    }
}
