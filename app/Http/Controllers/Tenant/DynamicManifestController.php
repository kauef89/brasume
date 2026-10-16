<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynamicManifestController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $settings = CompanySetting::getCached() ?? CompanySetting::first();

        $portal = $request->query('portal', 'customer');
        $isTeam = $portal === 'team';

        $appName = $settings->trade_name ?? $settings->app_name ?? $settings->company_name ?? 'Delivery';
        $shortName = $isTeam ? "{$appName} Equipe" : $appName;
        $startUrl = $isTeam ? '/team' : '/customer/home';

        $themeColor = $settings->theme_color ? $this->getHexCode($settings->theme_color) : '#f59e0b';
        $bgColor = $isTeam ? '#09090b' : '#000000';

        // 🚀 Resolve as imagens através da rota canônica /media/file do tenant
        $icon192 = $this->resolveIconUrl($settings->icon_192_path ?? $settings->icon_path ?? null, '192');
        $icon512 = $this->resolveIconUrl($settings->icon_path ?? $settings->icon_192_path ?? null, '512');

        $manifest = [
            "name"             => $appName,
            "short_name"       => $shortName,
            "start_url"        => $startUrl,
            "scope"            => "/",
            "display"          => "standalone",
            "background_color" => $bgColor,
            "theme_color"      => $themeColor,
            "icons"            => [
                [
                    "src"     => $icon192,
                    "sizes"   => "192x192",
                    "type"    => "image/png",
                    "purpose" => "any maskable"
                ],
                [
                    "src"     => $icon512,
                    "sizes"   => "512x512",
                    "type"    => "image/png",
                    "purpose" => "any maskable"
                ]
            ]
        ];

        return response()->json($manifest)
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }

    private function resolveIconUrl(?string $path, string $size): string
    {
        if (!empty($path)) {
            if (filter_var($path, FILTER_VALIDATE_URL)) {
                return $path;
            }

            // 🚀 Rota canônica de streaming do tenant
            return url('/media/file?path=' . ltrim($path, '/'));
        }

        // Fallback para ícone estático caso o tenant não tenha configurado
        $defaultPath = "assets/icons/icon-{$size}.png";
        if (file_exists(public_path($defaultPath))) {
            return asset($defaultPath);
        }

        return asset('favicon.ico');
    }

    private function getHexCode(string $color): string
    {
        $colors = [
            'amber'   => '#f59e0b',
            'red'     => '#ef4444',
            'emerald' => '#10b981',
            'blue'    => '#3b82f6',
            'violet'  => '#8b5cf6',
            'rose'    => '#f43f5e',
        ];

        return $colors[$color] ?? '#f59e0b';
    }
}
