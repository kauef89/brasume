<?php

namespace App\Services\Printing\Documents;

use App\Models\Order;
use App\Models\CompanySetting;
use App\Services\Printing\BlueprintBuilder;
use App\Services\Printing\OrderTableBuilder;
use Illuminate\Support\Str;

class DeliveryDispatch
{
    protected Order $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function getJobType(): string
    {
        return 'delivery_dispatch';
    }

    private function cleanText(?string $text): string
    {
        if (!$text) return '';
        return trim(Str::ascii(mb_strtoupper($text)));
    }

    private function abbreviateName(?string $name): string
    {
        if (!$name) return '';
        $parts = preg_split('/\s+/', trim($name));

        if (count($parts) === 1) return mb_strtoupper($parts[0]);

        $firstName = array_shift($parts);
        $lastNameInitial = mb_strtoupper(mb_substr(array_pop($parts), 0, 1));

        return mb_strtoupper("{$firstName} {$lastNameInitial}.");
    }

    private function formatPhone(?string $phone): string
    {
        if (!$phone) return '';
        $p = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($p) === 11) return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $p);
        if (strlen($p) === 10) return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $p);
        return $phone;
    }

    private function justifyRow(string $left, string $right, int $cols = 48): string
    {
        $left = $this->cleanText($left);
        $right = $this->cleanText($right);
        $spaces = max(1, $cols - strlen($left) - strlen($right));
        return $left . str_repeat(' ', $spaces) . $right;
    }

    private function removeViagemTag(?string $text): string
    {
        if (!$text) return '';
        $cleaned = preg_replace('/\s*\(\s*VIAGEM\s*\)\s*/i', ' ', $text);
        return trim(preg_replace('/\s+/', ' ', $cleaned));
    }

    private function cleanModifiersViagem(array $modifiers): array
    {
        array_walk_recursive($modifiers, function (&$value) {
            if (is_string($value)) {
                $value = $this->removeViagemTag($value);
            }
        });
        return $modifiers;
    }

    public function build(): array
    {
        $settings = CompanySetting::getCached()?->toArray() ?? [];
        $order = $this->order;
        $b = new BlueprintBuilder();

        $cols = !empty($settings['printer_columns']) ? (int) $settings['printer_columns'] : 48;

        $s3 = chr(29) . chr(33) . chr(34); // 3x3
        $sW = chr(29) . chr(33) . chr(16); // Dupla largura
        $s0 = chr(29) . chr(33) . chr(0);  // Reset

        // 🎯 1. Logotipo (Centralizado)
        $b->alignCenter();
        $tenant = function_exists('tenant') ? tenant() : null;
        $domain = $tenant?->domains?->first()?->domain
            ?? ($tenant ? $tenant->id . '.brasume.com.br' : request()->getHost());

        $logoUrl = !empty($settings['receipt_logo_url'])
            ? $settings['receipt_logo_url']
            : (!empty($settings['logo_path']) ? "https://{$domain}/media/file?path=" . $settings['logo_path'] : null);

        if ($logoUrl) {
            $b->image($logoUrl);
        } else {
            $b->text($this->cleanText($settings['trade_name'] ?? 'SISTEMA ERP'), true, '2x');
        }

        $typeTitle = 'ENTREGA';
        if (in_array($order->type, ['pickup', 'takeaway'])) $typeTitle = 'RETIRADA';
        if ($order->type === 'counter') $typeTitle = 'BALCAO';
        if ($order->type === 'table') $typeTitle = 'MESA ' . $order->table_number;

        // 🎯 2. Tipo do Pedido (Centralizado)
        $b->alignCenter()
          ->space()
          ->text($s3 . $typeTitle . $s0, true)
          ->divider();

        // 🎯 3. Linha do Pedido e Telefone em tamanho normal (1x)
        $time = $order->created_at ? $order->created_at->format('H:i') : now()->format('H:i');
        $formattedOrderId = number_format($order->id, 0, '', '.');
        $phone = $order->customer && $order->customer->phone ? $this->formatPhone($order->customer->phone) : '';

        $topLine = "#{$formattedOrderId} - {$time}";
        if ($phone) $topLine .= "   {$phone}";

        $b->alignCenter()
          ->text($topLine, true, '1x');

        // 🎯 4. Nome do Cliente (Abreviado LGPD e 3x)
        $clientName = $order->customer_name ?: 'CONSUMIDOR FINAL';
        $b->text($s3 . $this->cleanText($this->abbreviateName($clientName)) . $s0, true);

        // 🎯 5. Endereço de Entrega (Sem cabeçalho, direto na rota)
        $addr = $order->address;

        if ($order->type === 'delivery' && !empty($addr)) {
            $b->divider()
              ->alignCenter();

            $streetLine = '';
            $complement = '';
            $reference = '';

            if ($addr instanceof \App\Models\Address) {
                $streetLine = $addr->full_address
                    ?: (($addr->street?->name ?? 'Rua') . ', ' . $addr->number . ($addr->neighborhood ? ' - ' . $addr->neighborhood->name : ''));
                $complement = $addr->complement ?? '';
                $reference = $addr->reference ?? '';
            } elseif (is_array($addr)) {
                if (!empty($addr['full_address'])) {
                    $streetLine = $addr['full_address'];
                } else {
                    $street = is_array($addr['street'] ?? null) ? ($addr['street']['name'] ?? '') : ($addr['street'] ?? '');
                    $number = $addr['number'] ?? 'S/N';
                    $neighborhood = is_array($addr['neighborhood'] ?? null) ? ($addr['neighborhood']['name'] ?? '') : ($addr['neighborhood'] ?? '');

                    $streetLine = "{$street}, {$number}";
                    if (!empty($neighborhood)) {
                        $streetLine .= " - {$neighborhood}";
                    }
                }

                $complement = $addr['complement'] ?? '';
                $reference = $addr['reference'] ?? '';
            } else {
                $streetLine = (string) $addr;
            }

            // Quebra preservando palavras inteiras no modo dupla largura (máx 22 colunas)
            $wrappedStreetLines = explode("\n", wordwrap($this->cleanText($streetLine), 22, "\n", false));
            foreach ($wrappedStreetLines as $line) {
                $line = trim($line);
                if (!empty($line)) {
                    $b->text($sW . $line . $s0, true);
                }
            }

            if (!empty($complement)) {
                $wrappedCompLines = explode("\n", wordwrap('COMP: ' . $this->cleanText($complement), 44, "\n", false));
                foreach ($wrappedCompLines as $cline) {
                    $b->text(trim($cline), true, '1x');
                }
            }
            if (!empty($reference)) {
                $wrappedRefLines = explode("\n", wordwrap('REF: ' . $this->cleanText($reference), 44, "\n", false));
                foreach ($wrappedRefLines as $rline) {
                    $b->text(trim($rline), true, '1x');
                }
            }
        }

        // 🎯 6. Lista de Itens (Sem cabeçalho, direto na tabela)
        $b->divider()
          ->alignLeft();

        $cleanItems = $order->items ? $order->items->map(function ($item) {
            $clone = clone $item;

            foreach (['name', 'product_name', 'notes', 'observations', 'observation'] as $field) {
                if (!empty($clone->{$field})) {
                    $clone->{$field} = $this->removeViagemTag($clone->{$field});
                }
            }

            if ($clone->relationLoaded('product') && $clone->product) {
                $clone->product = clone $clone->product;
                if (!empty($clone->product->name)) {
                    $clone->product->name = $this->removeViagemTag($clone->product->name);
                }
            }

            if (!empty($clone->modifiers) && is_array($clone->modifiers)) {
                $clone->modifiers = $this->cleanModifiersViagem($clone->modifiers);
            }

            return $clone;
        }) : collect();

        OrderTableBuilder::buildDispatch($b, $cleanItems, 'A', true);

        // 🎯 7. Observações do Pedido
        if (!empty($order->notes)) {
            $b->divider()
              ->alignCenter()
              ->text('OBSERVACOES DO PEDIDO:', true)
              ->text($this->cleanText($order->notes), true, '1x');
        }

        // 🎯 8. Resumo Financeiro (Justificado)
        $b->divider()->alignLeft();

        if ($order->subtotal > 0) {
            $b->text($this->justifyRow('PRODUTOS:', 'R$ ' . $this->money($order->subtotal), $cols), true, '1x');
        }
        if ($order->delivery_fee > 0) {
            $b->text($this->justifyRow('TAXA ENTREGA:', 'R$ ' . $this->money($order->delivery_fee), $cols), true, '1x');
        }
        if ($order->service_fee > 0) {
            $b->text($this->justifyRow('TAXA SERVICO:', 'R$ ' . $this->money($order->service_fee), $cols), true, '1x');
        }
        if ($order->discount > 0) {
            $b->text($this->justifyRow('DESCONTO:', '-R$ ' . $this->money($order->discount), $cols), true, '1x');
        }

        $b->text($this->justifyRow('TOTAL DO PEDIDO:', 'R$ ' . $this->money($order->total), $cols), true, '1x')
          ->space();

        // 🎯 9. Apuração Financeira e Banners de Cobrança
        $validPayments = $order->payments
            ? $order->payments->filter(fn($p) => !in_array($p->status ?? 'paid', ['cancelled', 'failed', 'refunded']))
            : collect();

        $paidAmount = $validPayments->sum('amount');
        $toCollect = max(0, (float)$order->total - (float)$paidAmount);
        $isPaidStatus = in_array($order->status, ['closed', 'completed']) || $order->payment_status === 'paid';

        $b->alignCenter();

        if ($isPaidStatus || $toCollect <= 0.02) {
            $b->blueprint[] = [
                'cmd'    => 'text',
                'val'    => $s3 . '   JA PAGO   ' . $s0,
                'bold'   => true,
                'invert' => true
            ];
            $b->space()
              ->text('NAO COBRAR DO CLIENTE', true);
        } else {
            $b->blueprint[] = [
                'cmd'    => 'text',
                'val'    => $s3 . ' A COBRAR ' . $s0,
                'bold'   => true,
                'invert' => true
            ];

            $b->text(' ', false, '1x');
            $b->text('VALOR R$: ' . $this->money($toCollect), true, '2x')
              ->space();

            $methods = $order->payment_intent_methods
                ?? $order->payment_intent_methods
                ?? [];

            $hasPromises = (!empty($methods) && is_array($methods))
                || in_array($order->payment_intent_status, ['pay_on_delivery', 'pay_on_pickup', 'pending_choice']);

            if ($hasPromises && !empty($methods)) {
                $b->text('FORMA DE PAGAMENTO:', true);

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

                    $b->text($sW . $methodName . $s0, true);

                    if (count($methods) > 1) {
                        $b->text('VALOR: R$ ' . $this->money($amount));
                    }

                    if ($changeFor > 0 && $troco > 0) {
                        $b->text('PAGAMENTO EM: R$ ' . $this->money($changeFor))
                          ->text($sW . 'LEVAR TROCO: R$ ' . $this->money($troco) . $s0, true);
                    }
                    $b->space();
                }
            } else {
                $b->text('(!) FORMA NAO INFORMADA', true)
                  ->text('PERGUNTAR AO CLIENTE NA ENTREGA.');
            }
        }

        // 🎯 10. Rodapé
        $b->space()
          ->divider()
          ->alignCenter()
          ->text($this->cleanText($settings['receipt_footer_text'] ?? 'OBRIGADO PELA PREFERENCIA!'));

        return $b->cut()->get();
    }

    private function money($value): string
    {
        return number_format((float) $value, 2, ',', '.');
    }
}
