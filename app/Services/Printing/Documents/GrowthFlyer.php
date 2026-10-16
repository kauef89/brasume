<?php

namespace App\Services\Printing\Documents;

use App\Models\CompanySetting;
use App\Models\Coupon;
use App\Services\Printing\BlueprintBuilder;
use Illuminate\Support\Str;

class GrowthFlyer
{
    protected Coupon $coupon;
    protected string $appUrl;

    public function __construct(Coupon $coupon, ?string $appUrl = null)
    {
        $this->coupon = $coupon;
        $this->appUrl = $appUrl ?: route('customer.app', [
            'tab'   => 'menu',
            'cupom' => $coupon->code,
        ]);
    }

    public function getJobType(): string
    {
        return 'growth_flyer';
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

        // 🎯 Força alinhamento centralizado para todo o documento
        $b->alignCenter();

        // 1. Logotipo do Tenant (com fallback para Nome Fantasia)
        $tenant = function_exists('tenant') ? tenant() : null;
        $domain = $tenant?->domains?->first()?->domain
            ?? ($tenant ? $tenant->id . '.brasume.com.br' : request()->getHost());

        $logoUrl = !empty($settings['receipt_logo_url'])
            ? $settings['receipt_logo_url']
            : (!empty($settings['logo_path']) ? "https://{$domain}/media/file?path=" . $settings['logo_path'] : null);

        if ($logoUrl) {
            $b->image($logoUrl);
        } else {
            $b->text($this->cleanText($settings['trade_name'] ?? 'PRESENTE ESPECIAL'), true, '2x');
        }

        $valueStr = number_format($this->coupon->value, 2, ',', '.');

        // 2. Mensagem Centralizada de Indicação / Growth
        $b->alignCenter()
          ->divider()
          ->text('🎁 PRESENTE PARA SEU AMIGO', true)
          ->space()
          ->text("INDIQUE UM AMIGO E ELE GANHA:", true)
          ->text("R$ {$valueStr} OFF", true, '2x')
          ->text("no 1º pedido feito no nosso App!", true)
          ->divider()
          ->text("CUPOM EXCLUSIVO:", true)
          ->text($this->coupon->code, true, '2x')
          ->space()
          ->text("Aponte a camera para ativar direto:", true)
          ->qrCode($this->appUrl, 5)
          ->space()
          ->text("Valido por 30 dias. Limite: 1 uso.", true)
          ->divider();

        return $b->cut()->get();
    }
}
