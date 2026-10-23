<?php

namespace App\Filament\Resources\AccountReceivables\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use App\Services\Financial\Acquirers\Parsers\SicrediAposParser;
use App\Services\Financial\Acquirers\AcquirerReconciliationEngine;
use Exception;

class ImportAcquirerReportAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'import_acquirer_report';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Importar Relatório de Vendas (CSV)')
            ->icon('heroicon-o-receipt-percent')
            ->color('success')
            ->modalHeading('Conciliação de Vendas da Adquirente')
            ->modalDescription('Faça upload do Relatório Detalhado de Vendas (Sicredi / APOS SiTef / Stone) para alimentar as taxas MDR reais e identificar pendências do caixa.')
            ->modalSubmitActionLabel('Processar Relatório')
            ->modalWidth('xl')
            ->form([
                FileUpload::make('report_file')
                    ->label('Arquivo CSV')
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv'])
                    ->storeFiles(false)
                    ->required()
                    ->helperText('Formato padrão: delimitador ponto e vírgula (;).'),
            ])
            ->action(function (array $data) {
                try {
                    $filePath = $data['report_file']->getRealPath();

                    $parser = new SicrediAposParser();
                    $transactions = $parser->parseCsv($filePath);

                    if (empty($transactions)) {
                        Notification::make()
                            ->title('Nenhuma venda válida encontrada')
                            ->body('O arquivo enviado não possui registros de vendas aprovadas ou autorizadas.')
                            ->warning()
                            ->send();
                        return;
                    }

                    $engine = new AcquirerReconciliationEngine();
                    $results = $engine->reconcile($transactions);

                    $matched   = $results['matched'];
                    $ambiguous = $results['ambiguous'];
                    $orphan    = $results['orphan'];
                    $already   = $results['already_reconciled'];

                    $msgParts = [];
                    if ($matched > 0)   $msgParts[] = "✅ {$matched} venda(s) conciliada(s) automaticamente.";
                    if ($ambiguous > 0) $msgParts[] = "🟡 {$ambiguous} com dúvida (valores duplicados).";
                    if ($orphan > 0)    $msgParts[] = "🔴 {$orphan} órfã(s) (não lançadas no PDV).";
                    if ($already > 0)   $msgParts[] = "ℹ️ {$already} já processada(s) anteriormente.";

                    $notification = Notification::make()
                        ->title('Relatório Processado!')
                        ->body(implode("\n", $msgParts));

                    if ($ambiguous > 0 || $orphan > 0) {
                        $notification->warning()->persistent();
                    } else {
                        $notification->success();
                    }

                    $notification->send();

                } catch (Exception $e) {
                    Notification::make()
                        ->title('Falha no processamento')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
