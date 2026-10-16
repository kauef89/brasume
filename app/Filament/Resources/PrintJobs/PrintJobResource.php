<?php

namespace App\Filament\Resources\PrintJobs;

use App\Filament\Resources\PrintJobs\Pages\ListPrintJobs;
use App\Filament\Resources\PrintJobs\Pages\ViewPrintJob;
use App\Filament\Resources\PrintJobs\Schemas\PrintJobForm;
use App\Filament\Resources\PrintJobs\Schemas\PrintJobInfolist;
use App\Filament\Resources\PrintJobs\Tables\PrintJobsTable;
use App\Models\PrintJob;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PrintJobResource extends Resource
{
    protected static ?string $model = PrintJob::class;

    // 🚀 Ajustes Visuais e de Menu
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPrinter;
    protected static string|\UnitEnum|null $navigationGroup = 'PDV & Infraestrutura';
    protected static ?string $modelLabel = 'Log de Impressão';
    protected static ?string $pluralModelLabel = 'Logs de Impressão';

    // 🚀 BLINDAGEM DE SEGURANÇA: Proíbe criar e editar
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        return PrintJobForm::configure($schema); // O formulário não será usado, mas mantemos por estrutura
    }

    public static function infolist(Schema $schema): Schema
    {
        return PrintJobInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PrintJobsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPrintJobs::route('/'),
            'view' => ViewPrintJob::route('/{record}'),
            // 🚀 Rotas de create e edit foram removidas para ninguém aceder por URL!
        ];
    }
}
