<?php

namespace App\Filament\Resources\Payrolls\Actions;

use App\Models\BankAccount;
use App\Services\Financial\SicrediCnab240Service;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportSicrediCnabBulkAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'export_sicredi_cnab';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Exportar CNAB 240 (Sicredi)')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Exportação de Lote CNAB 240 — Sicredi')
            ->modalDescription('Deseja gerar a remessa de folha salarial para os holerites selecionados? Um lote de pagamento será gerado para a conciliação bancária.')
            ->modalSubmitActionLabel('Gerar Arquivo .REM')
            ->action(function (Collection $records): ?StreamedResponse {
                // 1. Localiza a conta corrente pagadora Sicredi PJ
                $sicredi = BankAccount::where('bank_code', '748')->first();

                if (! $sicredi || empty($sicredi->account_number)) {
                    Notification::make()
                        ->title('Conta Sicredi PJ não configurada')
                        ->body('Configure a agência e conta Sicredi em Bancos antes de gerar a remessa.')
                        ->danger()
                        ->send();

                    return null;
                }

                // 2. Valida se os colaboradores possuem agência e conta preenchidas
                $invalidRecords = $records->filter(function ($payslip) {
                    $emp = $payslip->employee;
                    $agency = $emp?->bank_agency ?: $payslip->bank_agency;
                    $account = $emp?->bank_account ?: $payslip->bank_account;

                    return empty($agency) || empty($account);
                });

                if ($invalidRecords->isNotEmpty()) {
                    $names = $invalidRecords->map(fn ($p) => $p->employee?->name ?? "Holerite #{$p->id}")->implode(', ');

                    Notification::make()
                        ->title('Dados bancários incompletos')
                        ->body("Os seguintes colaboradores estão sem Cooperativa ou Conta Sicredi cadastradas: {$names}")
                        ->warning()
                        ->persistent()
                        ->send();

                    return null;
                }

                // 3. Executa o gerador do layout CNAB 240 e cria o PaymentBatch
                $service = new SicrediCnab240Service($sicredi, now());
                $result = $service->generateSalaryBatch($records);

                Notification::make()
                    ->title('Remessa Gerada com Sucesso!')
                    ->body("Lote #{$result['batch_id']} consolidado com R$ " . number_format($result['total_amount'], 2, ',', '.') . " em {$result['count']} holerites.")
                    ->success()
                    ->send();

                // 4. Dispara o download imediato do arquivo .REM no navegador
                return response()->streamDownload(function () use ($result) {
                    echo $result['content'];
                }, $result['filename'], [
                    'Content-Type' => 'text/plain; charset=UTF-8',
                ]);
            })
            ->deselectRecordsAfterCompletion();
    }
}
