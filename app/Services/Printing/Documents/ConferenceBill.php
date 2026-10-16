<?php

namespace App\Services\Printing\Documents;

use App\Models\Order;
use App\Models\CompanySetting;
use App\Services\Printing\BlueprintBuilder;
use App\Services\Printing\ReceiptFormatter;
use App\Services\Printing\OrderTableBuilder;
use Illuminate\Support\Str;

class ConferenceBill
{
    protected Order $order;
    protected array $settings;

    public function __construct(Order $order, array $settings)
    {
        $this->order = $order;
        $this->settings = $settings;
    }

    public function getJobType(): string
    {
        return 'conference_bill';
    }

    private function cleanText(?string $text): string
    {
        if (!$text) return '';
        $text = str_replace(['[VIAGEM]', '(VIAGEM)'], '', $text);
        return trim(Str::ascii(mb_strtoupper($text)));
    }

    public function build(): array
    {
        $order = $this->order;
        $settings = $this->settings;
        $b = new BlueprintBuilder();

        // 🎯 1. LOGOTIPO DINÂMICO DO TENANT (OU NOME FANTASIA)
        $b->blueprint[] = ['cmd' => 'align', 'val' => 'ct'];

        $tenant = function_exists('tenant') ? tenant() : null;
        $domain = $tenant?->domains?->first()?->domain
            ?? ($tenant ? $tenant->id . '.brasume.com.br' : request()->getHost());

        $logoUrl = !empty($settings['receipt_logo_url'])
            ? $settings['receipt_logo_url']
            : (!empty($settings['logo_path']) ? "https://{$domain}/media/file?path=" . $settings['logo_path'] : null);

        if ($logoUrl) {
            $b->image($logoUrl);
        } else {
            $b->text($this->cleanText($settings['trade_name'] ?? $settings['company_name'] ?? 'SISTEMA ERP'), true, '2x');
        }

        $b->text(' ', false, '1x');

        $typeTitle = 'BALCAO';
        if ($order->type === 'delivery') $typeTitle = 'ENTREGA';
        if ($order->type === 'pickup' || $order->type === 'takeaway') $typeTitle = 'RETIRADA';
        if ($order->type === 'table') $typeTitle = 'MESA ' . ($order->table_number ?: '?');

        $b->text("*** {$typeTitle} ***", true, '2x')
          ->text(' ', false, '1x');

        $b->blueprint[] = ['cmd' => 'align', 'val' => 'lt'];
        $b->text('PEDIDO: #' . $order->id, true, '1x');

        $clientName = $order->customer_name ?: 'CONSUMIDOR FINAL';
        $b->text($this->cleanText('CLIENTE: ' . $clientName));

        if ($order->customer && $order->customer->phone) {
            $b->text('TEL: ' . $order->customer->phone);
        }

        $b->text('DATA: ' . now()->format('d/m/Y H:i'));

        if ($order->type === 'delivery' && $order->address) {
            $b->divider()->text('ENDERECO DE ENTREGA:', true);
            $addr = $order->address;
            $streetLine = "{$addr['street']}, {$addr['number']}";
            if (!empty($addr['neighborhood'])) $streetLine .= " - {$addr['neighborhood']}";
            $b->text($this->cleanText($streetLine));

            if (!empty($addr['complement'])) $b->text($this->cleanText('COMP: ' . $addr['complement']));
            if (!empty($addr['reference'])) $b->text($this->cleanText('REF: ' . $addr['reference']));
        }

        $b->divider()->text('CONFERENCIA DE ITENS:', true);

        $activeItems = $order->items ? $order->items->where('status', '!=', 'cancelled') : collect();
        OrderTableBuilder::buildConference($b, $activeItems);

        if ($order->notes) {
            $b->divider()->text('OBSERVACOES:', true)->text($this->cleanText($order->notes));
        }

        $b->divider();
        $b->blueprint[] = ['cmd' => 'align', 'val' => 'rt'];

        if ($order->subtotal > 0) $b->text('PRODUTOS R$: ' . $this->money($order->subtotal));
        if ($order->delivery_fee > 0) $b->text('TAXA ENTREGA R$: ' . $this->money($order->delivery_fee));
        if ($order->service_fee > 0) $b->text('TAXA SERVICO R$: ' . $this->money($order->service_fee));
        if ($order->discount > 0) $b->text('DESCONTO R$: -' . $this->money($order->discount));

        $b->text('TOTAL R$: ' . $this->money($order->total), true, '2x')->space();

        $b->blueprint[] = ['cmd' => 'align', 'val' => 'ct'];

        $isPaidStatus = in_array($order->status, ['closed', 'completed']) || $order->payment_status === 'paid';
        $paidAmount = $order->payments->sum('amount');
        $toCollect = max(0, (float)$order->total - (float)$paidAmount);

        if ($isPaidStatus || $toCollect <= 0.02) {
            $b->text('PAGO', true, '2x')->text('NAO COBRAR DO CLIENTE.');
        } else {
            $b->text('*** A COBRAR R$: ' . $this->money($toCollect) . ' ***', true, '2x')->space();

            $methods = $order->payment_intent_methods ?? [];
            $hasPendingIntents = (!empty($methods) && is_array($methods))
                || in_array($order->payment_intent_status, ['pay_on_delivery', 'pay_on_pickup', 'pending_choice']);

            $hasPayments = $order->payments->count() > 0 || $hasPendingIntents;

            if ($hasPayments) {
                $b->text('FORMA DE PAGTO', true);
                $b->blueprint[] = ['cmd' => 'align', 'val' => 'lt'];

                if (!empty($methods) && is_array($methods)) {
                    $orderChangeTarget = (float) ($order->change_target ?? $order->delivery_change_target ?? 0);

                    foreach ($methods as $method) {
                        $methodName = $this->cleanText($method['method_name'] ?? 'DINHEIRO');
                        $amount = (float) ($method['amount'] ?? $toCollect);
                        $changeFor = (float) ($method['change_target'] ?? $orderChangeTarget);
                        $troco = max(0, $changeFor - $amount);

                        if (str_contains($methodName, 'DINHEIRO')) {
                            if ($changeFor > 0 && $troco > 0) {
                                $methodName = 'DINHEIRO (LEVAR TROCO)';
                            } else {
                                $methodName = 'DINHEIRO - SEM TROCO';
                            }
                        }

                        $b->text($methodName, true, '2x');

                        if (count($methods) > 1) {
                            $b->text('VALOR R$: ' . $this->money($amount));
                        }
                        if ($changeFor > 0 && $troco > 0) {
                            $b->text('PAGAMENTO EM: R$ ' . $this->money($changeFor))
                              ->text('TROCO R$: ' . $this->money($troco), true);
                        }
                        $b->space();
                    }
                }

                foreach ($order->payments as $pay) {
                    $b->text($this->cleanText($pay->method) . ' (JA PAGO)', true)
                      ->text('VALOR R$: ' . $this->money($pay->amount))
                      ->space();
                }
            } else {
                $b->text('(!) FORMA NAO INFORMADA', true)->text('RECEBER NO FECHAMENTO.');
                $b->blueprint[] = ['cmd' => 'align', 'val' => 'lt'];
            }
        }

        $b->space();
        $b->blueprint[] = ['cmd' => 'align', 'val' => 'ct'];
        $b->divider();

        $dbSettings = CompanySetting::getCached();
        if ($dbSettings && !empty($dbSettings->receipt_footer_text)) {
            $b->text($this->cleanText($dbSettings->receipt_footer_text));
        }

        if (!empty($settings['receipt_promo_qrcode'])) {
            $b->qrCode($settings['receipt_promo_qrcode'], 6);
        }

        return $b->cut()->get();
    }

    private function money($value): string
    {
        return number_format((float) $value, 2, ',', '.');
    }
}
