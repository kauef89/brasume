<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action as PageAction;

// Page Actions (Cabeçalho)
use App\Filament\Pages\Actions\ImportV0ConsolidatedAction;
use App\Filament\Pages\Actions\ImportV1OrdersAction;
use App\Filament\Pages\Actions\ImportV2XmlAction;

// Table Actions (Linhas)
use App\Filament\Pages\Actions\MapFiscalDocumentsAction;
use App\Filament\Pages\Actions\UndoImportAction;

// Models
use App\Models\LegacyImportLog;

class ImportFiscalHistory extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = "heroicon-o-archive-box-arrow-down";
    protected static string|\UnitEnum|null $navigationGroup = "Legado Fiscal";
    protected static ?string $navigationLabel = "Central de Importações";
    protected static ?int $navigationSort = 10;
    protected static ?string $title = "Importação de Dados Históricos";

    protected string $view = "filament.pages.import-fiscal-history";

    public function importConsolidatedAction(): PageAction
    {
        return ImportV0ConsolidatedAction::make();
    }

    public function importV1OrdersAction(): PageAction
    {
        return ImportV1OrdersAction::make();
    }

    public function importV2XmlAction(): PageAction
    {
        return ImportV2XmlAction::make();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(LegacyImportLog::query())
            ->columns([
                TextColumn::make("created_at")
                    ->label("Data da Importação")
                    ->dateTime("d/m/Y H:i")
                    ->sortable(),
                TextColumn::make("import_tag")
                    ->label("Tag / Lote")
                    ->badge()
                    ->color("gray")
                    ->searchable(),
                TextColumn::make("type")
                    ->label("Conteúdo")
                    ->badge()
                    ->color("primary")
                    ->formatStateUsing(
                        fn(string $state): string => match ($state) {
                            "v0_consolidated" => "Faturamento Consolidado Diário",
                            "v1_detailed" => "Histórico de Pedidos (CSV)",
                            "v2_fiscal" => "Documentos Fiscais (XML)",
                            default => $state,
                        },
                    ),
                TextColumn::make("items_count")
                    ->label("Registros Extraídos")
                    ->numeric()
                    ->alignEnd(),
            ])
            ->defaultSort("created_at", "desc")
            ->actions([
                MapFiscalDocumentsAction::make(),
                UndoImportAction::make(),
            ]);
    }
}
