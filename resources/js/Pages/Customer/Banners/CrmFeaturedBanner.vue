// serve para mostrar um cupom de conversão na home

<template>
    <div v-if="coupon" class="mb-6 px-4 animate-in fade-in zoom-in-95 duration-300">
        <div
            class="c-glass-card p-5 md:p-6 relative select-none"
            :class="[
                bannerImage
                    ? 'bg-cover bg-center'
                    : coupon.purpose === 'birthday'
                        ? '!bg-pink-600/30 backdrop-blur-2xl'
                        : coupon.purpose === 'churn'
                            ? '!bg-amber-600/30 backdrop-blur-2xl'
                            : '!bg-primary-600/30 backdrop-blur-2xl'
            ]"
            :style="bannerImage ? { backgroundImage: `linear-gradient(rgba(0,0,0,0.65), rgba(0,0,0,0.85)), url(${bannerImage})` } : {}"
        >
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/10 backdrop-blur-md text-white/90 text-[10px] font-black uppercase tracking-wider mb-3">
                <i :class="[
                    'pi text-xs',
                    coupon.purpose === 'birthday' ? 'pi-gift text-yellow-300' :
                    coupon.purpose === 'churn' ? 'pi-heart text-rose-300' : 'pi-sparkles text-yellow-300'
                ]"></i>
                <span>
                    {{
                        coupon.purpose === 'birthday' ? 'Presente de Aniversário 🎉' :
                        coupon.purpose === 'churn' ? 'Que Saudades de Você! 🍔' : 'Especial para Você'
                    }}
                </span>
            </div>

            <div class="max-w-md relative z-10">
                <h3 class="c-title text-xl md:text-2xl">
                    {{ coupon.label }}
                </h3>
                <p class="c-text-muted mt-1 leading-snug">
                    {{ coupon.description }}
                </p>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <div class="bg-black/50 backdrop-blur-md px-3.5 py-2 rounded-xl font-mono text-xs font-black tracking-wider text-yellow-300 flex items-center gap-2">
                        <i class="pi pi-ticket text-xs"></i>
                        <span>{{ coupon.code }}</span>
                    </div>

                    <button
                        @click="$emit('use-coupon', coupon.code)"
                        class="c-btn-ghost !h-10 !px-5 !text-xs !bg-white !text-gray-900 active:!bg-white/90"
                    >
                        <span>Aproveitar Agora</span>
                        <i class="pi pi-arrow-right text-[10px]"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    coupon: { type: Object, default: null },
    companySettings: { type: Object, default: () => ({}) }
});

defineEmits(['use-coupon']);

const bannerImage = computed(() => {
    if (!props.coupon || !props.companySettings) return null;

    if (props.coupon.purpose === 'birthday' && props.companySettings.birthday_banner_path) {
        return `/media/file?path=${props.companySettings.birthday_banner_path}`;
    }
    if (props.coupon.purpose === 'churn' && props.companySettings.churn_banner_path) {
        return `/media/file?path=${props.companySettings.churn_banner_path}`;
    }
    return null;
});
</script>
