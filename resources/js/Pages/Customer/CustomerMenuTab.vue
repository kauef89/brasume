<template>
    <div class="flex flex-col h-full relative w-full overflow-hidden select-none">

        <!-- 🚀 1. TELEPORT DA BUSCA AO LADO DO TÍTULO "CARDÁPIO" -->
        <Teleport to="#customer-header-actions-target" v-if="isMounted && isActive && !isConfiguratorOpen && menuView === 'catalog'">
            <ExpandableSearch
                :model-value="menuSearch"
                @update:modelValue="$emit('update:menuSearch',$event)"
                placeholder="Buscar produtos..."
            />
        </Teleport>

        <!-- 🚀 2. TELEPORT DAS PÍLULAS E SELETOR NO SUB-HEADER -->
        <Teleport to="#customer-header-portal-target" v-if="isMounted && isActive && !isConfiguratorOpen">
            <div class="flex flex-col gap-2 pt-1 pb-1 animate-in fade-in duration-200">

                <!-- SELETOR SEGMENTED CONTROL -->
                <div v-if="customer && !isGuest" class="flex justify-center px-4">
                    <SegmentedControl
                        :model-value="menuView"
                        @update:model-value="$emit('update:menuView',$event)"
                        :options="menuViewOptions"
                    />
                </div>

                <!-- PÍLULAS DE CATEGORIA 100% SINCRONIZADAS COM A ORDEM DO CARDÁPIO CORRIDO -->
                <div v-if="menuView === 'catalog' && synchronizedCategories.length > 0" class="w-full overflow-hidden">
                    <CategoryMenu
                        :categories="synchronizedCategories"
                        :selected="activeCategoryId"
                        layout="horizontal"
                        :show-all="false"
                        :glass-mode="true"
                        @update:selected="handleCategoryClick"
                        class="shrink-0 pl-2"
                    />
                </div>

            </div>
        </Teleport>

        <!-- ÁREA DE ROLAGEM GERAL COM RECUO DINÂMICO -->
        <div
            class="flex-1 overflow-y-auto scroll-smooth hide-scrollbar relative"
            :style="{ paddingTop: 'var(--app-header-height, 7rem)' }"
            ref="scrollArea"
            @scroll="handleScroll"
        >

            <!-- MOTOR DE SWIPE -->
            <div
                class="flex w-full items-start touch-pan-y will-change-transform"
                :class="{ 'transition-transform duration-300 ease-out': !isDragging }"
                :style="{ transform: `translateX(calc(-${activeIndex * 100}% + ${dragOffset}px))` }"
                @touchstart="handleTouchStart"
                @touchmove="handleTouchMove"
                @touchend="handleTouchEnd"
            >

                <!-- 🍔 VISTA 1: CARDÁPIO COMPLETO (Busca removida daqui para não consumir altura) -->
                <div class="w-full shrink-0 px-4 pb-6 pt-2">
                    <EmptyState
                        v-if="groupedMenu.length === 0"
                        icon="pi-search"
                        title="Nenhum item encontrado"
                        class="mt-10"
                    />

                    <!-- Lista Linear do Cardápio -->
                    <div
                        v-for="group in groupedMenu"
                        :key="group.category.id"
                        :id="'category-group-' + group.category.id"
                        :ref="el => setCategoryRef(el, group.category.id)"
                        class="mb-8 last:mb-0"
                    >
                        <div class="flex items-center gap-3 mb-3 px-1">
                            <h2 class="c-title !text-lg md:!text-xl whitespace-nowrap">
                                {{ group.category.displayName || group.category.name }}
                            </h2>
                            <div class="h-px bg-white/10 flex-1"></div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
                            <CustomerProductCard
                                v-for="product in group.products"
                                :key="product.id"
                                :product="product"
                                layout="list"
                                @click="$emit('product-click', product)"
                            />
                        </div>
                    </div>
                </div>

                <!-- 🔥 VISTA 2: MAIS VENDIDOS -->
                <div v-if="customer && !isGuest" class="w-full shrink-0 px-4 pb-6 pt-2">
                    <div class="flex items-center gap-3 mb-4 px-1">
                        <h2 class="c-title !text-lg md:!text-xl whitespace-nowrap">Top 10: Mais Pedidos</h2>
                        <div class="h-px bg-white/10 flex-1"></div>
                    </div>

                    <TopSellingWidget
                        :customer-id="customer?.id"
                        layout="list"
                        @open-product="$emit('open-recommended',$event)"
                    />
                </div>

                <!-- ⭐ VISTA 3: FAVORITOS -->
                <div v-if="customer && !isGuest" class="w-full shrink-0 px-4 pb-6 pt-2">
                    <div class="flex items-center gap-3 mb-4 px-1">
                        <h2 class="c-title !text-lg md:!text-xl whitespace-nowrap">Meus Favoritos</h2>
                        <div class="h-px bg-white/10 flex-1"></div>
                    </div>

                    <CustomerFavoritesMenu
                        :favorites="enrichedFavorites"
                        @favorite-click="$emit('product-click',$event)"
                    />
                </div>

            </div>

            <!-- 🚀 Spacer de rolagem que obedece a ilha dinâmica do Customer App -->
            <div class="shrink-0 w-full pointer-events-none" :style="{ height: 'var(--app-footer-height, 120px)' }"></div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted, computed } from 'vue';
import CustomerProductCard from "@/Pages/Customer/Menu/CustomerProductCard.vue";
import EmptyState from "@/Components/UI/EmptyState.vue";
import ExpandableSearch from "@/Components/UI/ExpandableSearch.vue";
import SegmentedControl from '@/Components/UI/SegmentedControl.vue';
import CategoryMenu from "@/Pages/Pos/Components/Menu/CategoryMenu.vue";

import TopSellingWidget from '@/Pages/Customer/Menu/TopSellingWidget.vue';
import CustomerFavoritesMenu from '@/Pages/Customer/Menu/CustomerFavoritesMenu.vue';

import { useCustomerMenu } from "@/Pages/Customer/Composables/useCustomerMenu";
import { useCustomerFavorites } from '@/Pages/Customer/Composables/useCustomerFavorites';

const props = defineProps({
    isActive: { type: Boolean, default: true },
    isConfiguratorOpen: { type: Boolean, default: false },
    categories: { type: Array, required: true },
    products: { type: Array, required: true },
    menuSearch: { type: String, default: '' },
    menuView: { type: String, default: 'catalog' },
    customer: { type: Object, default: null },
    isGuest: { type: Boolean, default: true }
});

const emit = defineEmits([
    'product-click', 'open-recommended', 'update:menuCategory',
    'update:menuSearch', 'update:menuView', 'open-login',
    'update:visibleCategories'
]);

const isMounted = ref(false);
onMounted(() => { isMounted.value = true; });

const filteredCategories = computed(() => {
    return (props.categories || []).filter(c => {
        if (!c || !c.name) return false;
        const nameLower = String(c.name).toLowerCase().trim();
        return c.id !== 'all' &&
               c.id !== 0 &&
               c.id !== null &&
               c.id !== '' &&
               nameLower !== 'todos os itens' &&
               nameLower !== 'todos';
    });
});

const productDictionary = computed(() => {
    const dict = {};
    const len = props.products.length;
    for (let i = 0; i < len; i++) {
        dict[props.products[i].id] = props.products[i];
    }
    return dict;
});

const { favorites, fetchFavorites } = useCustomerFavorites();

watch(() => props.customer?.id, (newId) => {
    if (newId && !props.isGuest) {
        fetchFavorites(newId);
    }
}, { immediate: true });

const enrichedFavorites = computed(() => {
    if (!favorites.value || favorites.value.length === 0) return [];

    return favorites.value
        .map(fav => {
            const realProduct = productDictionary.value[fav.product_id || fav.id];
            if (!realProduct) return null;

            const rawSelections = fav.modifiers || fav.selections || [];
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
                    qty: fav.quantity || 1,
                    isTakeaway: false,
                    structuredModifiers: cleanedSelections.map(m => ({
                        id: m.id,
                        name: m.name,
                        price: Number(m.price || 0),
                        qty: Number(m.quantity || m.qty || 1),
                        is_joker: !m.is_joker,
                        group_id: m.group_id || m.modifier_group_id || null
                    }))
                }
            };
        })
        .filter(Boolean);
});

const menuViewOptions = computed(() => [
    { label: 'Cardápio', value: 'catalog', icon: 'pi-th-large' },
    { label: 'Top 10', value: 'bestsellers', icon: 'pi-bolt' },
    {
        label: enrichedFavorites.value.length > 0 ? `Favoritos (${enrichedFavorites.value.length})` : 'Favoritos',
        value: 'favorites',
        icon: 'pi-heart-fill'
    }
]);

const {
    searchQuery, activeCategoryId, scrollArea, setCategoryRef,
    groupedMenu, visibleCategories, scrollToCategory, handleScroll,
    autoOpenProduct
} = useCustomerMenu(props);

// 🚀 SINCRONIZAÇÃO DA ORDEM DO MENU: As pílulas usam a ordem exata de groupedMenu
const synchronizedCategories = computed(() => {
    if (groupedMenu.value && groupedMenu.value.length > 0) {
        return groupedMenu.value.map(g => g.category);
    }
    return filteredCategories.value;
});

watch(autoOpenProduct, (productToOpen) => {
    if (productToOpen) {
        emit('product-click', productToOpen);
    }
});

watch(visibleCategories, (newVal) => {
    emit('update:visibleCategories', newVal);
}, { immediate: true });

watch(() => props.menuSearch, (newVal) => { searchQuery.value = newVal; });
watch(activeCategoryId, (newVal) => { emit('update:menuCategory', newVal); });

const handleCategoryClick = (categoryId) => {
    activeCategoryId.value = categoryId;
    const targetEl = document.getElementById(`category-group-${categoryId}`);

    if (targetEl && scrollArea.value) {
        scrollArea.value.scrollTo({ top: targetEl.offsetTop - 10, behavior: 'smooth' });
    } else {
        scrollToCategory(categoryId);
    }
};

const views = computed(() => {
    if (props.customer && !props.isGuest) return ['catalog', 'bestsellers', 'favorites'];
    return ['catalog'];
});

const activeIndex = computed(() => {
    const idx = views.value.indexOf(props.menuView);
    return idx !== -1 ? idx : 0;
});

watch(activeIndex, () => {
    if (scrollArea.value) scrollArea.value.scrollTo({ top: 0, behavior: 'smooth' });
});

const isDragging = ref(false);
const dragOffset = ref(0);
const touchStartX = ref(0);
const touchStartY = ref(0);
let isHorizontalSwiping = null;

const handleTouchStart = (e) => {
    touchStartX.value = e.touches[0].clientX;
    touchStartY.value = e.touches[0].clientY;
    isDragging.value = true;
    dragOffset.value = 0;
    isHorizontalSwiping = null;
};

const handleTouchMove = (e) => {
    if (!isDragging.value) return;
    const currentX = e.touches[0].clientX;
    const currentY = e.touches[0].clientY;
    const diffX = currentX - touchStartX.value;
    const diffY = Math.abs(currentY - touchStartY.value);

    if (isHorizontalSwiping === null) {
        if (diffY > 10 && diffY > Math.abs(diffX)) {
            isHorizontalSwiping = false;
        } else if (Math.abs(diffX) > 10) {
            isHorizontalSwiping = true;
        }
    }

    if (isHorizontalSwiping) {
        if (
            (activeIndex.value === 0 && diffX > 0) ||
            (activeIndex.value === views.value.length - 1 && diffX < 0)
        ) {
            dragOffset.value = diffX * 0.3;
        } else {
            dragOffset.value = diffX;
        }
    }
};

const handleTouchEnd = () => {
    if (!isDragging.value) return;
    isDragging.value = false;

    if (isHorizontalSwiping) {
        const threshold = window.innerWidth * 0.25;

        if (dragOffset.value < -threshold && activeIndex.value < views.value.length - 1) {
            emit('update:menuView', views.value[activeIndex.value + 1]);
        } else if (dragOffset.value > threshold && activeIndex.value > 0) {
            emit('update:menuView', views.value[activeIndex.value - 1]);
        }
    }

    dragOffset.value = 0;
    isHorizontalSwiping = null;
};

const handleGlobalCategoryClick = (e) => {
    if (props.menuView !== 'catalog') emit('update:menuView', 'catalog');
    handleCategoryClick(e.detail);
};

onMounted(() => { window.addEventListener('menu-scroll-to-category', handleGlobalCategoryClick); });
onUnmounted(() => { window.removeEventListener('menu-scroll-to-category', handleGlobalCategoryClick); });
</script>
