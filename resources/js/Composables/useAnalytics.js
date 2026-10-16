import { usePage } from '@inertiajs/vue3';

export function useAnalytics() {
    const page = usePage();

    // 🚀 NOVO: CONFIGURAÇÃO PADRÃO DE CONSENTIMENTO V2
    const applyDefaultConsent = () => {
        window.dataLayer = window.dataLayer || [];
        window.gtag = function(){ window.dataLayer.push(arguments); };

        const isGranted = localStorage.getItem('cookie_consent') === 'granted';

        // O Google Tag exige que o consentimento seja definido ANTES de carregar o script
        window.gtag('consent', 'default', {
            'ad_storage': isGranted ? 'granted' : 'denied',
            'ad_user_data': isGranted ? 'granted' : 'denied',
            'ad_personalization': isGranted ? 'granted' : 'denied',
            'analytics_storage': isGranted ? 'granted' : 'denied'
        });

        return isGranted;
    };

    // 1. INJETOR DINÂMICO DOS SCRIPTS
    const initPixels = () => {
        const isGranted = applyDefaultConsent();
        const settings = page.props.publicSettings || page.props.companySettings || page.props.company || {};
        const metaId = settings.meta_pixel_id;
        const googleId = settings.google_pixel_id;

        // Injeta Meta Pixel
        if (metaId && !window.fbq) {
            !function(f,b,e,v,n,t,s)
            {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
            n.callMethod.apply(n,arguments):n.queue.push(arguments)};
            if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
            n.queue=[];t=b.createElement(e);t.async=!0;
            t.src=v;s=b.getElementsByTagName(e)[0];
            s.parentNode.insertBefore(t,s)}(window, document,'script',
            'https://connect.facebook.net/en_US/fbevents.js');

            // Revoga o FB se ainda não aceitou os cookies
            if (!isGranted) window.fbq('consent', 'revoke');

            window.fbq('init', String(metaId).trim());
            window.fbq('track', 'PageView');
        } else if (metaId && typeof window.fbq === 'function') {
            window.fbq('track', 'PageView');
        }

        // Injeta GTAG
        if (googleId && !document.querySelector(`script[src*="${googleId}"]`)) {
            const script = document.createElement('script');
            script.async = true;
            script.src = `https://www.googletagmanager.com/gtag/js?id=${String(googleId).trim()}`;
            document.head.appendChild(script);

            window.gtag('js', new Date());
            window.gtag('config', String(googleId).trim());
        } else if (googleId && typeof window.gtag === 'function') {
            window.gtag('event', 'page_view');
        }
    };

    // 🚀 NOVO: FUNÇÃO PARA QUANDO O USUÁRIO CLICAR EM "ACEITAR"
    const grantConsent = () => {
        localStorage.setItem('cookie_consent', 'granted');

        if (typeof window.gtag === 'function') {
            window.gtag('consent', 'update', {
                'ad_storage': 'granted',
                'ad_user_data': 'granted',
                'ad_personalization': 'granted',
                'analytics_storage': 'granted'
            });
        }
        if (typeof window.fbq === 'function') {
            window.fbq('consent', 'grant');
        }
    };

    // ... (MANTENHA OS MÉTODOS trackViewContent, trackAddToCart, trackInitiateCheckout, trackPurchase INTACTOS COMO ANTES)

    const trackViewContent = (product) => { /* Igual ao código anterior */ };
    const trackAddToCart = (product, totalValue) => { /* Igual ao código anterior */ };
    const trackInitiateCheckout = (cartSubtotal) => { /* Igual ao código anterior */ };
    const trackPurchase = (orderId, totalValue, items = []) => { /* Igual ao código anterior */ };

    return {
        initPixels,
        grantConsent, // <-- Expondo a nova função
        trackViewContent,
        trackAddToCart,
        trackInitiateCheckout,
        trackPurchase
    };
}
