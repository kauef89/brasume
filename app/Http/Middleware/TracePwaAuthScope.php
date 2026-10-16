<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TracePwaAuthScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $uri = $request->getRequestUri();

        $shouldLog = str_contains($uri, 'auth')
            || str_contains($uri, 'login')
            || str_contains($uri, 'gateway')
            || str_contains($uri, 'pos')
            || str_contains($uri, 'manifest');

        if ($shouldLog) {
            Log::channel('single')->info("📡 [PWA Probe - INBOUND]", [
                'full_url'         => $request->fullUrl(),
                'scheme'           => $request->getScheme(),
                'x_fwd_proto'      => $request->header('x-forwarded-proto'),
                'host'             => $request->getHost(),
                'method'           => $request->method(),
                'referer'          => $request->header('referer'),
                'sec_ch_ua_mobile' => $request->header('sec-ch-ua-mobile'),
            ]);
        }

        $response = $next($request);

        $inertiaRedirect = $response->headers->get('X-Inertia-Location');
        $isHttpRedirect = $response->isRedirection();

        if ($shouldLog && ($isHttpRedirect || $inertiaRedirect)) {
            $targetUrl = $inertiaRedirect ?: $response->headers->get('Location');
            $parsedTarget = parse_url($targetUrl);

            Log::channel('single')->warning("🚨 [PWA Probe - REDIRECT DETECTADO]", [
                'current_url'     => $request->fullUrl(),
                'redirect_target' => $targetUrl,
                'target_scheme'   => $parsedTarget['scheme'] ?? 'relative',
                'target_host'     => $parsedTarget['host'] ?? $request->getHost(),
                'status_code'     => $response->getStatusCode(),
                'type'            => $inertiaRedirect ? 'Inertia-Location' : 'HTTP-Redirect',
                'scheme_mismatch' => isset($parsedTarget['scheme']) && $parsedTarget['scheme'] !== $request->getScheme(),
                'host_mismatch'   => isset($parsedTarget['host']) && $parsedTarget['host'] !== $request->getHost(),
            ]);
        }

        return $response;
    }
}
