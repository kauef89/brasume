import { useOrderFormatting } from "@/Pages/Shared/Composables/useOrderFormatting";
import { formatCurrency, formatNumber } from "@/Utils/formatters";
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useWhatsAppMessages() {
    const page = usePage();
    const { abbreviateName, formatAddress } = useOrderFormatting();

    const supportPhone = computed(() => {
        const phone = page.props.companySettings?.support_whatsapp || '';
        return phone.replace(/\D/g, '');
    });
    const hasSupport = computed(() => supportPhone.value.length >= 10);

    // 👤 Extrai apenas o primeiro nome para comunicação direta e acolhedora com o cliente
    const getFirstName = (name) => {
        if (!name || typeof name !== 'string') return 'Cliente';
        const first = name.trim().split(/\s+/)[0];
        return first || 'Cliente';
    };

    // 🌐 Resolve a URL base priorizando o domínio próprio configurado
    const getTenantBaseUrl = () => {
        const customDomain = page.props.companySettings?.custom_domain;
        if (customDomain) {
            const clean = customDomain.replace(/^https?:\/\//i, '').replace(/\/+$/, '');
            return `https://${clean}`;
        }
        return typeof window !== 'undefined' ? window.location.origin : '';
    };

    // ⭐ Resolução da URL amigável de Avaliação do Google do Tenant
    const reviewInviteText = computed(() => {
        const googleLink = page.props.companySettings?.google_review_link;
        if (!googleLink) return '';

        const friendlyUrl = `${getTenantBaseUrl()}/avaliacao`;

        return `\n\n⭐ *Sua opinião vale muito para nós!*\nConte como foi sua experiência: ${friendlyUrl}`;
    });

    const sendWhatsApp = (phone, text) => {
        if (!phone) return false;
        const cleanPhone = phone.replace(/\D/g, '');
        const encodedText = encodeURIComponent(text);
        window.open(`whatsapp://send?phone=55${cleanPhone}&text=${encodedText}`, '_self');
        return true;
    };

    const openSupport = (topic, data = {}) => {
        if (!hasSupport.value) return alert('O número de suporte ainda não foi configurado pelo restaurante.');

        let message = 'Olá! Preciso de ajuda com o sistema.';
        switch (topic) {
            case 'login_cpf_exists': message = `Olá, estou com problemas no cadastro. O sistema diz que o meu CPF (${data.cpf || 'não informado'}) já está registrado. Vocês podem me ajudar?`; break;
            case 'registration_help': message = `Olá! Estou tentando realizar meu cadastro no aplicativo, mas estou com uma dúvida. Poderiam me ajudar?`; break;
            case 'order_issue': message = `Olá, preciso de ajuda com o meu pedido #${formatNumber(data.orderId)}.`; break;
        }
        sendWhatsApp(supportPhone.value, message);
    };

    // 🛵 Mantém a abreviação LGPD para o entregador/motoboy
    const dispatchCourier = (order, courierPhone) => {
        const name = order.customer_id ? abbreviateName(order.customer_name) : order.customer_name;
        const phone = order.customer_phone || order.customer?.phone || 'Não informado';
        const address = formatAddress(order);

        let payments = 'Não informado';
        if (order.payments && order.payments.length > 0) {
            payments = order.payments.map(p => `${p.method || 'Pago'} (${formatCurrency(p.amount)})`).join(', ');
        } else if (order.payment_intent_methods) {
            const methodsArray = typeof order.payment_intent_methods === 'string'
                ? JSON.parse(order.payment_intent_methods)
                : order.payment_intent_methods;

            payments = `Cobrar na entrega: ${methodsArray.map(m => m.method_name || m.method_id).join(', ')}`;
        }

        const msg = `🛵 *Nova Entrega!*\n\n*Pedido:* #${formatNumber(order.id)}\n*Cliente:* ${name}\n*Contato:* ${phone}\n*Endereço:* ${address}\n*Pagamentos:* ${payments}`;
        sendWhatsApp(courierPhone, msg);
    };

    // 💬 Mensagem direta ao cliente: usa o primeiro nome
    const notifyDeliveryOut = (order) => {
        const name = getFirstName(order.customer_name || order.customer?.name);
        const baseMsg = `${name}, o seu pedido #${formatNumber(order.id)} saiu para entrega e logo chegará até você! 🛵`;
        const fullMsg = `${baseMsg}${reviewInviteText.value}`;
        sendWhatsApp(order.customer?.phone || order.customer_phone, fullMsg);
    };

    // 💬 Mensagem direta ao cliente: usa o primeiro nome
    const notifyPickupReady = (order) => {
        const name = getFirstName(order.customer_name || order.customer?.name);
        const baseMsg = `${name}, o seu pedido #${formatNumber(order.id)} já está pronto e embalado! Pode vir retirar no balcão. 🍔`;
        const fullMsg = `${baseMsg}${reviewInviteText.value}`;
        sendWhatsApp(order.customer?.phone || order.customer_phone, fullMsg);
    };
    // 💰 Envia a cobrança e o código PIX Copia e Cola para o cliente
    const sendPixBilling = (order, amount, pixCode) => {
        const name = getFirstName(order.customer_name || order.customer?.name);
        const phone = order.customer_phone || order.customer?.phone;
        
        if (!phone) {
            alert('Não há número de WhatsApp vinculado a este pedido.');
            return false;
        }

        const formattedAmount = Number(amount || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        
        const msg = `Olá, ${name}!\n\nSegue o código PIX Copia e Cola para o pagamento do seu pedido #${formatNumber(order.id)} no valor de *${formattedAmount}*.\n\n*Cole o código abaixo no app do seu banco:*\n\n${pixCode}`;
        
        return sendWhatsApp(phone, msg);
    };

    return {
        hasSupport, abbreviateName, formatAddress, getFirstName, getTenantBaseUrl,
        sendPixBilling,
        sendWhatsApp, openSupport, dispatchCourier, notifyDeliveryOut, notifyPickupReady
    };
}
