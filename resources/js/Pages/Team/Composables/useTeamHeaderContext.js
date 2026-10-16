import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useTeamHeaderContext({
    activeTab,
    contextMenuVisible,
    selectedOrderContext,
    isAllowanceMode,
    cartRoutingMeta,
    cart,
    orderType,
    operatorName
}) {
    const page = usePage();

    const getFirstName = (fullName) => {
        if (!fullName || typeof fullName !== 'string') return '';
        return fullName.trim().split(' ')[0];
    };

    const headerContext = computed(() => {
        // 1. Contexto explícito de uma mesa/comanda aberta no menu de ações do mapa
        if (contextMenuVisible?.value && selectedOrderContext?.value) {
            const table = selectedOrderContext.value.dining_table?.name
                || selectedOrderContext.value.diningTable?.name
                || selectedOrderContext.value.table_number
                || selectedOrderContext.value.table?.name;

            const name = selectedOrderContext.value.customer_name;
            const id = selectedOrderContext.value.id;

            return {
                title: table ? `Mesa ${table}` : (name || `Pedido #${id}`),
                subtitle: selectedOrderContext.value.type === 'table' ? 'Mesa' : 'Balcão'
            };
        }

        // 2. Aba HOME: Saudação personalizada para o turno
        if (activeTab?.value === 'home') {
            const rawName = operatorName?.value || page.props.auth?.user?.name || 'Equipe';
            const firstName = getFirstName(rawName);

            return {
                title: `Bom turno, ${firstName}!`,
                subtitle: 'Painel da Equipe'
            };
        }

        // 3. Aba CARRINHO E CARDÁPIO (quando com itens em rascunho)
        if (activeTab?.value === 'cart' || (activeTab?.value === 'menu' && cartRoutingMeta?.value)) {
            if (isAllowanceMode?.value) {
                return {
                    title: 'Consumo Interno',
                    subtitle: 'Débito em Carteira'
                };
            }

            // A. Adicionando itens em pedido existente
            if (cartRoutingMeta?.value?.existing_order_id || cartRoutingMeta?.value?.id) {
                const table = cartRoutingMeta.value.dining_table?.name
                    || cartRoutingMeta.value.table?.name
                    || cartRoutingMeta.value.table_number;
                const ident = cartRoutingMeta.value.identifier;

                return {
                    title: table ? `Mesa ${table}` : (ident || `Comanda #${cartRoutingMeta.value.id}`),
                    subtitle: 'Adicionando Itens'
                };
            }

            // B. Novo Pedido com Destino já selecionado (Mesa Específica)
            if (cartRoutingMeta?.value?.type === 'table') {
                const tableName = cartRoutingMeta.value.table?.name || cartRoutingMeta.value.table_number;
                if (tableName) {
                    return {
                        title: `Mesa ${tableName}`,
                        subtitle: cartRoutingMeta.value.identifier ? `Ref: ${cartRoutingMeta.value.identifier}` : 'Novo Pedido'
                    };
                }
            }

            // C. Novo Pedido Delivery ou Balcão com identificador
            if (cartRoutingMeta?.value?.identifier) {
                return {
                    title: cartRoutingMeta.value.identifier,
                    subtitle: cartRoutingMeta.value.type === 'delivery' ? 'Delivery' : 'Balcão'
                };
            }

            // D. Fallback genérico para Carrinho sem destino
            if (activeTab?.value === 'cart' && cart?.value && cart.value.length > 0) {
                return {
                    title: orderType?.value === 'table' ? 'Mesa a Definir' : 'Comanda Aberta',
                    subtitle: 'Novo Pedido'
                };
            }
        }

        // 4. Demais abas: exibe o título nativo
        return {
            title: null,
            subtitle: null
        };
    });

    return {
        headerContext
    };
}
