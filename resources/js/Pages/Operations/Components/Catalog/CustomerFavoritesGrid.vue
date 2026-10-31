<template>
    <div class="flex flex-col w-full h-full animate-in fade-in duration-300">
        <div
            v-if="enrichedFavorites.length > 0"
            :class="[
                'grid pb-4 pt-1',
                layout !== 'list'
                    ? 'grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2.5 md:gap-3'
                    : 'grid-cols-1 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-2.5 md:gap-3'
            ]"
        >
            <div
                v-for="(item, index) in enrichedFavorites"
                :key="item.variant_signature || (item.id + '-' + index)"
                class="relative group/fav select-none"
            >
                <ProductCard
                    :product="item"
                    :layout="layout"
                    :class="[
                        'transition-all duration-200',
                        isMultiSelect && isItemSelected(item)
                            ? 'ring-4 ring-primary-500 scale-[0.98] rounded-2xl shadow-xl'
                            : ''
                    ]"
                    @click="onCardClick(item)"
                />

                <!-- CHECKBOX OVERLAY (QUANDO MODO SELEÇÃO LIGADO) -->
                <button
                    v-if="isMultiSelect"
                    type="button"
                    @click.stop="toggleItem(item)"
                    class="absolute top-2.5 left-2.5 z-20 w-7 h-7 rounded-xl flex items-center justify-center transition-all shadow-md cursor-pointer border-none outline-none"
                    :class="isItemSelected(item) ? 'bg-primary-500 text-white shadow-primary-500/30' : 'bg-black/60 text-white/70 backdrop-blur-md hover:bg-black/80 hover:text-white'"
                >
                    <i class="pi text-xs font-black" :class="isItemSelected(item) ? 'pi-check' : ''"></i>
                </button>
            </div>
        </div>
        <EmptyState
            v-else
            icon="pi pi-star"
            title="Nenhum favorito encontrado para este cliente."
            class="min-h-[300px]"
        />
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import ProductCard from '@/Pages/Pos/Components/Menu/ProductCard.vue';
import EmptyState from "@/Components/UI/EmptyState.vue";

const props = defineProps({
    favorites: { type: Array, required: true },
    layout: { type: String, default: 'square' },
    isMultiSelect: { type: Boolean, default: false },
    selectedItems: { type: Array, default: () => [] }
});

const emit = defineEmits(['favorite-click', 'update:selectedItems']);
const page = usePage();

const isItemSelected = (item) => {
    const sig = item.variant_signature || item.id;
    return props.selectedItems.some(i => (i.variant_signature || i.id) === sig);
};

const toggleItem = (item) => {
    const sig = item.variant_signature || item.id;
    const exists = props.selectedItems.some(i => (i.variant_signature || i.id) === sig);
    let updated;
    if (exists) {
        updated = props.selectedItems.filter(i => (i.variant_signature || i.id) !== sig);
    } else {
        updated = [...props.selectedItems, item];
    }
    emit('update:selectedItems', updated);
};

const onCardClick = (item) => {
    if (props.isMultiSelect) {
        toggleItem(item);
    } else {
        emit('favorite-click', item);
    }
};

const enrichedFavorites = computed(() => {
    const dict = {};
    (page.props.initialProducts || []).forEach(p => { dict[p.id] = p; });

    return (props.favorites || []).map((fav, index) => {
        const realProduct = dict[fav.product_id || fav.id];
        if (!realProduct) return null;

        const rawSelections = fav.modifiers || fav.selections || fav.favorite_selections || [];

        // MOTOR DE HIGIENIZAÇÃO (Tolerância Zero)
        const ingredientGroups = (realProduct.modifiers || []).filter(g => g.type === 'ingredient');
        let cleanedSelections = rawSelections;

        if (ingredientGroups.length > 0) {
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

                const matchedOption = allValidOptions.find(opt =>
                    String(opt.id) === selId || String(opt.name).toLowerCase().trim() === selName
                );

                if (matchedOption) {
                    tempCleaned.push({
                        ...sel,
                        id: matchedOption.id,
                        name: matchedOption.name,
                        price: Number(matchedOption.price || 0),
                        group_id: matchedOption.groupId,
                        is_ingredient: matchedOption.groupType === 'ingredient'
                    });
                } else if (sel.is_joker || selName.startsWith('obs:')) {
                    tempCleaned.push(sel);
                } else {
                    isMappingBroken = true;
                    break;
                }
            }

            if (isMappingBroken) return null;
            cleanedSelections = tempCleaned;
        }

        const rawComboItems = fav.combo_items || fav.comboItems || [];
        let hydratedComboItems = null;

        if (rawComboItems.length > 0) {
            hydratedComboItems = rawComboItems.map(c => {
                const rawSubMods = c.modifiers || c.structured_modifiers || c.structuredModifiers || [];
                const structuredModifiers = rawSubMods.map(m => ({
                    id: m.id ?? m.modifier_option_id ?? null,
                    group_id: m.group_id ?? m.modifier_group_id ?? null,
                    name: m.name,
                    price: Number(m.price || 0),
                    qty: Number(m.qty || m.quantity || 1)
                }));
                const modifiersText = structuredModifiers.map(m => (m.qty > 1 ? `${m.qty}x ` : "") + m.name).join(", ");

                return {
                    id: c.id,
                    product_id: Number(c.product_id),
                    name: c.name || c.product_name,
                    group_id: c.group_id ? Number(c.group_id) : null,
                    modifiersText: modifiersText,
                    structuredModifiers: structuredModifiers
                };
            });
        }

        return {
            ...realProduct,
            variant_signature: fav.variant_signature || `${fav.id}_${index}`,
            favorite_selections: cleanedSelections,
            combo_items: hydratedComboItems,
            notes: fav.notes || "",
            cartItem: {
                qty: fav.quantity || 1,
                notes: fav.notes || "",
                isTakeaway: false,
                structuredModifiers: cleanedSelections.map(m => ({
                    id: m.id || m.modifier_option_id,
                    name: m.name,
                    price: Number(m.price || 0),
                    qty: Number(m.quantity || m.qty || 1),
                    is_joker: !!m.is_joker,
                    group_id: m.group_id || m.modifier_group_id || null
                })),
                comboItems: hydratedComboItems
            }
        };
    }).filter(Boolean);
});
</script>