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
            const addr = (typeof metadata.address === 'object' && metadata.address !== null)
                ? metadata.address
                : (metadata.address_snapshot || metadata.address);

            if (addr) {
                if (typeof addr === 'string') {
                    summary += `📍 *Endereço:* ${addr}\n`;
                } else {
                    const streetName = addr.street?.name || addr.street_name || addr.street || '';
                    const numberVal = addr.no_number ? 'S/N' : (addr.number || 'S/N');
                    const neighborhoodName = addr.neighborhood?.name || addr.neighborhood_name || addr.neighborhood || '';
                    const fallbackLine = streetName
                        ? `${streetName}, ${numberVal}${neighborhoodName ? ' - ' + neighborhoodName : ''}`
                        : '';
                    const streetLine = addr.full_address || fallbackLine;

                    if (streetLine) {
                        summary += `📍 *Endereço:* ${streetLine}\n`;
                    }
                    if (addr.complement) summary += `   ↳ Comp: ${addr.complement}\n`;
                    if (addr.reference) summary += `   ↳ Ref: ${addr.reference}\n`;
                }
            }
        }

        summary += `--------------------------\n`;

        cart.forEach((item) => {
            const isCortesia = item.is_reward ? ' [CORTESIA]' : '';
            const cleanItemName = String(item.name || '').replace(/\s*[\(\[](Viagem|Para Levar|Takeaway)[\)\]]/gi, '').trim();
            summary += `*${item.qty} × ${cleanItemName}${isCortesia}* - ${formatCurrency(item.price * item.qty)}\n`;

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
                    const cleanChildName = String(c.name || '').replace(/\s*[\(\[](Viagem|Para Levar|Takeaway)[\)\]]/gi, '').trim();
                    summary += `   ↳ 1 × ${cleanChildName}`;

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

    const buildWhatsAppOrderSummary = (order) => {
        if (!order) return '';

        const rawItems = (order.items || []).filter(i => i.status !== 'cancelled');
        const rootItems = rawItems.filter(i => !i.parent_item_id);
        const childItems = rawItems.filter(i => Boolean(i.parent_item_id));

        const mappedCart = rootItems.map(item => {
            const qty = Math.max(1, Number(item.quantity || 1));
            const unitPrice = Number(item.unit_price ?? (Number(item.total_price || 0) / qty));

            const dbChildren = childItems.filter(c => String(c.parent_item_id) === String(item.id));
            const nestedChildren = item.sub_items || item.subItems || [];
            const allChildren = dbChildren.length > 0 ? dbChildren : nestedChildren;

            const comboItems = allChildren
                .filter(c => c.status !== 'cancelled')
                .map(c => ({
                    name: c.product_name || c.name,
                    price: Number(c.unit_price || c.additional_price || 0),
                    modifiers: c.modifiers || c.structuredModifiers || [],
                    notes: c.notes || ''
                }));

            return {
                qty,
                name: item.product_name || item.name,
                price: unitPrice,
                modifiers: item.modifiers || item.structuredModifiers || [],
                notes: item.notes || '',
                comboItems
            };
        });

        const metadata = {
            type: order.type,
            table: order.dining_table || order.diningTable || (order.table_number ? { name: order.table_number } : null),
            table_number: order.table_number,
            customer: order.customer || (order.customer_name ? { name: order.customer_name } : null),
            identifier: order.identifier || order.customer_name,
            address: order.address_snapshot || order.address || order.delivery_address
        };

        const calculatedSubtotal = mappedCart.reduce((acc, i) => acc + (i.price * i.qty), 0);
        const subtotal = Number(order.subtotal || calculatedSubtotal);
        const deliveryFee = Number(order.delivery_fee || 0);
        const serviceFee = Number(order.service_fee || 0);
        const discount = Number(order.discount || 0);
        const finalTotal = Number(order.total ?? Math.max(0, subtotal + deliveryFee + serviceFee - discount));

        let text = buildWhatsAppSummary(mappedCart, metadata, {
            subtotal,
            deliveryFee,
            serviceFee,
            discount,
            finalTotal
        });

        if (order.id) {
            text = text.replace('*RESUMO DO PEDIDO*', `*RESUMO DO PEDIDO #${order.id}*`);
        }

        const alreadyPaid = (order.payments || [])
            .filter(p => p.status !== 'cancelled')
            .reduce((sum, p) => sum + Number(p.amount || 0), 0);

        if (alreadyPaid > 0) {
            const balanceDue = Math.max(0, Number((finalTotal - alreadyPaid).toFixed(2)));
            text += `*Já Pago:* ${formatCurrency(alreadyPaid)}\n`;
            if (balanceDue > 0.01) {
                text += `*Saldo Restante:* ${formatCurrency(balanceDue)}\n`;
            } else {
                text += `*Status:* PAGO ✅\n`;
            }
        }

        return text;
    };

    return { buildWhatsAppSummary, buildWhatsAppOrderSummary };
}
