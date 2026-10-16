<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ReplaceHardcodedEnums extends Command
{
    protected $signature = 'dev:replace-enums {--dry-run : Apenas simula as substituições sem alterar os arquivos}';
    protected $description = 'Substitui arrays hardcoded em options() pelos Enums correspondentes criados no projeto.';

    /**
     * Mapeamento estruturado:
     * arquivo relativo => [campo => EnumClass]
     */
    protected array $replacements = [
        // Unidades de Medida
        'app/Filament/Resources/Ingredients/Schemas/IngredientForm.php' => [
            'unit_of_measure' => \App\Enums\UnitOfMeasure::class,
        ],
        'app/Filament/Resources/Supplies/Schemas/SupplyForm.php' => [
            'unit_of_measure' => \App\Enums\UnitOfMeasure::class,
            'status'          => \App\Enums\ProductStatus::class,
        ],
        'app/Filament/Resources/Products/Schemas/Sections/PriceAndStockSection.php' => [
            'unit_of_measure' => \App\Enums\UnitOfMeasure::class,
        ],
        'app/Filament/Resources/Products/Schemas/ProductForm.php' => [
            'unit_of_measure' => \App\Enums\UnitOfMeasure::class,
            'type'            => \App\Enums\ProductType::class,
        ],

        // Status de Produto
        'app/Filament/Resources/Products/Tables/ProductTable.php' => [
            'status' => \App\Enums\ProductStatus::class,
        ],
        'app/Filament/Resources/Products/Schemas/Sections/BasicSection.php' => [
            'status' => \App\Enums\ProductStatus::class,
            'type'   => \App\Enums\ProductType::class,
        ],

        // Pedidos & Fluxo de Vendas
        'app/Filament/Resources/Orders/Schemas/OrderForm.php' => [
            'type'           => \App\Enums\OrderType::class,
            'status'         => \App\Enums\OrderStatus::class,
            'payment_status' => \App\Enums\PaymentStatus::class,
        ],
        'app/Filament/Resources/Products/RelationManagers/TechnicalSheetsRelationManager.php' => [
            'order_type' => \App\Enums\OrderType::class,
        ],
        'app/Filament/Resources/LegacyOrders/Tables/LegacyOrdersTable.php' => [
            'order_type' => \App\Enums\OrderType::class,
        ],

        // Ordens de Produção
        'app/Filament/Resources/ProductionOrders/Tables/ProductionOrdersTable.php' => [
            'status' => \App\Enums\ProductionOrderStatus::class,
        ],
        'app/Filament/Resources/ProductionOrders/Schemas/ProductionOrderForm.php' => [
            'status' => \App\Enums\ProductionOrderStatus::class,
        ],

        // Cupons
        'app/Filament/Resources/Coupons/Tables/CouponTable.php' => [
            'type' => \App\Enums\CouponType::class,
        ],
        'app/Filament/Resources/Coupons/Schemas/CouponForm.php' => [
            'type' => \App\Enums\CouponType::class,
        ],

        // Hardware & Dispositivos
        'app/Filament/Resources/Printers/Schemas/PrinterForm.php' => [
            'type' => \App\Enums\PrinterType::class,
        ],
        'app/Filament/Resources/Devices/Tables/DevicesTable.php' => [
            'type' => \App\Enums\DeviceType::class,
        ],
        'app/Filament/Resources/Devices/Schemas/DeviceForm.php' => [
            'type' => \App\Enums\DeviceType::class,
        ],

        // Financeiro & Contas
        'app/Filament/Resources/BankAccounts/Tables/BankAccountsTable.php' => [
            'type' => \App\Enums\BankAccountType::class,
        ],
        'app/Filament/Resources/BankAccounts/Schemas/BankAccountForm.php' => [
            'type' => \App\Enums\BankAccountType::class,
        ],
        'app/Filament/Resources/Transactions/Tables/TransactionsTable.php' => [
            'type' => \App\Enums\TransactionType::class,
        ],
        'app/Filament/Resources/Transactions/Schemas/TransactionForm.php' => [
            'type' => \App\Enums\TransactionType::class,
        ],
        'app/Filament/Resources/RecurringExpenses/Tables/RecurringExpensesTable.php' => [
            'value_type' => \App\Enums\RecurringExpenseValueType::class,
        ],
        'app/Filament/Resources/RecurringExpenses/Schemas/RecurringExpenseForm.php' => [
            'value_type' => \App\Enums\RecurringExpenseValueType::class,
        ],
        'app/Filament/Resources/BankStatements/Actions/BulkRecurringAction.php' => [
            'value_type' => \App\Enums\RecurringExpenseValueType::class,
        ],

        // Lista de Compras
        'app/Filament/Resources/ShoppingListItems/Tables/ShoppingListItemsTable.php' => [
            'status' => \App\Enums\ShoppingListStatus::class,
        ],
        'app/Filament/Resources/ShoppingListItems/Schemas/ShoppingListItemForm.php' => [
            'status' => \App\Enums\ShoppingListStatus::class,
        ],

        // Perdas de Estoque
        'app/Filament/Resources/InventoryLosses/Schemas/InventoryLossForm.php' => [
            'loss_type' => \App\Enums\InventoryLossType::class,
        ],
        'app/Filament/Resources/InventoryLosses/Tables/InventoryLossesTable.php' => [
            'loss_type' => \App\Enums\InventoryLossType::class,
        ],
    ];

    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn("MODO SIMULAÇÃO ATIVADO (--dry-run). Nenhum arquivo será alterado em disco.\n");
        }

        $totalReplacements = 0;

        foreach ($this->replacements as $relativePath => $fields) {
            $fullPath = base_path($relativePath);

            if (!File::exists($fullPath)) {
                $this->warn("Arquivo não localizado: {$relativePath}");
                continue;
            }

            $originalContent = File::get($fullPath);
            $content = $originalContent;
            $fileChanged = false;

            foreach ($fields as $field => $enumClass) {
                $enumShortName = class_basename($enumClass);

                // Nova Expressão Regular com o modificador 's' para ler multilinhas (quebras de linha)
                // e a busca por 'make' mais relaxada (TextColumn::make, Select::make, etc).
                $pattern = '/(make\([\'"]' . preg_quote($field, '/') . '[\'"]\)(?:(?!make\().)*?->options)\(\s*\[[\s\S]*?\]\s*\)/s';

                if (preg_match($pattern, $content)) {
                    $content = preg_replace($pattern, "$1({$enumShortName}::class)", $content);
                    $content = $this->ensureImport($content, $enumClass);
                    $fileChanged = true;
                    $totalReplacements++;

                    $this->line("   - Campo <fg=cyan>{$field}</> -> <fg=green>{$enumShortName}::class</>");
                }
            }

            if ($fileChanged) {
                $this->info("✔ Alterações aplicadas em: {$relativePath}");

                if (!$isDryRun) {
                    File::put($fullPath, $content);
                }
            }
        }

        $this->info("\nOperação concluída. Total de ocorrências tratadas: {$totalReplacements}");

        if ($isDryRun) {
            $this->line("Execute sem a flag <fg=yellow>--dry-run</> para persistir as mudanças nos arquivos.");
        }
    }

    /**
     * Garante que a classe do Enum seja importada no bloco de uses do arquivo.
     */
    private function ensureImport(string $content, string $enumClass): string
    {
        if (str_contains($content, "use {$enumClass};")) {
            return $content;
        }

        // Injeta logo após o namespace da classe
        return preg_replace(
            '/(namespace\s+[^;]+;\s*\n)/',
            "$1\nuse {$enumClass};",
            $content,
            1
        );
    }
}
