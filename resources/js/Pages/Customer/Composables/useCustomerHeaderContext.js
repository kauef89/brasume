import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useCustomerHeaderContext({
    activeTab,
    activeAppendOrder,
    activeCategory,
    activeProduct,
    activeComboInnerProduct,
    productTotal,
    menuSearchQuery,
    menuView
}) {
    const page = usePage();

    const getFirstName = (fullName) => {
        if (!fullName || typeof fullName !== 'string') return '';
        return fullName.trim().split(' ')[0];
    };

    const headerContext = computed(() => {
        const user = page.props.auth?.user;
        const customer = user?.customer;
        const rawName = customer?.name || user?.name;
        const firstName = getFirstName(rawName);
        const tableMode = page.props.tableMode;

        // 🎯 1. Modificadores de Produto DENTRO de um Combo
        if (activeProduct?.value && activeComboInnerProduct?.value) {
            const innerName = activeComboInnerProduct.value.name
                || activeComboInnerProduct.value.product_name
                || 'Personalizando Item';

            return {
                title: innerName,
                subtitle: activeProduct.value.name,
                price: Number(productTotal?.value ?? activeProduct.value.price ?? 0)
            };
        }

        // 🎯 2. Configuração de Produto / Combo Aberto (com Preço Dinâmico)
        if (activeProduct?.value) {
            const rawPrice = productTotal?.value
                ?? activeProduct.value.total
                ?? activeProduct.value.price
                ?? activeProduct.value.base_price
                ?? 0;

            return {
                title: activeProduct.value.name,
                subtitle: activeProduct.value.category_name || activeCategory?.value?.name || 'Personalizando Item',
                price: Number(rawPrice)
            };
        }

        // 3. Modo Complemento Ativo (Append Order)
        if (activeAppendOrder?.value) {
            return {
                title: `Adicionar ao Pedido #${activeAppendOrder.value.id}`,
                subtitle: tableMode ? `Comanda na ${tableMode.name}` : 'Itens em Preparo na Cozinha'
            };
        }

        // 4. Modo Mesa: Títulos dedicados de Salão
        if (tableMode) {
            if (activeTab?.value === 'menu') {
                return {
                    title: 'Cardápio',
                    subtitle: tableMode.name
                };
            }

            if (activeTab?.value === 'orders') {
                return {
                    title: 'Conta da Mesa',
                    subtitle: `${tableMode.name} • Pedidos Ativos`
                };
            }

            if (activeTab?.value === 'cart') {
                return {
                    title: 'Novo Pedido',
                    subtitle: tableMode.name
                };
            }
        }

        // 5. Aba HOME: Saudação
        if (activeTab?.value === 'home') {
            return {
                isHome: true,
                firstName: firstName,
                title: null,
                subtitle: null
            };
        }

        // 6. Aba CARDÁPIO: Top 10, Favoritos ou Título Fixo
        if (activeTab?.value === 'menu') {
            if (menuView?.value === 'bestsellers') {
                return {
                    title: 'Top 10: Mais Pedidos',
                    subtitle: 'Destaques'
                };
            }

            if (menuView?.value === 'favorites') {
                return {
                    title: 'Meus Favoritos',
                    subtitle: 'Salvos por Você'
                };
            }

            // 🚀 FIXO: Permanece "Cardápio" sem trocar pelo nome da categoria durante o scroll
            return {
                title: 'Cardápio',
                subtitle: null
            };
        }

        // 7. Demais Abas
        const titles = {
            orders: { title: 'Meus Pedidos', subtitle: 'Acompanhamento' },
            account: { title: 'Minha Conta', subtitle: 'Perfil e Endereços' },
            cart: { title: 'Meu Carrinho', subtitle: 'Revisão do Pedido' }
        };

        return titles[activeTab?.value] || { title: 'Cardápio Digital', subtitle: 'Bem-vindo' };
    });

    return {
        headerContext
    };
}
