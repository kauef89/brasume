<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\Filament\CentralPanelProvider::class,  // 1º O Central Mestre
    App\Providers\Filament\AdminPanelProvider::class,    // 2º O Admin das Lojas
    App\Providers\TenancyServiceProvider::class,
];