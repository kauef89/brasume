import { computed, ref, watch } from 'vue';

export function useCashInput() {
    const isNoChange = ref(true);
    const formattedChangeTarget = ref('');

    // Transforma "50,00" no número 50.00 para envio ao backend e validações
    const numericChangeTarget = computed(() => {
        if (!formattedChangeTarget.value) return 0;
        return parseFloat(formattedChangeTarget.value.replace(/\./g, '').replace(',', '.')) || 0;
    });

    // A Mágica da digitação (da direita para a esquerda)
    const handleCashInput = (e) => {
        let val = e.target.value.replace(/\D/g, ''); // Remove absolutamente tudo que não for número

        if (!val || parseInt(val, 10) === 0) {
            formattedChangeTarget.value = '';
            isNoChange.value = true;
            return;
        }

        isNoChange.value = false;
        formattedChangeTarget.value = (parseInt(val, 10) / 100).toFixed(2).replace('.', ',');
    };

    // Se ligar o toggle "Sem Troco", limpa o input
    watch(isNoChange, (newVal) => {
        if (newVal) formattedChangeTarget.value = '';
    });

    const resetCashInput = () => {
        formattedChangeTarget.value = '';
        isNoChange.value = true;
    };

    return {
        isNoChange,
        formattedChangeTarget,
        numericChangeTarget,
        handleCashInput,
        resetCashInput
    };
}
