<?php

namespace App\Filament\Resources\BankStatements;

use App\Filament\Resources\BankStatements\Pages;
use App\Models\BankStatement;
use Filament\Resources\Resource;
use Filament\Tables\Table;

// Importamos APENAS a Tabela, pois não há Form global manual
use App\Filament\Resources\BankStatements\Tables\BankStatementsTable;

class BankStatementResource extends Resource
{
    protected static ?string $model = BankStatement::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Financeiro';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return "heroicon-o-arrows-right-left";
    }

    public static function getNavigationGroup(): ?string
    {
        return "Financeiro";
    }

    // 1. Define o número que vai aparecer na Badge
    public static function getNavigationBadge(): ?string
    {
        // Conta quantos extratos estão com o status "pending"
        $count = \App\Models\BankStatement::where("status", "pending")->count();

        // Retorna o número em string, ou null se for 0 (assim a badge some quando tudo estiver limpo 🎉)
        return $count > 0 ? (string) $count : null;
    }

    // 2. Define a cor da Badge (opcional, mas dá um destaque visual)
    public static function getNavigationBadgeColor(): ?string
    {
        // Se quiser que fique vermelho de "urgente", use 'danger'
        // 'warning' (amarelo), 'success' (verde), 'primary' (azul)
        return "warning";
    }

    public static function getModelLabel(): string
    {
        return "Conciliação Bancária";
    }

    public static function getPluralModelLabel(): string
    {
        return "Conciliações";
    }

    // Delega APENAS a Table
    public static function table(Table $table): Table
    {
        return BankStatementsTable::build($table);
    }

    public static function getPages(): array
    {
        return [
            // Como não há Create nem Edit manual, temos apenas a página Manage (index)
            "index" => Pages\ManageBankStatements::route("/"),
        ];
    }
}
