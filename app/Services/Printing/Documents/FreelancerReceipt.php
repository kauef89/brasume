<?php

namespace App\Services\Printing\Documents;

use App\Services\Printing\BlueprintBuilder;
use Illuminate\Support\Str;

class FreelancerReceipt
{
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
        public float $remainingBalance = 0.0,
        public bool $isConference = false,
        public ?array $courierDetails = null,
        public ?string $shiftOperatorName = null
    ) {}

    public function getJobType(): string
    {
        return $this->isConference ? 'freelancer_conference' : 'freelancer_receipt';
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

    protected function justifyText(string $leftText, string $rightText, int $width = 48): string
    {
        $cleanRight = $this->cleanText($rightText);
        $maxLeftLength = max(10, $width - mb_strlen($cleanRight) - 1);
        $cleanLeft = $this->cleanText($leftText);

        if (mb_strlen($cleanLeft) > $maxLeftLength) {
            $cleanLeft = mb_substr($cleanLeft, 0, $maxLeftLength - 1) . '.';
        }

        $spaceCount = max(1, $width - mb_strlen($cleanLeft) - mb_strlen($cleanRight));

        return $cleanLeft . str_repeat(' ', $spaceCount) . $cleanRight;
    }

    public function build(): array
    {
        $b = new BlueprintBuilder();

        $title = $this->isConference ? 'CONFERENCIA DE DIARIA' : 'RECIBO DE DIARIA EVENTUAL';
        $cashierOperator = $this->shiftOperatorName ?: $this->operatorName;

        $b->alignCenter()
            ->text($this->cleanText($title), bold: true, size: '2x');

        if ($this->isConference) {
            $b->text($this->cleanText('*** PRE-CONTA PARA CONFERENCIA ***'), bold: true);
        }

        $b->text($this->cleanText('EMISSAO: ' . now()->format('d/m/Y H:i')))
            ->text($this->cleanText('OPERADOR CAIXA: ' . $cashierOperator))
            ->divider()
            ->alignLeft()
            ->text($this->cleanText('COLABORADOR: ' . $this->freelancerName), bold: true);

        if ($this->pixKey && ($this->isConference || str_contains(strtolower($this->paymentMethod), 'pix'))) {
            $b->text($this->cleanText('CHAVE PIX: ' . $this->pixKey));
        }

        if (!empty($this->shiftDateLabel)) {
            $b->text($this->cleanText('DATA / TURNO: ' . $this->shiftDateLabel), bold: true);
        }

        // ==========================================
        // BLOCO ESPECIFICO: FECHAMENTO DO MOTOBOY
        // ==========================================
        $hasCourierBlock = !empty($this->courierDetails) && !empty($this->courierDetails['is_courier']);

        if ($hasCourierBlock) {
            $cd = $this->courierDetails;
            $b->divider();
            $b->text($this->cleanText('>>> FECHAMENTO DE ENTREGAS (MOTOBOY) <<<'), bold: true);

            $modelLabel = $cd['payment_model_label'] ?? 'Taxa Fixa por Corrida';
            $b->text($this->justifyText('MODELO REPASSE:', $modelLabel));

            if (($cd['payment_model'] ?? 'fixed') === 'distance' && (float) ($cd['fee_per_km'] ?? 0) > 0) {
                $b->text($this->justifyLine('VALOR POR KM RODADO:', (float) $cd['fee_per_km']));
            } elseif ((float) ($cd['default_delivery_fee'] ?? 0) > 0) {
                $rateLabel = ($cd['payment_model'] ?? 'fixed') === 'neighborhood'
                    ? 'TAXA PADRAO (FALLBACK):'
                    : 'TAXA FIXA POR ENTREGA:';
                $b->text($this->justifyLine($rateLabel, (float) $cd['default_delivery_fee']));
            }

            $b->text($this->justifyLine('DIARIA FIXA DO TURNO:', (float) ($cd['daily_fee'] ?? 0)));

            if ((float) ($cd['minimum_guaranteed'] ?? 0) > 0) {
                $b->text($this->justifyLine('GARANTIA MINIMA TURNO:', (float) $cd['minimum_guaranteed']));
            }

            // Lista corrida por corrida
            $deliveries = $cd['deliveries'] ?? [];
            $b->text($this->cleanText('-- LISTA DE ENTREGAS (' . count($deliveries) . ') --'), bold: true);

            if (!empty($deliveries)) {
                foreach ($deliveries as $del) {
                    $orderNum = '#' . ($del['id'] ?? '');
                    $time = !empty($del['time']) ? "[{$del['time']}] " : '';
                    $dest = $del['neighborhood'] ?? $del['customer'] ?? 'Entrega';
                    $kmTag = (($cd['payment_model'] ?? '') === 'distance' && (float) ($del['distance_km'] ?? 0) > 0)
                        ? ' (' . number_format((float) $del['distance_km'], 1, ',', '.') . 'km)'
                        : '';

                    $b->text($this->justifyLine("{$orderNum} {$time}{$dest}{$kmTag}", (float) ($del['courier_fee'] ?? 0)));

                    if (!empty($del['is_cash']) && (float) ($del['cash_amount'] ?? 0) > 0) {
                        $b->text($this->cleanText('   -> COBRADO EM DINHEIRO: R$ ' . number_format((float) $del['cash_amount'], 2, ',', '.')));
                    }
                }
            } else {
                $b->text($this->cleanText('(NENHUMA ENTREGA REGISTRADA NESTE TURNO)'));
            }

            $b->text($this->cleanText('-- RESUMO PRODUCAO MOTOBOY --'));
            $b->text($this->justifyLine('TOTAL CORRIDAS (' . (int) ($cd['deliveries_count'] ?? count($deliveries)) . '):', (float) ($cd['total_deliveries_fee'] ?? 0)));

            if (!empty($cd['minimum_applied']) && (float) ($cd['minimum_complement'] ?? 0) > 0) {
                $b->text($this->justifyLine('COMPLEMENTO PISO MINIMO:', (float) $cd['minimum_complement']));
            }

            $b->text($this->justifyLine('TOTAL BRUTO DIARIA+ENTREGAS:', (float) ($cd['final_payable'] ?? 0)), bold: true);

            if ((float) ($cd['cash_collected'] ?? 0) > 0) {
                $b->text($this->justifyLine('DINHEIRO COLETADO NA RUA:', (float) $cd['cash_collected']), bold: true);
            }
        }

        // ==========================================
        // 1. ITENS LIQUIDADOS / CONFERIDOS
        // ==========================================
        $b->divider();
        $sectionTitle = $this->isConference
            ? '>>> RESUMO PARA CONFERENCIA <<<'
            : '>>> ITENS LIQUIDADOS NESTE ACERTO <<<';
        $b->text($this->cleanText($sectionTitle), bold: true);

        if (!empty($this->settledItems)) {
            $services = array_filter($this->settledItems, fn($i) => ($i['category'] ?? '') === 'services');
            $excedentes = array_filter($this->settledItems, fn($i) => ($i['category'] ?? '') === 'excedente');
            $balcao = array_filter($this->settledItems, fn($i) => ($i['category'] ?? '') === 'balcao');

            // Se não for motoboy (onde o bloco acima já detalhou diária + corridas), imprime serviços normalmente
            if (!$hasCourierBlock && !empty($services)) {
                $b->text($this->cleanText('-- SERVICOS / DIARIAS --'));
                foreach ($services as $item) {
                    $b->text($this->justifyLine($item['title'], (float) $item['amount']));
                }
            } elseif ($hasCourierBlock) {
                $b->text($this->justifyLine('PRODUCAO + DIARIA MOTOBOY', (float) ($this->courierDetails['final_payable'] ?? 0)));
            }

            if (!empty($excedentes)) {
                $b->text($this->cleanText('-- EXCEDENTE DE LANCHE --'));
                foreach ($excedentes as $item) {
                    $time = !empty($item['time']) ? "[{$item['time']}] " : '';
                    $b->text($this->justifyLine($time . $item['title'], (float) $item['amount'], 48, true));
                }
            }

            if (!empty($balcao)) {
                $b->text($this->cleanText('-- CONSUMO INTERNO / BALCAO --'));
                foreach ($balcao as $item) {
                    $time = !empty($item['time']) ? "[{$item['time']}] " : '';
                    $b->text($this->justifyLine($time . $item['title'], (float) $item['amount'], 48, true));
                }
            }
        } else {
            $b->text($this->cleanText('(NENHUM VALOR FINANCEIRO SELECIONADO)'));
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
        $b->alignLeft();

        if ($this->isConference) {
            $b->text($this->justifyLine('VALOR TOTAL A PAGAR (LIQ):', $this->netAmount), bold: true);

            if ($hasCourierBlock && (float) ($this->courierDetails['cash_collected'] ?? 0) > 0) {
                $cashInHand = (float) $this->courierDetails['cash_collected'];
                $keepsCash = !empty($this->courierDetails['courier_keeps_cash']);

                if ($keepsCash) {
                    $b->text($this->justifyLine('(-) DINHEIRO RETIDO C/ MOTOBOY:', $cashInHand, 48, true));
                    $rawNetBeforeCash = round($this->netAmount + $cashInHand, 2);
                    if ($cashInHand > $rawNetBeforeCash) {
                        $b->text($this->justifyLine('TROCO A DEVOLVER AO CAIXA:', round($cashInHand - $rawNetBeforeCash, 2)), bold: true);
                    }
                } else {
                    $b->text($this->justifyLine('DINHEIRO A ENTREGAR AO CAIXA:', $cashInHand), bold: true);
                }
            }
        } else {
            $b->text($this->justifyLine('TOTAL PAGO AGORA:', $this->netAmount), bold: true)
                ->text($this->cleanText('FORMA DE PAGAMENTO: ' . $this->paymentMethod));

            if ($hasCourierBlock && (float) ($this->courierDetails['cash_collected'] ?? 0) > 0) {
                $cashInHand = (float) $this->courierDetails['cash_collected'];
                $keepsCash = !empty($this->courierDetails['courier_keeps_cash']);
                $cashLabel = $keepsCash
                    ? '(-) DINHEIRO ABATIDO NA DIARIA:'
                    : 'DINHEIRO ENTREGUE AO CAIXA:';
                $b->text($this->justifyLine($cashLabel, $cashInHand, 48, $keepsCash));
            }
        }

        if (abs($this->remainingBalance) > 0.01) {
            $balLabel = $this->remainingBalance > 0
                ? 'CREDITO P/ PROXIMO TURNO:'
                : 'DEBITO P/ PROXIMO TURNO:';
            $b->text($this->justifyLine($balLabel, abs($this->remainingBalance), 48, $this->remainingBalance < 0));
        } else {
            $b->text($this->cleanText('SALDO REMANESCENTE: R$ 0,00 (CONTA ZERADA)'));
        }

        $b->divider()
            ->space();

        if ($this->isConference) {
            $b->text($this->cleanText('DOCUMENTO DE CONFERENCIA PREVIA. CONFIRA OS VALORES ANTES DA QUITACAO NO CAIXA.'))
                ->space()
                ->space()
                ->alignCenter()
                ->text('____________________________________')
                ->text($this->cleanText('CONFERIDO POR: ' . $this->freelancerName));
        } else {
            $b->text($this->cleanText('DECLARO QUE CONFERI E RECEBI OS VALORES ESPECIFICADOS ACIMA, DANDO QUITACAO DOS ITENS LIQUIDADOS.'))
                ->space()
                ->space()
                ->alignCenter()
                ->text('____________________________________')
                ->text($this->cleanText($this->freelancerName));
        }

        $b->cut();

        return $b->get();
    }
}
