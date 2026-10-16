<?php

namespace App\Services\Marketing;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\CompanySetting;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Support\Str;

class CouponService
{
    public function applyCoupon(string $code, float $cartSubtotal, ?int $customerId = null, bool $isPos = false): array
    {
        $coupon = Coupon::where('code', strtoupper(trim($code)))->first();

        if (!$coupon) {
            return ['success' => false, 'message' => 'Cupom inválido ou não encontrado.'];
        }

        $validation = $this->validateRules($coupon, $cartSubtotal, $customerId, $isPos);

        if (!$validation['success']) {
            return $validation;
        }

        return [
            'success'   => true,
            'message'   => 'Cupom aplicado com sucesso!',
            'coupon_id' => $coupon->id,
            'code'      => $coupon->code,
            'type'      => $coupon->type,
            'value'     => (float) $coupon->value,
            'is_pos_only' => (bool) $coupon->is_pos_only,
        ];
    }

    private function validateRules(Coupon $coupon, float $cartSubtotal, ?int $customerId = null, bool $isPos = false): array
    {
        if (!$coupon->is_active) {
            return ['success' => false, 'message' => 'Este cupom está desativado.'];
        }

        // 🚀 BLINDAGEM DE CANAL: Clientes no CustomerApp não podem usar cupons de caixa
        if ($coupon->is_pos_only && !$isPos) {
            return ['success' => false, 'message' => 'Este cupom é de uso exclusivo no caixa do estabelecimento.'];
        }

        if ($coupon->starts_at && now()->lessThan($coupon->starts_at)) {
            return ['success' => false, 'message' => 'Este cupom ainda não é válido.'];
        }

        if ($coupon->expires_at && now()->greaterThan($coupon->expires_at)) {
            return ['success' => false, 'message' => 'Este cupom já expirou.'];
        }

        if ($coupon->global_usage_limit > 0 && $coupon->usage_count >= $coupon->global_usage_limit) {
            return ['success' => false, 'message' => 'Este cupom esgotou.'];
        }

        if ($coupon->min_subtotal > 0 && $cartSubtotal < (float) $coupon->min_subtotal) {
            return ['success' => false, 'message' => 'Valor mínimo para este cupom é R$ ' . number_format($coupon->min_subtotal, 2, ',', '.')];
        }

        if ($coupon->customer_id && $coupon->customer_id !== $customerId) {
            return ['success' => false, 'message' => 'Este cupom é exclusivo para outro cliente.'];
        }

        if ($customerId) {
            if ($coupon->user_usage_limit > 0) {
                $userUsages = $coupon->orders()
                    ->where('customer_id', $customerId)
                    ->whereNotIn('status', ['cancelled', 'voided', 'failed', 'refunded', 'pending_payment'])
                    ->count();

                if ($userUsages >= $coupon->user_usage_limit) {
                    return ['success' => false, 'message' => 'Limite de uso atingido para este cliente.'];
                }
            }

            if ($coupon->first_order_only) {
                $hasValidOrders = Order::where('customer_id', $customerId)
                    ->whereNotIn('status', ['cancelled', 'voided', 'failed', 'refunded', 'pending_payment'])
                    ->exists();

                if ($hasValidOrders) {
                    return ['success' => false, 'message' => 'Cupom exclusivo para primeira compra.'];
                }
            }
        } elseif (!$isPos && ($coupon->first_order_only || $coupon->user_usage_limit > 0)) {
            return ['success' => false, 'message' => 'Faça login para usar este cupom.'];
        }

        return ['success' => true];
    }

    /**
     * Lista todos os cupons disponíveis para o operador de PDV escolher
     */
    public function getAvailableCouponsForPos(float $cartSubtotal = 0, ?int $customerId = null): array
    {
        $coupons = Coupon::query()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->where(function ($q) use ($customerId) {
                $q->whereNull('customer_id');
                if ($customerId) {
                    $q->orWhere('customer_id', $customerId);
                }
            })
            ->orderBy('is_pos_only', 'desc')
            ->orderBy('value', 'desc')
            ->get();

        $result = [];

        foreach ($coupons as $coupon) {
            // Checa limite global
            if ($coupon->global_usage_limit > 0 && $coupon->usage_count >= $coupon->global_usage_limit) {
                continue;
            }

            $meetsMinSubtotal = ($coupon->min_subtotal <= 0 || $cartSubtotal >= (float) $coupon->min_subtotal);

            $result[] = [
                'id'                 => $coupon->id,
                'code'               => $coupon->code,
                'name'               => $coupon->name ?: $coupon->code,
                'label'              => $this->getCouponDiscountLabel($coupon),
                'type'               => $coupon->type,
                'value'              => (float) $coupon->value,
                'min_subtotal'       => (float) $coupon->min_subtotal,
                'meets_subtotal'     => $meetsMinSubtotal,
                'is_pos_only'        => (bool) $coupon->is_pos_only,
                'first_order_only'   => (bool) $coupon->first_order_only,
                'is_customer_bound'  => !is_null($coupon->customer_id),
                'description'        => $coupon->description ?: ($coupon->min_subtotal > 0
                    ? 'Mínimo: R$ ' . number_format($coupon->min_subtotal, 2, ',', '.')
                    : 'Sem valor mínimo'),
            ];
        }

        return $result;
    }

    public function calculateDiscountValue(Coupon $coupon, float $cartSubtotal, float $deliveryFee = 0, float $serviceFee = 0): float
    {
        if ($coupon->type === 'free_delivery') return $deliveryFee;
        if ($coupon->type === 'fixed') return min((float) $coupon->value, $cartSubtotal);
        if ($coupon->type === 'percent') return round($cartSubtotal * ($coupon->value / 100), 2);

        if ($coupon->type === 'fixed_total') {
            $totalBruto = $cartSubtotal + $deliveryFee + $serviceFee;
            $descontoNecessario = $totalBruto - (float) $coupon->value;

            return $descontoNecessario > 0 ? $descontoNecessario : 0.00;
        }

        return 0.00;
    }

    public function getWalletForCustomer(?int $customerId): array
    {
        $wallet = [];

        // 🚀 Oculta cupons de PDV da carteira do cliente final
        $publicCoupons = Coupon::whereNull('customer_id')
            ->where('is_active', true)
            ->where('is_pos_only', false)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->get();

        foreach ($publicCoupons as $coupon) {
            if ($this->isCouponAvailableFor($coupon, $customerId)) {
                $wallet[] = $this->formatCouponForFrontend($coupon);
            }
        }

        if (!$customerId) {
            return $wallet;
        }

        $this->generateAutomatedCouponsIfNeeded($customerId);

        $exclusiveCoupons = Coupon::where('customer_id', $customerId)
            ->where('is_active', true)
            ->where('is_pos_only', false)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->get();

        foreach ($exclusiveCoupons as $coupon) {
            if ($this->isCouponAvailableFor($coupon, $customerId)) {
                $wallet[] = $this->formatCouponForFrontend($coupon, true);
            }
        }

        return $wallet;
    }

    private function generateAutomatedCouponsIfNeeded(int $customerId): void
    {
        $settings = CompanySetting::getCached();
        $customer = Customer::find($customerId);

        if (!$customer || !$settings) return;

        if ($settings->auto_birthday_coupon && $customer->birth_date) {
            $isBirthday = Carbon::parse($customer->birth_date)->isBirthday();

            if ($isBirthday) {
                $receivedRecently = Coupon::where('customer_id', $customerId)
                    ->where('code', 'LIKE', 'NIVER%')
                    ->where('created_at', '>=', now()->subMonths(11))
                    ->exists();

                if (!$receivedRecently) {
                    Coupon::create([
                        'code'             => 'NIVER' . strtoupper(Str::random(4)),
                        'type'             => 'percent',
                        'value'            => 20,
                        'customer_id'      => $customerId,
                        'is_active'        => true,
                        'is_pos_only'      => false,
                        'expires_at'       => now()->endOfDay(),
                        'user_usage_limit' => 1,
                    ]);
                }
            }
        }

        if ($settings->auto_churn_coupon) {
            $lastOrder = Order::where('customer_id', $customerId)->latest('created_at')->first();

            if ($lastOrder && $lastOrder->created_at->diffInDays(now()) >= $settings->churn_days_threshold) {
                $alreadyHasChurnCoupon = Coupon::where('customer_id', $customerId)
                    ->where('code', 'LIKE', 'VOLTA%')
                    ->where('is_active', true)
                    ->where('expires_at', '>', now())
                    ->exists();

                if (!$alreadyHasChurnCoupon) {
                    Coupon::create([
                        'code'             => 'VOLTA' . strtoupper(Str::random(4)),
                        'type'             => 'percent',
                        'value'            => 15,
                        'customer_id'      => $customerId,
                        'is_active'        => true,
                        'is_pos_only'      => false,
                        'expires_at'       => now()->addDays(7),
                        'user_usage_limit' => 1,
                    ]);
                }
            }
        }
    }

    private function isCouponAvailableFor(Coupon $coupon, ?int $customerId): bool
    {
        if ($coupon->global_usage_limit > 0 && $coupon->usage_count >= $coupon->global_usage_limit) {
            return false;
        }

        if ($customerId && $coupon->user_usage_limit > 0) {
            $userUsages = $coupon->orders()
                ->where('customer_id', $customerId)
                ->whereNotIn('status', ['cancelled', 'voided', 'failed', 'refunded'])
                ->count();

            if ($userUsages >= $coupon->user_usage_limit) {
                return false;
            }
        }

        return true;
    }

    private function getCouponDiscountLabel(Coupon $coupon): string
    {
        if ($coupon->type === 'free_delivery') return 'Entrega Grátis';
        if ($coupon->type === 'fixed') return 'R$ ' . number_format($coupon->value, 2, ',', '.') . ' OFF';
        if ($coupon->type === 'percent') return round($coupon->value, 0) . '% OFF';
        if ($coupon->type === 'fixed_total') return 'Total Fixo R$ ' . number_format($coupon->value, 2, ',', '.');

        return 'Desconto';
    }

    private function formatCouponForFrontend(Coupon $coupon, bool $isExclusive = false): array
    {
        $purpose = 'general';
        if (str_starts_with($coupon->code, 'NIVER')) $purpose = 'birthday';
        if (str_starts_with($coupon->code, 'VOLTA')) $purpose = 'churn';

        return [
            'id'           => $coupon->id,
            'code'         => $coupon->code,
            'label'        => $this->getCouponDiscountLabel($coupon),
            'description'  => $coupon->min_subtotal > 0 ? "Em pedidos acima de R$ " . number_format($coupon->min_subtotal, 2, ',', '.') : 'Sem valor mínimo',
            'is_exclusive' => $isExclusive,
            'purpose'      => $purpose,
            'expires_at'   => $coupon->expires_at ? $coupon->expires_at->toIso8601String() : null,
        ];
    }
}
