<?php

namespace App\Filament\Resources\BankStatements\Actions;

use Filament\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use App\Models\BankAccount;
use App\Models\Transaction;
use Filament\Notifications\Notification;
use App\Services\Accounting\BankReconciliationService;

class BulkTransferAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'bulk_transfer';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Transferência em Lote (Cartões/Contas)')
            ->icon('heroicon-o-arrows-right-left')
            ->color('success')
            ->modalHeading('Transferência entre Contas / Pagamento de Fatura')
            ->modalDescription('As transações selecionadas serão liquidadas como transferências (sem impacto na DRE) e o saldo será movido para a conta de destino.')
            ->form([
                Select::make('target_account_id')
                    ->label('Conta de Destino / Origem')
                    ->helperText('Selecione o Cartão de Crédito ou a Conta Bancária que recebeu/enviou estes valores.')
                    ->options(BankAccount::where('is_active', true)->pluck('name', 'id'))
                    ->searchable()
                    ->required(),

                TextInput::make('description')
                    ->label('Descrição Padrão')
                    ->placeholder('Ex: Pagamento Fatura Nubank')
                    ->helperText('Se deixar em branco, manteremos a descrição original de cada extrato.'),
            ])
            ->action(function (Collection $records, array $data) {
                            $targetAccount = \App\Models\BankAccount::find($data['target_account_id']);
                            $count = 0;

                            foreach ($records as $stmt) {
                                if ($stmt->status !== 'pending' || $stmt->bank_account_id == $targetAccount->id) continue;

                                $desc = !empty($data['description'])
                                    ? $data['description'] . " - " . ($stmt->counterparty_name ?: $stmt->description)
                                    : "Transferência: " . ($stmt->counterparty_name ?: $stmt->description);

                                // 🚀 O MOTOR FAZ A MÁGICA DIRETAMENTE NA CONTA BANCÁRIA!
                                BankReconciliationService::finalize($stmt, $targetAccount, null, $desc);
                                $count++;
                            }

                            if ($count > 0) {
                                Notification::make()->title("{$count} transferências realizadas com sucesso!")->success()->send();
                            } else {
                                Notification::make()->title("Nenhuma ação realizada.")->warning()->send();
                            }
                        });
    }
}
