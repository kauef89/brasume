<?php

namespace App\Services\Financial\Acquirers;

use Carbon\Carbon;

class AcquirerTransactionDTO
{
    public function __construct(
        public string $acquirerName,         // sicredi, stone, rede...
        public string $terminalCode,         // ex: APTE0CD9
        public Carbon $transactionDate,      // Data e hora exata da maquininha
        public string $modality,             // credito, debito, pix
        public string $brand,                // mastercard, visa, elo, pix
        public float $grossAmount,           // Valor Bruto (ex: 107.00)
        public float $feeAmount,             // Taxa MDR (ex: 0.85)
        public float $netAmount,             // Valor Líquido (ex: 106.15)
        public string $authorizationCode,    // Código de autorização (ex: 450926)
        public string $nsu,                  // Comprovante / NSU (ex: 1016)
        public Carbon $expectedSettlementDate// Data prevista para cair na conta
    ) {}
}
