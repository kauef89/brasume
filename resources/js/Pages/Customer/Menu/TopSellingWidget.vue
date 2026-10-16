<template>
    <div class="w-full">
        <!-- Skeletons em Glass Translúcido -->
        <div v-if="isLoading" class="grid grid-cols-1 md:grid-cols-2 gap-2.5 w-full">
            <div
                v-for="i in 6"
                :key="'skeleton-' + i"
                class="w-full h-28 c-glass-card animate-pulse !bg-white/5"
            ></div>
        </div>

        <!-- Grade de Produtos em Destaque -->
        <div v-else-if="enrichedCombinations.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-2.5 w-full animate-in fade-in duration-500">
            <CustomerProductCard
                v-for="item in enrichedCombinations"
                :key="item.variant_signature || item.id"
                :product="item"
                layout="list"
                @click="$emit('open-product', $event)"
            />
        </div>

        <!-- Empty State em Glassmorphism Sem Bordas -->
        <div v-else class="c-glass-card text-center py-12 px-6 mt-2 flex flex-col items-center justify-center animate-in fade-in">
            <div class="w-16 h-16 rounded-full bg-white/5 flex items-center justify-center mb-3">
                <i class="pi pi-bolt text-2xl text-white/30"></i>
            </div>
            <h3 class="c-title !text-base mb-1">Carregando tendências...</h3>
            <p class="c-text-muted max-w-xs leading-relaxed">
                Assim que os pedidos começarem a sair, as combinações favoritas da galera aparecerão aqui.
            </p>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import CustomerProductCard from '@/Pages/Customer/Menu/CustomerProductCard.vue';
import { useFavoriteCombinations } from '@/Pages/Shared/Composables/useFavoriteCombinations';

defineProps({
    layout: { type: String, default: 'list' },
    customerId: { type: [String, Number], default: null }
});

defineEmits(['open-product']);

const { topCombinations, isLoading } = useFavoriteCombinations();
const page = usePage();

const enrichedCombinations = computed(() => {
    const dict = {};
    (page.props.initialProducts || []).forEach(p => { dict[p.id] = p; });

    return (topCombinations.value || []).map(combo => {
        const realProduct = dict[combo.product_id || combo.id];
        if (!realProduct) return null;

        const rawSelections = combo.modifiers || combo.selections || combo.favorite_selections || [];

        // 🚀 O MOTOR DE HIGIENIZAÇÃO DE INGREDIENTES
        const ingredientGroups = (realProduct.modifiers || []).filter(g => g.type === 'ingredient');
        let cleanedSelections = rawSelections;

        if (ingredientGroups.length > 0) {
            const validIngredients = [];
            ingredientGroups.forEach(g => {
                (g.options || []).forEach(opt => validIngredients.push({ ...opt, groupId: g.id }));
            });

            const tempCleaned = [];
            let isMappingBroken = false;

            for (const sel of rawSelections) {
                const selId = String(sel.id || sel.modifier_option_id);
                const selName = String(sel.name || '').toLowerCase().trim();
                const selGroupId = String(sel.group_id || sel.modifier_group_id);

                const matchedIngredient = validIngredients.find(opt =>
                    String(opt.id) === selId || String(opt.name).toLowerCase().trim() === selName
                );

                if (matchedIngredient) {
                    tempCleaned.push({
                        ...sel,
                        id: matchedIngredient.id,
                        name: matchedIngredient.name,
                        price: Number(matchedIngredient.price || 0),
                        group_id: matchedIngredient.groupId
                    });
                } else if (sel.type === 'ingredient' || sel.is_ingredient || (selGroupId && ingredientGroups.some(g => String(g.id) === selGroupId))) {
                    isMappingBroken = true;
                    break;
                } else {
                    tempCleaned.push(sel);
                }
            }

            if (isMappingBroken) return null;
            cleanedSelections = tempCleaned;
        }

        return {
            ...realProduct,
            favorite_selections: cleanedSelections,
            cartItem: {
                qty: combo.quantity || 1,
                isTakeaway: false,
                structuredModifiers: cleanedSelections.map(m => ({
                    id: m.id || m.modifier_option_id,
                    name: m.name,
                    price: Number(m.price || 0),
                    qty: Number(m.quantity || m.qty || 1),
                    is_joker: !!m.is_joker,
                    group_id: m.group_id || m.modifier_group_id || null
                }))
            }
        };
    }).filter(Boolean);
});
</script>
