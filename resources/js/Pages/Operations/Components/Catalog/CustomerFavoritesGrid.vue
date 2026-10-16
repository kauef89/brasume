<template>
    <div class="w-full h-full flex flex-col animate-in fade-in duration-300">
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-7 gap-2.5 overflow-y-auto hide-scrollbar pb-24">
            <ProductCard
                v-for="item in enrichedFavorites"
                :key="item.id + '-' + Math.random()"
                :product="item"
                @click="$emit('favorite-click', $event)"
            />
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import ProductCard from '@/Pages/Pos/Components/Menu/ProductCard.vue';

const props = defineProps({
    favorites: { type: Array, required: true }
});

defineEmits(['favorite-click']);
const page = usePage();

const enrichedFavorites = computed(() => {
    const dict = {};
    (page.props.initialProducts || []).forEach(p => { dict[p.id] = p; });

    return (props.favorites || []).map(fav => {
        const realProduct = dict[fav.product_id || fav.id];
        if (!realProduct) return null;

        const rawSelections = fav.modifiers || fav.selections || fav.favorite_selections || [];

        // 🚀 O MOTOR DE HIGIENIZAÇÃO (Tolerância Zero)
        const ingredientGroups = (realProduct.modifiers || []).filter(g => g.type === 'ingredient');
        let cleanedSelections = rawSelections;

        // Se o produto atual exige ingredientes, ativamos a varredura profunda
        if (ingredientGroups.length > 0) {

            // 1. Mapeia tudo o que é válido hoje (Ingredientes e Adicionais)
            const allValidOptions = [];
            (realProduct.modifiers || []).forEach(g => {
                (g.options || []).forEach(opt => {
                    allValidOptions.push({ ...opt, groupId: g.id, groupType: g.type });
                });
            });

            const tempCleaned = [];
            let isMappingBroken = false;

            for (const sel of rawSelections) {
                const selId = String(sel.id || sel.modifier_option_id);
                const selName = String(sel.name || '').toLowerCase().trim();

                // Procura correspondência no cardápio vivo (por ID ou Nome exato)
                const matchedOption = allValidOptions.find(opt =>
                    String(opt.id) === selId || String(opt.name).toLowerCase().trim() === selName
                );

                if (matchedOption) {
                    // Sobreviveu: Recebe a estrutura limpa e nova
                    tempCleaned.push({
                        ...sel,
                        id: matchedOption.id,
                        name: matchedOption.name,
                        price: Number(matchedOption.price || 0),
                        group_id: matchedOption.groupId,
                        is_ingredient: matchedOption.groupType === 'ingredient'
                    });
                } else if (sel.is_joker || selName.startsWith('obs:')) {
                    // Anotações e "Outros" passam livremente
                    tempCleaned.push(sel);
                } else {
                    // 🚀 MORTE SÚBITA: É um modificador órfão do legado que não existe mais.
                    // Como o produto exige ingredientes, abortamos a exibição por segurança.
                    isMappingBroken = true;
                    break;
                }
            }

            if (isMappingBroken) return null; // Aniquila o favorito sujo
            cleanedSelections = tempCleaned;
        }

        return {
            ...realProduct, // Mantém o schema oficial das categorias[cite: 5]
            favorite_selections: cleanedSelections,
            cartItem: {
                qty: fav.quantity || 1,
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
    }).filter(Boolean); // Limpa as falhas silenciosamente
});
</script>
