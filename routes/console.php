<?php

use Illuminate\Support\Facades\Schedule;
use App\Jobs\SystemHeartbeatJob;

// ==========================================
// 🚀 TAREFAS DOS TENANTS
// ==========================================

// Sincronização SEFAZ (Horários de pico - executado diretamente)
Schedule::command('sefaz:sync')
    ->cron('0 11,13,16 * * *')
    ->appendOutputTo(storage_path('logs/sefaz_sync.log'));

// Rotinas da Madrugada
Schedule::command('tenants:run loyalty:expire')
    ->dailyAt('02:30')
    ->appendOutputTo(storage_path('logs/loyalty_expire.log'));

Schedule::command('erp:renew-allowances')
    ->dailyAt('04:00')
    ->appendOutputTo(storage_path('logs/allowance_renewal.log'));

Schedule::command('erp:prune-print-jobs')
    ->dailyAt('03:00')
    ->appendOutputTo(storage_path('logs/prune_jobs.log'));

Schedule::command('pos:train-ai')
    ->dailyAt('03:00')
    ->withoutOverlapping();

Schedule::command('catalog:cache-top-combinations')
    ->dailyAt('03:00');

Schedule::command('tenants:run erp:generate-production')
    ->dailyAt('05:00')
    ->appendOutputTo(storage_path('logs/production_planning.log'));

Schedule::command('tenants:run erp:generate-procurement')
    ->dailyAt('06:00')
    ->appendOutputTo(storage_path('logs/procurement.log'));

// Rotinas Frequentes
Schedule::command('erp:monitor-checklists')
    ->everyFiveMinutes()
    ->appendOutputTo(storage_path('logs/checklists_monitor.log'));

Schedule::command('tenants:run store:notify-opening')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/store_opening_notifications.log'));

// ==========================================
// 🌐 MONITORAMENTO GLOBAL (Inline / Direto)
// ==========================================
// 🚀 Executa diretamente sem empilhar lixo na tabela jobs do banco
Schedule::call(function () {
    (new SystemHeartbeatJob)->handle();
})->everyMinute();
