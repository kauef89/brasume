<?php

namespace App\Services\RestaurantOps;

use App\Models\CompanySetting;

class StoreProfileService
{
    public function getPublicSettings(): array
    {
        $settings = CompanySetting::getCached();

        if (!$settings) {
            return [];
        }

        return [
            'trade_name' => $settings->trade_name ?? $settings->company_name ?? 'Nossa Loja',
            'support_whatsapp' => $settings->support_whatsapp,
            'whatsapp_handle' => $settings->whatsapp_handle,
            'meta_pixel_id' => $settings->meta_pixel_id,
            'google_pixel_id' => $settings->google_pixel_id,
            'logo_svg_path' => $settings->logo_svg_path,
            'logo_path' => $settings->logo_path,
            'birthday_banner_path' => $settings->birthday_banner_path,
            'churn_banner_path' => $settings->churn_banner_path,
            'address' => [
                'street' => $settings->street,
                'number' => $settings->number,
                'neighborhood' => $settings->neighborhood,
                'city' => $settings->city,
                'state' => $settings->state,
            ],
            'business_hours' => $settings->business_hours ?? [],
            'allow_guest' => $settings->customer_allow_guest ?? true,
            'is_24_hours' => (bool) $settings->is_24_hours,
            'is_closed_indefinitely' => (bool) $settings->is_closed_indefinitely,
            'home_bg_open' => $settings->home_bg_open ?? [],
            'home_bg_closed' => $settings->home_bg_closed ?? [],
        ];
    }
}
