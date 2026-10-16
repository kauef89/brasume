<template>
    <div class="w-full h-full flex flex-col shrink-0 bg-transparent select-none overflow-hidden">

        <div
            class="flex-1 overflow-y-auto hide-scrollbar p-3 flex flex-col gap-4 min-h-0"
            :class="{ 'pb-28': !showFooter }"
        >
            <div
                v-if="stepGroups.length === 0"
                class="flex-1 flex flex-col items-center justify-center p-8 text-center text-surface-muted opacity-60 my-auto"
            >
                <i class="pi pi-box text-3xl mb-2"></i>
                <p class="font-medium text-sm">Nenhum item selecionado</p>
            </div>

            <div
                v-for="step in stepGroups"
                :key="step.groupId"
                class="flex flex-col gap-2"
            >
                <div class="flex items-center justify-between gap-2 pt-1 pb-1 px-1 w-full">
                    <span class="text-xs font-black text-surface-text uppercase tracking-wider leading-tight truncate">
                        {{ step.title }}
                    </span>

                    <div class="h-px bg-surface-border/40 flex-1 min-w-[0.5rem]"></div>

                    <span
                        class="text-[11px] font-black tracking-wider shrink-0 tabular-nums px-2 py-0.5 rounded-full"
                        :class="step.totalQty >= step.maxChoices ? 'bg-success-500/10 text-success-smart' : 'bg-surface-2 text-surface-muted'"
                    >
                        {{ step.totalQty }}/{{ step.maxChoices }}
                    </span>
                </div>

                <div class="flex flex-col gap-1.5">
                    <div
                        v-for="sel in step.items"
                        :key="sel.signature"
                        class="flex flex-col py-2 px-2.5 bg-surface-1 rounded-xl border border-surface-border/40"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-baseline gap-1.5 flex-wrap">
                                    <span v-if="sel.qty > 1" class="font-black text-xs text-primary-smart">
                                        {{ sel.qty }}x
                                    </span>
                                    <h4 class="font-bold text-xs text-surface-text leading-tight">
                                        {{ sel.item.product_name || sel.item.name }}
                                    </h4>
                                </div>

                                <p v-if="getModifiersSummary(sel)" class="text-[11px] font-medium text-surface-muted mt-1 leading-snug">
                                    {{ getModifiersSummary(sel) }}
                                </p>
                            </div>

                            <div class="shrink-0 text-right">
                                <span
                                    class="text-xs font-black"
                                    :class="totalItemPrice(sel) > 0 ? 'text-primary-smart' : 'text-surface-muted text-[10px] uppercase tracking-wider'"
                                >
                                    {{ totalItemPrice(sel) > 0 ? `+ ${$formatCurrency(totalItemPrice(sel))}` : 'Incluso' }}
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 mt-2 pt-1.5 border-t border-surface-border/30">
                            <button
                                v-if="helpers?.hasModifiers(sel.item)"
                                type="button"
                                @click="$emit('edit', sel)"
                                class="inline-flex items-center gap-1 text-[11px] font-bold text-primary-smart hover:opacity-80 active:scale-95 transition-all border-none bg-transparent cursor-pointer p-0"
                            >
                                <i class="pi pi-pencil text-[10px]"></i>
                                <span>{{ sel.qty > 1 ? 'Editar 1 un.' : 'Editar' }}</span>
                            </button>

                            <button
                                type="button"
                                @click="removeGroup(sel.instanceIds)"
                                class="inline-flex items-center gap-1 text-[11px] font-bold text-danger-500 hover:opacity-80 active:scale-95 transition-all border-none bg-transparent cursor-pointer p-0"
                            >
                                <i class="pi pi-trash text-[10px]"></i>
                                <span>Remover</span>
                            </button>
                        </div>
                    </div>

                    <div
                        v-for="n in step.remainingChoices"
                        :key="`missing-${step.groupId}-${n}`"
                        @click="$emit('select-step', step)"
                        class="flex items-center justify-between p-2.5 rounded-xl border-2 border-dashed border-amber-500/30 bg-amber-500/5 text-amber-300 transition-all duration-200 cursor-pointer hover:bg-amber-500/10 hover:border-amber-500/50 active:scale-[0.99] group"
                    >
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-8 h-8 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform text-amber-400">
                                <i class="pi pi-plus text-xs font-black"></i>
                            </div>
                            <div class="flex flex-col min-w-0">
                                <h5 class="text-xs font-black text-surface-text leading-tight group-hover:text-amber-300 transition-colors">
                                    Falta um item aqui
                                </h5>
                                <p class="text-[10px] font-medium text-amber-400/90 leading-tight mt-0.5">
                                    Complete seu pedido
                                </p>
                            </div>
                        </div>

                        <span class="text-[10px] font-black uppercase tracking-wider text-amber-400/80 shrink-0 px-2 py-0.5 rounded-md bg-amber-500/10">
                            Pendente
                        </span>
                    </div>
                </div>
            </div>

        </div>

        <div
            v-if="showFooter"
            class="p-3 shrink-0 flex items-center gap-2"
        >
            <button
                type="button"
                @click="$emit('cancel')"
                class="h-12 px-4 rounded-xl border border-surface-border bg-surface-1 text-surface-muted hover:text-surface-text hover:bg-surface-3 transition-all font-bold text-xs uppercase tracking-wider outline-none cursor-pointer"
            >
                {{ isFirstStep ? 'Cancelar' : 'Voltar' }}
            </button>

            <button
                type="button"
                @click="$emit('next')"
                :disabled="!isValid"
                class="flex-1 h-12 px-4 rounded-xl font-black text-xs uppercase tracking-widest transition-all flex items-center justify-center gap-2 outline-none shadow-md border-none"
                :class="isValid
                    ? (isLastStep ? 'bg-success-500 hover:bg-success-600 text-white cursor-pointer active:scale-[0.98]' : 'bg-primary-500 hover:bg-primary-600 text-white cursor-pointer active:scale-[0.98]')
                    : 'bg-surface-3 text-surface-muted cursor-not-allowed opacity-50'"
            >
                <span>{{ isLastStep ? 'Concluir Combo' : 'Próxima Etapa' }}</span>
                <i :class="['pi text-xs font-black', isLastStep ? 'pi-check' : 'pi-arrow-right']"></i>
            </button>
        </div>

    </div>
</template>

<script setup>
import { computed } from 'vue';
import { formatCurrency } from "@/Utils/formatters";

const props = defineProps({
    allSelections: { type: Array, default: () => [] },
    groups: { type: Array, default: () => [] },
    helpers: { type: Object, default: () => ({}) },
    isFirstStep: { type: Boolean, default: false },
    isLastStep: { type: Boolean, default: false },
    isValid: { type: Boolean, default: false },
    isTeamMode: { type: Boolean, default: false },
    isCustomerMode: { type: Boolean, default: false }
});

const emit = defineEmits(['edit', 'remove', 'cancel', 'next', 'select-step']);

const isTeamApp = computed(() => {
    if (props.isTeamMode) return true;
    if (typeof window === 'undefined') return false;
    const path = window.location.pathname;
    return path.startsWith('/team') || path.startsWith('/waiter');
});

const showFooter = computed(() => {
    return !props.isCustomerMode && !props.isTeamMode && !isTeamApp.value;
});

const resolveGroupDetails = (groupId, sel = null) => {
    const group = (props.groups || []).find(g => String(g.id) === String(groupId))
        || sel?.group
        || null;

    const title = group?.instruction || group?.name || sel?.groupName || sel?.item?.group_name || 'Item do Combo';
    const maxChoices = Number(group?.max_choices) || 1;

    return { title, maxChoices };
};

const getModifiersSummary = (sel) => {
    const mods = sel.structuredModifiers || [];
    if (!Array.isArray(mods) || mods.length === 0) return '';
    return mods.map(m => `${(Number(m.qty || 1) > 1) ? m.qty + 'x ' : ''}${m.name}`).join(', ');
};

const stepGroups = computed(() => {
    const map = new Map();

    if (props.groups && props.groups.length > 0) {
        props.groups.forEach(g => {
            const gId = g.id ?? g.group_id ?? 'default';
            const details = resolveGroupDetails(gId);
            map.set(String(gId), {
                groupId: gId,
                title: details.title,
                maxChoices: details.maxChoices,
                itemsMap: new Map(),
                totalQty: 0
            });
        });
    }

    props.allSelections.forEach(sel => {
        const gId = String(sel.groupId ?? 'default');

        if (!map.has(gId)) {
            const details = resolveGroupDetails(sel.groupId, sel);
            map.set(gId, {
                groupId: sel.groupId,
                title: details.title,
                maxChoices: details.maxChoices,
                itemsMap: new Map(),
                totalQty: 0
            });
        }

        const step = map.get(gId);
        step.totalQty += 1;

        // 🚀 ASSINATURA ESTRUTURADA: Depende exclusivamente dos IDs e quantidades dos modificadores
        const modsSignature = (sel.structuredModifiers || [])
            .map(m => `${m.id || m.name}:${m.qty || 1}`)
            .sort()
            .join('|');
        const signature = `${sel.groupId}-${sel.item?.product_id || sel.item?.id}-${modsSignature}`;

        if (step.itemsMap.has(signature)) {
            const existing = step.itemsMap.get(signature);
            existing.qty++;
            existing.instanceIds.push(sel.instanceId);
        } else {
            step.itemsMap.set(signature, {
                signature,
                qty: 1,
                instanceIds: [sel.instanceId],
                instanceId: sel.instanceId,
                item: sel.item,
                structuredModifiers: sel.structuredModifiers || [],
                extraPrice: Number(sel.extraPrice || 0)
            });
        }
    });

    return Array.from(map.values()).map(step => ({
        groupId: step.groupId,
        title: step.title,
        maxChoices: step.maxChoices,
        totalQty: step.totalQty,
        remainingChoices: Math.max(0, step.maxChoices - step.totalQty),
        items: Array.from(step.itemsMap.values())
    }));
});

const totalItemPrice = (sel) => {
    const additional = Number(sel.item?.additional_price || 0);
    const extra = Number(sel.extraPrice || 0);
    return (additional + extra) * sel.qty;
};

const removeGroup = (instanceIds) => {
    instanceIds.forEach(id => {
        emit('remove', id);
    });
};
</script>
