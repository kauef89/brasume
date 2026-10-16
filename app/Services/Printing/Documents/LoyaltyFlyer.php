<?php

namespace App\Services\Printing\Documents;

use App\Models\CompanySetting;
use App\Services\Printing\BlueprintBuilder;
use Illuminate\Support\Str;

class LoyaltyFlyer
{
    protected string $customerName;
    protected int $pointsEarned;
    protected string $appUrl;

    public function __construct(string $customerName, int $pointsEarned, string $appUrl)
    {
        $this->customerName = $customerName;
        $this->pointsEarned = $pointsEarned;
        $this->appUrl = $appUrl;
    }

    public function getJobType(): string
    {
        return 'loyalty_flyer';
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
            $b->text($this->cleanText($settings['trade_name'] ?? 'PROGRAMA DE FIDELIDADE'), true, '2x');
        }

        // 2. Primeiro Nome do Cliente
        $firstName = Str::upper(explode(' ', trim($this->customerName))[0] ?? 'CLIENTE');

        // 3. Mensagem Centralizada de Cashback
        $b->alignCenter()
          ->divider()
          ->text('*** DINHEIRO DE VOLTA ***', true)
          ->space()
          ->text("PARABENS, {$firstName}!", true)
          ->text("VOCE GANHOU", true)
          ->text("+{$this->pointsEarned} PONTOS", true, '2x')
          ->text("de Cashback para a proxima compra!", true)
          ->divider()
          ->text("Aponte a camera para resgatar no App:", true)
          ->qrCode($this->appUrl, 5)
          ->space()
          ->text("Ou acesse:", true)
          ->text(str_replace(['https://', 'http://'], '', $this->appUrl), true)
          ->divider();

        return $b->cut()->get();
    }
}
