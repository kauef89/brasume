<?php

namespace App\Filament\Resources\Payrolls\Pages;

use App\Filament\Resources\Payrolls\PayrollResource;
use App\Services\Accounting\SciPayslipParserService;
use App\Services\Accounting\AccountingExportService; // Novo import
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\DatePicker; // Novo import
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ListPayrolls extends ListRecords
{
    protected static string $resource = PayrollResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // 1. Ação de enviar fechamento manual para a contabilidade
            Action::make('sendAccountingReport')
                ->label('Enviar Relatório ao DP')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->form([
                    DatePicker::make('month')
                        ->label('Selecione o Mês')
                        ->displayFormat('m/Y')
                        ->default(now()->subMonth())
                        ->required(),
                ])
                ->action(function (array $data) {
                    try {
                        $date = Carbon::parse($data['month']);
                        $service = new AccountingExportService();
                        $service->sendMonthlyClosing($date);

                        Notification::make()
                            ->title('Relatórios enviados!')
                            ->body('Os e-mails foram despachados para a contabilidade.')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Erro ao enviar')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            // 2. Ação que já existia de importar o PDF
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
