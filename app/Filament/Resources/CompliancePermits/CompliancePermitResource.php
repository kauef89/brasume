<?php

namespace App\Filament\Resources\CompliancePermits;

use App\Filament\Resources\CompliancePermits\Pages;
use App\Filament\Resources\CompliancePermits\Schemas\CompliancePermitForm;
use App\Filament\Resources\CompliancePermits\Tables\CompliancePermitsTable;
use App\Models\CompliancePermit;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class CompliancePermitResource extends Resource
{
    protected static ?string $model = CompliancePermit::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-shield-check';

    protected static string | UnitEnum | null $navigationGroup = 'Conformidade & Qualidade';

    protected static ?string $modelLabel = 'Alvará / Licença';

    protected static ?string $pluralModelLabel = 'Alvarás e Conformidade';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema(CompliancePermitForm::getFormSchema());
    }

    public static function table(Table $table): Table
    {
        return CompliancePermitsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCompliancePermits::route('/'),
            'create' => Pages\CreateCompliancePermit::route('/create'),
            'edit'   => Pages\EditCompliancePermit::route('/{record}/edit'),
        ];
    }
}
