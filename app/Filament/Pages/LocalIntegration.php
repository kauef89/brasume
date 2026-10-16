<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\CompanySetting;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Events\PrintJobCreated;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

class LocalIntegration extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';
    protected static string|\UnitEnum|null $navigationGroup = 'Configurações';
    protected static ?string $title = 'Integração Local (Impressoras)';
    protected static ?string $navigationLabel = 'Integração Local';
    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.pages.local-integration';

    public $bridgeToken;
    public $bridgeVersion;
    public $lastConnection;
    public $deepLink;

    public $windowsDownloadUrl;
    public $macDownloadUrl;

    public function mount()
    {
        $settings = CompanySetting::first();

        if (empty($settings->print_bridge_token)) {
            $settings->update(['print_bridge_token' => Str::random(32)]);
            $settings->refresh();
        }

        $this->bridgeToken = $settings->print_bridge_token;
        $this->bridgeVersion = $settings->print_bridge_version ?? 'Nunca conectada';
        $this->lastConnection = $settings->print_bridge_connected_at
            ? $settings->print_bridge_connected_at->diffForHumans()
            : 'N/A';

        $domain = request()->getHost();
        $this->deepLink = "brasumeprint://setup?domain={$domain}&token={$this->bridgeToken}";

        $this->windowsDownloadUrl = asset('downloads/BrasumePrint-Setup.exe');
        $this->macDownloadUrl = asset('downloads/BrasumePrint-Mac.zip');
    }

    // 🚀 NOVO TESTE DE IMPRESSÃO INTELIGENTE
    public function testPrint(\App\Services\Terminals\OrderPrintService $printService)
    {
        try {
            $printService->dispatchTestPrint();
            Notification::make()->title('Comando enviado para a impressora do Caixa!')->success()->send();
        } catch (\Exception $e) {
            Notification::make()->title('Atenção!')->body($e->getMessage())->danger()->send();
        }
    }
}
