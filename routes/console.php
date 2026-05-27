<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('sefaz:sync')
    ->dailyAt('11:00')
    ->appendOutputTo(storage_path('logs/sefaz_sync.log'));

Schedule::command('sefaz:sync')
    ->dailyAt('16:00')
    ->appendOutputTo(storage_path('logs/sefaz_sync.log'));