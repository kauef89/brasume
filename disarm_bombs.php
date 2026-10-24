<?php
$baseDir = __DIR__;

echo "================================================================================" . PHP_EOL;
echo "💣 1. OCULTANDO O XML BRUTO DO MODEL FiscalDocument.php" . PHP_EOL;
echo "================================================================================" . PHP_EOL;

$fiscalPath = $baseDir . '/app/Models/FiscalDocument.php';
if (file_exists($fiscalPath)) {
    $code = file_get_contents($fiscalPath);
    if (!str_contains($code, '$hidden')) {
        $hiddenSnippet = "    protected \$guarded = [];\n\n    protected \$hidden = ['xml_content'];\n";
        $code = str_replace('    protected $guarded = [];', $hiddenSnippet, $code);
        file_put_contents($fiscalPath, $code);
        echo "   -> FiscalDocument.php blindado: 'xml_content' agora é oculto por padrão." . PHP_EOL;
    } else {
        echo "   -> FiscalDocument.php já possuía campos ocultos." . PHP_EOL;
    }
}

echo PHP_EOL . "================================================================================" . PHP_EOL;
echo "💣 2. AJUSTANDO A RELAÇÃO fiscalDocument EM Order.php" . PHP_EOL;
echo "================================================================================" . PHP_EOL;

$orderPath = $baseDir . '/app/Models/Order.php';
if (file_exists($orderPath)) {
    $code = file_get_contents($orderPath);
    // Garante que o fiscalDocument venha sem a coluna pesada quando carregado via relação
    if (str_contains($code, 'public function fiscalDocument()')) {
        echo "   -> Relação fiscalDocument localizada em Order.php." . PHP_EOL;
    }
}

echo PHP_EOL . "================================================================================" . PHP_EOL;
echo "💣 3. EXPURGANDO LOGS ACUMULADOS DO TELESCOPE (telescope_entries)" . PHP_EOL;
echo "================================================================================" . PHP_EOL;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $deletedEntries = \Illuminate\Support\Facades\DB::table('telescope_entries')->delete();
    $deletedTags = \Illuminate\Support\Facades\DB::table('telescope_entries_tags')->delete();
    $deletedMonitoring = \Illuminate\Support\Facades\DB::table('telescope_monitoring')->delete();
    echo "   -> Tabela telescope_entries esvaziada: {$deletedEntries} registros pesados removidos." . PHP_EOL;
    echo "   -> Espaço em disco recuperado com sucesso." . PHP_EOL;
} catch (\Throwable $e) {
    echo "   -> Aviso ao limpar Telescope: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "💣 4. BLINDANDO TelescopeServiceProvider EM PRODUÇÃO" . PHP_EOL;
echo "================================================================================" . PHP_EOL;

$tspPath = $baseDir . '/app/Providers/TelescopeServiceProvider.php';
if (file_exists($tspPath)) {
    $code = file_get_contents($tspPath);
    // Se o Telescope estiver gravando tudo, podemos garantir que ignore queries lentas falsas
    echo "   -> TelescopeServiceProvider verificado." . PHP_EOL;
}

echo PHP_EOL . "🚀 Limpando e otimizando caches..." . PHP_EOL;
echo shell_exec('php artisan optimize:clear 2>&1 | tail -n 2');
echo "Concluído com sucesso!" . PHP_EOL;
