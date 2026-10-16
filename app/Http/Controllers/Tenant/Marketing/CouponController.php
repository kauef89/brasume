<?php

namespace App\Http\Controllers\Tenant\Marketing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Marketing\CouponService;

class CouponController extends Controller
{
    public function validateCode(Request $request, CouponService $couponService)
    {
        $request->validate([
            'code' => 'required|string',
            'subtotal' => 'required|numeric|min:0',
            'is_pos' => 'nullable|boolean',
            'customer_id' => 'nullable|integer',
        ]);

        $customerId = $request->customer_id ?? auth()->user()?->customer_id ?? session('guest_customer_id');
        $isPos = $request->boolean('is_pos');

        $result = $couponService->applyCoupon(
            $request->code,
            (float) $request->subtotal,
            $customerId ? (int) $customerId : null,
            $isPos
        );

        if (!$result['success']) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json($result);
    }

    public function getWallet(CouponService $couponService)
    {
        $customerId = auth()->user()?->customer_id ?? session('guest_customer_id');

        return response()->json([
            'coupons' => $couponService->getWalletForCustomer($customerId ? (int) $customerId : null)
        ]);
    }

    /**
     * Retorna a lista de cupons para o modal do PDV
     */
    public function getPosAvailableCoupons(Request $request, CouponService $couponService)
    {
        $subtotal = (float) $request->query('subtotal', 0);
        $customerId = $request->query('customer_id') ? (int) $request->query('customer_id') : null;

        return response()->json([
            'coupons' => $couponService->getAvailableCouponsForPos($subtotal, $customerId)
        ]);
    }
}
