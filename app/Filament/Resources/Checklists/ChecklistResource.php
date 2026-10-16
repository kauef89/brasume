<?php

namespace App\Filament\Resources\Checklists;

use App\Models\Checklist;
use BackedEnum;
use Filament\Resources\ResourceConfiguration;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use App\Filament\Resources\Checklists\Pages;
use App\Filament\Resources\Checklists\Schemas\ChecklistForm;
use App\Filament\Resources\Checklists\Tables\ChecklistsTable;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;
/**
 * @extends Resource<Model,ResourceConfiguration>
 */
class ChecklistResource extends Resource
{
    protected static ?string $model = Checklist::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static string|\UnitEnum|null $navigationGroup = 'Gestão & RH';
    protected static ?string $modelLabel = 'Tarefa';
    protected static ?string $pluralModelLabel = 'Tarefas';

    public static function form(Schema $schema): Schema
    {
        return ChecklistForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ChecklistsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChecklists::route('/'),
            'create' => Pages\CreateChecklist::route('/create'),
            'edit' => Pages\EditChecklist::route('/{record}/edit'),
        ];
    }
}
