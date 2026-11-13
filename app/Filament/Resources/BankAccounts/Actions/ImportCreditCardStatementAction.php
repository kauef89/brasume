<?php

namespace App\Filament\Resources\BankAccounts\Actions;

use App\Enums\BankAccountType;
use App\Models\AccountPayable;
use App\Models\BankAccount;
use App\Services\Accounting\SicrediCreditCardParserService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class ImportCreditCardStatementAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'import_credit_card';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Importar Fatura Sicredi (XLS / PDF)')
            ->icon('heroicon-o-credit-card')
            ->color('success')
            ->modalHeading('Importar Fatura do Cartão Sicredi Empresarial')
            ->modalDescription('Faça upload do extrato mensal (.xls ou .pdf). O sistema separa automaticamente os cartões físicos e virtuais, ignora o pagamento da fatura anterior e lança cada despesa na DRE.')
            ->modalSubmitActionLabel('Processar Fatura')
            ->visible(function (?BankAccount $record): bool {
                if (!$record) return false;
                $type = $record->type instanceof \BackedEnum ? $record->type->value : (string) $record->type;
                return $type === BankAccountType::CREDIT_CARD->value;
            })
            ->form([
                FileUpload::make('invoice_file')
                    ->label('Arquivo da Fatura (.xls, .xlsx ou .pdf)')
                    ->acceptedFileTypes([
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/pdf',
                    ])
                    ->storeFiles(false)
                    ->required(),

                Toggle::make('mark_as_paid')
                    ->label('Fatura já debitada / fechada (Liquidar na data de vencimento)')
                    ->helperText('Se ativado, os lançamentos entram como pagos na data de vencimento da fatura e abatem o saldo devedor deste cartão.')
                    ->default(true),
            ])
            ->action(function (BankAccount $record, array $data): void {
                try {
                    $uploadedFile = $data['invoice_file'];
                    $filePath = $uploadedFile->getRealPath();
                    $ext = $uploadedFile->getClientOriginalExtension() ?: 'xls';

                    $parser = new SicrediCreditCardParserService();
                    $result = $parser->parseFile($filePath, $ext, $record);

                    $dueDate = $result['metadata']['due_date'];
                    $markAsPaid = (bool) ($data['mark_as_paid'] ?? true);
                    $importedCount = 0;
                    $skippedCount = 0;

                    DB::transaction(function () use ($result, $record, $dueDate, $markAsPaid, &$importedCount, &$skippedCount) {
                        foreach ($result['transactions'] as $tx) {
                            $exists = AccountPayable::where('bank_account_id', $record->id)
                                ->where('observations', 'like', "%{$tx['unique_hash']}%")
                                ->exists();

                            if ($exists) {
                                $skippedCount++;
                                continue;
                            }

                            $txDateFmt = Carbon::parse($tx['transaction_date'])->format('d/m');
                            $instTag = ($tx['installment'] && $tx['installment'] !== '01/01') ? " ({$tx['installment']})" : '';
                            $cardTag = $tx['card_last_four'] ? " [Final {$tx['card_last_four']}]" : '';

                            AccountPayable::create([
                                'description'            => "{$tx['description']}{$instTag} • Compra {$txDateFmt}{$cardTag}",
                                'supplier_id'            => $tx['supplier_id'],
                                'amount'                 => $tx['amount'],
                                'due_date'               => $dueDate,
                                'payment_date'           => $markAsPaid ? $dueDate : null,
                                'status'                 => $markAsPaid ? 'paid' : 'pending',
                                'bank_account_id'        => $record->id,
                                'credit_card_id'         => $tx['credit_card_id'],
                                'is_personal'            => false,
                                'accounting_category'    => $tx['accounting_category'],
                                'accounting_subcategory' => $tx['accounting_subcategory'],
                                'observations'           => "Fatura Sicredi Venc. " . Carbon::parse($dueDate)->format('d/m/Y') . " | Hash: {$tx['unique_hash']}",
                            ]);

                            $importedCount++;
                        }
                    });

                    $tot = number_format($result['metadata']['total_amount'], 2, ',', '.');
                    $dueFmt = Carbon::parse($dueDate)->format('d/m/Y');
                    $body = "{$importedCount} lançamento(s) importado(s) na fatura de {$dueFmt}.";
                    if ($skippedCount > 0) {
                        $body .= " ({$skippedCount} já existente(s) ignorado(s)).";
                    }

                    Notification::make()
                        ->title("Fatura Processada: R$ {$tot}")
                        ->body($body)
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Falha ao processar fatura')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }
}