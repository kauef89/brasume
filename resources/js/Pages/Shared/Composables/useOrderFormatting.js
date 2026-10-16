import { abbreviateName } from "@/Utils/formatters";

export function useOrderFormatting() {

    const getOrderTitle = (orderOrMeta) => {
        if (!orderOrMeta) return "";
        const type = orderOrMeta.type;
        const tableName = String(
            orderOrMeta.dining_table?.name ||
            orderOrMeta.diningTable?.name ||
            orderOrMeta.table_number ||
            orderOrMeta.table?.name ||
            orderOrMeta.meta?.table?.name ||
            "Avulsa"
        );

        const customerName = orderOrMeta.customer_name || orderOrMeta.identifier || orderOrMeta.meta?.identifier || orderOrMeta.customer?.name || "";

        // 🔒 LGPD: Abrevia o nome universalmente antes de montar a string de retorno
        const safeCustomerName = customerName ? abbreviateName(customerName) : "";

        if (type === 'table') {
            const isGenericName = !customerName ||
                                  customerName.toLowerCase() === tableName.toLowerCase() ||
                                  customerName.toLowerCase() === `mesa ${tableName.toLowerCase()}` ||
                                  customerName.toLowerCase() === 'geral';

            return isGenericName ? `Mesa ${tableName}` : `Mesa ${tableName} (${safeCustomerName})`;
        }

        if (type === 'counter') {
            return safeCustomerName || "Balcão";
        }

        return safeCustomerName || "Delivery";
    };

    const formatModifiersList = (targetItem) => {
        if (!targetItem) return [];

        const raw = targetItem.structured_modifiers || targetItem.modifiers || targetItem.structuredModifiers;
        if (!Array.isArray(raw) || raw.length === 0) {
            if (targetItem.modifiers_text || targetItem.modifiersText) {
                return [targetItem.modifiers_text || targetItem.modifiersText];
            }
            return [];
        }

        // Pega a quantidade do item pai (pela prop ou pelo banco) para isolar
        const parentQty = Number(targetItem.quantity || targetItem.qty || targetItem.available_parts || 1);

        const groups = {};
        raw.forEach(m => {
            if (m.name === '\u200B') return;

            const totalModQty = Number(m.quantity ?? m.qty ?? 1);

            let unitQty = totalModQty;
            // Isola a quantidade do modificador por UNIDADE do prato
            if (parentQty > 1 && totalModQty >= parentQty && (totalModQty % parentQty === 0)) {
                unitQty = totalModQty / parentQty;
            }

            let text = `${unitQty > 1 ? unitQty + 'x ' : ''}${m.name}`;

            // Adiciona o valor financeiro extra na string, se houver
            const modPrice = Number(m.price || 0);
            if (modPrice > 0) {
                const fPrice = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2 }).format(modPrice);
                text += ` (+ R$ ${fPrice})`;
            }

            const gid = m.group_id || m.modifier_group_id || 'ungrouped';

            if (!groups[gid]) groups[gid] = [];
            groups[gid].push(text);
        });

        return Object.values(groups).map(g => g.join(', '));
    };

    const groupActiveItems = (itemsArray) => {
        if (!itemsArray || !Array.isArray(itemsArray)) return [];

        const allActive = itemsArray.filter((i) => i.status !== "cancelled");
        const parents = allActive.filter((i) => !i.parent_item_id);
        const children = allActive.filter((i) => !!i.parent_item_id);

        return parents.map((parent) => ({
            ...parent,
            hasChildren: children.some((c) => String(c.parent_item_id) === String(parent.id)),
            comboChildren: children.filter((c) => String(c.parent_item_id) === String(parent.id))
        }));
    };

    const parseAddress = (addressRaw) => {
        if (!addressRaw) return null;
        if (typeof addressRaw === 'object') return addressRaw;
        if (typeof addressRaw === 'string') {
            try { return JSON.parse(addressRaw); } catch(e) { return { full_address: addressRaw }; }
        }
        return addressRaw;
    };

    const formatAddress = (order) => {
        const a = parseAddress(order?.address_snapshot || order?.address || order?.deliveryAddress);
        if (!a) return null;
        if (a.full_address) return a.full_address;

        const streetName = a.street?.name || a.street_name || a.street || '';
        const neighborhood = a.neighborhood?.name || a.neighborhood_name || a.neighborhood || '';
        const comp = a.complement ? ` (${a.complement})` : '';
        const number = a.no_number ? 'S/N' : (a.number || 'S/N');

        if (streetName) {
            return `${streetName}, ${number} - ${neighborhood}${comp}`;
        }

        return null;
    };

    const getCustomerPhone = (order) => {
        if (!order) return null;
        const parsedAddr = parseAddress(order.address_snapshot || order.address);

        return order.customer?.phone
            || order.customer_phone
            || order.phone
            || parsedAddr?.phone
            || null;
    };

    const resolvePaymentIcon = (engineType, fiscalCode = null) => {
        if (engineType === 'bank_api') return 'pi-qrcode';
        if (engineType === 'money') return 'pi-money-bill';
        if (engineType === 'internal_loyalty') return 'pi-star-fill text-yellow-500';
        if (engineType === 'manual') {
            if (fiscalCode === '17') return 'pi-qrcode';
            if (['03', '04'].includes(String(fiscalCode))) return 'pi-credit-card';
            if (fiscalCode === '01') return 'pi-money-bill';
        }
        return 'pi fa-wallet';
    };

    const getPaymentBadges = (order) => {
        let badges = [];
        if (!order) return badges;

        if (order.payments && order.payments.length > 0) {
            order.payments.forEach(p => {
                const method = p.paymentMethod || p.payment_method;
                const engine = method?.engine_type || p.engine_type;
                const fiscalCode = method?.fiscal_code || p.fiscal_code;
                const label = p.method_name || p.method || method?.name || 'Pagamento';

                badges.push({
                    label,
                    icon: resolvePaymentIcon(engine, fiscalCode)
                });
            });
            return badges;
        }

        if (order.payment_intent_methods) {
            let methodsArray = order.payment_intent_methods;
            if (typeof methodsArray === 'string') {
                try { methodsArray = JSON.parse(methodsArray); } catch (e) { methodsArray = []; }
            }

            if (Array.isArray(methodsArray)) {
                methodsArray.forEach(m => {
                    const engine = m.engine_type;
                    const fiscalCode = m.fiscal_code;
                    const label = String(m.method || m.method_name || m.name || 'Pagamento');

                    badges.push({
                        label,
                        icon: resolvePaymentIcon(engine, fiscalCode)
                    });
                });
            }
        }
        return badges;
    };

    return {
        getOrderTitle,
        formatModifiersList,
        groupActiveItems,
        abbreviateName,
        formatAddress,
        parseAddress,
        getCustomerPhone,
        getPaymentBadges
    };
}
