<x-filament-panels::page>
    <div class="space-y-6">

        {{-- 1. CARDS DE RESUMO DA AUDITORIA --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            {{-- Gaveta Física --}}
            <div class="p-4 rounded-xl border bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800 shadow-sm">
                <div class="flex items-center justify-between text-xs font-semibold text-gray-500 uppercase">
                    <span>💵 Gaveta (Dinheiro)</span>
                    @if(abs($diag['cash_difference']) < 0.05)
                        <span class="px-2 py-0.5 rounded text-xs bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">Exato</span>
                    @elseif($diag['cash_difference'] > 0)
                        <span class="px-2 py-0.5 rounded text-xs bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300">Sobra</span>
                    @else
                        <span class="px-2 py-0.5 rounded text-xs bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300">Falta</span>
                    @endif
                </div>
                <div class="mt-2 text-2xl font-bold {{ $diag['cash_difference'] < -0.05 ? 'text-rose-600' : ($diag['cash_difference'] > 0.05 ? 'text-blue-600' : 'text-gray-900 dark:text-white') }}">
                    R$ {{ number_format($shift->closing_balance_actual, 2, ',', '.') }}
                </div>
                <div class="mt-1 text-xs text-gray-500">
                    Esperado: R$ {{ number_format($shift->closing_balance_expected, 2, ',', '.') }} |
                    Dif: <strong class="{{ $diag['cash_difference'] < -0.05 ? 'text-rose-600' : ($diag['cash_difference'] > 0.05 ? 'text-blue-600' : 'text-emerald-600') }}">
                        R$ {{ number_format($diag['cash_difference'], 2, ',', '.') }}
                    </strong>
                </div>
            </div>

            {{-- Adquirente / Maquininhas --}}
            <div class="p-4 rounded-xl border bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800 shadow-sm">
                <div class="flex items-center justify-between text-xs font-semibold text-gray-500 uppercase">
                    <span>💳 Maquininhas (POS/TEF)</span>
                    @if(!$hasSettlementsDeclared && !$hasAcquirerImported)
                        <span class="px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">Sem Fita</span>
                    @elseif(abs($diag['electronic_difference']) < 0.05)
                        <span class="px-2 py-0.5 rounded text-xs bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">100% Batido</span>
                    @else
                        <span class="px-2 py-0.5 rounded text-xs bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">Divergente</span>
                    @endif
                </div>
                <div class="mt-2 text-2xl font-bold {{ (!$hasSettlementsDeclared && !$hasAcquirerImported) ? 'text-gray-500' : (abs($diag['electronic_difference']) >= 0.05 ? 'text-amber-600' : 'text-gray-900 dark:text-white') }}">
                    @if(!$hasSettlementsDeclared && !$hasAcquirerImported)
                        <span class="text-base font-medium text-gray-400">Não Auditável</span>
                    @else
                        R$ {{ number_format($diag['electronic_difference'], 2, ',', '.') }}
                    @endif
                </div>
                <div class="mt-1 text-xs text-gray-500">
                    @if(!$hasSettlementsDeclared && !$hasAcquirerImported)
                        Turno antigo sem fita digitada e sem CSV
                    @else
                        Fitas: {{ $acqTransactions->count() }} transações conciliadas
                    @endif
                </div>
            </div>

            {{-- Pix Direto (Banco/Chave) --}}
            <div class="p-4 rounded-xl border bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800 shadow-sm">
                <div class="flex items-center justify-between text-xs font-semibold text-gray-500 uppercase">
                    <span>💠 Pix Bancário (Chave/API)</span>
                    @if($bankStatements->isEmpty())
                        <span class="px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">Sem Extrato</span>
                    @elseif(abs($pixDiff) < 0.05 && $directPixPayments->count() === $pixMatches->count())
                        <span class="px-2 py-0.5 rounded text-xs bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">100% Batido</span>
                    @else
                        <span class="px-2 py-0.5 rounded text-xs bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">Divergente</span>
                    @endif
                </div>

                <div class="mt-2 text-2xl font-bold {{ $bankStatements->isEmpty() ? 'text-gray-500' : (abs($pixDiff) >= 0.05 ? 'text-amber-600' : 'text-gray-900 dark:text-white') }}">
                    R$ {{ number_format($pixSystemTotal, 2, ',', '.') }}
                </div>

                <div class="mt-1 text-xs text-gray-500">
                    @if($bankStatements->isEmpty())
                        Aguardando importação de extrato
                    @else
                        Extrato: R$ {{ number_format($pixStatementTotal, 2, ',', '.') }} | 
                        Dif: <strong class="{{ abs($pixDiff) < 0.05 ? 'text-emerald-600' : 'text-amber-600' }}">
                            R$ {{ number_format($pixDiff, 2, ',', '.') }}
                        </strong>
                        ({{ $pixMatches->count() }} de {{ $directPixPayments->count() }} conciliados)
                    @endif
                </div>
            </div>

            {{-- Quebra Líquida Final --}}
            @php
                $hasRealDiscrepancy = abs($diag['cash_difference']) >= 0.05;
                if ($hasSettlementsDeclared || $hasAcquirerImported) {
                    $hasRealDiscrepancy = $hasRealDiscrepancy || abs($diag['electronic_difference']) >= 0.05;
                }
            @endphp
            <div class="p-4 rounded-xl border {{ $hasRealDiscrepancy ? 'bg-rose-50 border-rose-200 dark:bg-rose-950/30 dark:border-rose-900' : 'bg-emerald-50 border-emerald-200 dark:bg-emerald-950/30 dark:border-emerald-900' }} shadow-sm">
                <div class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                    ⚖ Integridade Geral do Turno
                </div>
                <div class="mt-2 text-2xl font-bold {{ $hasRealDiscrepancy ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                    @if($hasRealDiscrepancy)
                        @if(abs($diag['cash_difference']) >= 0.05 && ($hasSettlementsDeclared || $hasAcquirerImported) && abs($diag['electronic_difference']) >= 0.05)
                            Divergência Gaveta + POS
                        @elseif(abs($diag['cash_difference']) >= 0.05)
                            Divergência na Gaveta
                        @else
                            Divergência na Maquininha
                        @endif
                    @else
                        100% Conciliado
                    @endif
                </div>
                <div class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                    @if($hasSettlementsDeclared || $hasAcquirerImported)
                        Quebra Adquirente: R$ {{ number_format($diag['electronic_difference'], 2, ',', '.') }}
                    @else
                        Maquininhas não auditadas neste turno
                    @endif
                </div>
            </div>
        </div>

        {{-- 2. TRANSAÇÕES ÓRFÃS DA MAQUININHA --}}
        @if($orphanAcq->isNotEmpty())
            <div class="p-5 rounded-xl bg-orange-50 dark:bg-orange-950/30 border border-orange-300 dark:border-orange-800 shadow-sm space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="font-bold text-orange-900 dark:text-orange-200 text-sm flex items-center gap-2">
                            <span>💳</span> Transações da Maquininha Sem Pedido no Caixa ({{ $orphanAcq->count() }} Órfãs)
                        </h4>
                        <p class="text-xs text-orange-800 dark:text-orange-300 mt-1">
                            Valores capturados pelas maquininhas durante este turno que não possuem lançamento correspondente no PDV.
                        </p>
                    </div>
                </div>

                <div class="divide-y divide-orange-200 dark:divide-orange-800/60">
                    @foreach($orphanAcq as $oTx)
                        <div class="py-3 flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs">
                            <div class="font-mono">
                                <span class="font-bold text-gray-900 dark:text-white text-sm">R$ {{ number_format($oTx->gross_amount, 2, ',', '.') }}</span>
                                <span class="mx-2 text-gray-400">•</span>
                                <span class="font-semibold text-orange-950 dark:text-orange-200">{{ $oTx->modality }} {{ $oTx->brand }}</span>
                                <span class="mx-2 text-gray-400">•</span>
                                <span>NSU: {{ $oTx->nsu }}</span>
                                <span class="mx-2 text-gray-400">•</span>
                                <span>TID: {{ $oTx->terminal_code }}</span>
                                <span class="mx-2 text-gray-400">•</span>
                                <span class="text-gray-500">{{ $oTx->transaction_date->format('H:i:s') }}</span>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <button 
                                    wire:click="abrirModalVendaAvulsa({{ $oTx->id }})"
                                    type="button"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold transition shadow-sm"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                    Gerar Venda Avulsa
                                </button>
                                <button 
                                    wire:click="ignorarTransacaoMaquininha({{ $oTx->id }})"
                                    type="button"
                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-gray-200 hover:bg-gray-300 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg text-xs font-semibold transition"
                                >
                                    Ignorar
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- 3. ALERTA DE CONFLITO DOCUMENTAL --}}
        @if(!empty($documentalConflicts))
            <div class="space-y-3">
                @foreach($documentalConflicts as $conflict)
                    <div class="p-5 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-700 shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded text-xs font-bold bg-amber-200 text-amber-900 dark:bg-amber-900 dark:text-amber-200">
                                    🚨 Conflito Documental Identificado
                                </span>
                                <span class="text-xs font-bold text-gray-900 dark:text-white">
                                    Pedido #{{ $conflict['order_id'] }} ({{ $conflict['customer_name'] }}) - R$ {{ number_format($conflict['amount'], 2, ',', '.') }}
                                </span>
                            </div>
                            <p class="text-xs text-amber-900 dark:text-amber-200 leading-relaxed">
                                No PDV foi registrado como <strong>"{{ $conflict['current_method_name'] }}"</strong>, porém a maquininha comprovou a transação via <strong>"{{ $conflict['acquirer_proof'] }}"</strong>.
                            </p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button 
                                wire:click="retificarFormaPagamento({{ $conflict['payment_id'] }}, {{ $conflict['suggested_method_id'] }})"
                                type="button"
                                class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-bold transition shadow-sm"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                Retificar para {{ $conflict['suggested_method_name'] }}
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- 4. AJUSTE DE GAVETA: LANÇAMENTO DE SANGRIA/DESPESA NÃO REGISTRADA --}}
        @if($diag['cash_difference'] < -0.05)
            <div class="p-5 rounded-xl bg-rose-50 dark:bg-rose-950/30 border border-rose-300 dark:border-rose-800 shadow-sm space-y-3">
                <h4 class="font-bold text-rose-900 dark:text-rose-200 text-sm flex items-center gap-2">
                    <span>💵</span> Falta de Dinheiro na Gaveta (R$ {{ number_format(abs($diag['cash_difference']), 2, ',', '.') }})
                </h4>
                <p class="text-xs text-rose-800 dark:text-rose-300">
                    Se este valor foi retirado do cofre para pagamento de insumos, motoboy ou fornecedor e o operador esqueceu de lançar a sangria, registre o ajuste abaixo:
                </p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 pt-1">
                    <input 
                        type="number" 
                        step="0.01" 
                        wire:model="sangriaAmount" 
                        placeholder="Valor da Sangria (Ex: {{ abs($diag['cash_difference']) }})" 
                        class="rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm"
                    />
                    <input 
                        type="text" 
                        wire:model="sangriaReason" 
                        placeholder="Motivo / Fornecedor (Ex: Compra de Pão / Adiantamento)" 
                        class="rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm"
                    />
                    <button 
                        wire:click="registrarSangriaEsquecida"
                        type="button"
                        class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-xs font-bold transition"
                    >
                        Registrar Sangria & Recalcular Gaveta
                    </button>
                </div>
            </div>
        @endif

        {{-- 5. TABELA ANALÍTICA DE PEDIDOS E CONCILIAÇÃO DOCUMENTAL --}}
        <div class="rounded-xl border bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-800 flex justify-between items-center">
                <h3 class="text-base font-bold text-gray-900 dark:text-white">
                    Raio-X de Pagamentos do Turno ({{ $payments->count() }} Lançamentos)
                </h3>
                <span class="text-xs text-gray-500">
                    Cruzamento direto com adquirente e extratos
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50 text-xs font-semibold uppercase text-gray-500 tracking-wider">
                        <tr>
                            <th class="px-4 py-3">Pedido / Canal</th>
                            <th class="px-4 py-3">Horário</th>
                            <th class="px-4 py-3">Cliente</th>
                            <th class="px-4 py-3">Forma no PDV</th>
                            <th class="px-4 py-3">Valor</th>
                            <th class="px-4 py-3">Conciliação Documental</th>
                            <th class="px-4 py-3">Detalhes da Fita / Extrato</th>
                            <th class="px-4 py-3 text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @foreach($payments as $p)
                            @php
                                $order = $p->order;
                                $engine = $p->paymentMethod?->engine_type;
                                $engineVal = $engine instanceof \BackedEnum ? $engine->value : (string) $engine;
                                $pMethodName = mb_strtolower($p->method ?? '');

                                // Classificação robusta compatível com histórico legado
                                $isCash = ($engineVal === 'money') || str_contains($pMethodName, 'dinheiro') || str_contains($pMethodName, 'cash');
                                $isDirectPix = in_array($engineVal, ['pix_static', 'bank_api']) || str_contains($pMethodName, 'chave') || (str_contains($pMethodName, 'pix') && !str_contains($pMethodName, 'máq') && !str_contains($pMethodName, 'maq'));
                                $isCardOrMachine = in_array($engineVal, ['manual', 'smart_pos', 'tef', 'pix_machine']) || str_contains($pMethodName, 'créd') || str_contains($pMethodName, 'cred') || str_contains($pMethodName, 'déb') || str_contains($pMethodName, 'deb') || str_contains($pMethodName, 'máq') || str_contains($pMethodName, 'maq');
                                $isInternal = in_array($engineVal, ['internal', 'courtesy', 'staff']) || str_contains($pMethodName, 'interno') || str_contains($pMethodName, 'cortesia') || str_contains($pMethodName, 'bonif');

                                $acqObj = $p->acquirerTransaction ?? $acqTransactions->get($p->id) ?? $acqTransactions->get("order_{$p->order_id}");
                                $statusVal = $acqObj ? ($acqObj->status instanceof \BackedEnum ? $acqObj->status->value : $acqObj->status) : null;
                                $isMatched = ($statusVal === 'matched');
                                $isBundle = $isMatched && !empty($acqObj->notes) && str_contains($acqObj->notes, 'Lote');

                                $pixMatch = $pixMatches->get($p->id);

                                $hasConflict = false;
                                $targetMethodToSuggest = null;

                                if ($isMatched && $acqObj) {
                                    $acqMod = mb_strtolower($acqObj->modality ?? '');
                                    if ($isCash || $isDirectPix) {
                                        $hasConflict = true;
                                        $targetMethodToSuggest = (str_contains($acqMod, 'créd') || str_contains($acqMod, 'cred')) ? $creditMethod : $debitMethod;
                                    } elseif (str_contains($acqMod, 'créd') && str_contains($pMethodName, 'déb')) {
                                        $hasConflict = true;
                                        $targetMethodToSuggest = $creditMethod;
                                    } elseif (str_contains($acqMod, 'déb') && str_contains($pMethodName, 'créd')) {
                                        $hasConflict = true;
                                        $targetMethodToSuggest = $debitMethod;
                                    }
                                }
                            @endphp
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition {{ $hasConflict ? 'bg-amber-50/40 dark:bg-amber-950/20' : '' }}">
                                <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">
                                    #{{ $p->order_id }}
                                    @if($order?->type === 'delivery')
                                        <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300">Delivery</span>
                                    @elseif($order?->type === 'table')
                                        <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">Mesa {{ $order->table_number }}</span>
                                    @elseif($order?->type === 'pickup')
                                        <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300">Balcão/Retirada</span>
                                    @else
                                        <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">Balcão</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500">
                                    {{ $p->created_at?->format('H:i') }}
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-700 dark:text-gray-300">
                                    {{ $order?->customer_name ?? 'Consumidor Final' }}
                                </td>
                                <td class="px-4 py-3 font-medium">
                                    <span class="{{ $hasConflict ? 'line-through text-gray-400' : '' }}">
                                        {{ $p->method }}
                                    </span>
                                    @if($hasConflict && $targetMethodToSuggest)
                                        <div class="text-[11px] font-bold text-amber-700 dark:text-amber-300">
                                            👉 {{ $targetMethodToSuggest->name }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-bold text-gray-900 dark:text-white">
                                    R$ {{ number_format($p->amount, 2, ',', '.') }}
                                </td>

                                {{-- COLUNA DE STATUS DE CONCILIAÇÃO --}}
                                <td class="px-4 py-3">
                                    @if($hasConflict)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950/70 dark:text-amber-300">
                                            ⚠️ Conflito de Modalidade
                                        </span>
                                    @elseif($isCash)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-950/70 dark:text-blue-300">
                                            💵 Gaveta Física
                                        </span>
                                    @elseif($isDirectPix)
                                        @if($pixMatch)
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                Conciliado no Extrato
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-purple-100 text-purple-800 dark:bg-purple-950/70 dark:text-purple-300">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                                Chave Pix (Extrato)
                                            </span>
                                        @endif
                                    @elseif($isCardOrMachine)
                                        @if($isBundle)
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-teal-100 text-teal-800 dark:bg-teal-950/70 dark:text-teal-300">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                                                Conciliado em Lote (1:N)
                                            </span>
                                        @elseif($isMatched)
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                Conciliado na Adquirente
                                            </span>
                                        @elseif(!$hasAcquirerImported && !$hasSettlementsDeclared)
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                                ⚪ Sem Arquivo da Adquirente
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-950/70 dark:text-rose-300">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                Sem Comprovante na Fita
                                            </span>
                                        @endif
                                    @elseif($isInternal)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300">
                                            Interno / Bonificação
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300">
                                            {{ $p->method ?? 'Outro' }}
                                        </span>
                                    @endif
                                </td>

                                {{-- COLUNA DE DETALHES COMPROBATÓRIOS --}}
                                <td class="px-4 py-3 text-xs">
                                    @if($isMatched && $acqObj)
                                        <div class="text-gray-700 dark:text-gray-300 font-mono text-[11px]">
                                            <strong>{{ $acqObj->modality }} {{ $acqObj->brand }}</strong> • NSU: {{ $acqObj->nsu }} • TID: {{ $acqObj->terminal_code }}
                                            @if($isBundle)
                                                <div class="text-[10px] font-semibold text-teal-700 dark:text-teal-400">
                                                    🔗 Lote Total: R$ {{ number_format($acqObj->gross_amount, 2, ',', '.') }} (Fatia deste pedido: R$ {{ number_format($p->amount, 2, ',', '.') }})
                                                </div>
                                            @else
                                                <div class="text-[10px] text-gray-500">
                                                    Bruto: R$ {{ number_format($acqObj->gross_amount, 2, ',', '.') }} | Taxa: R$ {{ number_format($acqObj->fee_amount, 2, ',', '.') }} | Líq: R$ {{ number_format($acqObj->net_amount, 2, ',', '.') }}
                                                </div>
                                            @endif
                                        </div>
                                    @elseif($isCardOrMachine)
                                        @if(!$hasAcquirerImported && !$hasSettlementsDeclared)
                                            <span class="text-gray-400 text-[11px]">
                                                Importe o relatório da adquirente desta data para conciliar os cartões
                                            </span>
                                        @else
                                            <span class="text-rose-600 dark:text-rose-400 font-medium">
                                                ⚠ Não localizado no relatório da adquirente
                                            </span>
                                        @endif
                                    @elseif($isDirectPix)
                                        @if($pixMatch)
                                            <div class="text-emerald-700 dark:text-emerald-400 font-mono text-[11px]">
                                                <strong>Extrato Sicredi:</strong> {{ $pixMatch->description ?? 'Crédito Pix' }} (Doc: {{ $pixMatch->document_number ?? $pixMatch->id }})
                                            </div>
                                        @else
                                            <span class="text-gray-500">
                                                Aguardando importação do extrato bancário (OFX/API) do dia
                                            </span>
                                        @endif
                                    @elseif($isCash)
                                        <span class="text-gray-500">
                                            Conferido no fechamento físico do cofre
                                        </span>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>

                                {{-- COLUNA DE AÇÃO --}}
                                <td class="px-4 py-3 text-center">
                                    @if($hasConflict && $targetMethodToSuggest)
                                        <button 
                                            wire:click="retificarFormaPagamento({{ $p->id }}, {{ $targetMethodToSuggest->id }})"
                                            type="button"
                                            title="Retificar para {{ $targetMethodToSuggest->name }}"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-600 hover:bg-amber-700 text-white rounded text-xs font-bold transition shadow-sm"
                                        >
                                            Corrigir
                                        </button>
                                    @elseif(isset($paymentCountByOrder) && $paymentCountByOrder->get($p->order_id)?->count() > 1 && !$pixMatches->has($p->id))
                                        <button 
                                            wire:click="removerPagamentoDuplicado({{ $p->id }})"
                                            type="button"
                                            title="Remover este lançamento duplicado sem comprovante no extrato"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 bg-rose-600 hover:bg-rose-700 text-white rounded text-xs font-bold transition shadow-sm"
                                        >
                                            <x-filament::icon icon="heroicon-m-trash" class="w-3.5 h-3.5" />
                                            Remover Duplicado
                                        </button>
                                    @else
                                        <span class="text-gray-400 text-xs">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- 6. PARECER DO GESTOR --}}
        <div class="p-6 rounded-xl border bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800 shadow-sm space-y-4">
            <h3 class="text-base font-bold text-gray-900 dark:text-white">
                Parecer de Auditoria & Ciência do Gestor
            </h3>
            <textarea 
                wire:model="managerNotes" 
                rows="3" 
                class="w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm focus:ring-primary-500 focus:border-primary-500"
                placeholder="Registre as justificativas ou observações da auditoria deste turno..."
            ></textarea>
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-3 border-t border-gray-200 dark:border-gray-800">
                <div class="text-xs text-gray-500">
                    Status da Auditoria: 
                    <span class="font-bold text-gray-900 dark:text-white">
                        {{ $shift->status instanceof \App\Enums\ShiftStatus ? $shift->status->getLabel() : ($shift->status ?? 'Pendente') }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <button 
                        wire:click="saveNotes" 
                        type="button" 
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg text-xs font-bold transition"
                    >
                        Salvar Parecer
                    </button>

                    <button 
                        wire:click="homologarAuditoria" 
                        type="button" 
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold shadow-md transition flex items-center gap-1.5"
                    >
                        <x-filament::icon icon="heroicon-m-check-badge" class="w-4 h-4" />
                        <span>Concluir & Homologar Turno</span>
                    </button>
                </div>
            </div>
        </div>

    </div>

{{-- 🚀 MODAL INTERATIVO: GERAR VENDA AVULSA RETROATIVA --}}
    @if($showRetroactiveModal)
        <div class="fixed inset-0 z-[100] overflow-y-auto bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="relative w-full max-w-2xl bg-white dark:bg-stone-900 rounded-3xl shadow-2xl border border-stone-200 dark:border-stone-800 p-6 flex flex-col gap-5 animate-in fade-in zoom-in-95 duration-200">
                
                {{-- CABEÇALHO DO MODAL --}}
                <div class="flex items-center justify-between border-b border-stone-200 dark:border-stone-800 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-black">
                            <x-filament::icon icon="heroicon-m-shopping-cart" class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-black text-stone-900 dark:text-white leading-tight">Gerar Venda Avulsa da Maquininha</h3>
                            <p class="text-xs text-stone-500 mt-0.5">{{ $retroactiveTxInfo }}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-[10px] uppercase font-bold text-stone-400 block tracking-wider">Valor Alvo</span>
                        <span class="text-xl font-black text-emerald-600 dark:text-emerald-400">R$ {{ number_format($retroactiveTargetAmount, 2, ',', '.') }}</span>
                    </div>
                </div>

                {{-- BLOCO 1: SUGESTÕES RÁPIDAS (HEURÍSTICA INTELIGENTE) --}}
                @if(!empty($suggestedCombinations))
                    <div class="flex flex-col gap-2">
                        <span class="text-xs font-black uppercase tracking-wider text-stone-400 flex items-center gap-1.5">
                            <x-filament::icon icon="heroicon-m-sparkles" class="w-4 h-4 text-amber-500" /> Sugestões que fecham a conta:
                        </span>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @foreach($suggestedCombinations as $idx => $sug)
                                <button
                                    type="button"
                                    wire:click="aplicarSugestao({{ $idx }})"
                                    class="p-2.5 rounded-xl border text-left transition flex items-center justify-between text-xs font-bold outline-none cursor-pointer bg-stone-50 dark:bg-stone-800/60 hover:bg-emerald-50 hover:border-emerald-500/50 dark:hover:bg-emerald-950/20 border-stone-200 dark:border-stone-700 text-stone-700 dark:text-stone-200"
                                >
                                    <span class="truncate mr-2">{{ $sug['label'] }}</span>
                                    <x-filament::icon icon="heroicon-m-arrow-right" class="w-3.5 h-3.5 text-emerald-500 shrink-0" />
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- BLOCO 2: ITENS SELECIONADOS NO CARRINHO RETROATIVO --}}
                <div class="bg-stone-50 dark:bg-stone-800/40 border border-stone-200 dark:border-stone-800 rounded-2xl p-4 flex flex-col gap-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-black uppercase tracking-wider text-stone-500">Itens Deste Pedido</span>
                        @php
                            $currentTotal = collect($manualItems)->sum(fn($i) => ($i['quantity'] ?? 1) * ($i['unit_price'] ?? 0));
                            $diff = round($currentTotal - $retroactiveTargetAmount, 2);
                        @endphp
                        <span class="text-xs font-bold" class="{{ $diff == 0 ? 'text-emerald-500' : 'text-amber-500' }}">
                            Total Itens: R$ {{ number_format($currentTotal, 2, ',', '.') }}
                            @if(abs($diff) > 0.01)
                                (Diferença: R$ {{ number_format($diff, 2, ',', '.') }})
                            @endif
                        </span>
                    </div>

                    @if(empty($manualItems))
                        <div class="py-4 text-center text-xs text-stone-400 font-medium">
                            Nenhum item adicionado. Clique em uma sugestão acima ou escolha produtos abaixo.
                        </div>
                    @else
                        <div class="flex flex-col divide-y divide-stone-200 dark:divide-stone-700/60 max-h-40 overflow-y-auto">
                            @foreach($manualItems as $idx => $item)
                                <div class="py-2 flex items-center justify-between text-xs">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-stone-800 dark:text-stone-200">{{ $item['name'] }}</span>
                                        <span class="text-[10px] text-stone-400">R$ {{ number_format($item['unit_price'], 2, ',', '.') }} un.</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button type="button" wire:click="alterarQtdItem({{ $idx }}, -1)" class="w-6 h-6 rounded bg-stone-200 dark:bg-stone-700 flex items-center justify-center font-bold text-stone-600 dark:text-stone-300">-</button>
                                        <span class="font-black w-6 text-center">{{ $item['quantity'] }}</span>
                                        <button type="button" wire:click="alterarQtdItem({{ $idx }}, 1)" class="w-6 h-6 rounded bg-stone-200 dark:bg-stone-700 flex items-center justify-center font-bold text-stone-600 dark:text-stone-300">+</button>
                                        <span class="font-black text-stone-700 dark:text-stone-300 ml-2 w-16 text-right">R$ {{ number_format($item['quantity'] * $item['unit_price'], 2, ',', '.') }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- BLOCO 3: SELETOR MANUAL DE PRODUTOS --}}
                <div class="flex items-center gap-2">
                    <select
                        wire:model="selectedProductIdToAdd"
                        class="flex-1 h-10 px-3 rounded-xl bg-white dark:bg-stone-800 border border-stone-200 dark:border-stone-700 text-xs font-bold text-stone-800 dark:text-stone-200 outline-none"
                    >
                        <option value="">Selecione outro produto do cardápio para adicionar...</option>
                        @foreach(\App\Models\Product::where('status', 'available')->orderBy('name')->get() as $prod)
                            <option value="{{ $prod->id }}">
                                {{ $prod->name }} — R$ {{ number_format($prod->dine_in_price ?: ($prod->delivery_price ?: 0), 2, ',', '.') }}
                            </option>
                        @endforeach
                    </select>
                    <x-filament::button size="sm" color="gray" wire:click="adicionarProdutoManual">
                        Adicionar
                    </x-filament::button>
                </div>

                {{-- DADOS DO CLIENTE (OPCIONAL) --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <input
                        type="text"
                        wire:model="retroactiveCustomerName"
                        placeholder="Nome do cliente (opcional)"
                        class="h-9 px-3 rounded-xl bg-white dark:bg-stone-800 border border-stone-200 dark:border-stone-700 text-xs text-stone-800 dark:text-stone-200 outline-none font-medium"
                    />
                    <input
                        type="text"
                        wire:model="retroactiveNotes"
                        placeholder="Observações da venda (opcional)"
                        class="h-9 px-3 rounded-xl bg-white dark:bg-stone-800 border border-stone-200 dark:border-stone-700 text-xs text-stone-800 dark:text-stone-200 outline-none font-medium"
                    />
                </div>

                {{-- RODAPÉ DE AÇÕES DO MODAL --}}
                <div class="flex items-center justify-between border-t border-stone-200 dark:border-stone-800 pt-4">
                    <button
                        type="button"
                        wire:click="criarVendaDiversaRapida"
                        class="text-xs text-stone-500 hover:text-stone-800 dark:hover:text-stone-300 font-bold underline outline-none"
                        title="Gera a venda como item diverso no valor exato sem selecionar produtos"
                    >
                        Lançar como Venda Diversa (Sem Itens)
                    </button>

                    <div class="flex items-center gap-2">
                        <x-filament::button color="gray" wire:click="$set('showRetroactiveModal', false)">
                            Cancelar
                        </x-filament::button>

                        <x-filament::button color="success" icon="heroicon-m-check" wire:click="confirmarVendaAvulsa">
                            Concluir Venda e Conciliar
                        </x-filament::button>
                    </div>
                </div>

            </div>
        </div>
    @endif
</x-filament-panels::page>
