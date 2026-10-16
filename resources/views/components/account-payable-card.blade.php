@props(['payable'])

@php
    $val = number_format((float) $payable->amount, 2, ",", ".");
    $date = $payable->due_date->format('d/m/Y');
    $name = $payable->supplier->name ?? ($payable->employee->name ?? 'Fornecedor S/N');
    $isLate = $payable->due_date < now()->startOfDay();
    $color = $isLate ? "text-danger-600" : "text-warning-600";
@endphp

<div class="flex flex-col h-full p-6 rounded-xl bg-gray-50 dark:bg-gray-800/80 border border-gray-200 dark:border-gray-700">
    <div class="flex items-center justify-between text-sm font-semibold text-gray-500 uppercase pb-4 border-b border-gray-200 dark:border-gray-700">
        <span class="flex items-center">
            <x-heroicon-m-calendar class="w-4 h-4 mr-2 text-gray-400 shrink-0"/>
            Venc: {{ $date }}
        </span>
        @if($isLate)
            <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-danger-100 text-danger-800">Em Atraso</span>
        @endif
    </div>

    <div class="grow mt-4 mb-8">
        <p class="text-lg text-gray-900 dark:text-white font-medium leading-tight">
            {{ $payable->description }}
        </p>
        <p class="text-sm text-gray-500 mt-2 flex items-center">
            <x-heroicon-m-user class="w-4 h-4 mr-1.5 text-gray-400 shrink-0"/>
            {{ $name }}
        </p>
    </div>

    <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
        <p class="text-xs text-gray-500 uppercase font-medium mb-1">Valor Original</p>
        <p class="text-2xl font-medium {{ $color }}">
            R$ {{ $val }}
        </p>
    </div>
</div>
