<?php

namespace App\Services\Printing\Documents;

use App\Models\Order;
use App\Models\CompanySetting;
use App\Services\Printing\BlueprintBuilder;
use App\Services\Marketing\OrderClaimService;
use Illuminate\Support\Str;

class TableClaimFlyer
{
    protected Order $order;
    protected string $claimUrl;
    protected array $rewards;

    public function __construct(Order $order, string $claimUrl)
    {
        $this->order = $order;
        $this->claimUrl = $claimUrl;

        $claimService = app(OrderClaimService::class);
        $this->rewards = $claimService->calculateEstimatedRewards($order);
    }

    public function getJobType(): string
    {
        return 'table_claim_flyer';
    }

    private function cleanText(?string $text): string
    {
        if (!$text) return '';
        return trim(Str::ascii(mb_strtoupper($text)));
    }

    public function build(): array
    {
        $settings = CompanySetting::getCached()?->toArray() ?? [];
        $b = new BlueprintBuilder();

        $b->alignCenter();

        // 🎯 1. Logotipo do Tenant
        $tenant = function_exists('tenant') ? tenant() : null;
        $domain = $tenant?->domains?->first()?->domain
            ?? ($tenant ? $tenant->id . '.brasume.com.br' : request()->getHost());

        $logoUrl = !empty($settings['receipt_logo_url'])
            ? $settings['receipt_logo_url']
            : (!empty($settings['logo_path']) ? "https://{$domain}/media/file?path=" . $settings['logo_path'] : null);

        if ($logoUrl) {
            $b->image($logoUrl);
        } else {
            $b->text($this->cleanText($settings['trade_name'] ?? 'SEU CASHBACK'), true, '2x');
        }

        $cashbackFormatted = number_format($this->rewards['cashback_value'], 2, ',', '.');
        $points = $this->rewards['points'];
        $tableName = $this->order->table_number ? "MESA {$this->order->table_number}" : 'SUA CONTA';

        // 🎯 2. Gancho Psicológico de Posse
        $b->alignCenter()
          ->divider()
          ->text('*** DINHEIRO DE VOLTA ***', true)
          ->space()
          ->text("CONSUMO NA {$tableName}", true)
          ->text("VOCE TEM", false)
          ->text("R$ {$cashbackFormatted} DE SALDO", true, '2x')
          ->text("esperando por voce no nosso App!", true)
          ->space()
          ->text("({$points} pontos de Cashback acumulados)")
          ->divider()
          ->text("Aponte a camera do celular", true)
          ->text("para resgatar o seu saldo agora:", true)
          ->space()
          ->qrCode($this->claimUrl, 5)
          ->space()
          ->text("CODIGO DO COMPROVANTE:", true)
          ->text($this->order->claim_hash, true, '2x')
          ->space()
          ->text("Valido por 48 horas. Uso unico.")
          ->divider();

        return $b->cut()->get();
    }
}
