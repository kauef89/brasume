<template>
    <div class="flex flex-col h-full w-full bg-surface-1 text-surface-text overflow-hidden font-sans p-4 md:p-6 gap-6 relative">

        <Teleport v-if="isMounted && isActive" to="#dynamic-island-portal">
            <div class="flex items-center gap-1 bg-surface-1 border border-surface-border p-1 rounded-full shadow-sm mr-2">
                <button
                    v-for="printer in safePrinters"
                    :key="printer.id"
                    @click="openFallbackModal(printer)"
                    class="w-8 h-8 rounded-full flex items-center justify-center border-2 transition-colors outline-none cursor-pointer"
                    :class="getPrinterCompactClass(printer)"
                    :title="`${printer.name} - ${printer.last_status === 'failed' ? 'Falha' : (printer.fallback_printer_id ? 'Desviando Rota' : 'Online')}`"
                >
                    <i class="pi pi-print text-xs"></i>
                </button>
            </div>

            <div class="flex items-center gap-2 h-10 md:h-12 shrink-0">
                <Transition name="fade">
                    <div v-if="selectedJobs.length > 0" class="flex items-center gap-2 h-full">
                        <button @click="retrySelected" class="px-4 h-full rounded-full bg-primary-500 text-white font-black text-[10px] md:text-xs uppercase tracking-widest hover:bg-primary-600 border-none transition-colors shadow-sm outline-none cursor-pointer">
                            Reimprimir ({{ selectedJobs.length }})
                        </button>
                    </div>
                </Transition>

                <button @click="retryAllFailed" class="px-4 h-full rounded-full bg-primary-500 hover:bg-primary-600 text-[10px] md:text-xs font-black text-white uppercase tracking-widest transition-colors flex items-center gap-2 outline-none shadow-sm cursor-pointer border-none">
                    <i class="pi pi-refresh"></i>
                    <span class="hidden md:inline">Tentar Novamente Falhas</span>
                </button>
            </div>
        </Teleport>

        <div class="flex-1 flex flex-col gap-6 overflow-hidden">
            <div class="flex-1 flex flex-col bg-surface-2 rounded-[2rem] overflow-hidden min-h-0">

                <div class="flex flex-col flex-1 min-h-0">
                    <div class="flex items-center gap-4 px-6 py-3 bg-surface-2 border-b border-surface-border micro-label shrink-0 pr-8">
                        <div class="w-8 shrink-0 flex items-center justify-center cursor-pointer hover:opacity-80" @click="toggleSelectAll">
                            <Checkbox :binary="true" :modelValue="isAllSelected" />
                        </div>
                        <div class="w-16 shrink-0">Hora</div>
                        <div class="w-48 shrink-0">Impressora</div>
                        <div class="flex-1 min-w-[150px]">Tipo de Trabalho</div>
                        <div class="w-24 shrink-0 text-center">Pedido</div>
                        <div class="w-28 shrink-0 text-center">Status</div>
                        <div class="w-20 shrink-0 text-center">Ações</div>
                    </div>

                    <div class="flex-1 overflow-y-auto flex flex-col gap-0 hide-scrollbar bg-surface-2">
                        <PrintJobRow
                            v-for="job in safePrintJobs"
                            :key="job.id"
                            :job="job"
                            :is-selected="selectedJobs.includes(job.id)"
                            @toggle-select="toggleSelection"
                            @retry="retrySingle"
                        />

                        <EmptyState
                            v-if="safePrintJobs.length === 0"
                            icon="pi-print"
                            title="A fila de impressão está vazia."
                        />
                    </div>
                </div>
            </div>
        </div>

        <FallbackModal
            v-model:visible="isFallbackModalVisible"
            :printer="printerToConfigure"
            :all-printers="safePrinters"
            @save="saveFallback"
        />

    </div>
</template>

<script setup>
import axios from 'axios';
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from "@/Layouts/DesktopLayout.vue";
import PrintJobRow from "@/Pages/Operations/Printers/PrintJobRow.vue";
import FallbackModal from "@/Pages/Operations/Printers/FallbackModal.vue";
import Checkbox from 'primevue/checkbox';
import EmptyState from "@/Components/UI/EmptyState.vue";

defineOptions({ layout: AppLayout });

// 🚀 BLINDAGEM DE PROPS COM FALLBACKS DEFAULT NATIVOS DO VUE
const props = defineProps({
    printers: {
        type: Array,
        default: () => []
    },
    printJobs: {
        type: Array,
        default: () => []
    },
    isActive: {
        type: Boolean,
        default: false
    }
});

// 🚀 COMPUTEDS SEGURAS CONTRA ERROS SILENCIOSOS
const safePrinters = computed(() => Array.isArray(props.printers) ? props.printers : []);
const safePrintJobs = computed(() => Array.isArray(props.printJobs) ? props.printJobs : []);

const isMounted = ref(false);
onMounted(() => { isMounted.value = true; });

const getPrinterCompactClass = (printer) => {
    if (printer.fallback_printer_id) return 'bg-warning-500/10 border-warning-500/50 text-warning-600 hover:bg-warning-500/20';
    if (printer.last_status === 'failed') return 'bg-danger-500/10 border-danger-500/50 text-danger-500 hover:bg-danger-500/20 animate-pulse';
    return 'bg-success-500/10 border-success-500/30 text-success-500 hover:bg-success-500/20';
};

const selectedJobs = ref([]);

const isAllSelected = computed(() => {
    return safePrintJobs.value.length > 0 && selectedJobs.value.length === safePrintJobs.value.length;
});

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedJobs.value = [];
    } else {
        selectedJobs.value = safePrintJobs.value.map(j => j.id);
    }
};

const toggleSelection = (id) => {
    const idx = selectedJobs.value.indexOf(id);
    if (idx > -1) selectedJobs.value.splice(idx, 1);
    else selectedJobs.value.push(id);
};

const retrySingle = (id) => {
    axios.post(route('operations.printers.retry'), { job_ids: [id] });
};

const retrySelected = () => {
    axios.post(route('operations.printers.retry'), { job_ids: selectedJobs.value }, {
        preserveScroll: true,
        onSuccess: () => { selectedJobs.value = []; }
    });
};

const retryAllFailed = () => {
    router.post(route('operations.printers.retry-all'), { preserveState: true, }, { preserveState: true });
};

const isFallbackModalVisible = ref(false);
const printerToConfigure = ref(null);

const openFallbackModal = (printer) => {
    printerToConfigure.value = printer;
    isFallbackModalVisible.value = true;
};

const saveFallback = (payload) => {
    router.post(route('operations.printers.fallback', payload.printer_id), { preserveState: true,  fallback_printer_id: payload.fallback_printer_id }, {
        preserveScroll: true,
        onSuccess: () => { isFallbackModalVisible.value = false; }
    });
};
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
