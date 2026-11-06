<?php

namespace App\Services\Financial;

use App\Models\BankAccount;
use App\Models\CompanySetting;
use App\Models\PaymentBatch;
use App\Models\Payslip;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SicrediCnab240Service
{
    protected BankAccount $sourceAccount;
    protected Carbon $paymentDate;
    protected int $sequenceNumber;

    public function __construct(BankAccount $sourceAccount, ?Carbon $paymentDate = null, int $sequenceNumber = 1)
    {
        $this->sourceAccount = $sourceAccount;
        $this->paymentDate   = $paymentDate ?: Carbon::now();
        $this->sequenceNumber = $sequenceNumber;
    }

    /**
     * Gera o conteúdo textual de 240 colunas do arquivo .rem para Sicredi
     */
    public function generateSalaryBatch(Collection $payslips, string $batchDescription = 'FOLHA SALARIAL'): array
    {
        $lines = [];
        $totalAmount = 0.0;
        $lotSequence = 1;
        $recordsInLot = 0;

        $tenantCnpj = preg_replace('/\D/', '', CompanySetting::get('cnpj', '00000000000000'));
        $companyName = mb_substr(strtoupper(CompanySetting::get('company_name', 'LANCHONETE')), 0, 30);

        $agency = str_pad(preg_replace('/\D/', '', (string) $this->sourceAccount->agency), 5, '0', STR_PAD_LEFT);
        $account = str_pad(preg_replace('/\D/', '', (string) $this->sourceAccount->account_number), 12, '0', STR_PAD_LEFT);
        $accountDigit = substr((string) ($this->sourceAccount->account_digit ?: '0'), 0, 1);

        // 1. HEADER DE ARQUIVO (Registro 0)
        $lines[] = $this->buildFileHeader($tenantCnpj, $companyName, $agency, $account, $accountDigit);

        // 2. HEADER DE LOTE (Registro 1 - Serviço 30: Pagamento Salários / Forma 01: Crédito em Conta Sicredi)
        $lines[] = $this->buildLotHeader($lotSequence, $tenantCnpj, $companyName, $agency, $account, $accountDigit, $batchDescription);
        $recordsInLot++;

        // 3. DETALHES (Segmentos A e B para cada holerite)
        $detailSeq = 1;
        foreach ($payslips as $payslip) {
            $netSalary = (float) $payslip->net_salary;
            if ($netSalary <= 0) continue;

            $totalAmount += $netSalary;
            $employee = $payslip->employee;

            // Segmento A: Dados do Favorecido e Valor
            $lines[] = $this->buildSegmentA($lotSequence, $detailSeq++, $payslip, $netSalary);
            $recordsInLot++;

            // Segmento B: CPF e Informações Complementares
            $lines[] = $this->buildSegmentB($lotSequence, $detailSeq++, $employee);
            $recordsInLot++;
        }

        // 4. TRAILER DE LOTE (Registro 5)
        $recordsInLot++; // Conta o próprio trailer de lote
        $lines[] = $this->buildLotTrailer($lotSequence, $recordsInLot, $totalAmount);

        // 5. TRAILER DE ARQUIVO (Registro 9)
        $totalFileRecords = count($lines) + 1;
        $lines[] = $this->buildFileTrailer($totalFileRecords, 1);

        $content = implode("\r\n", $lines) . "\r\n";

        // Criação do Lote no Banco de Dados
        $batch = PaymentBatch::create([
            'description'  => "{$batchDescription} - " . $this->paymentDate->format('d/m/Y'),
            'total_amount' => $totalAmount,
            'status'       => 'transmitted',
            'payment_date' => $this->paymentDate->toDateString(),
        ]);

        Payslip::whereIn('id', $payslips->pluck('id'))->update([
            'payment_batch_id' => $batch->id,
            'status'           => 'processed',
        ]);

        return [
            'content'      => $content,
            'filename'     => 'SICREDI_FOLHA_' . $this->paymentDate->format('Ymd') . '_' . str_pad((string)$batch->id, 4, '0', STR_PAD_LEFT) . '.REM',
            'batch_id'     => $batch->id,
            'total_amount' => $totalAmount,
            'count'        => $payslips->count(),
        ];
    }

    protected function buildFileHeader(string $cnpj, string $name, string $agency, string $account, string $dv): string
    {
        $h = '';
        $h .= '748';                                               // 001-003: Código Sicredi
        $h .= '0000';                                              // 004-007: Lote de Serviço
        $h .= '0';                                                 // 008-008: Tipo de Registro (0=Header)
        $h .= str_repeat(' ', 9);                                  // 009-017: Uso FEBRABAN
        $h .= '2';                                                 // 018-018: Tipo de Inscrição (2=CNPJ)
        $h .= str_pad($cnpj, 14, '0', STR_PAD_LEFT);              // 019-032: Número de Inscrição
        $h .= str_pad($this->sourceAccount->pix_key ?: '', 20, ' '); // 033-052: Código do Convênio no Sicredi
        $h .= $agency;                                             // 053-057: Cooperativa / Agência
        $h .= ' ';                                                 // 058-058: DV da Agência
        $h .= $account;                                            // 059-070: Conta Corrente
        $h .= $dv;                                                 // 071-071: DV da Conta
        $h .= ' ';                                                 // 072-072: DV Ag/Conta
        $h .= str_pad($name, 30, ' ', STR_PAD_RIGHT);             // 073-102: Nome da Empresa
        $h .= str_pad('SICREDI', 30, ' ', STR_PAD_RIGHT);          // 103-132: Nome do Banco
        $h .= str_repeat(' ', 10);                                 // 133-142: Uso FEBRABAN
        $h .= '1';                                                 // 143-143: Código Remessa (1)
        $h .= now()->format('dmY');                                // 144-151: Data de Geração
        $h .= now()->format('His');                                // 152-157: Hora de Geração
        $h .= str_pad((string) $this->sequenceNumber, 6, '0', STR_PAD_LEFT); // 158-163: Sequencial Arquivo
        $h .= '081';                                               // 164-166: Versão do Layout (Febraban)
        $h .= '01600';                                             // 167-171: Densidade de Gravação
        $h .= str_repeat(' ', 69);                                 // 172-240: Reservado Banco/Empresa

        return mb_substr($h, 0, 240);
    }

    protected function buildLotHeader(int $lotSeq, string $cnpj, string $name, string $agency, string $account, string $dv, string $desc): string
    {
        $h = '';
        $h .= '748';                                               // 001-003: Banco
        $h .= str_pad((string) $lotSeq, 4, '0', STR_PAD_LEFT);     // 004-007: Número do Lote
        $h .= '1';                                                 // 008-008: Tipo de Registro (1=Header Lote)
        $h .= 'C';                                                 // 009-009: Tipo de Operação (C=Crédito)
        $h .= '30';                                                // 010-011: Tipo de Serviço (30=Salários)
        $h .= '01';                                                // 012-013: Forma de Lançamento (01=Crédito em Conta Sicredi)
        $h .= '040';                                               // 014-016: Versão do Layout do Lote
        $h .= ' ';                                                 // 017-017: Uso FEBRABAN
        $h .= '2';                                                 // 018-018: Tipo de Inscrição (2=CNPJ)
        $h .= str_pad($cnpj, 14, '0', STR_PAD_LEFT);              // 019-032: CNPJ
        $h .= str_pad('', 20, ' ');                                // 033-052: Convênio
        $h .= $agency;                                             // 053-057: Agência
        $h .= ' ';                                                 // 058-058: DV Agência
        $h .= $account;                                            // 059-070: Conta Corrente
        $h .= $dv;                                                 // 071-071: DV Conta
        $h .= ' ';                                                 // 072-072: DV Ag/Conta
        $h .= str_pad($name, 30, ' ', STR_PAD_RIGHT);             // 073-102: Nome da Empresa
        $h .= str_pad($desc, 40, ' ', STR_PAD_RIGHT);             // 103-142: Informação Mensagem/Finalidade
        $h .= str_repeat(' ', 98);                                 // 143-240: Reservado

        return mb_substr($h, 0, 240);
    }

    protected function buildSegmentA(int $lotSeq, int $recSeq, Payslip $payslip, float $amount): string
    {
        $emp = $payslip->employee;
        $destAgency = str_pad(preg_replace('/\D/', '', (string) ($emp->bank_agency ?: $payslip->bank_agency)), 5, '0', STR_PAD_LEFT);
        $destAccount = str_pad(preg_replace('/\D/', '', (string) ($emp->bank_account ?: $payslip->bank_account)), 12, '0', STR_PAD_LEFT);
        $destDv = substr((string) ($emp->bank_account_digit ?: ($payslip->bank_account_digit ?: '0')), 0, 1);
        $docNumber = str_pad("HOL-{$payslip->id}", 20, ' ', STR_PAD_RIGHT);
        $empName = mb_substr(strtoupper($emp->name), 0, 30);
        $cents = str_pad((string) ((int) round($amount * 100)), 15, '0', STR_PAD_LEFT);

        $s = '';
        $s .= '748';                                               // 001-003: Banco
        $s .= str_pad((string) $lotSeq, 4, '0', STR_PAD_LEFT);     // 004-007: Lote
        $s .= '3';                                                 // 008-008: Tipo Registro (3=Detalhe)
        $s .= str_pad((string) $recSeq, 5, '0', STR_PAD_LEFT);     // 009-013: Sequencial no Lote
        $s .= 'A';                                                 // 014-014: Código Segmento
        $s .= '0';                                                 // 015-015: Tipo de Movimento (0=Inclusão)
        $s .= '00';                                                // 016-017: Código Instrução
        $s .= '000';                                               // 018-020: Câmara Compensação (000=Sicredi Próprio)
        $s .= '748';                                               // 021-023: Banco Favorecido (Sicredi)
        $s .= $destAgency;                                         // 024-028: Cooperativa Favorecido
        $s .= ' ';                                                 // 029-029: DV Agência
        $s .= $destAccount;                                        // 030-041: Conta Favorecido
        $s .= $destDv;                                             // 042-042: DV Conta
        $s .= ' ';                                                 // 043-043: DV Ag/Conta
        $s .= str_pad($empName, 30, ' ', STR_PAD_RIGHT);          // 044-073: Nome Favorecido
        $s .= $docNumber;                                          // 074-093: Seu Número
        $s .= $this->paymentDate->format('dmY');                   // 094-101: Data do Pagamento
        $s .= 'BRL';                                               // 102-104: Tipo Moeda
        $s .= str_repeat('0', 15);                                 // 105-119: Quantidade Moeda
        $s .= $cents;                                              // 120-134: Valor do Pagamento
        $s .= str_repeat(' ', 20);                                 // 135-154: Nosso Número (Banco)
        $s .= str_repeat('0', 8);                                  // 155-162: Data Real Efetivação
        $s .= str_repeat('0', 15);                                 // 163-177: Valor Real
        $s .= str_repeat(' ', 40);                                 // 178-217: Outras Informações
        $s .= '00';                                                // 218-219: Finalidade
        $s .= str_repeat(' ', 10);                                 // 220-229: Reservado
        $s .= '0';                                                 // 230-230: Aviso ao Favorecido
        $s .= str_repeat(' ', 10);                                 // 231-240: Ocorrências

        return mb_substr($s, 0, 240);
    }

    protected function buildSegmentB(int $lotSeq, int $recSeq, $employee): string
    {
        $cpf = str_pad(preg_replace('/\D/', '', (string) $employee->cpf), 14, '0', STR_PAD_LEFT);

        $s = '';
        $s .= '748';                                               // 001-003: Banco
        $s .= str_pad((string) $lotSeq, 4, '0', STR_PAD_LEFT);     // 004-007: Lote
        $s .= '3';                                                 // 008-008: Tipo Registro (3=Detalhe)
        $s .= str_pad((string) $recSeq, 5, '0', STR_PAD_LEFT);     // 009-013: Sequencial
        $s .= 'B';                                                 // 014-014: Código Segmento
        $s .= ' ';                                                 // 015-015: Uso FEBRABAN
        $s .= '1';                                                 // 016-016: Tipo Inscrição (1=CPF)
        $s .= $cpf;                                                // 017-030: CPF Favorecido
        $s .= str_repeat(' ', 30);                                 // 031-060: Logradouro
        $s .= str_repeat('0', 5);                                  // 061-065: Número
        $s .= str_repeat(' ', 15);                                 // 066-080: Complemento
        $s .= str_repeat(' ', 15);                                 // 081-095: Bairro
        $s .= str_repeat(' ', 20);                                 // 096-115: Cidade
        $s .= str_repeat('0', 8);                                  // 116-123: CEP
        $s .= str_repeat(' ', 2);                                  // 124-125: UF
        $s .= str_repeat(' ', 115);                                // 126-240: Reservado / PIX

        return mb_substr($s, 0, 240);
    }

    protected function buildLotTrailer(int $lotSeq, int $totalRecords, float $totalAmount): string
    {
        $cents = str_pad((string) ((int) round($totalAmount * 100)), 18, '0', STR_PAD_LEFT);

        $t = '';
        $t .= '748';                                               // 001-003: Banco
        $t .= str_pad((string) $lotSeq, 4, '0', STR_PAD_LEFT);     // 004-007: Lote
        $t .= '5';                                                 // 008-008: Tipo Registro (5=Trailer Lote)
        $t .= str_repeat(' ', 9);                                  // 009-017: Uso FEBRABAN
        $t .= str_pad((string) $totalRecords, 6, '0', STR_PAD_LEFT); // 018-023: Total de Registros do Lote
        $t .= $cents;                                              // 024-041: Somatório dos Valores
        $t .= str_repeat('0', 18);                                 // 042-059: Somatório Moeda
        $t .= str_repeat(' ', 181);                                // 060-240: Reservado

        return mb_substr($t, 0, 240);
    }

    protected function buildFileTrailer(int $totalRecords, int $totalLots): string
    {
        $t = '';
        $t .= '748';                                               // 001-003: Banco
        $t .= '9999';                                              // 004-007: Lote de Serviço
        $t .= '9';                                                 // 008-008: Tipo Registro (9=Trailer Arquivo)
        $t .= str_repeat(' ', 9);                                  // 009-017: Uso FEBRABAN
        $t .= str_pad((string) $totalLots, 6, '0', STR_PAD_LEFT);  // 018-023: Total de Lotes do Arquivo
        $t .= str_pad((string) $totalRecords, 6, '0', STR_PAD_LEFT); // 024-029: Total de Registros do Arquivo
        $t .= str_repeat(' ', 211);                                // 030-240: Reservado

        return mb_substr($t, 0, 240);
    }
}
