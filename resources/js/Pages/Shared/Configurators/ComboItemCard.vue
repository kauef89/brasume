<template>
    <button
        type="button"
        @click="handleClick"
        :disabled="isDisabled"
        class="relative overflow-hidden p-option transition-all duration-300 outline-none! text-left group flex flex-col justify-end"
        :class="[
            layout === 'square' || layout === 'grid'
                ? 'w-full aspect-[3/4] rounded-2xl border border-transparent hover:border-primary-500/50'
                : 'w-full min-h-[100px] rounded-2xl border border-transparent',
            isDisabled ? 'grayscale opacity-50 cursor-not-allowed' : 'cursor-pointer active:scale-[0.98]'
        ]"
    >
        <!-- 🖼️ IMAGEM FULL-BLEED DE FUNDO -->
        <img
            v-if="finalImageUrl"
            :src="finalImageUrl"
            class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
            loading="lazy"
        />
        <div
            v-else
            class="absolute inset-0 bg-surface-2 flex items-center justify-center"
        >
            <i class="pi pi-image text-surface-muted/30 text-3xl"></i>
        </div>

        <!-- 🌑 SCRIM GRADIENTE ESCURO NA BASE (SEM DIVISÃO) -->
        <div class="absolute inset-0 bg-gradient-to-t from-black/95 via-black/50 to-transparent pointer-events-none"></div>

        <!-- 📝 INFOS DO PRODUTO SOBREPOSTAS NA BASE -->
        <div class="relative z-10 p-3.5 flex flex-col justify-end w-full min-w-0">
            <h3 class="font-black text-white text-sm leading-tight break-words drop-shadow-md">
                {{ productName }}
            </h3>

            <p
                v-if="productDescription"
                class="text-[11px] font-medium text-white/75 leading-snug w-full mt-1 line-clamp-2 drop-shadow"
            >
                {{ productDescription }}
            </p>
        </div>
    </button>
</template>

<script setup>
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";

const props = defineProps({
    item: { type: Object, required: true },
    disabled: { type: Boolean, default: false },
    hasModifiers: { type: Boolean, default: false },
    layout: { type: String, default: "square" }
});

const emit = defineEmits(["add"]);
const page = usePage();

const productName = computed(() => {
    return props.item?.product_name || props.item?.product?.name || props.item?.name || "";
});

const productDescription = computed(() => {
    return props.item?.description || props.item?.product?.description || "";
});

const isOutOfStock = computed(() => {
    const prod = props.item?.product || props.item || {};
    if (["out_of_stock", "unavailable", "paused"].includes(prod.status)) return true;
    if (prod.track_inventory && parseFloat(prod.current_stock) <= 0) return true;
    return false;
});

const isDisabled = computed(() => props.disabled || isOutOfStock.value);

const finalImageUrl = computed(() => {
    if (!props.item) return null;

    // 1. Imagem do próprio item de combo ou do produto
    const itemOrProd = props.item.product || props.item;
    const path = props.item.image || props.item.image_path || itemOrProd.image || itemOrProd.image_path;
    if (path) {
        const ts = props.item.updated_at || itemOrProd.updated_at
            ? new Date(props.item.updated_at || itemOrProd.updated_at).getTime()
            : "";
        return path.startsWith("http") ? path : `/media/file?path=${path}${ts ? `&v=${ts}` : ""}`;
    }

    // 2. Busca pela categoria do produto e escala na árvore de categorias-mãe
    const categories = page?.props?.initialCategories || page?.props?.categories || [];
    const products = page?.props?.initialProducts || [];

    let currentCatId = props.item.category_id || itemOrProd.category_id;
    if (!currentCatId) {
        const prodId = props.item.product_id || props.item.id;
        const foundProd = products.find(p => String(p.id) === String(prodId));
        if (foundProd) {
            currentCatId = foundProd.category_id;
        }
    }

    let depth = 0;
    while (currentCatId && depth < 10) {
        const cat = categories.find(c => String(c.id) === String(currentCatId));
        if (!cat) break;
        const catPath = cat.image_path || cat.image;
        if (catPath) {
            const ts = cat.updated_at ? new Date(cat.updated_at).getTime() : "";
            return catPath.startsWith("http") ? catPath : `/media/file?path=${catPath}${ts ? `&v=${ts}` : ""}`;
        }
        currentCatId = cat.parent_id && Number(cat.parent_id) !== 0 ? cat.parent_id : null;
        depth++;
    }

    return null;
});

const handleClick = () => {
    if (isDisabled.value) return;
    emit("add", props.item, true);
};
</script>
