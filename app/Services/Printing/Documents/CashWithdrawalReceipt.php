<?php

namespace App\Services\Printing\Documents;

use App\Services\Printing\Contracts\PrintableDocument;
use App\Services\Printing\ReceiptFormatter;

class CashWithdrawalReceipt implements PrintableDocument
{
    public function __construct(
        protected string $title,
        protected string $beneficiaryName,
        protected string $beneficiaryType, // 'Colaborador' | 'Fornecedor'
        protected float $amount,
        protected string $description,
        protected string $operatorName,
        protected ?string $payableReference = null,
        protected ?float $remainingBalance = null
    ) {}

    public function format(ReceiptFormatter $fmt): void
    {
        $fmt->init()
            ->alignCenter()
            ->bold(true)
            ->doubleHeight(true)
            ->line($this->title)
            ->doubleHeight(false)
            ->bold(false)
            ->line('COMPROVANTE DE RETIRADA DE CAIXA')
            ->separator('=')
            ->alignLeft()
            ->keyValue('Data/Hora:', now()->format('d/m/Y H:i'))
            ->keyValue('Operador:', $this->operatorName)
            ->keyValue($this->beneficiaryType . ':', $this->beneficiaryName);

        if ($this->payableReference) {
            $fmt->keyValue('Título Ref.:', $this->payableReference);
        }

        $fmt->separator('-')
            ->bold(true)
            ->doubleHeight(true)
            ->keyValue('VALOR PAGO:', 'R$ ' . number_format($this->amount, 2, ',', '.'))
            ->doubleHeight(false)
            ->bold(false);

        if ($this->remainingBalance !== null) {
            $fmt->keyValue('Saldo Restante:', 'R$ ' . number_format($this->remainingBalance, 2, ',', '.'));
        }

        $fmt->separator('-')
            ->line('Historico / Motivo:')
            ->line($this->description)
            ->feed(2)
            ->alignCenter()
            ->line('________________________________________')
            ->line($this->beneficiaryName)
            ->line('Assinatura do Recebedor')
            ->feed(2)
            ->cut();
    }
}
