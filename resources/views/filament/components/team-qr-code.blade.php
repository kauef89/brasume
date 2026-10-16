@php
    // Monta a URL exata do tenant atual
    $tenantDomain = tenant('domains')->first()->domain ?? request()->getHost();
    $protocol = request()->secure() ? 'https://' : 'http://';

    // A URL que atua como deeplink para o PWA
    $teamLoginUrl = $protocol . $tenantDomain . '/team-login';

    // Gera o SVG inline (não pesa na requisição e renderiza perfeitamente nítido)
    $qrSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::size(250)
        ->margin(2)
        ->generate($teamLoginUrl);
@endphp

<div class="flex flex-col items-center justify-center w-full gap-6 p-6 bg-white border rounded-xl border-gray-200 dark:bg-gray-900 dark:border-gray-800">

    <!-- Renderiza o QR Code na Tela -->
    <div class="p-4 bg-white rounded-lg shadow-sm">
        {!! $qrSvg !!}
    </div>

    <div class="text-center">
        <h3 class="text-lg font-bold text-gray-900 dark:text-white">QR Code de Bate-Ponto / Login</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400">URL: {{ $teamLoginUrl }}</p>
    </div>

    <!-- Botões de Download -->
    <div class="flex gap-4">
        {{-- Botão SVG (Vetor - Melhor para gráficas e grandes impressões) --}}
        <x-filament::button
            tag="a"
            href="/api/qr-code/download?format=svg&url={{ urlencode($teamLoginUrl) }}"
            target="_blank"
            icon="heroicon-o-arrow-down-tray"
            color="info"
        >
            Baixar SVG (Vetor)
        </x-filament::button>

        {{-- Botão PNG (Imagem - Melhor para mandar no WhatsApp ou imprimir no Word) --}}
        <x-filament::button
            tag="a"
            href="/api/qr-code/download?format=png&url={{ urlencode($teamLoginUrl) }}"
            target="_blank"
            icon="heroicon-o-photo"
            color="success"
        >
            Baixar PNG (Imagem)
        </x-filament::button>
    </div>
</div>
