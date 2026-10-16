<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;
use Illuminate\Support\Facades\File;

class RefactorFilamentMenus extends Command
{
    protected $signature = 'filament:refactor-menus';
    protected $description = 'Reorganiza todos os Resources do Filament nos novos grupos arquiteturais.';

    // Mapeamento: 'NomeDaClasseResource' => 'Novo Grupo'
    protected array $menuMap = [
        // Vendas & Atendimento
        'OrderResource'               => 'Vendas & Atendimento',
        'CashShiftResource'           => 'Vendas & Atendimento',
        'TableAreaResource'           => 'Vendas & Atendimento',

        // Cardápio
        'ProductResource'             => 'Cardápio',
        'CategoryResource'            => 'Cardápio',
        'ProductModifierResource'     => 'Cardápio',

        // CRM & Marketing
        'CustomerResource'            => 'CRM & Marketing',
        'OfferResource'               => 'CRM & Marketing',
        'CouponResource'              => 'CRM & Marketing',

        // Estoque & Compras
        'IngredientResource'          => 'Estoque & Compras',
        'SupplyResource'              => 'Estoque & Compras',
        'ReceivedInvoiceResource'     => 'Estoque & Compras',
        'SupplierResource'            => 'Estoque & Compras',
        'StorageLocationResource'     => 'Estoque & Compras',
        'ShoppingListItemResource'    => 'Estoque & Compras',
        'InventoryLossResource'       => 'Estoque & Compras',
        'GoodsReceiptResource'        => 'Estoque & Compras', // Romaneio / Canhoto corrigido

        // Produção & Cozinha
        'ProductionOrderResource'     => 'Produção & Cozinha',
        'KitchenResource'             => 'Produção & Cozinha',

        // Logística
        'NeighborhoodResource'        => 'Logística',
        'StreetResource'              => 'Logística',

        // Financeiro
        'TransactionResource'         => 'Financeiro',
        'BankAccountResource'         => 'Financeiro',
        'AccountReceivableResource'   => 'Financeiro',
        'AccountPayableResource'      => 'Financeiro',
        'RecurringExpenseResource'    => 'Financeiro',
        'PersonalPurchaseResource'    => 'Financeiro',
        'BankStatementResource'       => 'Financeiro',

        // Fiscal
        'FiscalDocumentResource'      => 'Fiscal',

        // Gestão & RH
        'EmployeeResource'            => 'Gestão & RH',
        'ChecklistResource'           => 'Gestão & RH',

        // PDV & Infraestrutura
        'PaymentMethodResource'       => 'PDV & Infraestrutura',
        'DeviceResource'              => 'PDV & Infraestrutura',
        'PrinterResource'             => 'PDV & Infraestrutura',
        'PosAuditResource'            => 'PDV & Infraestrutura',
        'PrintJobResource'            => 'PDV & Infraestrutura',

        // Configurações
        'UserResource'                => 'Configurações',

        // Sistema Legado
        'LegacyOrderResource'         => 'Sistema Legado',
        'LegacyDailyRevenueResource'  => 'Sistema Legado',
        'LegacyCustomerResource'      => 'Sistema Legado',
        'LegacyProductResource'       => 'Sistema Legado',
        'LegacyModifierResource'      => 'Sistema Legado',
        'LegacyNeighborhoodResource'  => 'Sistema Legado',
        'LegacyPaymentMethodResource' => 'Sistema Legado',
    ];

    public function handle()
    {
        $finder = new Finder();
        $finder->files()->in(app_path('Filament/Resources'))->name('*Resource.php');

        $updatedCount = 0;

        foreach ($finder as $file) {
            $className = $file->getFilenameWithoutExtension();
            $path = $file->getRealPath();

            // Pula arquivos que não estão no mapeamento
            if (!array_key_exists($className, $this->menuMap)) {
                $this->warn("⚠️  Ignorado: {$className} (Não mapeado)");
                continue;
            }

            $newGroup = $this->menuMap[$className];
            $content = File::get($path);

            // Verifica se já existe a propriedade $navigationGroup no arquivo
            if (preg_match('/\$navigationGroup\s*=/', $content)) {
                // Substitui o valor da propriedade existente (aceitando qualquer tipagem do PHP/Filament)
                $content = preg_replace(
                    '/(protected\s+static\s+(?:.*?)?\$navigationGroup\s*=\s*)[\'"][^\'"]*[\'"]\s*;/s',
                    "$1'{$newGroup}';",
                    $content
                );
            } else {
                // Se não existir, injeta logo abaixo do $model
                $content = preg_replace(
                    '/(protected\s+static\s+\?string\s+\$model\s*=\s*[^;]+;)/',
                    "$1\n\n    protected static ?string \$navigationGroup = '{$newGroup}';",
                    $content
                );
            }

            File::put($path, $content);
            $this->line("✅ Atualizado: {$className} -> <info>{$newGroup}</info>");
            $updatedCount++;
        }

        $this->info("\n🚀 Refatoração concluída! {$updatedCount} Resources foram organizados.");
        $this->line("Dica: Rode 'php artisan optimize:clear' caso os menus não atualizem imediatamente no painel.");
    }
}
