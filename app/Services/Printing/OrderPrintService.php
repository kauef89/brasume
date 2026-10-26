<?php

namespace App\Services\Printing;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Printer;
use App\Models\Kitchen;
use App\Models\CompanySetting;
use App\Models\FiscalDocument;
use App\Services\Printing\Documents\ConferenceBill;
use App\Services\Printing\Documents\CustomerReceipt;
use App\Services\Printing\Documents\DeliveryDispatch;
use App\Services\Printing\Documents\FiscalReceipt;
use App\Services\Printing\Documents\KitchenTicket;
use App\Services\Printing\Documents\GrowthFlyer;
use App\Services\Printing\Documents\LoyaltyFlyer;
use App\Services\Printing\Documents\TableClaimFlyer;
use App\Services\Marketing\CouponService;
use App\Services\Marketing\OrderClaimService;
use Exception;

class OrderPrintService
{
    public function __construct(
        protected PrintDispatcher $dispatcher,
        protected CouponService $couponService,
        protected OrderClaimService $claimService
    ) {}

    protected function resolveCashierPrinter(): Printer
    {
        $printer = Printer::where('is_cashier', true)->where('is_active', true)->first()
            ?? Printer::where('is_active', true)->first();

        if (!$printer) {
            throw new Exception('Nenhuma impressora ativa configurada para o caixa/balcão.');
        }

        return $printer;
    }

    public function isEligibleChannel(Order $order): bool
    {
        return in_array($order->type, ['delivery', 'counter', 'takeaway', 'pickup']);
    }

    public function isClaimFlyerEligible(Order $order): bool
    {
        return empty($order->customer_id)
            && in_array($order->type, ['table', 'counter', 'takeaway', 'pickup', 'delivery']);
    }

    public function isFromCustomerApp(Order $order): bool
    {
        return $order->source === 'customer_app'
            || (empty($order->employee_id) && !in_array($order->source, ['pos_app', 'team_app', 'pos', 'team']));
    }

    public function isFromOperatorApp(Order $order): bool
    {
        return in_array($order->source, ['pos_app', 'team_app', 'pos', 'team'])
            || !empty($order->employee_id);
    }

    
    
    public function printPixGuide(\App\Models\Order $order, string $qrCodeData, ?string $expiresAt = null): void
    {
        $printer = $this->resolveCashierPrinter();
        $document = new \App\Services\Printing\Documents\PixGuide($order, $qrCodeData, $expiresAt);
        $this->dispatcher->dispatch($printer, $document);
    }

    public function printConferenceBill(Order $order): void
    {
        $printer = $this->resolveCashierPrinter();

        $order->refresh()->loadMissing([
            'items.modifiers',
            'items.subItems.modifiers',
            'customer',
            'payments.paymentMethod'
        ]);

        $settings = CompanySetting::getCached()?->toArray() ?? [];
        $document = new ConferenceBill($order, $settings);

        $this->dispatcher->dispatch($printer, $document);
    }

    public function dispatchCheckoutReceipts(Order $order, string $printAction): void
    {
        $printer = $this->resolveCashierPrinter();

        $order->refresh()->loadMissing([
            'items.modifiers',
            'items.subItems.modifiers',
            'customer',
            'payments.paymentMethod',
            'fiscalDocument'
        ]);

        $settings = CompanySetting::getCached();
        $settingsArray = $settings?->toArray() ?? [];

        // 1. Recibo Não-Fiscal / Interno (cobre 'non_fiscal', 'receipt', 'customer_receipt', 'both', 'all')
        if (in_array($printAction, ['non_fiscal', 'receipt', 'customer_receipt', 'both', 'all'], true)) {
            $document = new CustomerReceipt($order, $settingsArray, $order->customer?->cpf);
            $this->dispatcher->dispatch($printer, $document);
        }

        // 2. Cupom Fiscal NFC-e (cobre 'fiscal', 'nfce', 'fiscal_receipt', 'both', 'all')
        if (in_array($printAction, ['fiscal', 'nfce', 'fiscal_receipt', 'both', 'all'], true)) {
            $this->printFiscalReceipt($order, $printer);
        }

        $isEligibleChannel = $this->isEligibleChannel($order);
        $isLoyaltyEnabled = (bool) ($settings?->loyalty_is_active && $settings?->print_loyalty_flyer);

        // 3. Flyer de Fidelidade / Cashback
        $shouldPrintLoyalty = $isLoyaltyEnabled
            && $isEligibleChannel
            && !empty($order->customer_id)
            && $this->isFromOperatorApp($order)
            && (($settings?->loyalty_flyer_mode === 'auto') || in_array($printAction, ['loyalty', 'all'], true));

        $hasEarnedRewards = (int) ($order->loyalty_points_earned ?? 0) > 0 || (float) ($order->cashback_earned ?? 0) > 0;

        if ($shouldPrintLoyalty && $order->customer && $hasEarnedRewards) {
            $appUrl = route('customer.app', ['tab' => 'wallet']);
            $points = (int) ($order->loyalty_points_earned ?: ($order->cashback_earned * 20));
            $document = new LoyaltyFlyer($order->customer->name, $points, $appUrl);
            $this->dispatcher->dispatch($printer, $document);
        }

        // 4. Flyer de Growth (Indicação de Amigo)
        $isGrowthEnabled = (bool) ($settings?->print_growth_flyer);
        $shouldPrintGrowth = $isGrowthEnabled
            && $isEligibleChannel
            && $this->isFromCustomerApp($order)
            && (($settings?->growth_flyer_mode === 'auto') || in_array($printAction, ['growth', 'all'], true));

        if ($shouldPrintGrowth) {
            $couponValue = (float) ($settings?->growth_flyer_value ?? 10.00);
            $coupon = $this->couponService->generateGrowthCoupon($couponValue);

            $appUrl = route('customer.app', [
                'tab'   => 'menu',
                'cupom' => $coupon->code,
            ]);

            $document = new GrowthFlyer($coupon, $appUrl);
            $this->dispatcher->dispatch($printer, $document);
        }

        // 5. Flyer de Resgate O2O (Mesa sem Cliente Identificado)
        $shouldPrintClaim = (bool) ($settings?->loyalty_is_active)
            && $this->isClaimFlyerEligible($order)
            && (float) $order->total > 0
            && (($settings?->loyalty_flyer_mode === 'auto') || in_array($printAction, ['table_claim', 'all'], true));

        if ($shouldPrintClaim) {
            $this->printTableClaimFlyer($order);
        }
    }

    public function printFiscalReceipt(Order $order, ?Printer $printer = null): void
    {
        $printer = $printer ?? $this->resolveCashierPrinter();

        $fiscalDoc = ($order->relationLoaded('fiscalDocument') && $order->fiscalDocument)
            ? $order->fiscalDocument
            : (FiscalDocument::where('order_id', $order->id)
                ->whereIn('status', ['authorized', 'emitted'])
                ->latest('id')
                ->first()
                ?? FiscalDocument::where('order_id', $order->id)->latest('id')->first());

        $xmlContent = $fiscalDoc?->xml_content
            ?? $fiscalDoc?->xml
            ?? $fiscalDoc?->xml_authorized
            ?? $order->fiscal_xml
            ?? null;

        if (empty($xmlContent)) {
            throw new Exception("NFC-e autorizada não foi encontrada para o pedido #{$order->id}. Emita a nota antes de imprimir.");
        }

        $document = new FiscalReceipt($order, $xmlContent);
        $this->dispatcher->dispatch($printer, $document);
    }

    public function printTableClaimFlyer(Order $order): void
    {
        $printer = $this->resolveCashierPrinter();

        $settings = CompanySetting::getCached();
        if (!$settings || !$settings->loyalty_is_active) {
            return;
        }

        $claimHash = $this->claimService->generateClaimHashForOrder($order);
        $claimUrl = route('customer.order.claim', ['hash' => $claimHash]);
        $document = new TableClaimFlyer($order, $claimUrl);

        $this->dispatcher->dispatch($printer, $document);
    }

    public function printDeliveryDispatch(Order $order, bool $includeLoyalty = false, bool $includeGrowth = false): void
    {
        $printer = $this->resolveCashierPrinter();

        $document = new DeliveryDispatch($order);
        $this->dispatcher->dispatch($printer, $document);

        $settings = CompanySetting::getCached();
        $isEligibleChannel = $this->isEligibleChannel($order);

        $hasEarned = (int) ($order->loyalty_points_earned ?? 0) > 0 || (float) ($order->cashback_earned ?? 0) > 0;
        if ($includeLoyalty && $isEligibleChannel && $this->isFromOperatorApp($order) && $settings?->loyalty_is_active && $settings?->print_loyalty_flyer && $order->customer && $hasEarned) {
            $appUrl = route('customer.app', ['tab' => 'wallet']);
            $points = (int) ($order->loyalty_points_earned ?: 0);
            $document = new LoyaltyFlyer($order->customer->name, $points, $appUrl);
            $this->dispatcher->dispatch($printer, $document);
        }

        if ($includeGrowth && $isEligibleChannel && $this->isFromCustomerApp($order) && $settings?->print_growth_flyer) {
            $couponValue = (float) ($settings?->growth_flyer_value ?? 10.00);
            $coupon = $this->couponService->generateGrowthCoupon($couponValue);

            $appUrl = route('customer.app', [
                'tab'   => 'menu',
                'cupom' => $coupon->code,
            ]);

            $document = new GrowthFlyer($coupon, $appUrl);
            $this->dispatcher->dispatch($printer, $document);
        }
    }

    protected function resolvePrinterForItem(OrderItem $item, ?OrderItem $parentItem = null): ?Printer
    {
        $printer = $item->product?->kitchen?->printer;

        if (!$printer && $item->kitchen_id) {
            $printer = Kitchen::find($item->kitchen_id)?->printer;
        }

        if (!$printer && $parentItem) {
            $printer = $parentItem->product?->kitchen?->printer;
            if (!$printer && $parentItem->kitchen_id) {
                $printer = Kitchen::find($parentItem->kitchen_id)?->printer;
            }
        }

        return ($printer && $printer->is_active) ? $printer : null;
    }

    public function reprintKdsItem(OrderItem $item, string|float|int $qty = 'all'): void
    {
        $item->loadMissing([
            'order.items.modifiers',
            'product.kitchen.printer',
            'modifiers',
            'subItems.modifiers',
            'subItems.product.kitchen.printer',
            'parentItem.product.kitchen.printer'
        ]);

        $originalQty = (float) ($item->quantity ?: 1);
        $targetQty = $originalQty;

        if ($qty === 'single') {
            $targetQty = 1.0;
        } elseif (is_numeric($qty) && (float) $qty > 0 && (float) $qty < $originalQty) {
            $targetQty = (float) $qty;
        }

        $isScaled = $targetQty < $originalQty;
        $ratio = $isScaled ? ($targetQty / $originalQty) : 1.0;

        $printableItem = clone $item;
        $printableItem->setRelations($item->getRelations());
        $printableItem->quantity = $targetQty;

        if ($isScaled && $item->relationLoaded('modifiers')) {
            $scaledModifiers = $item->modifiers->map(function ($mod) use ($ratio) {
                $clonedMod = clone $mod;
                $clonedMod->quantity = max(1, (int) round(($clonedMod->quantity ?: 1) * $ratio));
                return $clonedMod;
            });
            $printableItem->setRelation('modifiers', $scaledModifiers);
        }

        $subItems = $printableItem->subItems;
        if (($subItems === null || $subItems->isEmpty()) && $printableItem->order && $printableItem->order->relationLoaded('items')) {
            $subItems = $printableItem->order->items->where('parent_item_id', $printableItem->id);
        }

        if ($subItems && $subItems->isNotEmpty()) {
            if ($isScaled) {
                $subItems = $subItems->map(function ($sub) use ($ratio) {
                    $clonedSub = clone $sub;
                    $clonedSub->setRelations($sub->getRelations());
                    $clonedSub->quantity = max(1, (int) round(($clonedSub->quantity ?: 1) * $ratio));

                    if ($sub->relationLoaded('modifiers')) {
                        $scaledSubMods = $sub->modifiers->map(function ($m) use ($ratio) {
                            $clonedM = clone $m;
                            $clonedM->quantity = max(1, (int) round(($clonedM->quantity ?: 1) * $ratio));
                            return $clonedM;
                        });
                        $clonedSub->setRelation('modifiers', $scaledSubMods);
                    }

                    return $clonedSub;
                });
            }

            $groupedByPrinter = $subItems->groupBy(function ($sub) use ($printableItem) {
                $p = $this->resolvePrinterForItem($sub, $printableItem);
                return $p ? $p->id : 'none';
            });

            $dispatched = 0;

            foreach ($groupedByPrinter as $printerId => $printerSubItems) {
                if ($printerId === 'none') continue;

                $firstSub = $printerSubItems->first();
                $printer = $this->resolvePrinterForItem($firstSub, $printableItem);

                if (!$printer) continue;

                foreach ($printerSubItems as $subItem) {
                    $subItem->_combo_name = $printableItem->product_name;
                }

                $document = new KitchenTicket(
                    $printableItem->order,
                    $printerSubItems,
                    'item',
                    false,
                    true
                );

                $this->dispatcher->dispatch($printer, $document);
                $dispatched++;
            }

            if ($dispatched === 0) {
                throw new Exception('Nenhuma impressora ativa configurada para as praças deste combo.');
            }

            return;
        }

        $parent = $printableItem->parentItem
            ?? ($printableItem->parent_item_id ? OrderItem::find($printableItem->parent_item_id) : null);

        $printer = $this->resolvePrinterForItem($printableItem, $parent);

        if (!$printer) {
            throw new Exception('Nenhuma impressora configurada para esta praça de produção.');
        }

        if ($parent && empty($printableItem->_combo_name)) {
            $printableItem->_combo_name = $parent->product_name;
        }

        $document = new KitchenTicket(
            $printableItem->order,
            collect([$printableItem]),
            'item',
            false,
            true
        );

        $this->dispatcher->dispatch($printer, $document);
    }
}

class_alias(OrderPrintService::class, 'App\Services\Terminals\PosPrintService');
