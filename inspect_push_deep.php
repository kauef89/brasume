<?php
$baseDir = __DIR__;

echo "================================================================================" . PHP_EOL;
echo "1. TRECHO DO SERVICE WORKER (public/sw.js) - push e click" . PHP_EOL;
echo "================================================================================" . PHP_EOL;
$lines = file($baseDir . '/public/sw.js');
for ($i = 68; $i < min(count($lines), 125); $i++) {
    echo ($i + 1) . ": " . $lines[$i];
}

echo PHP_EOL . "================================================================================" . PHP_EOL;
echo "2. TODAS AS NOTIFICAÇÕES QUE USAM WebPushChannel" . PHP_EOL;
echo "================================================================================" . PHP_EOL;
echo shell_exec("grep -rn 'WebPush' app/Notifications/ 2>/dev/null") ?: "Nenhuma." . PHP_EOL;

echo PHP_EOL . "================================================================================" . PHP_EOL;
echo "3. CAMPOS DE LOGO/ÍCONE EM CompanySetting" . PHP_EOL;
echo "================================================================================" . PHP_EOL;
$cols = \Illuminate\Support\Facades\Schema::hasTable('company_settings')
    ? \Illuminate\Support\Facades\Schema::getColumnListing('company_settings')
    : [];
$iconCols = array_filter($cols, fn($c) => str_contains($c, 'logo') || str_contains($c, 'icon') || str_contains($c, 'badge') || str_contains($c, 'image'));
echo "Colunas de imagem/ícone: " . implode(', ', $iconCols) . PHP_EOL;
echo "================================================================================" . PHP_EOL;
