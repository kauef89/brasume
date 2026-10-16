<?php

namespace App\Services\Printing\Documents;

use App\Services\Printing\BlueprintBuilder;

class FreelancerReceipt
{
    public function __construct(
        public string $freelancerName,
        public float $netAmount,
        public float $grossAmount = 0.0,
        public float $consumptionAmount = 0.0,
        public string $paymentMethod = 'Dinheiro',
        public string $operatorName = 'Caixa',
        public ?string $pixKey = null
    ) {}

    public function getJobType(): string
    {
        return 'freelancer_receipt';
    }

    public function build(): array
    {
        $b = new BlueprintBuilder();

        $b->alignCenter()
            ->text('RECIBO DE DIÁRIA EVENTUAL', bold: true, size: '2x')
            ->text('Data: ' . now()->format('d/m/Y H:i'))
            ->text('Operador: ' . $this->operatorName)
            ->divider()
            ->alignLeft()
            ->text('Colaborador: ' . $this->freelancerName);

        if ($this->pixKey && str_contains(strtolower($this->paymentMethod), 'pix')) {
            $b->text('Chave PIX: ' . $this->pixKey);
        }

        $b->divider()
            ->alignLeft()
            ->text('Diária Bruta:        R$ ' . number_format($this->grossAmount ?: $this->netAmount, 2, ',', '.'));

        if ($this->consumptionAmount > 0) {
            $b->text('(-) Consumo Turno:   R$ ' . number_format($this->consumptionAmount, 2, ',', '.'));
        }

        $b->text('(=) Valor Líquido:   R$ ' . number_format($this->netAmount, 2, ',', '.'), bold: true)
            ->text('Forma de Pagamento:  ' . $this->paymentMethod)
            ->divider()
            ->space()
            ->text('Declaro que recebi os valores acima especificados, dando plena e geral quitação pelos serviços prestados no respectivo turno.')
            ->space()
            ->space()
            ->alignCenter()
            ->text('____________________________________')
            ->text($this->freelancerName)
            ->cut();

        return $b->get();
    }
}
