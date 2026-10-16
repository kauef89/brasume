<?php

namespace App\Services\Marketing;

use App\Models\Order;
use App\Models\CompanySetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ServerSideTrackingService
{
    public function trackPurchase(Order $order): void
    {
        $settings = CompanySetting::getCached() ?? CompanySetting::first();
        if (!$settings) return;

        // 1. DISPARO GOOGLE ANALYTICS 4 (MEASUREMENT PROTOCOL)
        if (!empty($settings->google_pixel_id) && !empty($settings->google_api_secret)) {
            $this->dispatchGooglePurchase($order, $settings);
        }

        // 2. DISPARO META (CONVERSIONS API / CAPI)
        if (!empty($settings->meta_pixel_id) && !empty($settings->meta_capi_token)) {
            $this->dispatchMetaPurchase($order, $settings);
        }
    }

    protected function dispatchGooglePurchase(Order $order, CompanySetting $settings): void
    {
        try {
            $order->loadMissing('items.product');

            $items = $order->items->map(function ($item) {
                return [
                    'item_id'   => (string) ($item->product_id ?? $item->id),
                    'item_name' => $item->product_name ?? 'Produto',
                    'price'     => (float) $item->unit_price,
                    'quantity'  => (int) $item->quantity,
                ];
            })->values()->all();

            // Client ID de fallback se o cookie _ga não foi lido a tempo
            $clientId = $order->ga_client_id ?: sprintf('%08x.%010d', crc32($order->ip_address ?? '127.0.0.1'), time());

            $payload = [
                'client_id' => $clientId,
                'events'    => [
                    [
                        'name'   => 'purchase',
                        'params' => [
                            'transaction_id' => (string) $order->id,
                            'value'          => (float) $order->total,
                            'currency'       => 'BRL',
                            'items'          => $items,
                        ],
                    ],
                ],
            ];

            $url = 'https://www.google-analytics.com/mp/collect?' . http_build_query([
                'measurement_id' => trim($settings->google_pixel_id),
                'api_secret'     => trim($settings->google_api_secret),
            ]);

            // Disparo assíncrono/rápido para não onerar o checkout
            Http::timeout(3)->post($url, $payload);
            Log::info("🎯 [Marketing Server-Side] Evento Purchase GA4 enviado para o Pedido #{$order->id}");

        } catch (\Throwable $e) {
            Log::warning("⚠️ [Marketing Server-Side] Falha ao enviar Purchase GA4: " . $e->getMessage());
        }
    }

    protected function dispatchMetaPurchase(Order $order, CompanySetting $settings): void
    {
        try {
            $url = "https://graph.facebook.com/v19.0/{$settings->meta_pixel_id}/events";

            $payload = [
                'data' => [
                    [
                        'event_name'       => 'Purchase',
                        'event_time'       => time(),
                        'event_id'         => "order_{$order->id}",
                        'action_source'    => 'website',
                        'user_data'        => [
                            'client_ip_address' => $order->ip_address,
                            'client_user_agent' => $order->user_agent,
                            'fbp'               => $order->fbp,
                            'fbc'               => $order->fbclid ? "fb.1." . time() . ".{$order->fbclid}" : null,
                            'ph'                => $order->customer_phone ? hash('sha256', preg_replace('/\D/', '', $order->customer_phone)) : null,
                        ],
                        'custom_data'      => [
                            'currency' => 'BRL',
                            'value'    => (float) $order->total,
                            'order_id' => (string) $order->id,
                        ],
                    ],
                ],
                'access_token' => $settings->meta_capi_token,
            ];

            Http::timeout(3)->post($url, $payload);
            Log::info("🎯 [Marketing Server-Side] Evento Purchase Meta CAPI enviado para o Pedido #{$order->id}");

        } catch (\Throwable $e) {
            Log::warning("⚠️ [Marketing Server-Side] Falha ao enviar Purchase Meta CAPI: " . $e->getMessage());
        }
    }
}
