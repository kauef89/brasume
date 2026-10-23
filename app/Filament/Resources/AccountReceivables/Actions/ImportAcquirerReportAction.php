<?php

namespace App\Filament\Resources\AccountReceivables\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
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

        $this->label('Importar Vendas de Cartão (CSV)')
            ->icon('heroicon-o-receipt-percent')
            ->color('success')
            ->modalHeading('Conciliação de Cartões e Maquininhas')
            ->modalDescription('Faça upload do Relatório Detalhado de Vendas da adquirente (Sicredi / APOS / Stone) para conciliar os pagamentos do caixa, registrar as taxas MDR reais e prever a liquidação financeira.')
            ->modalSubmitActionLabel('Processar Conciliação')
            ->modalWidth('2xl')
            ->form([
                FileUpload::make('report_file')
                    ->label('Arquivo CSV da Adquirente')
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv'])
                    ->storeFiles(false)
                    ->required()
                    ->helperText('Formato aceito: CSV com delimitador ponto e vírgula (;).'),
            ])
            ->action(function (array $data) {
                try {
                    $uploadedFile = $data['report_file'];
                    $filePath = $uploadedFile->getRealPath();

                    // 1. Parser do arquivo bruto
                    $parser = new SicrediAposParser();
                    $transactions = $parser->parseCsv($filePath);

                    if (empty($transactions)) {
                        Notification::make()
                            ->title('Nenhuma transação válida encontrada')
                            ->body('O arquivo enviado não possui vendas aprovadas ou autorizadas.')
                            ->warning()
                            ->send();
                        return;
                    }

                    // 2. Motor de conciliação heurística
                    $engine = new AcquirerReconciliationEngine();
                    $results = $engine->reconcile($transactions);

                    $matchedCount = $results['matched'];
                    $divergentCount = $results['divergent'];
                    $skippedCount = $results['already_reconciled'];

                    if ($matchedCount > 0) {
                        Notification::make()
                            ->title("Conciliação Realizada!")
                            ->body("{$matchedCount} transação(ões) vinculada(s) aos pedidos com sucesso. As taxas MDR e previsões de repasse foram computadas no Contas a Receber.")
                            ->success()
                            ->duration(6000)
                            ->send();
                    }

                    if ($divergentCount > 0) {
                        // Monta o resumo visual das divergências
                        $divergencesList = $results['divergences'];
                        $rowsHtml = '';

                        foreach (array_slice($divergencesList, 0, 5) as $div) {
                            $rowsHtml .= "
                                <div style='border-bottom: 1px dashed #e5e7eb; padding: 4px 0; font-size: 11px;'>
                                    <strong>{$div['date']}</strong> | {$div['terminal']} | <strong>R$ " . number_format($div['amount'], 2, ',', '.') . "</strong> ({$div['modality']})<br>
                                    <span style='color: #dc2626;'>Motivo:</span> {$div['reason']}
                                </div>
                            ";
                        }

                        if (count($divergencesList) > 5) {
                            $remaining = count($divergencesList) - 5;
                            $rowsHtml .= "<div style='font-size: 10px; color: #6b7280; margin-top: 4px;'>... e mais {$remaining} divergência(s).</div>";
                        }

                        Notification::make()
                            ->title("{$divergentCount} Venda(s) Não Localizada(s) no Caixa")
                            ->warning()
                            ->persistent()
                            ->body(new HtmlString("
                                <div style='line-height: 1.4;'>
                                    Transações aprovadas na maquininha sem pedido correspondente com mesmo valor e horário no sistema:
                                    <div style='margin-top: 8px; max-height: 200px; overflow-y: auto;'>
                                        {$rowsHtml}
                                    </div>
                                </div>
                            "))
                            ->send();
                    }

                    if ($matchedCount === 0 && $divergentCount === 0 && $skippedCount > 0) {
                        Notification::make()
                            ->title('Relatório Já Processado')
                            ->body("Todas as {$skippedCount} transações deste arquivo já haviam sido conciliadas anteriormente.")
                            ->info()
                            ->send();
                    }

                } catch (Exception $e) {
                    Notification::make()
                        ->title('Falha ao processar conciliação')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
