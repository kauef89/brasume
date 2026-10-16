export function usePaymentFormatting() {

    // Unifica a lógica do ícone baseada na engine ou gateway do pagamento
    const getPaymentIcon = (method) => {
        if (!method) return 'pi fa-wallet';

        if (method.engine_type === 'bank_api' || method.gateway_driver) {
            if (method.category === 'pix' || String(method.gateway_driver).includes('pix')) return 'pi-qrcode';
            return 'pi-bolt';
        }

        if (method.engine_type === 'money') return 'pi-money-bill';
        return 'pi-credit-card';
    };

    // Unifica o subtítulo descritivo
    const getPaymentSubtitle = (method) => {
        if (!method) return '';

        if (method.engine_type === 'bank_api' || method.gateway_driver) return 'Pagar online agora';
        if (method.engine_type === 'money') return 'Pagamento em espécie';
        return 'A maquininha vai até você';
    };

    return { getPaymentIcon, getPaymentSubtitle };
}
