@props(['candidate', 'score'])

@php
    $val = "R$ " . number_format(abs((float) $candidate->amount), 2, ",", ".");
    $date = \Carbon\Carbon::parse($candidate->due_date)->format("d/m/Y");
    $badgeColor = $score >= 80
        ? "bg-success-100 text-success-800 dark:bg-success-800 dark:text-success-100"
        : ($score >= 50 ? "bg-warning-100 text-warning-800 dark:bg-warning-800 dark:text-warning-100" : "bg-danger-100 text-danger-800 dark:bg-danger-800 dark:text-danger-100");
    $name = $candidate->supplier->name ?? ($candidate->employee->name ?? "Fornecedor S/N");
@endphp

<div class='flex items-center justify-between w-full gap-4 px-2 py-1'>
    <div class='flex items-center text-sm text-gray-600 dark:text-gray-300 w-28'>
        <x-heroicon-m-calendar class="w-4 h-4 mr-1.5 text-gray-400 shrink-0"/>
        {{ $date }}
    </div>
    <div class='flex items-center grow text-left text-gray-800 dark:text-gray-200'>
        <x-heroicon-m-user class="w-4 h-4 mr-1.5 text-gray-400 shrink-0"/>
        {{ $name }}
    </div>
    <div class='flex items-center justify-end text-gray-700 dark:text-gray-300 w-32'>
        <x-heroicon-m-currency-dollar class="w-4 h-4 mr-1.5 text-gray-400 shrink-0"/>
        {{ $val }}
    </div>
    <div class='w-32 text-right'>
        <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] uppercase font-medium tracking-wider whitespace-nowrap shrink-0 {{ $badgeColor }}">
            <x-heroicon-m-check class="w-4 h-4 mr-1.5 shrink-0"/>
            {{ $score }}% Match
        </span>
    </div>
</div>
