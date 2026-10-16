<template>
    <div
        v-if="isInAppBrowser"
        class="fixed inset-0 z-[100] bg-black/95 backdrop-blur-xl flex flex-col justify-between p-6 text-white select-none animate-in fade-in duration-300"
    >
        <!-- PONTEIRO VISUAL PARA O CANTO SUPERIOR DIREITO -->
        <div class="flex justify-end items-start w-full pt-2 pr-2 animate-bounce">
            <div class="flex flex-row items-end gap-1">
                <span class="text-[11px] font-black uppercase tracking-widest text-amber-400 bg-amber-500/20 px-3 py-1 rounded-full border border-amber-500/30">
                    Toque nos 3 pontinhos aqui
                </span>
                <i class="pi pi-arrow-up-right text-3xl text-amber-400 mr-2"></i>
            </div>
        </div>

        <!-- CONTEÚDO PRINCIPAL CENTRALIZADO -->
        <div class="flex flex-col items-center text-center max-w-sm mx-auto my-auto gap-4">

            <!-- 🛡️ IDENTIFICAÇÃO OFICIAL: LOGO OU NOME -->
            <div class="flex items-center justify-center shadow-lg animate-in fade-in slide-in-from-top-2 duration-300 mb-3">
                <!-- Opção 1: Apenas a Logo (100px e invertida para o fundo escuro) -->
                <img
                    v-if="hasLogo"
                    :src="logoUrl"
                    :alt="storeName"
                    class="w-[100px] h-auto max-h-9 object-contain invert pointer-events-none"
                />

                <!-- Opção 2: Fallback com Nome e Ícone (quando não houver logo cadastrada) -->
                <div v-else class="flex items-center gap-2">
                    <i class="pi pi-shop text-xs text-amber-400"></i>
                    <span class="text-xs font-black tracking-wide text-white uppercase truncate max-w-[200px]">
                        {{ storeName }}
                    </span>
                    <i class="pi pi-verified text-xs text-emerald-400" title="Cardápio Oficial"></i>
                </div>
            </div>

            <!-- 🐱 CABEÇA DO GATINHO CHORANDO -->
            <div class="relative group">
                <div class="absolute -inset-1.5 bg-gradient-to-r from-amber-500 via-rose-500 to-amber-500 rounded-full blur-md opacity-50 animate-pulse"></div>
                <img
                    src="/img/cat.webp"
                    alt="Gatinho chorando de fome"
                    class="relative w-28 h-28 object-cover rounded-full shadow-2xl"
                />
            </div>

            <!-- FRASE DE DESTAQUE APELATIVA -->
            <div class="flex flex-col gap-1.5 mt-1">
                <span class="text-[11px] font-black uppercase tracking-widest text-amber-400">
                    Toda vez que você usa o navegador do Insta...
                </span>
                <h2 class="text-xl font-black tracking-tight text-white leading-tight">
                    um gatinho chora!
                </h2>
                <p class="text-xs text-white/70 leading-relaxed max-w-xs mx-auto">
                    O Instagram bloqueia pagamentos via PIX e a navegação do cardápio fica péssima. Abra nosso site no navegador do celular para fazer seu pedido sem estresse!
                </p>
            </div>

            <!-- PASSO A PASSO POR SISTEMA OPERACIONAL -->
            <div class="w-full bg-white/5 border border-white/10 rounded-2xl p-4 flex flex-col gap-3 text-left">
                <div class="flex items-center gap-3">
                    <span class="w-6 h-6 rounded-full bg-amber-400 text-black text-xs font-black flex items-center justify-center shrink-0">1</span>
                    <span class="text-xs font-bold text-white/90">
                        Toque nos <b>três pontinhos (...)</b> no topo da tela.
                    </span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="w-6 h-6 rounded-full bg-amber-400 text-black text-xs font-black flex items-center justify-center shrink-0">2</span>
                    <span class="text-xs font-bold text-white/90">
                        Selecione
                        <span class="text-amber-300 font-extrabold">
                            {{ os === 'ios' ? '"Abrir no Safari"' : '"Abrir no Chrome / Navegador"' }}
                        </span>.
                    </span>
                </div>
            </div>

            <!-- BOTÃO DIRETO PARA ANDROID (INTENT CHROME) -->
            <a
                v-if="os === 'android'"
                :href="getAndroidIntentUrl()"
                class="w-full h-12 bg-amber-400 hover:bg-amber-300 text-black font-black text-sm uppercase tracking-widest rounded-full flex items-center justify-center gap-2 shadow-lg shadow-amber-400/25 transition-all outline-none"
            >
                <i class="pi pi-external-link text-sm"></i>
                Abrir Direto no Chrome
            </a>
        </div>

        <!-- OPÇÃO DISCRETA DE CONTINUAR -->
        <div class="w-full text-center pb-2">
            <button
                type="button"
                @click="isInAppBrowser = false"
                class="text-[11px] font-bold text-white/40 hover:text-white/60 bg-transparent border-none outline-none cursor-pointer underline"
            >
                Continuar aqui mesmo (pode travar no pedido)
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useInAppBrowser } from '@/Composables/useInAppBrowser';

const page = usePage();
const { isInAppBrowser, os, getAndroidIntentUrl } = useInAppBrowser();

const companySettings = computed(() => page.props.companySettings || page.props.company || {});

const hasLogo = computed(() => {
    return !!(companySettings.value.logo_svg_path || companySettings.value.logo_path);
});

const logoUrl = computed(() => {
    const path = companySettings.value.logo_svg_path || companySettings.value.logo_path;
    return path ? `/media/file?path=${path}` : '';
});

const storeName = computed(() => {
    return companySettings.value.trade_name || companySettings.value.company_name || 'Lanchonete';
});
</script>
