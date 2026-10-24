<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "================================================================================" . PHP_EOL;
echo "1. CustomerFavoritesService.php (Cálculo de Favoritos no Backend)" . PHP_EOL;
echo "================================================================================" . PHP_EOL;
$ref = new ReflectionClass(App\Services\Customers\CustomerFavoritesService::class);
$fn = $ref->getFileName();
echo "Arquivo: " . $fn . PHP_EOL . PHP_EOL;
echo file_get_contents($fn) . PHP_EOL;

echo "================================================================================" . PHP_EOL;
echo "2. ProductGrid.vue (Grid de Produtos do POS)" . PHP_EOL;
echo "================================================================================" . PHP_EOL;
echo file_get_contents(__DIR__ . '/resources/js/Pages/Pos/Components/Menu/ProductGrid.vue') . PHP_EOL;

echo "================================================================================" . PHP_EOL;
echo "3. CustomerFavoritesMenu.vue (Menu de Favoritos do Cliente)" . PHP_EOL;
echo "================================================================================" . PHP_EOL;
echo file_get_contents(__DIR__ . '/resources/js/Pages/Customer/Menu/CustomerFavoritesMenu.vue') . PHP_EOL;
