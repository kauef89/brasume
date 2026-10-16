@props(['statement'])

@php
    $color = $statement->amount < 0 ? "text-danger-600" : "text-success-600";
    $amount = number_format(abs((float) $statement->amount), 2, ",", ".");
@endphp

<div class="flex flex-col h-full p-6 rounded-xl bg-gray-50 dark:bg-gray-800/80 border border-gray-200 dark:border-gray-700">
    <div class="flex items-center text-sm font-semibold text-gray-500 uppercase pb-4 border-b border-gray-200 dark:border-gray-700">
        {{-- O componente nativo do BladeHeroicons --}}
        <x-heroicon-m-calendar class="w-4 h-4 mr-2 text-gray-400 shrink-0"/>
        {{ $statement->bank_date->format('d/m/Y') }}
    </div>

    <div class="grow mt-4 mb-8">
        <p class="text-lg text-gray-900 dark:text-white font-medium leading-tight">
            {{ $statement->description }}
        </p>
        <p class="text-xs text-gray-400 mt-2 uppercase">
            FITID: {{ $statement->fitid }}
        </p>
    </div>

    <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
        <p class="text-2xl font-medium {{ $color }}">
            R$ {{ $amount }}
        </p>
    </div>
</div>
