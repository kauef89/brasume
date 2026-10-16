cat << 'EOF' > resources/js/Pages/Pos/Components/Checkout/ItemActionPopover.vue
<template>
    <Popover
        ref="popoverRef"
        :dismissable="true"
        class="!rounded-2xl !p-2 shadow-2xl border border-surface-border bg-surface-1 min-w-[220px] z-50 overflow-visible lateral-popover-theme"
        @show="handleShow"
        @hide="handleHide"
    >
        <div ref="contentRef" class="flex flex-col gap-1 text-surface-text select-none">

            <!-- Cabeçalho do Item -->
            <div class="px-2 py-1 border-b border-surface-border/50 flex flex-col">
                <div class="flex items-center gap-1.5">
                    <span class="text-[9px] font-black uppercase tracking-widest text-primary-500">Opções</span>
                    <span v-if="activeItem?.is_combo_child" class="text-[9px] font-bold uppercase tracking-wider bg-surface-2 text-surface-muted px-1.5 py-0.5 rounded">
                        Subitem
                    </span>
                    <span v-else-if="activeItem?.has_exploded_children" class="text-[9px] font-bold uppercase tracking-wider bg-primary-500/10 text-primary-500 px-1.5 py-0.5 rounded">
                        Combo Pai
                    </span>
                </div>
                <span class="text-xs font-bold text-surface-text truncate max-w-[200px] mt-0.5">
                    {{ activeItem?.product_name || activeItem?.name }}
                </span>
            </div>

            <!-- Seletor de Escopo em Abas (Somente Qtd > 1 e se NÃO for subitem) -->
            <div
                v-if="mode === 'details' && (activeItem?.quantity || 1) > 1 && !activeItem?.is_combo_child"
                class="flex p-0.5 bg-surface-2 rounded-xl border border-surface-border/60 my-1"
            >
                <button
                    type="button"
                    @click="actionScope = 'single'"
                    class="flex-1 py-1 text-[11px] font-bold rounded-lg transition-all outline-none border-none cursor-pointer flex items-center justify-center gap-1"
                    :class="actionScope === 'single' ? 'bg-surface-1 text-primary-smart shadow-sm' : 'text-surface-muted hover:text-surface-text'"
                >
                    1 un.
                </button>
                <button
                    type="button"
                    @click="actionScope = 'all'"
                    class="flex-1 py-1 text-[11px] font-bold rounded-lg transition-all outline-none border-none cursor-pointer flex items-center justify-center gap-1"
                    :class="actionScope === 'all' ? 'bg-surface-1 text-primary-smart shadow-sm' : 'text-surface-muted hover:text-surface-text'"
                >
                    Todas ({{ activeItem?.quantity }}x)
                </button>
            </div>

            <!-- Ações: Modo Detalhes (OrderDetails) -->
            <div v-if="mode === 'details'" class="flex flex-col gap-0.5 pt-0.5">

                <!-- 1. Reimprimir -->
                <button type="button" @click="dispatchAction('reprint')" class="action-btn">
                    <i class="pi pi-print action-icon"></i>
                    <span>{{ actionScope === 'single' && (activeItem?.quantity || 1) > 1 ? 'Reimprimir 1 un.' : 'Reimprimir' }}</span>
                </button>

                <!-- 2. Duplicar (Subitem duplica compulsoriamente como Avulso) -->
                <button type="button" @click="dispatchAction('duplicate')" class="action-btn">
                    <i class="pi pi-plus-circle action-icon"></i>
                    <span>
                        {{ activeItem?.is_combo_child
                            ? 'Duplicar como Avulso (+1)'
                            : (actionScope === 'single' && (activeItem?.quantity || 1) > 1
                                ? 'Duplicar (+1)'
                                : ((activeItem?.quantity || 1) > 1 ? `Duplicar (+${activeItem?.quantity})` : 'Duplicar (+1)'))
                        }}
                    </span>
                </button>

                <!-- 3. EDITAR -->
                <button
                    type="button"
                    @click="dispatchAction('edit')"
                    class="action-btn"
                >
                    <i class="pi pi-pencil action-icon"></i>
                    <span>{{ activeItem?.has_exploded_children || activeItem?.is_combo_child ? 'Editar Combo' : (actionScope === 'single' && (activeItem?.quantity || 1) > 1 ? 'Editar 1 un.' : 'Editar') }}</span>
                </button>

                <!-- 4. Transferir (Bloqueado para subitens de combo) -->
                <button v-if="!activeItem?.is_combo_child" type="button" @click="dispatchAction('transfer')" class="action-btn">
                    <i class="pi pi-sync action-icon"></i>
                    <span>{{ actionScope === 'single' && (activeItem?.quantity || 1) > 1 ? 'Transferir 1 un.' : 'Transferir' }}</span>
                </button>

                <!-- 5. Cancelar / Estornar (Bloqueado para subitens de combo) -->
                <template v-if="!activeItem?.is_combo_child">
                    <div class="h-px bg-surface-border/50 my-1"></div>
                    <button type="button" @click="dispatchAction('cancel')" class="action-btn font-bold text-surface-text hover:text-primary-smart">
                        <i class="pi pi-trash action-icon text-primary-500"></i>
                        <span>{{ actionScope === 'single' && (activeItem?.quantity || 1) > 1 ? 'Estornar 1 un.' : 'Cancelar / Estornar' }}</span>
                    </button>
                </template>
            </div>

            <!-- Ações: Modo Checkout -->
            <div v-else class="flex flex-col gap-0.5 pt-0.5">
                <button type="button" @click="dispatchQuickAdd" class="action-btn">
                    <i class="pi pi-plus-circle action-icon"></i>
                    <span>{{ activeItem?.is_combo_child ? 'Adicionar como Avulso (+1)' : 'Adicionar Lançamento (+1)' }}</span>
                </button>

                <button v-if="!activeItem?.is_combo_child" type="button" @click="dispatchQuickCancel" class="action-btn font-bold text-surface-text hover:text-primary-smart">
                    <i class="pi pi-trash action-icon text-primary-500"></i>
                    <span>Estornar (Requer Senha)</span>
                </button>
            </div>
        </div>
    </Popover>
</template>

<script setup>
import { ref, nextTick, onBeforeUnmount } from "vue";
import Popover from "primevue/popover";

const props = defineProps({
    mode: { type: String, default: 'checkout' }
});

const emit = defineEmits(["action", "quick-add", "quick-cancel"]);

const popoverRef = ref(null);
const contentRef = ref(null);
const activeItem = ref(null);
const triggerEl = ref(null);
const actionScope = ref('single');

const toggle = (event, item) => {
    activeItem.value = item;
    actionScope.value = 'single';
    triggerEl.value = event.currentTarget || event.target;

    if (popoverRef.value) {
        popoverRef.value.toggle(event);
    }
};

const hide = () => {
    if (popoverRef.value) {
        popoverRef.value.hide();
    }
};

const handleOutsideClick = (e) => {
    if (!contentRef.value || !triggerEl.value) return;

    const clickedInside = contentRef.value.contains(e.target);
    const clickedTrigger = triggerEl.value.contains(e.target);

    if (!clickedInside && !clickedTrigger) {
        hide();
    }
};

const handleShow = () => {
    window.addEventListener('pointerdown', handleOutsideClick, true);

    nextTick(() => {
        if (!contentRef.value || !triggerEl.value) return;

        const popoverContainer = contentRef.value.closest('.p-popover') || contentRef.value.parentElement;
        if (!popoverContainer) return;

        const triggerRect = triggerEl.value.getBoundingClientRect();
        const popoverRect = popoverContainer.getBoundingClientRect();

        let left = triggerRect.right + 12 + window.scrollX;
        let isFlipped = false;

        if (triggerRect.right + 12 + popoverRect.width > window.innerWidth - 16) {
            left = triggerRect.left - popoverRect.width - 12 + window.scrollX;
            isFlipped = true;
        }

        let top = triggerRect.top + (triggerRect.height / 2) - 26 + window.scrollY;

        if (top + popoverRect.height > window.innerHeight + window.scrollY - 16) {
            top = window.innerHeight + window.scrollY - popoverRect.height - 16;
        }
        if (top < window.scrollY + 16) {
            top = window.scrollY + 16;
        }

        popoverContainer.style.left = `${Math.round(left)}px`;
        popoverContainer.style.top = `${Math.round(top)}px`;

        if (isFlipped) {
            popoverContainer.classList.add('flipped-left');
        } else {
            popoverContainer.classList.remove('flipped-left');
        }
    });
};

const handleHide = () => {
    window.removeEventListener('pointerdown', handleOutsideClick, true);
};

onBeforeUnmount(() => {
    handleHide();
});

const dispatchAction = (type) => {
    hide();
    const qty = ((activeItem.value?.quantity || 1) > 1) ? actionScope.value : 'all';
    emit('action', {
        type,
        item: activeItem.value,
        qty
    });
};

const dispatchQuickAdd = () => {
    hide();
    emit('quick-add', activeItem.value);
};

const dispatchQuickCancel = () => {
    hide();
    emit('quick-cancel', activeItem.value);
};

defineExpose({ toggle, hide });
</script>

<style scoped>
:deep(.p-popover:before),
:deep(.p-popover:after) {
    display: none !important;
}

:deep(.lateral-popover-theme::before) {
    content: '';
    position: absolute;
    left: -5px;
    top: 20px;
    width: 10px;
    height: 10px;
    background-color: var(--surface-1);
    border-left: 1px solid var(--surface-border);
    border-top: 1px solid var(--surface-border);
    transform: rotate(-45deg);
    pointer-events: none;
    z-index: 1;
}

:deep(.lateral-popover-theme.flipped-left::before) {
    left: auto;
    right: -5px;
    border-left: none;
    border-top: none;
    border-right: 1px solid var(--surface-border);
    border-bottom: 1px solid var(--surface-border);
    transform: rotate(-45deg);
}

.action-btn {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 0.625rem;
    padding: 0.45rem 0.625rem;
    border-radius: 0.75rem;
    font-size: 0.75rem;
    color: var(--surface-text);
    background: transparent;
    border: none;
    cursor: pointer;
    text-align: left;
    transition: background-color 0.15s ease, color 0.15s ease;
    outline: none;
}

.action-btn:hover {
    background-color: var(--surface-2);
    color: var(--primary-smart, var(--primary-500));
}

.action-icon {
    font-size: 0.75rem;
    color: var(--surface-muted);
    transition: color 0.15s ease;
}

.action-btn:hover .action-icon {
    color: var(--primary-smart, var(--primary-500));
}
</style>
EOF
