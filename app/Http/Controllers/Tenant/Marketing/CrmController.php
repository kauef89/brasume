<?php

namespace App\Http\Controllers\Tenant\Marketing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\Marketing\CrmService;

class CrmController extends Controller
{
    public function __construct(
        protected CrmService $crmService
    ) {}

    public function toggleAutomation(Request $request)
    {
        $request->validate([
            'key' => 'required|string|in:auto_birthday_coupon,auto_churn_coupon',
            'value' => 'required|boolean'
        ]);

        $this->crmService->toggleAutomation($request->key, $request->value);

        return back();
    }

    public function createCoupon(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|unique:coupons,code',
            'type' => 'required|string|in:percent,fixed,free_delivery',
            'value' => 'required_unless:type,free_delivery|numeric|min:0',
            'expires_at' => 'nullable|date'
        ]);

        $this->crmService->createCoupon($data);

        return back();
    }
}
