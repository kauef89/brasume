<?php

namespace App\Providers\Filament;
use App\Filament\Central\Pages\Dashboard;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class CentralPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
        {
            return $panel
                ->id('central') // Identificador único
                ->path('hub')
                ->domain('app.brasume.com.br') // O SEGREDO: Só abre neste domínio!
                ->login()
                ->colors([
                    'primary' => \Filament\Support\Colors\Color::Amber, // Cor diferente para o painel Master!
                ])
                ->discoverResources(in: app_path('Filament/Central/Resources'), for: 'App\\Filament\\Central\\Resources')
                ->discoverPages(in: app_path('Filament/Central/Pages'), for: 'App\\Filament\\Central\\Pages')
                ->discoverWidgets(in: app_path('Filament/Central/Widgets'), for: 'App\\Filament\\Central\\Widgets')
                ->middleware([
                    // NOTE BEM: NÃO INCLUA OS MIDDLEWARES DO STANCL AQUI!
                    // Este painel não é um tenant, ele é o "Deus" do sistema.
                    \Illuminate\Cookie\Middleware\EncryptCookies::class,
                    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
                    \Illuminate\Session\Middleware\StartSession::class,
                    \Illuminate\Session\Middleware\AuthenticateSession::class,
                    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
                    \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
                    \Illuminate\Routing\Middleware\SubstituteBindings::class,
                    \Filament\Http\Middleware\DisableBladeIconComponents::class,
                    \Filament\Http\Middleware\DispatchServingFilamentEvent::class,
                ])
                ->authMiddleware([
                    \Filament\Http\Middleware\Authenticate::class,
                ]);
        }
}
