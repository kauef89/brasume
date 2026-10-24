<?php
$baseDir = __DIR__;

echo "1. A aplicar persistência em ShiftCashForm.vue..." . PHP_EOL;
$formPath = $baseDir . '/resources/js/Pages/Pos/Components/Cashier/ShiftCashForm.vue';
$formCode = file_get_contents($formPath);

$targetHeader = '            <!-- CABEÇALHO DO PAINEL DE CONTAGEM -->
            <div class="flex items-center gap-3 shrink-0">
                <div
                    class="w-10 h-10 rounded-2xl flex items-center justify-center shrink-0 shadow-sm"
                    :class="isOpenMode ? \'bg-primary-500/15 text-primary-500\' : \'bg-danger-500/15 text-danger-500\'"
                >
                    <i :class="[\'pi text-lg font-black\', isOpenMode ? \'pi-lock-open\' : \'pi-lock\']"></i>
                </div>
                <div>
                    <h2 class="text-xl font-black text-surface-text leading-tight">
                        {{ isOpenMode ? \'Abertura de Caixa\' : \'Fechamento de Caixa\' }}
                    </h2>
                    <p class="text-xs font-medium text-surface-muted">
                        {{ isOpenMode ? \'Informe as cédulas e moedas do fundo de troco.\' : \'Realize a contagem do dinheiro físico presente na gaveta.\' }}
                    </p>
                </div>
            </div>';

$newHeader = '            <!-- CABEÇALHO DO PAINEL DE CONTAGEM -->
            <div class="flex items-center gap-3 shrink-0">
                <div
                    class="w-10 h-10 rounded-2xl flex items-center justify-center shrink-0 shadow-sm"
                    :class="isOpenMode ? \'bg-primary-500/15 text-primary-500\' : \'bg-danger-500/15 text-danger-500\'"
                >
                    <i :class="[\'pi text-lg font-black\', isOpenMode ? \'pi-lock-open\' : \'pi-lock\']"></i>
                </div>
                <div>
                    <h2 class="text-xl font-black text-surface-text leading-tight">
                        {{ isOpenMode ? \'Abertura de Caixa\' : \'Fechamento de Caixa\' }}
                    </h2>
                    <p class="text-xs font-medium text-surface-muted">
                        {{ isOpenMode ? \'Informe as cédulas e moedas do fundo de troco.\' : \'Realize a contagem do dinheiro físico presente na gaveta.\' }}
                    </p>
                </div>
                <button
                    v-if="hasValues"
                    type="button"
                    @click="resetForm"
                    class="ml-auto px-3 py-1.5 rounded-xl bg-surface-1 hover:bg-danger-500/10 text-surface-muted hover:text-danger-500 text-xs font-bold transition-all flex items-center gap-1.5 border border-surface-border/50 cursor-pointer outline-none"
                    title="Zerar contagem atual"
                >
                    <i class="pi pi-refresh text-[10px]"></i>
                    <span>Zerar</span>
                </button>
            </div>';

if (str_contains($formCode, $targetHeader)) {
    $formCode = str_replace($targetHeader, $newHeader, $formCode);
}

$oldScriptStart = 'const simpleTotal = ref(0);
const retainedCoins = ref(0);
const counts = ref({
    b200: null, b100: null, b50: null, b20: null, b10: null, b5: null, b2: null,
    c1: null, c50: null, c25: null, c10: null, c5: null
});

// Reseta o form: chamável do componente pai
const resetForm = () => {
    simpleTotal.value = 0;
    retainedCoins.value = 0;
    Object.keys(counts.value).forEach(key => {
        counts.value[key] = null;
    });
};';

$newScriptStart = 'const storageKey = computed(() => `pos_shift_cash_${props.mode}`);

const loadPersistedData = () => {
    try {
        const raw = localStorage.getItem(`pos_shift_cash_${props.mode}`);
        if (!raw) return null;
        return JSON.parse(raw);
    } catch (e) {
        return null;
    }
};

const initialSaved = loadPersistedData();

const simpleTotal = ref(initialSaved?.simpleTotal ?? 0);
const retainedCoins = ref(initialSaved?.retainedCoins ?? 0);
const counts = ref({
    b200: null, b100: null, b50: null, b20: null, b10: null, b5: null, b2: null,
    c1: null, c50: null, c25: null, c10: null, c5: null,
    ...(initialSaved?.counts || {})
});

const hasValues = computed(() => {
    if (props.companySettings.cash_opening_mode === \'total\') {
        return Number(simpleTotal.value) > 0 || Number(retainedCoins.value) > 0;
    }
    return Object.values(counts.value).some(v => v !== null && v > 0);
});

watch(() => props.mode, () => {
    const saved = loadPersistedData();
    simpleTotal.value = saved?.simpleTotal ?? 0;
    retainedCoins.value = saved?.retainedCoins ?? 0;
    counts.value = {
        b200: null, b100: null, b50: null, b20: null, b10: null, b5: null, b2: null,
        c1: null, c50: null, c25: null, c10: null, c5: null,
        ...(saved?.counts || {})
    };
});

// Reseta o form: chamável do componente pai
const resetForm = () => {
    simpleTotal.value = 0;
    retainedCoins.value = 0;
    Object.keys(counts.value).forEach(key => {
        counts.value[key] = null;
    });
    try {
        localStorage.removeItem(storageKey.value);
    } catch (e) {}
};';

if (str_contains($formCode, $oldScriptStart)) {
    $formCode = str_replace($oldScriptStart, $newScriptStart, $formCode);
}

$oldWatch = 'watch([counts, simpleTotal, retainedCoins, calculatedTotal, coinsTotal, isConfirmDisabled], () => {
    const isDetailed = props.companySettings.cash_opening_mode !== \'total\';';

$newWatch = 'watch([counts, simpleTotal, retainedCoins, calculatedTotal, coinsTotal, isConfirmDisabled], () => {
    try {
        localStorage.setItem(storageKey.value, JSON.stringify({
            counts: counts.value,
            simpleTotal: simpleTotal.value,
            retainedCoins: retainedCoins.value
        }));
    } catch (e) {}
    const isDetailed = props.companySettings.cash_opening_mode !== \'total\';';

if (str_contains($formCode, $oldWatch)) {
    $formCode = str_replace($oldWatch, $newWatch, $formCode);
}

file_put_contents($formPath, $formCode);
echo "   -> ShiftCashForm.vue atualizado com sucesso." . PHP_EOL;

echo "2. A aplicar persistência no comentário em ShiftCashSidebar.vue..." . PHP_EOL;
$sidebarPath = $baseDir . '/resources/js/Pages/Pos/Components/Cashier/ShiftCashSidebar.vue';
$sidebarCode = file_get_contents($sidebarPath);

$targetSidebarObs = 'const isOpenMode = computed(() => props.mode === \'open\');
const observation = ref(\'\');
const isConfirming = ref(false);';

$newSidebarObs = 'const isOpenMode = computed(() => props.mode === \'open\');
const storageObsKey = computed(() => `pos_shift_obs_${props.mode}`);

const getInitialObs = () => {
    try {
        return localStorage.getItem(`pos_shift_obs_${props.mode}`) || \'\';
    } catch (e) {
        return \'\';
    }
};

const observation = ref(getInitialObs());
const isConfirming = ref(false);

watch(() => props.mode, () => {
    observation.value = getInitialObs();
});

watch(observation, (newVal) => {
    try {
        localStorage.setItem(storageObsKey.value, newVal || \'\');
    } catch (e) {}
});';

if (str_contains($sidebarCode, $targetSidebarObs)) {
    $sidebarCode = str_replace($targetSidebarObs, $newSidebarObs, $sidebarCode);
    file_put_contents($sidebarPath, $sidebarCode);
    echo "   -> ShiftCashSidebar.vue atualizado com sucesso." . PHP_EOL;
}

echo "3. A blindar preserveState e limpeza no useCashier.js..." . PHP_EOL;
$cashierJsPath = $baseDir . '/resources/js/Pages/Pos/Composables/useCashier.js';
$cashierJsCode = file_get_contents($cashierJsPath);

$targetCashierAction = '    const executeShiftAction = (finalPayload) => {
        const isClosing = shiftActionType.value === \'close\';
        const endpoint = isClosing ? route(\'operations.cashier.close\') : route(\'operations.cashier.open\');

        router.post(endpoint, finalPayload, {
            preserveState: isClosing,
            onSuccess: () => {
                isShiftModalVisible.value = false;
                pendingShiftPayload.value = null;

                if (isClosing) {
                    toast.add({ severity: \'success\', summary: \'Turno Fechado\', detail: \'O turno foi encerrado com sucesso!\', life: 4000 });
                    isLogoutPromptVisible.value = true;
                }
            },';

$newCashierAction = '    const executeShiftAction = (finalPayload) => {
        const isClosing = shiftActionType.value === \'close\';
        const endpoint = isClosing ? route(\'operations.cashier.close\') : route(\'operations.cashier.open\');

        router.post(endpoint, finalPayload, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                isShiftModalVisible.value = false;
                pendingShiftPayload.value = null;

                try {
                    localStorage.removeItem(\'pos_shift_cash_open\');
                    localStorage.removeItem(\'pos_shift_cash_close\');
                    localStorage.removeItem(\'pos_shift_obs_open\');
                    localStorage.removeItem(\'pos_shift_obs_close\');
                } catch (e) {}

                if (isClosing) {
                    toast.add({ severity: \'success\', summary: \'Turno Fechado\', detail: \'O turno foi encerrado com sucesso!\', life: 4000 });
                    isLogoutPromptVisible.value = true;
                } else {
                    toast.add({ severity: \'success\', summary: \'Caixa Aberto\', detail: \'Turno aberto com sucesso!\', life: 4000 });
                }
            },';

if (str_contains($cashierJsCode, $targetCashierAction)) {
    $cashierJsCode = str_replace($targetCashierAction, $newCashierAction, $cashierJsCode);
    file_put_contents($cashierJsPath, $cashierJsCode);
    echo "   -> useCashier.js atualizado com sucesso." . PHP_EOL;
}

echo "4. A compilar os assets do frontend..." . PHP_EOL;
echo shell_exec('npm run build 2>&1 | tail -n 5');
echo "Concluído com sucesso!" . PHP_EOL;
