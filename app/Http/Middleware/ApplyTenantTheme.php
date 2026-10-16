<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\CompanySetting;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;


class ApplyTenantTheme
{
    public function handle(Request $request, Closure $next): Response
    {
        $themeColor = 'amber';

        // 🚀 AQUI O TENANT JÁ FOI INICIALIZADO PELO PACOTE STANCL!
        // Portanto, ele vai consultar a tabela do banco de dados do cliente correto.
        $setting = CompanySetting::getCached();

        if ($setting && $setting->theme_color) {
            $themeColor = $setting->theme_color;
        }

        $filamentColors = [
            'amber'   => Color::Amber,
            'orange'  => Color::Orange,
            'rose'    => Color::Rose,
            'pink'    => Color::Pink,
            'purple'  => Color::Purple,
            'violet'  => Color::Violet,
            'indigo'  => Color::Indigo,
            'blue'    => Color::Blue,
            'sky'     => Color::Sky,
            'cyan'    => Color::Cyan,
            'teal'    => Color::Teal,
            'emerald' => Color::Emerald,
            'slate'   => Color::Slate,
            'fuchsia' => Color::Fuchsia,
            'lime'    => Color::Lime,
        ];

        // 🎨 Força o Filament a trocar de cor em tempo real
        FilamentColor::register([
            'primary' => $filamentColors[$themeColor] ?? Color::Amber,
        ]);

        return $next($request);
    }
}
