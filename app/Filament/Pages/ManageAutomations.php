<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use App\Models\ProductCostHistory;
use Filament\Notifications\Notification;

class ManageAutomations extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-bolt';
    protected static string | \UnitEnum | null $navigationGroup = 'Estoque & Compras';
    protected static ?string $title = 'Regras de Automação';
    protected static ?int $navigationSort = 5;

    // Aponta para a view que criaremos no Passo 2
    protected string $view = 'filament.pages.manage-automations';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ProductCostHistory::query()
                    ->whereIn('id', function ($query) {
                        // 🚀 O SEGREDO: Subquery para driblar o strict_mode do MySQL e trazer 1 regra única por Produto+Fornecedor
                        $query->selectRaw('MAX(id)')
                            ->from('product_cost_histories')
                            ->where('auto_approve', true)
                            ->groupBy('supplier_id', 'xml_name');
                    })
                    ->latest('id')
            )
            ->columns([
                TextColumn::make('supplier.name')
                    ->label('Fornecedor')
                    ->searchable()
                    ->sortable()
                    ->default('Desconhecido')
                    ->description(fn($record) => 'CNPJ: ' . ($record->supplier->document ?? 'N/A')),

                TextColumn::make('xml_name')
                    ->label('Produto na Nota (XML)')
                    ->searchable()
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('product.name')
                    ->label('Insumo Vinculado')
                    ->color('primary')
                    ->weight('bold')
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label('Regra criada em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Action::make('revogar')
                    ->label('Revogar Automação')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Revogar Automação')
                    ->modalDescription('O robô deixará de dar entrada neste item automaticamente nas próximas notas. Esta ação não apaga o histórico de custos passado, apenas remove a permissão futura.')
                    ->modalSubmitActionLabel('Sim, Revogar')
                    ->action(function (ProductCostHistory $record) {

                        // 🚀 A MÁGICA DA REVOGAÇÃO: Limpa a flag de auto-aprovação de TODO o histórico desse item com esse fornecedor
                        ProductCostHistory::where('supplier_id', $record->supplier_id)
                            ->where('xml_name', $record->xml_name)
                            ->update(['auto_approve' => false]);

                        Notification::make()
                            ->title('Automação revogada com sucesso!')
                            ->success()
                            ->send();
                    })
            ])
            ->emptyStateHeading('Nenhuma regra de automação ativa')
            ->emptyStateDescription('Quando você marcar a opção "Aprovar automaticamente no futuro" na importação de notas, os robôs assumirão o controle e as regras aparecerão aqui.')
            ->emptyStateIcon('heroicon-o-bolt-slash');
    }
}
