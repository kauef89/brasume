<?php

namespace App\Filament\Central\Resources\FailedJobs;

use App\Models\FailedJob;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use App\Filament\Central\Resources\FailedJobs\Tables\FailedJobsTable;
use App\Filament\Central\Resources\FailedJobs\Pages;

class FailedJobResource extends Resource
{
    protected static ?string $model = FailedJob::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?string $navigationLabel = 'Falhas em Filas (Jobs)';
    protected static ?string $modelLabel = 'Falha de Processamento';
    protected static ?string $pluralModelLabel = 'Falhas em Filas';

    protected static string | \UnitEnum | null $navigationGroup = 'Monitoramento e Logs';

    public static function table(Table $table): Table
    {
        return FailedJobsTable::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFailedJobs::route('/'),
        ];
    }
}
