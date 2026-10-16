@php
    $settings = null;

    // 🚀 PROTEÇÃO ANTI-500: Só busca as configurações se houver um Inquilino carregado na conexão
    if (tenant()) {
        $settings = \App\Models\CompanySetting::getCached();
    }

    $themeColor = $settings?->theme_color ?? 'amber';

    // 🚀 BLINDAGEM: Identifica se é portal da equipe/operação para não carregar pixels de marketing
    $isTeamPortal = request()->is('team*', 'pos*', 'kds*', 'operations*', 'pdv', 'caixa', 'garcom', 'cozinha');
    $manifestUrl = $isTeamPortal ? '/manifest.json?portal=team' : '/manifest.json?portal=customer';
@endphp
<!DOCTYPE html>
<!-- 🚀 FIXADO NA CLASSE DARK -->
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark" data-theme="{{ $themeColor }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover" />

        <title inertia>{{ $settings?->app_name ?? config('app.name', 'Brasume') }}</title>

        <!-- 🚀 MANIFEST DINÂMICO INJETADO AQUI -->
        <link rel="manifest" href="{{ $manifestUrl }}">

        <!-- 🚀 TEMA FIXADO NO ESCURO (Barra de status do celular) -->
        <meta name="theme-color" content="#0c0a09">

        <!-- Padrão moderno Universal -->
        <meta name="mobile-web-app-capable" content="yes">

        <!-- Padrões da Apple -->
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="{{ $settings?->app_name ?? 'Brasume' }}">
        <link rel="apple-touch-icon" href="{{ $settings?->icon_path ? '/media/file?path='.$settings->icon_path : '/icon-192x192.png' }}">

        <!-- 🚀 SCRIPT ANTI-FLICKER (Travado no Escuro) -->
        <script>
            (function() {
                try {
                    // 1. Força o sistema no Escuro, sobreescrevendo preferências em cache
                    document.documentElement.classList.add('dark');
                    localStorage.setItem('pos_color_scheme', 'dark');
                    localStorage.setItem('theme', 'dark');

                    // 2. Garantia de Cache Local do PWA (Evita salto de cor principal)
                    var localColor = localStorage.getItem('pos_primary_color');
                    if (localColor && localColor !== '{{ $themeColor }}') {
                        document.documentElement.setAttribute('data-theme', localColor);
                    }
                } catch (e) {}
            })();
        </script>

        <!-- 🚀 DIRETIVA DO ZIGGY -->
        @routes

        <!-- SCRIPTS DE MARKETING E CONSENTIMENTO (Apenas cardápio/cliente) -->
        @if(!$isTeamPortal && $settings)

            {{-- 1. GOOGLE CONSENT MODE V2 (Sempre síncrono e antes de qualquer Tag) --}}
            @if(!empty($settings->google_pixel_id))
                <script>
                    window.dataLayer = window.dataLayer || [];
                    function gtag(){dataLayer.push(arguments);}

                    // Resgata se o usuário já consentiu anteriormente
                    var hasPriorConsent = localStorage.getItem('cookie_consent_granted') === 'true';
                    var initialStatus = hasPriorConsent ? 'granted' : 'denied';

                    // 🛡️ Define o estado padrão obrigatório antes de qualquer download externo
                    gtag('consent', 'default', {
                        'ad_storage': initialStatus,
                        'ad_user_data': initialStatus,
                        'ad_personalization': initialStatus,
                        'analytics_storage': initialStatus,
                        'wait_for_update': 500
                    });
                </script>

                {{-- Tag externa baixada SOMENTE após o consentimento padrão ser registrado --}}
                <script async src="https://www.googletagmanager.com/gtag/js?id={{ trim($settings->google_pixel_id) }}"></script>
                <script>
                    gtag('js', new Date());
                    gtag('config', '{{ trim($settings->google_pixel_id) }}');
                </script>
            @endif

            {{-- 2. META PIXEL --}}
            @if(!empty($settings->meta_pixel_id))
                <script>
                    !function(f,b,e,v,n,t,s)
                    {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
                    n.callMethod.apply(n,arguments):n.queue.push(arguments)};
                    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
                    n.queue=[];t=b.createElement(e);t.async=!0;
                    t.src=v;s=b.getElementsByTagName(e)[0];
                    s.parentNode.insertBefore(t,s)}(window, document,'script',
                    'https://connect.facebook.net/en_US/fbevents.js');
                    fbq('init', '{{ trim($settings->meta_pixel_id) }}');
                    fbq('track', 'PageView');
                </script>
                <noscript>
                    <img height="1" width="1" style="display:none"
                        src="https://www.facebook.com/tr?id={{ trim($settings->meta_pixel_id) }}&ev=PageView&noscript=1"/>
                </noscript>
            @endif

        @endif

        @vite(['resources/js/app.js', 'resources/css/app.css'])

        @inertiaHead

    </head>
    <!-- 🚀 CORES ESCURAS NATIVAS NO BODY (Evita tela branca durante a renderização do Vue) -->
    <body class="font-sans antialiased bg-stone-950 text-stone-100 selection:bg-primary-500 selection:text-white">
        @inertia
    </body>
</html>
