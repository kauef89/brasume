<template>
    <div class="flex flex-col gap-3 animate-in fade-in slide-in-from-top-2">
        <div class="flex items-center justify-between mb-1 px-1">
            <h3 class="font-black text-surface-muted text-[11px] uppercase tracking-widest">ESTIMATIVA DE ENTREGA</h3>
            <button @click="$emit('close')" class="w-7 h-7 rounded-full text-surface-muted flex items-center justify-center outline-none p-hover">
                <i class="pi pi-times text-[10px] font-medium"></i>
            </button>
        </div>

        <div class="flex flex-wrap items-center gap-2 mt-1">

            <template v-if="estimateMode !== 'none'">
                <button
                    v-for="(est, idx) in generatedEstimates"
                    :key="idx"
                    @click="copyToClipboard(est.value)"
                    class="p-option p-hover rounded-xl flex-1 p-3 text-sm font-medium whitespace-nowrap"
                >
                    {{ est.label }}
                </button>
            </template>

            <button
                v-if="estimateBusy && estimateMode !== 'none'"
                @click="copyToClipboard('busy')"
                class="p-option p-hover rounded-xl flex-1 p-3 text-sm font-medium whitespace-nowrap"
            >
                Lotado
            </button>

            <button
                @click="copyToClipboard(null)"
                class="p-option p-hover rounded-xl flex-1 p-3 text-sm font-medium whitespace-nowrap"
            >
                Sem estimativa
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed } from "vue";
import { useToast } from "primevue/usetoast";
import { usePosSettings } from "@/Pages/Pos/Composables/usePosSettings";
import { useOrderExport } from "@/Pages/Shared/Composables/useOrderExport"; // 🚀 Importado

const props = defineProps({ cart: Array, metadata: Object, totals: Object });
const emit = defineEmits(['close']);
const toast = useToast();
const { estimateMode, estimateMin, estimateMax, estimateStep, estimateBusy } = usePosSettings();
const { buildWhatsAppSummary } = useOrderExport(); // 🚀 Instanciado

const generatedEstimates = computed(() => {
    let options = [];
    let current = estimateMin.value;
    let step = estimateStep.value > 0 ? estimateStep.value : 15;

    while (current < estimateMax.value) {
        let next = current + step;
        if (estimateMode.value === 'range') {
            options.push({ label: `${current}-${next} min`, value: `${current} a ${next} minutos` });
        } else {
            options.push({ label: `${current} min`, value: `${current} minutos` });
        }
        current = next;
    }
    return options;
});

const copyToClipboard = async (estimateType) => {
    // 🚀 Chamada limpa para o Composable!
    let text = buildWhatsAppSummary(props.cart, props.metadata, props.totals);

    if (estimateType) {
        text += `\n\n⏱️ *Previsão de Entrega:* `;
        if (estimateType === 'busy') {
            text += `Devido ao alto volume de pedidos, não conseguimos enviar uma estimativa exata no momento. Seu pedido sairá o mais rápido possível!`;
        } else {
            text += estimateType;
        }
    }

    try {
        await navigator.clipboard.writeText(text);
        toast.add({ severity: "success", summary: "Copiado!", detail: "Resumo copiado com sucesso.", life: 3000 });
        emit('close');
    } catch (err) {
        toast.add({ severity: "error", summary: "Erro", detail: "Não foi possível copiar o texto.", life: 3000 });
    }
};
</script>
