@php
    $fmt = fn($val) => 'R$ ' . number_format($val, 2, ',', '.');
    $hasModifiers = isset($modifierGroups) && $modifierGroups->count() > 0;
@endphp

<div
    x-data="{
        init() {
            // INTERCETOR DE CLIQUES PURO E DIRETO DAS ABAS
            document.addEventListener('click', (e) => {
                let tabButton = e.target.closest('.fi-tabs-item, [role=\'tab\']');
                if (tabButton) {
                    let isSimulator = tabButton.innerText.includes('Simulador') || tabButton.innerText.includes('SIMULADOR');
                    if (isSimulator) {
                        $wire.recalculateAndSync();
                    } else {
                        $dispatch('reset-margin-widget');
                    }
                }
            });
        }
    }"
    @style(['display: none' => !$hasModifiers])
    class="p-6 space-y-8"
>

    {{-- 🚀 FIELDSET: ENGENHARIA DE PREÇO & MARGEM (Ajustado e Centralizado com Space-Between) --}}
    <fieldset class="fi-fo-fieldset rounded-xl border border-gray-200 p-6 dark:border-gray-800">
        <legend class="px-2 text-sm font-medium select-none flex items-center gap-2">
            Precificação
        </legend>

        {{-- Alterado para flexbox com justify-between (space-between) e centralizado horizontalmente no container --}}
        <div class="flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-6 max-w-4xl mx-auto pt-2">

            {{-- Input 1: Preço Arbitrário (Com largura controlada) --}}
            <div class="w-full sm:max-w-xs">
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
                    Novo preço
                </label>
                <div class="flex rounded-lg border border-gray-300 dark:border-gray-700 bg-primary-500/10">
                    <span class="flex select-none items-center pl-3 text-gray-400 text-sm font-medium">R$</span>
                    <input type="text"
                        inputmode="decimal"
                        wire:model.live.debounce.800ms="customPrice"
                        wire:keydown.enter="$refresh"
                        x-on:keydown.enter="$el.blur()"
                        x-on:input="$el.value = $el.value.replace(/\./g, ',').replace(/[^0-9,]/g, '')"
                        placeholder="Ex: 29,90"
                        class="block w-full border-0 bg-transparent py-2 pl-2 text-gray-900 dark:text-white placeholder:text-gray-400 text-sm font-medium focus:outline-none focus:ring-0">
                </div>
                <p class="text-[10px] text-gray-400 mt-1">Limpe o campo (Backspace) para voltar ao padrão.</p>
            </div>

            {{-- Input 2: Margem Desejada (Com largura controlada) --}}
            <div class="w-full sm:max-w-xs">
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
                    Nova margem
                </label>
                <div class="flex rounded-lg border border-gray-300 dark:border-gray-700 bg-primary-500/10">
                    <input type="text"
                        inputmode="decimal"
                        wire:model.live.debounce.800ms="desiredMargin"
                        wire:keydown.enter="$refresh"
                        x-on:keydown.enter="$el.blur()"
                        x-on:input="$el.value = $el.value.replace(/\./g, ',').replace(/[^0-9,]/g, '')"
                        placeholder="Ex: 45"
                        class="block w-full border-0 bg-transparent py-2 pl-3 text-gray-900 dark:text-white placeholder:text-gray-400 text-sm font-medium text-right focus:outline-none focus:ring-0">
                    <span class="flex select-none items-center pr-3 text-gray-400 text-sm font-medium pl-2">%</span>
                </div>
                <p class="text-[10px] text-gray-400 mt-1">O preço se ajustará automaticamente para garantir esta margem.</p>
            </div>

        </div>
    </fieldset>

    {{-- 🚀 GRUPOS DE MODIFICADORES COM TRAVA DE LIMITE (Blindado contra o Livewire) --}}
    @if($hasModifiers)
        @foreach($modifierGroups as $group)
            @php
                $maxSelections = ($group->max_selections ?? 0) > 0 ? $group->max_selections : 'null';
                // Pegamos todos os IDs desse grupo específico para o Alpine saber quem é quem
                $optionIds = $group->options->pluck('id')->toJson();
            @endphp

            <fieldset class="fi-fo-fieldset rounded-xl border border-gray-200 p-6 dark:border-gray-800 space-y-4"
                x-data="{
                    max: {{ $maxSelections }},
                    groupOptionIds: {{ $optionIds }}.map(String),

                    // 1. Lê os itens selecionados DIRETAMENTE do cérebro do Livewire
                    get selected() {
                        return ($wire.selectedAddons || []).map(String);
                    },

                    // 2. Calcula se o limite FOI ATINGIDO analisando os itens deste grupo
                    get reached() {
                        if (this.max === null) return false;
                        let count = this.selected.filter(id => this.groupOptionIds.includes(id)).length;
                        return count >= this.max;
                    },

                    // 3. Função que diz se um botão específico deve ser bloqueado
                    isDisabled(optionId) {
                        return this.reached && !this.selected.includes(String(optionId));
                    }
                }"
            >
                <legend class="flex items-center gap-3 px-2 text-sm font-medium text-gray-950 dark:text-white select-none">
                    <span>{{ $group->name }}</span>

                    @if($group->type === 'ingredient')
                        <span class="fi-badge flex items-center justify-center rounded-md px-2 py-0.5 text-[10px] font-medium tracking-wide uppercase bg-gray-50 text-gray-600 border border-gray-200 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">ingrediente</span>
                    @else
                        <span class="fi-badge flex items-center justify-center rounded-md px-2 py-0.5 text-[10px] font-medium tracking-wide uppercase bg-amber-50 text-amber-600 border border-amber-200 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-400">adicional</span>
                    @endif

                    @if($maxSelections !== 'null')
                        <span class="text-[10px] font-medium text-gray-500 dark:text-gray-400">
                            (Máx: {{ $maxSelections }})
                        </span>
                    @endif
                </legend>

                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4 pt-2">
                    @foreach($group->options as $option)
                        {{-- O Alpine agora controla o CSS em tempo real usando x-bind:class --}}
                        <label class="flex items-start gap-3 cursor-pointer select-none group transition-opacity duration-200"
                            x-bind:class="isDisabled('{{ $option->id }}') ? 'opacity-50 cursor-not-allowed' : ''"
                        >
                            {{-- O Alpine bloqueia o input em tempo real usando x-bind:disabled --}}
                            <input type="checkbox" wire:model.live="selectedAddons" value="{{ $option->id }}"
                                x-bind:disabled="isDisabled('{{ $option->id }}')"
                                class="fi-checkbox rounded border-gray-300 text-primary-600 dark:border-gray-700 dark:bg-gray-900 mt-0.5 focus:ring-0 focus:ring-offset-0 disabled:opacity-50 disabled:cursor-not-allowed">

                            <div class="flex flex-col min-w-0">
                                <span class="fi-ta-text-item-label text-sm font-medium text-gray-700 dark:text-gray-200 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition truncate leading-tight">
                                    {{ $option->name }}
                                </span>
                                <span class="text-[10px] {{ $option->price > 0 ? 'text-emerald-600 dark:text-emerald-400 font-medium' : 'text-gray-400 dark:text-gray-500' }}">
                                    {{ $option->price > 0 ? '+ ' . $fmt($option->price) : 'padrão' }}
                                </span>
                            </div>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endforeach
    @endif
</div>
