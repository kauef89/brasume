<?php

namespace App\Filament\Resources\Suppliers\Actions;

// 🚀 O IMPORT CORRETO (O mesmo que usámos na folha de pagamento)
use Filament\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use App\Models\AccountPayable;
use App\Models\Transaction;
use App\Models\RecurringExpense;
use App\Models\PersonalPurchase;
use App\Models\ProductCostHistory;
use App\Models\CostHistory;
use App\Models\Supplier;

class MergeSuppliersBulkAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'merge_suppliers';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Fundir Fornecedores')
            ->icon('heroicon-o-arrows-pointing-in')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Fundir Fornecedores Duplicados')
            ->modalDescription('Atenção: Esta ação é irreversível! Selecione qual será o fornecedor "Principal". O sistema moverá todo o histórico das empresas selecionadas para este fornecedor principal e apagará os restantes.')
            ->form([
                Select::make('master_supplier_id')
                    ->label('Fornecedor Principal (O Sobrevivente)')
                    ->options(fn() => Supplier::pluck('name', 'id'))
                    ->required()
                    ->searchable()
            ])
            ->action(function (Collection $records, array $data) {
                $masterId = $data['master_supplier_id'];

                // Pega todos os IDs selecionados, EXCETO se o próprio Master estiver na seleção
                $mergedIds = $records->where('id', '!=', $masterId)->pluck('id')->toArray();

                if (empty($mergedIds)) {
                    Notification::make()
                        ->title('Nenhum fornecedor extra para fundir.')
                        ->warning()
                        ->send();
                    return;
                }

                // 🚀 TRANSFERÊNCIA EM MASSA DE TODO O HISTÓRICO
                DB::transaction(function () use ($masterId, $mergedIds) {

                    // 1. Move Contas a Pagar
                    AccountPayable::whereIn('supplier_id', $mergedIds)->update(['supplier_id' => $masterId]);

                    // 2. Move Transações Bancárias
                    Transaction::whereIn('supplier_id', $mergedIds)->update(['supplier_id' => $masterId]);

                    // 3. Move Despesas Recorrentes (Contratos)
                    RecurringExpense::whereIn('supplier_id', $mergedIds)->update(['supplier_id' => $masterId]);

                    // 4. Move Compras Pessoais e Históricos de Custo
                    PersonalPurchase::whereIn('supplier_id', $mergedIds)->update(['supplier_id' => $masterId]);
                    ProductCostHistory::whereIn('supplier_id', $mergedIds)->update(['supplier_id' => $masterId]);
                    CostHistory::whereIn('supplier_id', $mergedIds)->update(['supplier_id' => $masterId]);

                    // 5. Arruma a Árvore de Matriz/Filial (Filiais órfãs ganham o novo Pai)
                    Supplier::whereIn('parent_id', $mergedIds)->update(['parent_id' => $masterId]);

                    // 6. DELETA OS REPETIDOS (Soft Delete)
                    Supplier::whereIn('id', $mergedIds)->delete();
                });

                Notification::make()
                    ->title(count($mergedIds) . ' fornecedor(es) fundido(s) com sucesso!')
                    ->success()
                    ->send();
            });
    }
}
