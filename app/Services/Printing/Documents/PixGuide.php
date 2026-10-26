<?php

namespace App\Services\Printing\Documents;

use App\Models\Order;
use App\Models\CompanySetting;
use App\Services\Printing\BlueprintBuilder;

class PixGuide
{
    protected Order $order;
    protected string $qrCodeData;
    protected ?string $expiresAt;

    public function __construct(Order $order, string $qrCodeData, ?string $expiresAt = null)
    {
        $this->order = $order;
        $this->qrCodeData = $qrCodeData;
        $this->expiresAt = $expiresAt;
    }

    public function getJobType(): string
    {
        return 'pix_guide';
    }

    public function build(): array
    {
        $settings = CompanySetting::getCached() ?? CompanySetting::first();
        $b = new BlueprintBuilder();

        $companyName = strtoupper($settings->trade_name ?? $settings->company_name ?? 'EMPRESA');

        $b->alignCenter()
          ->text($companyName, true, '2x')
          ->space()
          ->text('CHAVE PIX PARA PAGAMENTO', true)
          ->space();

        $b->alignLeft()
          ->text('PEDIDO #' . $this->order->id, true)
          ->text('VALOR R$: ' . number_format($this->order->total ?? 0, 2, ',', '.'), true, '2x')
          ->space();

        $b->alignCenter()
          ->qrCode($this->qrCodeData, 8)
          ->space();

        if ($this->expiresAt) {
            $b->text('Valido ate: ' . \Carbon\Carbon::parse($this->expiresAt)->format('d/m/Y H:i:s'))
              ->space();
        }

        $b->alignCenter()
          ->text('Aponte a camera do seu celular ou o')
          ->text('app do seu banco para o QR Code acima.')
          ->text('O pagamento sera baixado automaticamente.')
          ->space()
          ->space()
          ->cut();

        return $b->get();
    }
}
