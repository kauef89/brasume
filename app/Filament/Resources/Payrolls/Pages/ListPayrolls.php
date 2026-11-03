<?php

namespace App\Filament\Resources\Payrolls\Pages;

use App\Filament\Resources\Payrolls\PayrollResource;
use App\Services\Accounting\SciPayslipParserService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ListPayrolls extends ListRecords
{
    protected static string $resource = PayrollResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importPdf')
                ->label('Importar PDF da Contabilidade')
                ->icon('heroicon-o-document-arrow-up')
                ->color('success')
                ->schema([
                    FileUpload::make('pdf_file')
                        ->label('Ficheiro PDF do SCI (Recibos de Salário)')
                        ->acceptedFileTypes(['application/pdf'])
                        ->disk('local')
                        ->directory('temp_payroll_imports')
                        ->required(),
                ])
                ->action(function (array $data) {
                    try {
                        $path = Storage::disk('local')->path($data['pdf_file']);
                        $service = new SciPayslipParserService();
                        $payroll = $service->processPdf($path);

                        Notification::make()
                            ->title('Folha Importada com Sucesso!')
                            ->body('Competência ' . Carbon::parse($payroll->reference_period)->format('m/Y') . ' processada.')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Erro ao processar PDF')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
