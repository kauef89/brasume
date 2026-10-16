<template>
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="visible" class="fixed inset-0 z-[100] flex items-end md:items-center justify-center pointer-events-auto">
                <div class="absolute inset-0 bg-black/55 transition-opacity cursor-pointer" @click="close"></div>

                <Transition name="slide-up">
                    <div v-if="visible"
                         v-bind="$attrs"
                         class="relative bg-surface-3/75 saturate-100 dark:bg-surface-1/30 dark:saturate-100 border border-white/10 backdrop-blur-xl w-full md:w-auto min-w-[320px] max-w-full md:max-w-lg lg:max-w-2xl max-h-[90dvh] flex flex-col md:rounded-3xl rounded-t-[2rem] shadow-[0_-20px_60px_rgba(0,0,0,0.85)] overflow-hidden pointer-events-auto z-10"
                         @click.stop>

                        <div class="md:hidden w-full flex justify-center pt-4 pb-2 cursor-pointer" @click="close">
                            <div class="w-12 h-1.5 bg-surface-border/80 rounded-full"></div>
                        </div>

                        <!-- 🚀 REMOVIDO O p-6! O conteúdo dita o próprio respiro e a altura é flexível -->
                        <div class="overflow-y-auto hide-scrollbar flex-1 min-h-0 w-full flex flex-col">
                            <slot></slot>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { watch, onUnmounted } from 'vue';

defineOptions({ inheritAttrs: false });
const props = defineProps({ visible: { type: Boolean, required: true } });
const emit = defineEmits(['update:visible', 'close', 'save']);

const close = () => { emit('update:visible', false); emit('close'); };
const handleEscape = (e) => { if (e.key === 'Escape' && props.visible) close(); };

watch(() => props.visible, (isVisible) => {
    if (typeof document !== 'undefined') {
        if (isVisible) { document.body.style.overflow = 'hidden'; document.addEventListener('keydown', handleEscape); }
        else { document.body.style.overflow = ''; document.removeEventListener('keydown', handleEscape); }
    }
});

onUnmounted(() => {
    if (typeof document !== 'undefined') { document.body.style.overflow = ''; document.removeEventListener('keydown', handleEscape); }
});
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.slide-up-enter-active, .slide-up-leave-active { transition: transform 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.1), opacity 0.3s ease; }
.slide-up-enter-from, .slide-up-leave-to { transform: translateY(100%); opacity: 0; }
@media (min-width: 768px) {
    .slide-up-enter-from, .slide-up-leave-to { transform: scale(0.95) translateY(10px); opacity: 0; }
    .slide-up-enter-active, .slide-up-leave-active { transition: transform 0.2s ease-out, opacity 0.2s ease-out; }
}
</style>
