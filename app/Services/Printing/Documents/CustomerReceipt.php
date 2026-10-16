<?php

namespace App\Services\Printing\Documents;

use App\Models\Order;
use App\Services\Printing\BlueprintBuilder;
use App\Services\Printing\ReceiptFormatter;
use App\Services\Printing\OrderTableBuilder;
use Illuminate\Support\Str;

class CustomerReceipt
{
    public function __construct(
        public Order $order,
        public array $settings,
        public ?string $cpf = null
    ) {}

    public function getJobType(): string
    {
        return 'customer_receipt';
    }

    public function getOrderId(): ?int
    {
        return $this->order?->id;
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

        $cols = !empty($settings['printer_columns']) ? (int) $settings['printer_columns'] : 48;

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
            $companyName = $this->cleanText($settings['trade_name'] ?? $settings['company_name'] ?? 'SISTEMA ERP');
            $b->text($companyName, true, '2x');
        }

        $b->text('RECIBO DE PAGAMENTO', true, '1x')
          ->text('PEDIDO: #' . $order->id);

        if ($this->cpf) {
            $b->text('CPF/CNPJ: ' . $this->cpf);
        }

        $b->blueprint[] = ['cmd' => 'align', 'val' => 'lt'];
        $b->divider();

        // 🎯 2. TABELA DE ITENS (JUSTIFICADA COM RECEIPTFORMATTER)
        $activeItems = $order->items ? $order->items->where('status', '!=', 'cancelled') : collect();
        $hasDecimal = OrderTableBuilder::hasDecimalQuantity($activeItems, fn($item) => (float) $item->quantity);

        $qtySize = $hasDecimal ? 6 : 4;
        $priceSize = 10;
        $fixedTotal = $qtySize + 1 + 1 + $priceSize;
        $descSize = max(15, $cols - $fixedTotal);

        $headers = ['QTD', ' ', 'DESCRICAO', ' ', 'VALOR R$'];
        $sizes   = [$qtySize, 1, 0, 1, $priceSize];
        $aligns  = ['L', 'L', 'L', 'L', 'R'];

        $b->text(ReceiptFormatter::columns($headers, $sizes, $aligns, $cols), true);
        $b->text(ReceiptFormatter::divider($cols));

        $parents = $activeItems->filter(fn($item) => empty($item->parent_item_id));
        $children = $activeItems->filter(fn($item) => !empty($item->parent_item_id));

        foreach ($parents as $parent) {
            $qty = (float) $parent->quantity;
            $qtyStr = substr(OrderTableBuilder::formatQty($qty, $hasDecimal) . 'x', 0, $qtySize);

            $rawName = $this->cleanText($parent->product_name);
            if (!empty($parent->is_reward)) {
                $rawName .= ' [CORTESIA]';
            }
            $pName = substr($rawName, 0, $descSize);
            $totalItem = substr(number_format((float) $parent->total_price, 2, ',', '.'), 0, $priceSize);

            $b->text(ReceiptFormatter::columns(
                [$qtyStr, ' ', $pName, ' ', $totalItem],
                $sizes,
                $aligns,
                $cols
            ), true);

            if (!empty($parent->notes)) {
                $cleanNotes = substr($this->cleanText($parent->notes), 0, max(5, $descSize - 7));
                $b->text(ReceiptFormatter::columns(['', '  (OBS: ' . $cleanNotes . ')'], [$qtySize + 1, 0], ['L', 'L'], $cols), false);
            }

            $rawMods = $parent->modifiers ?? $parent->structured_modifiers ?? $parent->structuredModifiers ?? [];
            if (!empty($rawMods)) {
                foreach ($rawMods as $mod) {
                    $modObj = is_array($mod) ? (object) $mod : $mod;
                    $cleanMod = substr($this->cleanText($modObj->name ?? ''), 0, $descSize);
                    $modQty = (float)($modObj->quantity ?? $modObj->qty ?? 1);
                    $prefix = ($modQty > 1) ? ((int)$modQty . 'X ') : '';
                    $sign = (str_starts_with($cleanMod, 'SEM ') || str_starts_with($cleanMod, 'NAO ')) ? '- ' : '+ ';
                    $modPrice = (float)($modObj->price ?? 0) > 0 ? number_format((float)$modObj->price, 2, ',', '.') : '';

                    $b->text(ReceiptFormatter::columns(
                        ['', ' ', '  ' . $sign . $prefix . $cleanMod, ' ', $modPrice],
                        [$qtySize, 1, 0, 1, $priceSize],
                        ['L', 'L', 'L', 'L', 'R'],
                        $cols
                    ), false);
                }
            }

            $itemChildren = $children->filter(fn($c) => (int)$c->parent_item_id === (int)$parent->id);
            if ($itemChildren->isEmpty() && method_exists($parent, 'relationLoaded') && $parent->relationLoaded('subItems')) {
                $itemChildren = $parent->subItems->filter(fn($c) => ($c->status ?? null) !== 'cancelled');
            }

            foreach ($itemChildren as $child) {
                $cQty = (float) $child->quantity;
                $cQtyStr = substr(OrderTableBuilder::formatQty($cQty, $hasDecimal) . 'x ', 0, $qtySize);
                $cName = '   > ' . $cQtyStr . $this->cleanText($child->product_name);
                $cDesc = substr($cName, 0, $descSize);
                $cTotal = ((float)$child->total_price > 0) ? substr(number_format((float)$child->total_price, 2, ',', '.'), 0, $priceSize) : '';

                $b->text(ReceiptFormatter::columns(
                    ['', ' ', $cDesc, ' ', $cTotal],
                    $sizes,
                    $aligns,
                    $cols
                ), false);

                if (!empty($child->notes)) {
                    $cNotes = substr($this->cleanText($child->notes), 0, max(5, $descSize - 9));
                    $b->text(ReceiptFormatter::columns(['', '    (OBS: ' . $cNotes . ')'], [$qtySize + 1, 0], ['L', 'L'], $cols), false);
                }
            }
        }

        $b->divider();

        // 🎯 3. FECHAMENTO FINANCEIRO JUSTIFICADO
        $hasFeesOrDiscount = (float) $order->delivery_fee > 0
            || (float) $order->service_fee > 0
            || (float) $order->discount > 0;

        if ($hasFeesOrDiscount) {
            if ((float) $order->subtotal > 0) {
                $b->text(ReceiptFormatter::columns(['SUBTOTAL:', 'R$ ' . number_format((float) $order->subtotal, 2, ',', '.')], [24, 0], ['L', 'R'], $cols));
            }
            if ((float) $order->delivery_fee > 0) {
                $b->text(ReceiptFormatter::columns(['TAXA DE ENTREGA:', 'R$ ' . number_format((float) $order->delivery_fee, 2, ',', '.')], [24, 0], ['L', 'R'], $cols));
            }
            if ((float) $order->service_fee > 0) {
                $b->text(ReceiptFormatter::columns(['TAXA DE SERVICO:', 'R$ ' . number_format((float) $order->service_fee, 2, ',', '.')], [24, 0], ['L', 'R'], $cols));
            }
            if ((float) $order->discount > 0) {
                $b->text(ReceiptFormatter::columns(['DESCONTO:', '-R$ ' . number_format((float) $order->discount, 2, ',', '.')], [24, 0], ['L', 'R'], $cols));
            }
            $b->divider();
        }

        $b->text(ReceiptFormatter::columns(
            ['TOTAL PAGO:', 'R$ ' . number_format((float) $order->total, 2, ',', '.')],
            [20, 0],
            ['L', 'R'],
            $cols
        ), true);

        // 🎯 4. FORMAS DE PAGAMENTO
        if ($order->relationLoaded('payments') && $order->payments->isNotEmpty()) {
            $b->space()->text('FORMA DE PAGAMENTO:', true);
            foreach ($order->payments as $p) {
                $methodName = $this->cleanText($p->paymentMethod?->name ?? $p->method ?? 'PAGAMENTO');
                $amount = 'R$ ' . number_format((float) $p->amount, 2, ',', '.');
                $b->text(ReceiptFormatter::columns([' - ' . $methodName, $amount], [24, 0], ['L', 'R'], $cols));
            }
        }

        // 🎯 5. RODAPÉ
        $b->space();
        $b->blueprint[] = ['cmd' => 'align', 'val' => 'ct'];
        $b->divider();

        if (!empty($settings['receipt_footer_text'])) {
            $b->text($this->cleanText($settings['receipt_footer_text']));
        } else {
            $b->text('OBRIGADO PELA PREFERENCIA!');
        }

        if (!empty($settings['receipt_promo_qrcode'])) {
            $b->space()
              ->text('AVALIE OU GANHE DESCONTOS:')
              ->qrCode($settings['receipt_promo_qrcode'], 6);
        }

        return $b->cut()->get();
    }
}
