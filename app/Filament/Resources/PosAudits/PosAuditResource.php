<?php

namespace App\Filament\Resources\PosAudits;

use App\Models\PosAudit;
use Filament\Resources\Resource;
use Filament\Schemas\Schema; // 🚀 Aqui está a correção (Filament v4)
use Filament\Tables\Table;
use App\Filament\Resources\PosAudits\Pages;
use App\Filament\Resources\PosAudits\Schemas\PosAuditInfolist;
use App\Filament\Resources\PosAudits\Tables\PosAuditTable;

class PosAuditResource extends Resource
{
    protected static ?string $model = PosAudit::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-eye';
    protected static string|\UnitEnum|null $navigationGroup = 'PDV & Infraestrutura';
    protected static ?string $modelLabel = 'Auditoria do PDV';
    protected static ?string $pluralModelLabel = 'Auditoria do PDV';

    public static function canCreate(): bool { return false; }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return false; }

    public static function table(Table $table): Table
    {
        return PosAuditTable::configure($table);
    }

    // 🚀 Assinatura atualizada para o Filament v4
    public static function infolist(Schema $schema): Schema
    {
        return PosAuditInfolist::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPosAudits::route('/'),
        ];
    }
}
