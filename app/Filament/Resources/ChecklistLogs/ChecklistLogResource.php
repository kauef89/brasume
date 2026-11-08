<?php

namespace App\Filament\Resources\ChecklistLogs;

use App\Filament\Resources\ChecklistLogs\Pages;
use App\Filament\Resources\ChecklistLogs\Tables\ChecklistLogsTable;
use App\Models\ChecklistLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class ChecklistLogResource extends Resource
{
    protected static ?string $model = ChecklistLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static string|\UnitEnum|null $navigationGroup = 'Gestão & RH';
    protected static ?string $slug = 'auditoria-tarefas';
    protected static ?string $modelLabel = 'Execução de Tarefa';
    protected static ?string $pluralModelLabel = 'Auditoria de Tarefas';
    protected static ?int $navigationSort = 20;

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function table(Table $table): Table
    {
        return ChecklistLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChecklistLogs::route('/'),
        ];
    }
}