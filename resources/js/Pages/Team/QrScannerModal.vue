<template>
    <BaseModal
        :modelValue="modelValue"
        @update:modelValue="$emit('update:modelValue', $event)"
        title="Escaneie o QR Code"
        icon="pi-camera"
        width="400px"
    >
        <div class="flex flex-col items-center justify-center p-4 min-h-[300px] relative">

            <!-- Leitor de QR Code -->
            <div class="w-full h-64 rounded-2xl overflow-hidden bg-black relative border-2 border-surface-border">
                <qrcode-stream
                    v-if="modelValue"
                    @detect="$emit('detect', $event)"
                    @error="$emit('error', $event)"
                    :track="paintBoundingBox"
                ></qrcode-stream>

                <!-- Tela de carregamento -->
                <div v-if="isAuthorizing" class="absolute inset-0 bg-surface-900/80 backdrop-blur-sm flex flex-col items-center justify-center text-white z-10">
                    <i class="pi pi-spin pi-spinner text-4xl text-primary-500 mb-3"></i>
                    <span class="font-black tracking-widest uppercase text-xs">Autorizando...</span>
                </div>
            </div>

            <p class="text-xs text-surface-muted mt-4 font-medium text-center">
                Aponte a câmara para a tela do computador que deseja liberar.
            </p>
        </div>
    </BaseModal>
</template>

<script setup>
import { QrcodeStream } from 'vue-qrcode-reader';

defineProps({
    modelValue: Boolean,
    isAuthorizing: Boolean
});

defineEmits(['update:modelValue', 'detect', 'error']);

// A lógica visual de desenhar o quadrado de rastreio fica encapsulada aqui!
const paintBoundingBox = (detectedCodes, ctx) => {
    for (const detectedCode of detectedCodes) {
        const { boundingBox: { x, y, width, height } } = detectedCode;
        ctx.lineWidth = 4;
        ctx.strokeStyle = '#00d492';
        ctx.strokeRect(x, y, width, height);
    }
};
</script>
