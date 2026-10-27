<?php

namespace App\Filament\Resources\FiscalDocuments\Actions;

use App\Services\Fiscal\FiscalExportService;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Notifications\Notification;
use ZipArchive;

class ExportFiscalDataAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'export_fiscal_data';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Exportar Relatórios')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('primary')
            ->form([
                // Linha 1: Filtros de Data (Grid de 3)
                Grid::make(3)->schema([
                    Select::make('quick_period')
                        ->label('Período')
                        ->options([
                            'this_month' => 'Este Mês',
                            'last_month' => 'Mês Passado',
                            'custom'     => 'Personalizado',
                        ])
                        ->default('this_month')
                        ->live()
                        ->afterStateUpdated(function (string $state, Set $set) {
                            if ($state === 'this_month') {
                                $set('start_date', now()->startOfMonth()->format('Y-m-d'));
                                $set('end_date', now()->endOfMonth()->format('Y-m-d'));
                            } elseif ($state === 'last_month') {
                                $set('start_date', now()->subMonth()->startOfMonth()->format('Y-m-d'));
                                $set('end_date', now()->subMonth()->endOfMonth()->format('Y-m-d'));
                            }
                        }),

                    DatePicker::make('start_date')
                        ->label('Data Inicial')
                        ->required()
                        ->default(now()->startOfMonth()),

                    DatePicker::make('end_date')
                        ->label('Data Final')
                        ->required()
                        ->default(now()->endOfMonth()),
                ])->extraAttributes(['class' => 'mb-4']),

                // Linha 2: Toggles de Exportação (Grid de 3)
                Grid::make(3)->schema([
                    Toggle::make('export_xml')
                        ->label('XMLs (ZIP)')
                        ->default(true)
                        ->live(),

                    Toggle::make('export_general')
                        ->label('Relatório Sintético')
                        ->default(true),

                    Toggle::make('export_monophasic')
                        ->label('Monofásicos (PDF)')
                        ->default(false),
                ]),

                // Linha 3: Opções Contábeis
                Grid::make(1)->schema([
                    Toggle::make('distribute_freight_in_products')
                        ->label('Distribuir frete nos produtos (Compatibilidade Contábil)')
                        ->helperText('Incorpora o frete proporcionalmente no preço dos itens e zera a tag de frete no XML exportado.')
                        ->default(false)
                        ->visible(fn (Get $get) => (bool) $get('export_xml')),
                ]),
            ])
            ->action(function (array $data, FiscalExportService $exportService) {
                try {
                    $filesToDownload = [];

                    if ($data['export_xml']) {
                        $filesToDownload[] = $exportService->generateXmlZip($data['start_date'], $data['end_date'], (bool) ($data['distribute_freight_in_products'] ?? false));
                    }
                    if ($data['export_general']) {
                        $filesToDownload[] = $exportService->generateGeneralReportPdf($data['start_date'], $data['end_date']);
                    }
                    if ($data['export_monophasic']) {
                        $filesToDownload[] = $exportService->generateMonophasicReportPdf($data['start_date'], $data['end_date']);
                    }

                    if (empty($filesToDownload)) {
                        throw new \Exception("Selecione pelo menos um tipo de relatório para exportar.");
                    }

                    if (count($filesToDownload) === 1) {
                        return response()->download($filesToDownload[0])->deleteFileAfterSend(true);
                    }

                    $tempDir = storage_path('app/temp_fiscal_exports');
                    if (!file_exists($tempDir)) {
                        mkdir($tempDir, 0755, true);
                    }

                    $masterZipName = "Fechamento_Fiscal_{$data['start_date']}_a_{$data['end_date']}.zip";
                    $masterZipPath = $tempDir . '/' . $masterZipName;

                    $zip = new ZipArchive();
                    if ($zip->open($masterZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                        foreach ($filesToDownload as $filePath) {
                            $zip->addFile($filePath, basename($filePath));
                        }
                        $zip->close();
                    } else {
                        throw new \Exception("Falha ao criar o arquivo ZIP com os relatórios agrupados.");
                    }

                    foreach ($filesToDownload as $filePath) {
                        @unlink($filePath);
                    }

                    return response()->download($masterZipPath)->deleteFileAfterSend(true);

                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Erro na Exportação')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
