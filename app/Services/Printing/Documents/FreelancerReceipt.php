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
     * @param array $ledgerItems Array de consumos, ex: [['description' => '1X REFRI', 'amount' => 5.0], ...]
     */
    public function __construct(
        public string $freelancerName,
        public float $netAmount,
        public float $grossAmount = 0.0,
        public float $consumptionAmount = 0.0,
        public string $paymentMethod = 'Dinheiro',
        public string $operatorName = 'Caixa',
        public ?string $pixKey = null,
        public array $ledgerItems = []
    ) {}

    public function getJobType(): string
    {
        return 'freelancer_receipt';
    }

    /**
     * Remove acentos e converte para CAIXA ALTA (Uppercase).
     */
    protected function cleanText(string $text): string
    {
        return mb_strtoupper(Str::ascii($text));
    }

    /**
     * Cria uma linha justificada com o texto à esquerda e o valor (com R$) alinhado à direita.
     * Considera bobinas de 48 colunas de largura.
     */
    protected function justifyLine(string $leftText, float $amount, int $width = 48, bool $isNegative = false): string
    {
        $left = $this->cleanText($leftText);
        $amountStr = number_format(abs($amount), 2, ',', '.');
        $right = ($isNegative ? '(-) R$ ' : 'R$ ') . str_pad($amountStr, 6, ' ', STR_PAD_LEFT);

        $spaceCount = max(1, $width - mb_strlen($left) - mb_strlen($right));

        return $left . str_repeat(' ', $spaceCount) . $right;
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

        // ==========================================
        // EXTRATO DO DIA (DETALHAMENTO DE CONSUMO)
        // ==========================================
        $b->divider();

        if (!empty($this->ledgerItems)) {
            $b->text($this->cleanText('EXTRATO DE CONSUMO INTERNO:'), bold: true);
            foreach ($this->ledgerItems as $item) {
                // Filtra lançamentos zerados ou descrições de lanche da equipa (vale-alimentação)
                if (
                    $item['amount'] > 0 &&
                    !str_contains(mb_strtolower($item['description']), 'lanche da equipa') &&
                    !str_contains(mb_strtolower($item['description']), 'vale alimentação')
                ) {
                    $b->text($this->justifyLine($item['description'], (float) $item['amount']));
                }
            }
            $b->divider();
        }

        // ==========================================
        // FECHAMENTO FINANCEIRO JUSTIFICADO
        // ==========================================
        $gross = $this->grossAmount > 0 ? $this->grossAmount : $this->netAmount;

        $b->alignLeft()
          ->text($this->justifyLine('DIARIA BRUTA:', $gross));

        if ($this->consumptionAmount > 0) {
            $b->text($this->justifyLine('CONSUMO DO TURNO:', $this->consumptionAmount, 48, true));
        }

        $b->text($this->justifyLine('(=) VALOR LIQUIDO:', $this->netAmount), bold: true)
          ->text($this->cleanText('FORMA DE PAGAMENTO: ' . $this->paymentMethod))
          ->divider()
          ->space()
          ->text($this->cleanText('DECLARO QUE RECEBI OS VALORES ACIMA ESPECIFICADOS, DANDO PLENA E GERAL QUITACAO PELOS SERVICOS PRESTADOS NO RESPECTIVO TURNO.'))
          ->space()
          ->space()
          ->alignCenter()
          ->text('____________________________________')
          ->text($this->cleanText($this->freelancerName))
          ->cut();

        return $b->get();
    }
}
