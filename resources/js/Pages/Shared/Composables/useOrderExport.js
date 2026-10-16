import { useOrderFormatting } from "@/Pages/Shared/Composables/useOrderFormatting";
import { formatCurrency } from "@/Utils/formatters";

export function useOrderExport() {
    const { formatModifiersList, abbreviateName } = useOrderFormatting();

    const buildWhatsAppSummary = (cart, metadata, totals) => {
        let summary = `*RESUMO DO PEDIDO*\n`;

        if (metadata?.type === 'counter' || metadata?.type === 'pickup' || metadata?.type === 'takeaway') {
            summary += `🛍️ *Retirada*\n`;
        } else if (metadata?.type === 'table') {
            const tableLabel = metadata?.table?.name || metadata?.dining_table?.name || metadata?.table_number || '';
            summary += `🍽️ *Mesa ${tableLabel}*\n`;
        }

        if (metadata?.customer?.name) {
            summary += `\n👤 *Cliente:* ${abbreviateName(metadata.customer.name)}\n`;
        } else if (metadata?.identifier) {
            summary += `\n👤 *Cliente:* ${abbreviateName(metadata.identifier)}\n`;
        }

        if (metadata?.type === 'delivery') {
            const addr = metadata.address || metadata.address_snapshot;
            if (addr) {
                const streetLine = addr.full_address || `${addr.street?.name || addr.street || ''}, ${addr.number || 'S/N'} - ${addr.neighborhood?.name || addr.neighborhood || ''}`;
                summary += `📍 *Endereço:* ${streetLine}\n`;

                if (addr.complement) summary += `   ↳ Comp: ${addr.complement}\n`;
                if (addr.reference) summary += `   ↳ Ref: ${addr.reference}\n`;
            }
        }

        summary += `--------------------------\n`;

        cart.forEach((item) => {
            const isCortesia = item.is_reward ? ' [CORTESIA]' : '';
            summary += `*${item.qty} × ${item.name}${isCortesia}* - ${formatCurrency(item.price * item.qty)}\n`;

            const modsList = formatModifiersList(item);
            if (modsList && modsList.length > 0) {
                modsList.forEach(modLine => {
                    summary += `   ↳ ${modLine}\n`;
                });
            }

            if (item.modifiersText && (!modsList || modsList.length === 0)) {
                summary += `   ↳ Obs: ${item.modifiersText}\n`;
            } else if (item.notes) {
                summary += `   ↳ Obs: ${item.notes}\n`;
            }

            if (item.comboItems && item.comboItems.length > 0) {
                item.comboItems.forEach((c) => {
                    summary += `   ↳ 1 × ${c.name}`;

                    if (c.price > 0 || c.additional_price > 0) {
                        const extraPrice = c.price || c.additional_price;
                        summary += ` (+ ${formatCurrency(extraPrice)})`;
                    }
                    summary += `\n`;

                    const comboModsList = formatModifiersList(c);
                    if (comboModsList && comboModsList.length > 0) {
                        comboModsList.forEach(modLine => {
                            summary += `      ↳ ${modLine}\n`;
                        });
                    }

                    if (c.modifiersText && (!comboModsList || comboModsList.length === 0)) {
                        summary += `      ↳ Obs: ${c.modifiersText}\n`;
                    } else if (c.notes) {
                        summary += `      ↳ Obs: ${c.notes}\n`;
                    }
                });
            }
            summary += `\n`;
        });

        summary += `--------------------------\n`;

        summary += `*Subtotal:* ${formatCurrency(totals.subtotal)}\n`;

        if (totals.deliveryFee > 0 && metadata?.type === 'delivery') {
            summary += `*Taxa de Entrega:* ${formatCurrency(totals.deliveryFee)}\n`;
        }
        if (totals.serviceFee > 0) {
            summary += `*Taxa de Serviço:* ${formatCurrency(totals.serviceFee)}\n`;
        }
        if (totals.discount > 0) {
            summary += `*Desconto:* -${formatCurrency(totals.discount)}\n`;
        }

        summary += `*TOTAL:* ${formatCurrency(totals.finalTotal)}\n`;

        return summary;
    };

    return { buildWhatsAppSummary };
}
