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

    protected function asciiPad(string $text, int $length, string $padChar = ' ', int $direction = STR_PAD_RIGHT): string
    {
        $clean = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        $clean = preg_replace('/[^A-Za-z0-9 \-\.\,\/]/', '', $clean);
        $clean = strtoupper($clean);

        if (strlen($clean) > $length) {
            $clean = substr($clean, 0, $length);
        }

        return str_pad($clean, $length, $padChar, $direction);
    }

    protected function numPad($number, int $length): string
    {
        $clean = preg_replace('/\D/', '', (string) $number);
        if (strlen($clean) > $length) {
            $clean = substr($clean, -$length);
        }
        return str_pad($clean, $length, '0', STR_PAD_LEFT);
    }

    /**
     * Calcula o Dígito Verificador Módulo 11 (Padrão FEBRABAN / Sicredi)
     */
    protected function calculateMod11(string $number): string
    {
        $clean = preg_replace('/\D/', '', $number);
        $weights = [2, 3, 4, 5, 6, 7, 8, 9];
        $sum = 0;
        $wIdx = 0;

        for ($i = strlen($clean) - 1; $i >= 0; $i--) {
            $sum += (int) $clean[$i] * $weights[$wIdx % count($weights)];
            $wIdx++;
        }

        $rem = $sum % 11;
        if ($rem === 0 || $rem === 1) {
            return '0';
        }

        return (string) (11 - $rem);
    }

    public function generateSalaryBatch(Collection $payslips, string $batchDescription = 'FOLHA SALARIAL'): array
    {
        $lines = [];
        $totalAmount = 0.0;
        $lotSequence = 1;
        $recordsInLot = 0;

        $tenantCnpj = $this->numPad(CompanySetting::get('cnpj', '00000000000000'), 14);
        $companyName = CompanySetting::get('company_name', 'LANCHONETE');

        $agency = $this->numPad($this->sourceAccount->agency, 5);

        // Posição 058 (G009): Se agency_digit estiver cadastrado e não for zero, usa-o; caso contrário, calcula o Mod11 da cooperativa
        $agencyDv = (!empty($this->sourceAccount->agency_digit) && $this->sourceAccount->agency_digit !== '0')
            ? substr($this->sourceAccount->agency_digit, 0, 1)
            : $this->calculateMod11($this->sourceAccount->agency);

        $account = $this->numPad($this->sourceAccount->account_number, 12);
        $accountDv = $this->numPad($this->sourceAccount->account_digit ?: '0', 1);

        // Código do Convênio no Sicredi (G007 - 20 posições Alfa, alinhado à esquerda)
        $agreementCode = $this->sourceAccount->agreement_number ?: $this->sourceAccount->account_number;
        $agreement = $this->asciiPad($agreementCode, 20);

        // 1. HEADER DE ARQUIVO (Manual Sicredi Pág. 6)
        $lines[] = $this->buildFileHeader($tenantCnpj, $companyName, $agreement, $agency, $agencyDv, $account, $accountDv);

        // 2. HEADER DE LOTE (Manual Sicredi Pág. 11: Serviço 30 / Forma 01)
        $lines[] = $this->buildLotHeader($lotSequence, $tenantCnpj, $companyName, $agreement, $agency, $agencyDv, $account, $accountDv, $batchDescription);
        $recordsInLot++;

        // 3. DETALHES (Segmentos A e B para cada holerite)
        $detailSeq = 1;
        foreach ($payslips as $payslip) {
            $netSalary = (float) $payslip->net_salary;
            if ($netSalary <= 0) continue;

            $totalAmount += $netSalary;
            $employee = $payslip->employee;

            // Segmento A: Dados Bancários e Valor
            $lines[] = $this->buildSegmentA($lotSequence, $detailSeq++, $payslip, $netSalary);
            $recordsInLot++;

            // Segmento B: CPF e Identificação
            $lines[] = $this->buildSegmentB($lotSequence, $detailSeq++, $employee);
            $recordsInLot++;
        }

        // 4. TRAILER DE LOTE (Manual Sicredi Pág. 24)
        $recordsInLot++;
        $lines[] = $this->buildLotTrailer($lotSequence, $recordsInLot, $totalAmount);

        // 5. TRAILER DE ARQUIVO (Manual Sicredi Pág. 7)
        $totalFileRecords = count($lines) + 1;
        $lines[] = $this->buildFileTrailer($totalFileRecords, 1);

        $content = implode("\r\n", $lines) . "\r\n";

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

        // Nomenclatura oficial do Sicredi para convênio de 4 dígitos: CCCCDDSS.REM (Manual pág. 9)
        $cleanConvenio = substr($this->asciiPad($agreementCode, 4, '0'), 0, 4);
        $batchSeq = str_pad((string) ($batch->id % 100), 2, '0', STR_PAD_LEFT);
        $filename = $cleanConvenio . $this->paymentDate->format('d') . $batchSeq . '.REM';

        return [
            'content'      => $content,
            'filename'     => $filename,
            'batch_id'     => $batch->id,
            'total_amount' => $totalAmount,
            'count'        => $payslips->count(),
        ];
    }

    /**
     * Header de Arquivo (Manual Sicredi Pág. 6)
     */
    protected function buildFileHeader(string $cnpj, string $name, string $agreement, string $agency, string $agencyDv, string $account, string $accountDv): string
    {
        $h = '';
        $h .= '748';                                                     // 01.0 (001-003): Banco Sicredi (3)
        $h .= '0000';                                                    // 02.0 (004-007): Lote de Serviço (4)
        $h .= '0';                                                       // 03.0 (008-008): Tipo de Registro (1)
        $h .= str_repeat(' ', 9);                                        // 04.0 (009-017): Uso Exclusivo SICREDI (9)
        $h .= '2';                                                       // 05.0 (018-018): Tipo Inscrição 2=CNPJ (1)
        $h .= $this->numPad($cnpj, 14);                                  // 06.0 (019-032): Número Inscrição (14)
        $h .= $agreement;                                                // 07.0 (033-052): Código do Convênio G007 (20)
        $h .= $agency;                                                   // 08.0 (053-057): Agência Mantenedora (5)
        $h .= $agencyDv;                                                 // 09.0 (058-058): DV Agência G009 (1)
        $h .= $account;                                                  // 10.0 (059-070): Número Conta Corrente (12)
        $h .= $accountDv;                                                // 11.0 (071-071): Dígito Verificador Conta (1)
        $h .= ' ';                                                       // 12.0 (072-072): Dígito Verificador Ag/Conta (1 Branco)
        $h .= $this->asciiPad($name, 30);                                // 13.0 (073-102): Nome da Empresa (30)
        $h .= $this->asciiPad('SICREDI', 30);                            // 14.0 (103-132): Nome do Banco (30)
        $h .= str_repeat(' ', 10);                                       // 15.0 (133-142): Uso Exclusivo SICREDI (10)
        $h .= '1';                                                       // 16.0 (143-143): Código Remessa = '1' (1)
        $h .= now()->format('dmY');                                      // 17.0 (144-151): Data Geração DDMMAAAA (8)
        $h .= now()->format('His');                                      // 18.0 (152-157): Hora Geração HHMMSS (6)
        $h .= $this->numPad($this->sequenceNumber, 6);                   // 19.0 (158-163): Número Sequencial Arquivo NSA (6)
        $h .= '082';                                                     // 20.0 (164-166): Versão Layout Arquivo G019 = 082 (Manual Sicredi)
        $h .= '01600';                                                   // 21.0 (167-171): Densidade Gravação (5)
        $h .= str_repeat(' ', 20);                                       // 22.0 (172-191): Reservado Banco (20)
        $h .= str_repeat(' ', 20);                                       // 23.0 (192-211): Reservado Empresa (20)
        $h .= str_repeat(' ', 29);                                       // 24.0 (212-240): Uso Exclusivo SICREDI (29)

        return substr($h, 0, 240);
    }

    /**
     * Header de Lote (Manual Sicredi Pág. 11)
     */
    protected function buildLotHeader(int $lotSeq, string $cnpj, string $name, string $agreement, string $agency, string $agencyDv, string $account, string $accountDv, string $desc): string
    {
        $h = '';
        $h .= '748';                                                     // 01.1 (001-003): Banco (3)
        $h .= $this->numPad($lotSeq, 4);                                 // 02.1 (004-007): Lote de Serviço (4)
        $h .= '1';                                                       // 03.1 (008-008): Tipo Registro (1)
        $h .= 'C';                                                       // 04.1 (009-009): Tipo Operação C=Crédito (1)
        $h .= '30';                                                      // 05.1 (010-011): Tipo Serviço 30=Salários (2)
        $h .= '01';                                                      // 06.1 (012-013): Forma Lançamento 01=Crédito CC (2)
        $h .= '042';                                                     // 07.1 (014-016): Versão Layout do Lote G030 = 042 (Manual Sicredi)
        $h .= ' ';                                                       // 08.1 (017-017): Uso Exclusivo SICREDI (1)
        $h .= '2';                                                       // 09.1 (018-018): Tipo Inscrição 2=CNPJ (1)
        $h .= $this->numPad($cnpj, 14);                                  // 10.1 (019-032): Número Inscrição (14)
        $h .= $agreement;                                                // 11.1 (033-052): Código Convênio G007 (20)
        $h .= $agency;                                                   // 12.1 (053-057): Agência Mantenedora (5)
        $h .= $agencyDv;                                                 // 13.1 (058-058): DV Agência G009 (1)
        $h .= $account;                                                  // 14.1 (059-070): Número Conta Corrente (12)
        $h .= $accountDv;                                                // 15.1 (071-071): Dígito Verificador Conta (1)
        $h .= ' ';                                                       // 16.1 (072-072): Dígito Verificador Ag/Conta (1 Branco)
        $h .= $this->asciiPad($name, 30);                                // 17.1 (073-102): Nome Empresa (30)
        $h .= $this->asciiPad($desc, 40);                                // 18.1 (103-142): Informação 1 Mensagem (40)
        $h .= str_repeat(' ', 30);                                       // 19.1 (143-172): Logradouro (30)
        $h .= '00000';                                                   // 20.1 (173-177): Número Local (5 num)
        $h .= str_repeat(' ', 15);                                       // 21.1 (178-192): Complemento (15)
        $h .= str_repeat(' ', 20);                                       // 22.1 (193-212): Cidade (20)
        $h .= '00000';                                                   // 23.1 (213-217): CEP (5 num)
        $h .= str_repeat(' ', 3);                                        // 24.1 (218-220): Complemento CEP (3)
        $h .= '  ';                                                      // 25.1 (221-222): Sigla Estado UF (2)
        $h .= str_repeat(' ', 8);                                        // 26.1 (223-230): Uso Exclusivo SICREDI = 8 Brancos (Manual Sicredi)
        $h .= str_repeat(' ', 10);                                       // 27.1 (231-240): Códigos Ocorrências Retorno (10)

        return substr($h, 0, 240);
    }

    /**
     * Segmento A (Manual Sicredi Pág. 13)
     */
    protected function buildSegmentA(int $lotSeq, int $recSeq, Payslip $payslip, float $amount): string
    {
        $emp = $payslip->employee;
        $destAgency = $this->numPad($emp->bank_agency ?: $payslip->bank_agency, 5);
        $destAccount = $this->numPad($emp->bank_account ?: $payslip->bank_account, 12);
        $destDv = $this->numPad($emp->bank_account_digit ?: ($payslip->bank_account_digit ?: '0'), 1);
        $docNumber = $this->asciiPad("HOL-{$payslip->id}", 20);
        $empName = $this->asciiPad($emp->name, 30);
        $cents = $this->numPad((int) round($amount * 100), 15);

        $s = '';
        $s .= '748';                                                     // 01.3A (001-003): Banco (3)
        $s .= $this->numPad($lotSeq, 4);                                 // 02.3A (004-007): Lote (4)
        $s .= '3';                                                       // 03.3A (008-008): Tipo Registro (1)
        $s .= $this->numPad($recSeq, 5);                                 // 04.3A (009-013): Sequencial Registro no Lote (5)
        $s .= 'A';                                                       // 05.3A (014-014): Código Segmento (1)
        $s .= '0';                                                       // 06.3A (015-015): Tipo Movimento 0=Inclusão (1)
        $s .= '00';                                                      // 07.3A (016-017): Código Instrução (2)
        $s .= '000';                                                     // 08.3A (018-020): Código Câmara Compensação (3)
        $s .= '748';                                                     // 09.3A (021-023): Banco Favorecido (3)
        $s .= $destAgency;                                               // 10.3A (024-028): Agência Favorecido (5)
        $s .= ' ';                                                       // 11.3A (029-029): DV Agência Favorecido = 1 Branco (Manual Sicredi Pág. 13 e 30)
        $s .= $destAccount;                                              // 12.3A (030-041): Conta Favorecido (12)
        $s .= $destDv;                                                   // 13.3A (042-042): Dígito Verificador Conta (1)
        $s .= ' ';                                                       // 14.3A (043-043): Dígito Verificador Ag/Conta (1 Branco)
        $s .= $empName;                                                  // 15.3A (044-073): Nome Favorecido (30)
        $s .= $docNumber;                                                // 16.3A (074-093): Seu Número (20)
        $s .= $this->paymentDate->format('dmY');                         // 17.3A (094-101): Data Pagamento (8)
        $s .= 'BRL';                                                     // 18.3A (102-104): Tipo Moeda (3)
        $s .= str_repeat('0', 15);                                       // 19.3A (105-119): Quantidade Moeda (15)
        $s .= $cents;                                                    // 20.3A (120-134): Valor Pagamento (15)
        $s .= str_repeat(' ', 20);                                       // 21.3A (135-154): Nosso Número (20)
        $s .= str_repeat('0', 8);                                        // 22.3A (155-162): Data Real Efetivação (8)
        $s .= str_repeat('0', 15);                                       // 23.3A (163-177): Valor Real Efetivação (15)
        $s .= str_repeat(' ', 40);                                       // 24.3A (178-217): Informação 2 (40)
        $s .= '  ';                                                      // 25.3A (218-219): Finalidade DOC (2)
        $s .= str_repeat(' ', 5);                                        // 26.3A (220-224): Finalidade TED = Brancos p/ Salários (Manual pág. 50)
        $s .= '  ';                                                      // 27.3A (225-226): Finalidade Complementar (2)
        $s .= '   ';                                                     // 28.3A (227-229): Uso Exclusivo SICREDI (3)
        $s .= '0';                                                       // 29.3A (230-230): P006 Aviso ao Favorecido (1)
        $s .= str_repeat(' ', 10);                                       // 30.3A (231-240): Códigos Ocorrências Retorno (10)

        return substr($s, 0, 240);
    }

    /**
     * Segmento B (Manual Sicredi Pág. 14)
     */
    protected function buildSegmentB(int $lotSeq, int $recSeq, $employee): string
    {
        $cpf = $this->numPad($employee->cpf, 14);

        $s = '';
        $s .= '748';                                                     // 01.3B (001-003): Banco (3)
        $s .= $this->numPad($lotSeq, 4);                                 // 02.3B (004-007): Lote (4)
        $s .= '3';                                                       // 03.3B (008-008): Tipo Registro (1)
        $s .= $this->numPad($recSeq, 5);                                 // 04.3B (009-013): Sequencial Registro no Lote (5)
        $s .= 'B';                                                       // 05.3B (014-014): Código Segmento (1)
        $s .= '   ';                                                     // 06.3B (015-017): Uso Exclusivo SICREDI (3)
        $s .= '1';                                                       // 07.3B (018-018): Tipo Inscrição 1=CPF (1)
        $s .= $cpf;                                                      // 08.3B (019-032): Número Inscrição CPF Favorecido (14)
        $s .= str_repeat(' ', 30);                                       // 09.3B (033-062): Logradouro (30)
        $s .= '00000';                                                   // 10.3B (063-067): Número Local (5 num)
        $s .= str_repeat(' ', 15);                                       // 11.3B (068-082): Complemento (15)
        $s .= str_repeat(' ', 15);                                       // 12.3B (083-097): Bairro (15)
        $s .= str_repeat(' ', 20);                                       // 13.3B (098-117): Cidade (20)
        $s .= '00000';                                                   // 14.3B (118-122): CEP (5 num)
        $s .= '   ';                                                     // 15.3B (123-125): Complemento CEP (3)
        $s .= '  ';                                                      // 16.3B (126-127): Sigla Estado (2)
        $s .= $this->paymentDate->format('dmY');                         // 17.3B (128-135): Data Vencimento (8)
        $s .= str_repeat('0', 15);                                       // 18.3B (136-150): Valor Nominal (15)
        $s .= str_repeat('0', 15);                                       // 19.3B (151-165): Valor Abatimento (15)
        $s .= str_repeat('0', 15);                                       // 20.3B (166-180): Valor Desconto (15)
        $s .= str_repeat('0', 15);                                       // 21.3B (181-195): Valor Mora (15)
        $s .= str_repeat('0', 15);                                       // 22.3B (196-210): Valor Multa (15)
        $s .= str_repeat(' ', 15);                                       // 23.3B (211-225): Código/Documento Favorecido (15)
        $s .= '0';                                                       // 24.3B (226-226): Aviso ao Favorecido (1)
        $s .= str_repeat('0', 6);                                        // 25.3B (227-232): Uso SIAPE (6)
        $s .= str_repeat('0', 8);                                        // 26.3B (233-240): Identificação SPB (8)

        return substr($s, 0, 240);
    }

    /**
     * Trailer de Lote (Manual Sicredi Pág. 24)
     */
    protected function buildLotTrailer(int $lotSeq, int $totalRecords, float $totalAmount): string
    {
        $cents = $this->numPad((int) round($totalAmount * 100), 18);

        $t = '';
        $t .= '748';                                                     // 01.5 (001-003): Banco (3)
        $t .= $this->numPad($lotSeq, 4);                                 // 02.5 (004-007): Lote (4)
        $t .= '5';                                                       // 03.5 (008-008): Tipo Registro (1)
        $t .= str_repeat(' ', 9);                                        // 04.5 (009-017): Uso Exclusivo SICREDI (9)
        $t .= $this->numPad($totalRecords, 6);                           // 05.5 (018-023): Quantidade Registros Lote (6)
        $t .= $cents;                                                    // 06.5 (024-041): Somatória Valores (18)
        $t .= str_repeat('0', 18);                                       // 07.5 (042-059): Somatória Quantidade Moedas (18)
        $t .= '000000';                                                  // 08.5 (060-065): Número Aviso Débito (6)
        $t .= str_repeat(' ', 165);                                      // 09.5 (066-230): Uso Exclusivo SICREDI (165)
        $t .= str_repeat(' ', 10);                                       // 10.5 (231-240): Códigos Ocorrências Retorno (10)

        return substr($t, 0, 240);
    }

    /**
     * Trailer de Arquivo (Manual Sicredi Pág. 7)
     */
    protected function buildFileTrailer(int $totalRecords, int $totalLots): string
    {
        $t = '';
        $t .= '748';                                                     // 01.9 (001-003): Banco (3)
        $t .= '9999';                                                    // 02.9 (004-007): Lote de Serviço (4)
        $t .= '9';                                                       // 03.9 (008-008): Tipo Registro (1)
        $t .= str_repeat(' ', 9);                                        // 04.9 (009-017): Uso Exclusivo SICREDI (9)
        $t .= $this->numPad($totalLots, 6);                              // 05.9 (018-023): Quantidade Lotes Arquivo (6)
        $t .= $this->numPad($totalRecords, 6);                           // 06.9 (024-029): Quantidade Registros Arquivo (6)
        $t .= '000000';                                                  // 07.9 (030-035): Qtde Contas Conciliação (6)
        $t .= str_repeat(' ', 205);                                      // 08.9 (036-240): Uso Exclusivo SICREDI (205)

        return substr($t, 0, 240);
    }
}
