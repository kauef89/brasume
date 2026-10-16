import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useWhatsAppSupport() {
    const page = usePage();

    const supportPhone = computed(() => {
        const phone = page.props.publicSettings?.support_whatsapp || page.props.companySettings?.support_whatsapp || '';
        return phone.replace(/\D/g, '');
    });

    const whatsappHandle = computed(() => {
        let handle = page.props.publicSettings?.whatsapp_handle || page.props.companySettings?.whatsapp_handle || '';
        return handle.replace('@', '').trim();
    });

    const hasSupport = computed(() => supportPhone.value.length >= 10 || whatsappHandle.value.length > 0);

    const generateLink = (topic, data = {}) => {
        if (!hasSupport.value) return null;

        let message = 'Olá! Preciso de ajuda com o sistema.';

        switch (topic) {
            case 'login_cpf_exists':
                message = `Olá, estou com problemas no cadastro do App. O sistema diz que o meu CPF (${data.cpf || 'não informado'}) já está registrado, mas não consigo acessar. Vocês podem me ajudar?`;
                break;
            // 🚀 Template específico para o modal de Upgrade
            case 'upgrade_cpf_exists':
                message = `Olá! Estou tentando ativar o acompanhamento em tempo real no aplicativo, mas o sistema informou que o meu CPF (${data.cpf || 'não informado'}) já está cadastrado. Podem me ajudar a liberar meu acesso?`;
                break;
            case 'registration_help':
                message = `Olá! Estou tentando realizar meu cadastro no aplicativo, mas estou com uma dúvida/problema. Poderiam me ajudar?`;
                break;
            case 'order_issue':
                message = `Olá, preciso de ajuda com o meu pedido #${data.orderId || ''}.`;
                break;
            case 'address_not_found':
                message = `Olá! Estou tentando cadastrar meu endereço para entrega, mas não estou conseguindo localizar minha rua/CEP no aplicativo. Podem me ajudar?`;
                break;
        }

        const encodedMessage = encodeURIComponent(message);

        // 🚀 PRIORIDADE 1: Se houver telefone numérico, usa o link direto do WhatsApp (mais estável)
        if (supportPhone.value.length >= 10) {
            return `https://wa.me/55${supportPhone.value}?text=${encodedMessage}`;
        }

        // PRIORIDADE 2: Fallback para o @handle se não houver número configurado
        if (whatsappHandle.value) {
            return `https://wa.me/${whatsappHandle.value}?text=${encodedMessage}`;
        }

        return null;
    };

    const openSupport = (topic, data = {}) => {
        const link = generateLink(topic, data);
        if (link) {
            // 🚀 _blank preserva a aplicação aberta em segundo plano
            window.open(link, '_blank');
        } else {
            alert('O número de suporte ainda não foi configurado pelo restaurante.');
        }
    };

    return { hasSupport, generateLink, openSupport };
}
