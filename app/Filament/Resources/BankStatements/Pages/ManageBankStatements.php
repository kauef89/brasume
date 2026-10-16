<?php

namespace App\Filament\Resources\BankStatements\Pages;

use App\Filament\Resources\BankStatements\BankStatementResource;
use App\Filament\Traits\TeleportsTabs;
use Filament\Resources\Pages\ManageRecords;
use App\Filament\Resources\BankStatements\Actions\AutoConciliateAction;
use App\Filament\Resources\BankStatements\Actions\AutoConciliateBatchAction;
use App\Filament\Resources\BankStatements\Actions\ImportOfxAction;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use App\Models\BankStatement;

class ManageBankStatements extends ManageRecords
{
    use TeleportsTabs;
    protected static string $resource = BankStatementResource::class;

    public array $skippedIds = [];
    public ?int $currentStmtId = null;

    protected function getHeaderActions(): array
    {
        return [
            // A sua nova Automação em Lote (Botão Dourado de Raio)
            AutoConciliateBatchAction::make(),

            // A nossa Ação passo a passo anterior (renomeie no ecrã se quiser)
            AutoConciliateAction::make()->label('Conciliação Passo a Passo')->color('gray')->icon('heroicon-m-forward'),

            ImportOfxAction::make(),
        ];
    }

    public function getTabs(): array
        {
            return [
                // 🔴 ABA 1: Foco apenas nas Despesas (Padrão ao abrir a página)
                'saidas_pendentes' => Tab::make('Saídas')
                    ->icon('heroicon-m-arrow-trending-down')
                    ->badge(BankStatement::where('status', 'pending')->where('type', 'debit')->count())
                    ->badgeColor('danger')
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending')->where('type', 'debit')),

                // 🟢 ABA 2: Foco apenas nas Receitas
                'entradas_pendentes' => Tab::make('Entradas ')
                    ->icon('heroicon-m-arrow-trending-up')
                    ->badge(BankStatement::where('status', 'pending')->where('type', 'credit')->count())
                    ->badgeColor('success')
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending')->where('type', 'credit')),

                // 🗄️ ABA 3: O Histórico
                'historico' => Tab::make('Histórico')
                    ->icon('heroicon-m-archive-box')
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('status', '!=', 'pending')),
            ];
        }
}
