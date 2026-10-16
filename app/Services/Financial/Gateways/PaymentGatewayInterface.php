<?php

namespace App\Services\Financial\Gateways;

use App\Models\Order;

interface PaymentGatewayInterface
{
    public function generatePayment(Order $order, float $amount): array;

    public function testConnection(): bool;

    // 🚀 NOVO: Contrato de Estorno
    public function refundPayment(Order $order, float $amount): array;
}
