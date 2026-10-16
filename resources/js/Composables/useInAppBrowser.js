import { onMounted, ref } from 'vue';

export function useInAppBrowser() {
    const isInstagram = ref(false);
    const isInAppBrowser = ref(false);
    const os = ref('other'); // 'ios' | 'android' | 'other'

    onMounted(() => {
        if (typeof window === 'undefined') return;

        const ua = navigator.userAgent || navigator.vendor || window.opera || '';

        // 1. Identificação do Instagram e outras Webviews sociais
        isInstagram.value = /Instagram/i.test(ua);
        isInAppBrowser.value = isInstagram.value || /FBAN|FBAV|ByteLocale|TikTok/i.test(ua);

        // 2. Identificação do Sistema Operacional
        if (/iPhone|iPad|iPod/i.test(ua)) {
            os.value = 'ios';
        } else if (/Android/i.test(ua)) {
            os.value = 'android';
        }
    });

    // 🚀 Dica para Android: Intent URI para tentar forçar abertura no Chrome nativo
    const getAndroidIntentUrl = () => {
        if (typeof window === 'undefined') return '#';
        const cleanUrl = window.location.href.replace(/^https?:\/\//, '');
        return `intent://${cleanUrl}#Intent;scheme=https;package=com.android.chrome;end`;
    };

    return {
        isInstagram,
        isInAppBrowser,
        os,
        getAndroidIntentUrl
    };
}
