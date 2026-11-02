<?php

namespace App\Services\Printing\Documents;

use App\Services\Printing\BlueprintBuilder;
use Illuminate\Support\Str;

class FreelancerReceipt
{
    /**
     * @param string $freelancerName
     * @param float $netAmount
     * @param float $grossAmount
     * @param float $consumptionAmount
     * @param string $paymentMethod
     * @param string $operatorName
     * @param string|null $pixKey
     * @param string $shiftDateLabel Ex: "DOM - 01/11"
     * @param array $settledItems Itens pagos/descontados neste fechamento
     * @param array $rolledItems Itens adiados para o próximo turno
     * @param float $remainingBalance Saldo restante em conta após o acerto
     */
    public function __construct(
        public string $freelancerName,
        public float $netAmount,
        public float $grossAmount = 0.0,
        public float $consumptionAmount = 0.0,
        public string $paymentMethod = 'Dinheiro',
        public string $operatorName = 'Caixa',
        public ?string $pixKey = null,
        public string $shiftDateLabel = '',
        public array $settledItems = [],
        public array $rolledItems = [],
        public float $remainingBalance = 0.0
    ) {}

    public function getJobType(): string
    {
        return 'freelancer_receipt';
    }

    protected function cleanText(string $text): string
    {
        return mb_strtoupper(Str::ascii($text));
    }

    protected function justifyLine(string $leftText, float $amount, int $width = 48, bool $isNegative = false): string
    {
        $amountStr = number_format(abs($amount), 2, ',', '.');
        $right = ($isNegative ? '(-) R$ ' : 'R$ ') . str_pad($amountStr, 6, ' ', STR_PAD_LEFT);

        $maxLeftLength = max(10, $width - mb_strlen($right) - 1);
        $cleanLeft = $this->cleanText($leftText);

        if (mb_strlen($cleanLeft) > $maxLeftLength) {
            $cleanLeft = mb_substr($cleanLeft, 0, $maxLeftLength - 1) . '.';
        }

        $spaceCount = max(1, $width - mb_strlen($cleanLeft) - mb_strlen($right));

        return $cleanLeft . str_repeat(' ', $spaceCount) . $right;
    }

    public function build(): array
    {
        $b = new BlueprintBuilder();

        $b->alignCenter()
            ->text($this->cleanText('RECIBO DE DIARIA EVENTUAL'), bold: true, size: '2x')
            ->text($this->cleanText('DATA: ' . now()->format('d/m/Y H:i')))
            ->text($this->cleanText('OPERADOR: ' . $this->operatorName))
            ->divider()
            ->alignLeft()
            ->text($this->cleanText('COLABORADOR: ' . $this->freelancerName));

        if ($this->pixKey && str_contains(strtolower($this->paymentMethod), 'pix')) {
            $b->text($this->cleanText('CHAVE PIX: ' . $this->pixKey));
        }

        if (!empty($this->shiftDateLabel)) {
            $b->text($this->cleanText('TURNO REFERENCIA: ' . $this->shiftDateLabel), bold: true);
        }

        // ==========================================
        // 1. ITENS LIQUIDADOS NESTE ACERTO
        // ==========================================
        $b->divider();
        $b->text($this->cleanText('>>> ITENS LIQUIDADOS NESTE ACERTO <<<'), bold: true);

        if (!empty($this->settledItems)) {
            $services = array_filter($this->settledItems, fn($i) => ($i['category'] ?? '') === 'services');
            $excedentes = array_filter($this->settledItems, fn($i) => ($i['category'] ?? '') === 'excedente');
            $balcao = array_filter($this->settledItems, fn($i) => ($i['category'] ?? '') === 'balcao');

            if (!empty($services)) {
                $b->text($this->cleanText('-- SERVICOS / DIARIAS --'));
                foreach ($services as $item) {
                    $b->text($this->justifyLine($item['title'], (float) $item['amount']));
                }
            }

            if (!empty($excedentes)) {
                $b->text($this->cleanText('-- EXCEDENTE DE LANCHE --'));
                foreach ($excedentes as $item) {
                    $time = !empty($item['time']) ? "[{$item['time']}] " : '';
                    $b->text($this->justifyLine($time . $item['title'], (float) $item['amount'], 48, true));
                }
            }

            if (!empty($balcao)) {
                $b->text($this->cleanText('-- CONSUMO BALCAO / PESSOAL --'));
                foreach ($balcao as $item) {
                    $time = !empty($item['time']) ? "[{$item['time']}] " : '';
                    $b->text($this->justifyLine($time . $item['title'], (float) $item['amount'], 48, true));
                }
            }
        } else {
            $b->text($this->cleanText('(NENHUM VALOR FINANCEIRO BAIXADO HOJE)'));
        }

        // ==========================================
        // 2. ITENS ROLADOS PARA O FUTURO (SE HOUVER)
        // ==========================================
        if (!empty($this->rolledItems)) {
            $b->divider();
            $b->text($this->cleanText('>>> ITENS ROLADOS P/ O PROXIMO TURNO <<<'), bold: true);

            foreach ($this->rolledItems as $item) {
                $time = !empty($item['time']) ? "[{$item['time']}] " : '';
                $isCredit = ($item['type'] ?? '') === 'service' || ($item['category'] ?? '') === 'services';
                $prefix = $isCredit ? '(+) ' : '(-) ';
                $b->text($this->justifyLine($prefix . $time . $item['title'], (float) $item['amount'], 48, !$isCredit));
            }
        }

        // ==========================================
        // 3. FECHAMENTO FINANCEIRO JUSTIFICADO
        // ==========================================
        $b->divider();
        $b->alignLeft()
            ->text($this->justifyLine('TOTAL PAGO AGORA:', $this->netAmount), bold: true)
            ->text($this->cleanText('FORMA DE PAGAMENTO: ' . $this->paymentMethod));

        if (abs($this->remainingBalance) > 0.01) {
            $balLabel = $this->remainingBalance > 0
                ? 'CREDITO P/ PROXIMO TURNO:'
                : 'DEBITO P/ PROXIMO TURNO:';
            $b->text($this->justifyLine($balLabel, abs($this->remainingBalance), 48, $this->remainingBalance < 0));
        } else {
            $b->text($this->cleanText('SALDO REMANESCENTE: R$ 0,00 (CONTA ZERADA)'));
        }

        $b->divider()
            ->space()
            ->text($this->cleanText('DECLARO QUE CONFERI E RECEBI OS VALORES ESPECIFICADOS ACIMA, DANDO QUITACAO DOS ITENS LIQUIDADOS.'))
            ->space()
            ->space()
            ->alignCenter()
            ->text('____________________________________')
            ->text($this->cleanText($this->freelancerName))
            ->cut();

        return $b->get();
    }
}
