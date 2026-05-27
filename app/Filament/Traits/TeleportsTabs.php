<?php

namespace App\Filament\Traits;

use Illuminate\Contracts\View\View;

trait TeleportsTabs
{
    /**
     * Injeta magicamente o script de teletransporte de abas no rodapé da página.
     */
    public function getFooter(): ?View
    {
        return view("filament.components.teleport-tabs");
    }
}
