<?php

namespace App\Services\Printing\Documents;

use App\Models\Order;
use App\Models\CompanySetting;
use App\Services\Printing\BlueprintBuilder;

class PixGuide
{
    public static function build(Order $order, string $qrCodeData, ?string $expiresAt = null): string
    {
        $settings = CompanySetting::getCached() ?? CompanySetting::first();
        $builder = new BlueprintBuilder();

        $builder->align('center')
                ->text($settings->company_name ?? 'Empresa')
                ->text('CHAVE PIX PARA PAGAMENTO')
                ->feed(1);

        $builder->text('Pedido #' . $order->id)
                ->text('Valor: R$ ' . number_format($order->total ?? 0, 2, ',', '.'))
                ->feed(1);

        $builder->qrcode($qrCodeData, 8)
                ->feed(1);

        if ($expiresAt) {
            $builder->text('Válido até: ' . \Carbon\Carbon::parse($expiresAt)->format('d/m/Y H:i:s'))
                    ->feed(1);
        }

        $builder->align('left')
                ->text('Aponte a camera do seu celular ou o')
                ->text('app do seu banco para o QR Code acima.')
                ->text('O pagamento sera baixado automaticamente.')
                ->feed(2)
                ->cut();

        return $builder->build();
    }
}
