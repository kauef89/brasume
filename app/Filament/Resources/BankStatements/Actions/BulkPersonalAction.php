<?php

namespace App\Filament\Resources\BankStatements\Actions;

use Filament\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use App\Models\Supplier;
use App\Models\AccountPayable;
use App\Models\Transaction;
use Filament\Notifications\Notification;
use App\Services\Accounting\BankReconciliationService;

class BulkPersonalAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'bulk_personal';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Marcar como Despesa Pessoal')
            ->icon('heroicon-o-user')
            ->color('info')
            ->modalHeading('Lançar Despesas Pessoais em Lote')
            ->form([
                // 🚀 A SUA SOLUÇÃO DE MEIO-TERMO
                Toggle::make('identify_supplier')
                    ->label('Deseja identificar o Favorecido / Sócio?')
                    ->helperText('Se não ativar, usaremos um cadastro genérico (Uso Pessoal) para não poluir os seus fornecedores oficiais.')
                    ->live()
                    ->default(false),

                Select::make('supplier_id')
                    ->label('Favorecido / Sócio')
                    ->options(Supplier::where('is_active', true)->pluck('name', 'id'))
                    ->searchable()
                    ->visible(fn(Get $get) => $get('identify_supplier'))
                    ->required(fn(Get $get) => $get('identify_supplier'))
                    ->createOptionForm([
                        Section::make("Novo Favorecido")
                            ->schema(\App\Filament\Resources\Suppliers\Schemas\SupplierForm::getFormSchema())
                    ])
                    ->createOptionUsing(
                        fn(array $data) => \App\Models\Supplier::create($data)->id
                    ),

                TextInput::make('description')
                    ->label('Descrição')
                    ->placeholder('Ex: PIX Avó, Lanche')
                    ->helperText('Se deixar em branco, manteremos o nome que veio do banco.'),
            ])
            ->action(function (Collection $records, array $data) {
                            if (!empty($data['identify_supplier']) && !empty($data['supplier_id'])) {
                                $supplierId = $data['supplier_id'];
                            } else {
                                $genericSupplier = Supplier::firstOrCreate(
                                    ['name' => 'Uso Pessoal / Sócios'],
                                    ['type' => 'physical', 'is_personal' => true, 'accounting_category' => 'socios', 'accounting_subcategory' => 'pessoal', 'is_active' => true]
                                );
                                $supplierId = $genericSupplier->id;
                            }

                            $count = 0;

                            foreach ($records as $stmt) {
                                if ($stmt->status !== 'pending' || $stmt->amount >= 0) continue;

                                $finalDescription = !empty($data['description'])
                                    ? $data['description'] . " - " . $stmt->counterparty_name
                                    : "Uso Pessoal: " . ($stmt->counterparty_name ?? $stmt->description);

                                $payable = AccountPayable::create([
                                    "description" => $finalDescription,
                                    "supplier_id" => $supplierId,
                                    "amount" => abs((float) $stmt->amount),
                                    "due_date" => $stmt->bank_date,
                                    "is_personal" => true,
                                    "accounting_category" => "socios",
                                    "accounting_subcategory" => "pessoal",
                                ]);

                                // 🚀 O MOTOR FAZ A MÁGICA
                                BankReconciliationService::finalize($stmt, $payable, $supplierId, $finalDescription);
                                $count++;
                            }

                            Notification::make()->title("{$count} transações marcadas como Uso Pessoal!")->success()->send();
                        });
    }
}
