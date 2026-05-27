<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Product;
use App\Models\BankStatement;
use App\Models\ReceivedInvoice;
use App\Filament\Resources\BankStatements\BankStatementResource;
use App\Filament\Resources\ReceivedInvoices\ReceivedInvoiceResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Ingredients\IngredientResource;
use App\Filament\Resources\Supplies\SupplyResource;

class SystemAlerts extends Component
{
    public function render()
    {
        $alerts = collect();

        // 1. Conciliações Bancárias Pendentes
        $pendingConciliations = BankStatement::where('status', 'pending')->count();
        if ($pendingConciliations > 0) {
            $alerts->push([
                'title' => 'Conciliações Pendentes',
                'message' => "Você tem {$pendingConciliations} extratos para conciliar.",
                'icon' => 'heroicon-o-building-library',
                'color' => 'text-primary-600 dark:text-primary-400',
                'bg' => 'bg-primary-50 dark:bg-primary-900/20',
                'url' => BankStatementResource::getUrl('index'),
            ]);
        }

        // 2. Notas Fiscais Pendentes de Manifesto
        $pendingInvoices = ReceivedInvoice::where('status', 'pending')->count();
        if ($pendingInvoices > 0) {
            $alerts->push([
                'title' => 'Novas Notas Fiscais',
                'message' => "Você tem {$pendingInvoices} notas aguardando manifesto.",
                'icon' => 'heroicon-o-document-text',
                'color' => 'text-warning-600 dark:text-warning-400',
                'bg' => 'bg-warning-50 dark:bg-warning-900/20',
                'url' => ReceivedInvoiceResource::getUrl('index'),
            ]);
        }

        // 3. Produtos Duplicados (Macro-áreas)
        $this->checkDuplicates($alerts, 'external', 'Produtos', ProductResource::class);
        $this->checkDuplicates($alerts, 'internal', 'Insumos', IngredientResource::class);
        $this->checkDuplicates($alerts, 'supply', 'Materiais de Consumo', SupplyResource::class);

        return view('livewire.system-alerts', [
            'alerts' => $alerts,
            'totalAlerts' => $alerts->count(),
        ]);
    }

    // Função auxiliar para não repetir o código de checagem de duplicados
    private function checkDuplicates(&$alerts, $type, $label, $resourceClass)
    {
        $duplicateCount = Product::whereHas('category', fn($q) => $q->where('type', $this->categoryType ?? $type))
            ->select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(id) > 1')
            ->get()
            ->count();

        if ($duplicateCount > 0) {
            $alerts->push([
                'title' => "{$label} Duplicados",
                'message' => "A base tem {$duplicateCount} {$label} precisando de mesclagem.",
                'icon' => 'heroicon-o-sparkles',
                'color' => 'text-danger-600 dark:text-danger-400',
                'bg' => 'bg-danger-50 dark:bg-danger-900/20',
                'url' => $resourceClass::getUrl('index'),
            ]);
        }
    }
}
