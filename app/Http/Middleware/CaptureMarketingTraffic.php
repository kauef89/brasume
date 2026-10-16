<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CaptureMarketingTraffic
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->is('team*') && !$request->is('pos*') && !$request->is('admin*') && !$request->is('filament*')) {
            $params = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'fbclid', 'gclid'];
            $traffic = session('marketing_traffic', []);
            $hasMarketing = false;

            foreach ($params as $param) {
                if ($request->filled($param)) {
                    $traffic[$param] = substr((string) $request->query($param), 0, 255);
                    $hasMarketing = true;
                }
            }

            // 🚀 Extração do Client ID do Google Analytics (Cookie _ga: GA1.1.XXXXXXXX.YYYYYYYY)
            $rawGa = $request->cookie('_ga');
            if ($rawGa && preg_match('/(?:GA1\.\d+\.)?(\d+\.\d+)/', $rawGa, $matches)) {
                $traffic['ga_client_id'] = $matches[1];
                $hasMarketing = true;
            }

            // Cookie do Facebook para CAPI
            if ($request->hasCookie('_fbp')) {
                $traffic['fbp'] = $request->cookie('_fbp');
                $hasMarketing = true;
            }

            if ($hasMarketing) {
                $traffic['landing_url'] = substr($request->fullUrl(), 0, 500);
                $traffic['referrer_url'] = substr((string) $request->header('referer'), 0, 500);
                session(['marketing_traffic' => $traffic]);
            }
        }

        return $next($request);
    }
}
