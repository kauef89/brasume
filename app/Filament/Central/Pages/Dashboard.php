<?php

namespace App\Filament\Central\Pages;

// ATENÇÃO: Note que ele estende o Dashboard oficial do Filament, e não uma Page vazia!
class Dashboard extends \Filament\Pages\Dashboard
{
    // Opcional: Se quiser dar um título diferente à página inicial
    protected static ?string $title = 'Painel Mestre';
}