<template>
    <div class="flex flex-col gap-6 animate-in fade-in slide-in-from-bottom-2 duration-300">

        <SettingCard title="Modo de Estimativa" description="Configure os botões de tempo ao enviar o pedido.">
            <SegmentedControl
                v-model="estimateMode"
                :options="[
                    { label: 'Desativado', value: 'none' },
                    { label: 'Exato', value: 'exact' },
                    { label: 'Faixa', value: 'range' }
                ]"
            />
        </SettingCard>

        <div v-if="estimateMode !== 'none'" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="flex flex-col gap-2 bg-surface-1 p-5 rounded-3xl border border-surface-border/50">
                <span class="micro-label text-center">Tempo Mínimo (min)</span>
                <QtyStepper v-model="estimateMin" :min="0" :step="5" class="w-full bg-transparent border-none mt-2" />
            </div>

            <div class="flex flex-col gap-2 bg-surface-1 p-5 rounded-3xl border border-surface-border/50">
                <span class="micro-label text-center">Tempo Máximo (min)</span>
                <QtyStepper v-model="estimateMax" :min="5" :step="5" class="w-full bg-transparent border-none mt-2" />
            </div>

            <div class="flex flex-col gap-2 bg-surface-1 p-5 rounded-3xl border border-surface-border/50">
                <span class="micro-label text-center">Progressão (min)</span>
                <QtyStepper v-model="estimateStep" :min="5" :step="5" class="w-full bg-transparent border-none mt-2" />
            </div>
        </div>

        <div v-if="estimateMode !== 'none'" class="flex items-center gap-4 bg-surface-1 border border-surface-border/50 p-5 rounded-3xl">
            <Checkbox v-model="estimateBusy" :binary="true" inputId="busyCheck" />
            <label for="busyCheck" class="text-sm font-medium text-surface-text cursor-pointer select-none">Ativar botão "Lotado" (Sem estimativa)</label>
        </div>

    </div>
</template>

<script setup>
import SettingCard from "./SettingCard.vue";
import Checkbox from "primevue/checkbox";
import { usePosSettings } from "@/Pages/Pos/Composables/usePosSettings";

const { estimateMode, estimateMin, estimateMax, estimateStep, estimateBusy } = usePosSettings();
</script>
