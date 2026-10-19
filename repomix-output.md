This file is a merged representation of a subset of the codebase, containing specifically included files and files not matching ignore patterns, combined into a single document by Repomix.

# File Summary

## Purpose
This file contains a packed representation of a subset of the repository's contents that is considered the most important context.
It is designed to be easily consumable by AI systems for analysis, code review,
or other automated processes.

## File Format
The content is organized as follows:
1. This summary section
2. Repository information
3. Directory structure
4. Repository files (if enabled)
5. Multiple file entries, each consisting of:
  a. A header with the file path (## File: path/to/file)
  b. The full contents of the file in a code block

## Usage Guidelines
- This file should be treated as read-only. Any changes should be made to the
  original repository files, not this packed version.
- When processing this file, use the file path to distinguish
  between different files in the repository.
- Be aware that this file may contain sensitive information. Handle it with
  the same level of security as you would the original repository.

## Notes
- Some files may have been excluded based on .gitignore rules and Repomix's configuration
- Binary files are not included in this packed representation. Please refer to the Repository Structure section for a complete list of file paths, including binary files
- Only files matching these patterns are included: app/Http/Controllers/Tenant/Operations/InventoryMovementController.php, resources/js/Pages/Team/Composables/useInventoryCount.js, resources/js/Pages/Team/InventoryItemRow.vue, resources/js/Pages/Team/InventoryTab.vue, app/Filament/Resources/Products/Schemas/Sections/PriceAndStockSection.php, app/Filament/Resources/Products/Schemas/Sections/WmsSection.php, app/Filament/Resources/Products/Tables/ProductTable.php
- Files matching these patterns are excluded: vendor/**, node_modules/**, public/**, storage/**, bootstrap/cache/**, database/migrations/**, database/factories/**, tests/**, .git/**, *.lock, package-lock.json, composer.lock, repomix*.xml
- Files matching patterns in .gitignore are excluded
- Files matching default ignore patterns are excluded
- Files are sorted by Git change count (files with more changes are at the bottom)

# Directory Structure
```
app/
  Filament/
    Resources/
      Products/
        Schemas/
          Sections/
            PriceAndStockSection.php
            WmsSection.php
        Tables/
          ProductTable.php
  Http/
    Controllers/
      Tenant/
        Operations/
          InventoryMovementController.php
resources/
  js/
    Pages/
      Team/
        Composables/
          useInventoryCount.js
        InventoryItemRow.vue
        InventoryTab.vue
```

# Files

## File: app/Filament/Resources/Products/Schemas/Sections/PriceAndStockSection.php
```php
<?php

namespace App\Filament\Resources\Products\Schemas\Sections;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\UnitOfMeasure;
use Filament\Forms;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use App\Models\Product;

class PriceAndStockSection
{
    public static function make(): Section
    {
        return Section::make("Preços e Estoque")
            ->icon("heroicon-o-currency-dollar")
            ->schema([
                Section::make("Precificação")->schema([
                    Grid::make(4)->schema([
                        Forms\Components\TextInput::make("dine_in_price")
                            ->label("Preço Salão")
                            ->numeric()
                            ->prefix('R$'),
                        Forms\Components\TextInput::make("delivery_price")
                            ->label("Preço Delivery")
                            ->numeric()
                            ->prefix('R$'),
                        Forms\Components\TextInput::make("promo_price")
                            ->label("Preço Promocional")
                            ->numeric()
                            ->prefix('R$'),
                        Forms\Components\TextInput::make("base_cost")
                            ->label("Custo Médio")
                            ->numeric()
                            ->prefix('R$')
                            ->readOnly()
                            ->formatStateUsing(function ($state, ?Product $record) {
                                if ($record) {
                                    $realCost = \App\Services\Catalog\ProductCostingService::calculateBaseCost($record);
                                    return number_format($realCost, 2, ".", "");
                                }
                                return $state;
                            })
                            ->helperText("Calculado via Ficha Técnica ou Histórico de Compras"),
                    ]),
                ]),

                Section::make("Controle de Estoque")
                    ->visible(fn(Get $get) => $get("type") !== "combo")
                    ->schema([
                        Grid::make(4)->schema([
                            Forms\Components\TextInput::make("sku")
                                ->label("SKU")
                                ->maxLength(255),
                            Forms\Components\TextInput::make("barcode")
                                ->label("Código de Barras (EAN)")
                                ->maxLength(255),
                            Forms\Components\Select::make("unit_of_measure")
                                ->label("Unid. Medida")
                                ->options(UnitOfMeasure::class)
                                ->default("UN")
                                ->required(),
                            Forms\Components\TextInput::make("conversion_factor")
                                ->label("Fator de Conversão")
                                ->numeric()
                                ->default(1),
                        ]),

                        // 🚀 AQUI: Expandimos a Grid para acomodar a nova inteligência de níveis!
                        Grid::make(5)->schema([
                            Forms\Components\Toggle::make("track_inventory")
                                ->label("Controlar Estoque?")
                                ->default(true)
                                ->live(),
                            Forms\Components\TextInput::make("current_stock")
                                ->label("Estoque Atual")
                                ->numeric()
                                ->default(0)
                                ->visible(fn(Get $get) => $get("track_inventory")),
                            Forms\Components\TextInput::make("min_stock")
                                ->label("Estoque Mínimo")
                                ->numeric()
                                ->default(0)
                                ->visible(fn(Get $get) => $get("track_inventory")),
                            Forms\Components\TextInput::make("max_stock")
                                ->label("Estoque Máximo")
                                ->helperText("Teto da lista de compras")
                                ->numeric()
                                ->default(0)
                                ->visible(fn(Get $get) => $get("track_inventory")),
                            Forms\Components\TextInput::make("par_level")
                                ->label("Nível Ideal (Produção)")
                                ->helperText("Exclusivo para preparo")
                                ->numeric()
                                ->default(0)
                                // 🚀 Só é exibido para itens de fabrico interno!
                                ->visible(fn(Get $get) => $get("type") === "manufactured"),
                        ]),
                    ]),
            ])
            ->columnSpanFull();
    }
}
```

## File: app/Filament/Resources/Products/Schemas/Sections/WmsSection.php
```php
<?php

namespace App\Filament\Resources\Products\Schemas\Sections;

use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

class WmsSection
{
    public static function make(): Section
    {
        return Section::make("WMS - Gestão de Saldo e Reposição por Local")
            ->description("Distribua fisicamente o estoque deste produto. O Saldo Atual configurado aqui reflete imediatamente no PDV.")
            ->icon("heroicon-o-arrows-right-left")
            // 🚀 AQUI: Oculta a sessão inteira se o produto exigir preparo na cozinha
            ->visible(fn(Get $get) =>
                $get("type") !== "combo" &&
                $get("track_inventory") === true &&
                $get("requires_preparation") !== true
            )
            ->schema([
                Forms\Components\Repeater::make("storageRules")
                    ->relationship("storageRules")
                    ->label("Locais de Armazenamento")
                    ->addActionLabel("Adicionar Local")
                    ->schema([
                        Forms\Components\Select::make("storage_location_id")
                            ->label("Local (Destino)")
                            ->relationship("location", "name")
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpan(2),

                        Forms\Components\Select::make("source_location_id")
                            ->label("Puxar de onde? (Origem)")
                            ->relationship("sourceLocation", "name")
                            ->searchable()
                            ->preload()
                            ->helperText("De onde a equipe tira para abastecer o destino.")
                            ->columnSpan(2),

                        // 🚀 NOVO CAMPO DE SALDO
                        Forms\Components\TextInput::make("current_stock")
                            ->label("Saldo Atual")
                            ->numeric()
                            ->inputMode('decimal')
                            ->step('any')
                            ->default(0)
                            ->required()
                            ->columnSpan(1),

                        Forms\Components\TextInput::make("max_capacity")
                            ->label("Capacidade")
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->columnSpan(1),

                        Forms\Components\TextInput::make("restock_threshold")
                            ->label("Ponto de Reposição")
                            ->numeric()
                            ->default(0)
                            ->helperText("Avisa a equipe quando o saldo chegar neste valor.")
                            ->columnSpan(2),
                    ])
                    ->columns(6)
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string =>
                        isset($state['storage_location_id']) ? "Armazenamento e Saldo" : null
                    )
            ])
            ->columnSpanFull();
    }
}
```

## File: resources/js/Pages/Team/Composables/useInventoryCount.js
```javascript
import { ref, computed } from 'vue';
import axios from 'axios';
import { useToast } from 'primevue/usetoast';

export function useInventoryCount(catalogRef) {
    const toast = useToast();

    // Filtros e Modos
    const activeMode = ref('inventory');
    const selectedLocation = ref('all');
    const selectedType = ref('all');
    const searchQuery = ref('');

    // Estado do Balanço
    const expandedItemKey = ref(null);
    const draftCounts = ref({});
    const savingItemKey = ref(null);

    const modeOptions = [
        { label: 'Status', value: 'status', icon: 'pi-bolt' },
        { label: 'Inventário', value: 'inventory', icon: 'pi-clipboard' }
    ];

    const typeFilters = [
        { label: 'Todos', value: 'all', icon: 'pi-th-large' },
        { label: 'Revenda', value: 'resale', icon: 'pi-shopping-bag' },
        { label: 'Insumos', value: 'ingredients', icon: 'pi-apple' },
        { label: 'Suprimentos', value: 'supplies', icon: 'pi-box' },
    ];

    const hasActiveFilters = computed(() => {
        return selectedType.value !== 'all' || selectedLocation.value !== 'all';
    });

    const resetFilters = () => {
        selectedType.value = 'all';
        selectedLocation.value = 'all';
    };

    const makeKey = (locationId, productId) => `${locationId || 0}_${productId}`;

    // Mapeamento por Ponto Físico com Saldo Local e Ordenação por Categoria (Prateleiras)
    const locationBlocks = computed(() => {
        const rawCatalog = catalogRef.value || [];
        const term = (searchQuery.value || '').trim().toLowerCase();
        const blocksMap = new Map();

        rawCatalog.forEach(prod => {
            if (activeMode.value === 'inventory' && !prod.track_inventory) return;

            if (term) {
                const matches = (prod.name || '').toLowerCase().includes(term) ||
                                (prod.category?.name || '').toLowerCase().includes(term) ||
                                (prod.sku || '').toLowerCase().includes(term) ||
                                String(prod.id).includes(term);
                if (!matches) return;
            }

            const type = (prod.type || 'resale').toLowerCase();
            const section = (type === 'resale') ? 'resale' : (type === 'ingredient' ? 'ingredients' : 'supplies');
            if (selectedType.value !== 'all' && selectedType.value !== section) return;

            const rawLocations = Array.isArray(prod.storage_locations) && prod.storage_locations.length > 0
                ? prod.storage_locations
                : [{ id: 0, name: 'Estoque Geral / Salão', type: 'storage', current_stock: prod.current_stock }];

            const totalGlobalStock = Number(prod.current_stock || 0);

            rawLocations.forEach(loc => {
                const locId = loc.id || 0;
                if (selectedLocation.value !== 'all' && String(selectedLocation.value) !== String(locId)) return;

                if (!blocksMap.has(locId)) {
                    blocksMap.set(locId, {
                        location_id: locId,
                        location_name: loc.name || 'Estoque Geral',
                        type: loc.type || 'storage',
                        sort_order: Number(loc.sort_order ?? 99),
                        items: []
                    });
                }

                const localStock = Number(
                    loc.pivot?.current_stock ??
                    loc.current_stock ??
                    (rawLocations.length === 1 ? totalGlobalStock : 0)
                );

                blocksMap.get(locId).items.push({
                    ...prod,
                    local_expected_stock: localStock,
                    total_system_stock: totalGlobalStock
                });
            });
        });

        // Ordenação por Categoria (Prateleiras) e depois Nome do Produto
        blocksMap.forEach(block => {
            block.items.sort((a, b) => {
                const catOrderA = a.category?.sort_order ?? 999;
                const catOrderB = b.category?.sort_order ?? 999;
                if (catOrderA !== catOrderB) return catOrderA - catOrderB;

                const catNameA = a.category?.name || 'Geral';
                const catNameB = b.category?.name || 'Geral';
                if (catNameA !== catNameB) return catNameA.localeCompare(catNameB, 'pt-BR');

                return (a.name || '').localeCompare(b.name || '', 'pt-BR');
            });
        });

        return Array.from(blocksMap.values()).sort((a, b) => {
            if (a.sort_order !== b.sort_order) return a.sort_order - b.sort_order;
            if (a.type !== b.type) return a.type === 'display' ? -1 : 1;
            return a.location_name.localeCompare(b.location_name);
        });
    });

    // Rota de Caminhada
    const routeLocations = computed(() => {
        const rawCatalog = catalogRef.value || [];
        const locMap = new Map();

        rawCatalog.forEach(prod => {
            if (!prod.track_inventory) return;
            const locations = Array.isArray(prod.storage_locations) ? prod.storage_locations : [];
            locations.forEach(loc => {
                if (!loc.id) return;
                if (!locMap.has(loc.id)) {
                    locMap.set(loc.id, {
                        id: loc.id,
                        name: loc.name,
                        type: loc.type || 'storage',
                        sort_order: Number(loc.sort_order ?? 99),
                        totalCount: 0,
                        touchedCount: 0
                    });
                }
                const entry = locMap.get(loc.id);
                entry.totalCount++;
                if (draftCounts.value[makeKey(loc.id, prod.id)] !== undefined && draftCounts.value[makeKey(loc.id, prod.id)] !== null) {
                    entry.touchedCount++;
                }
            });
        });

        return Array.from(locMap.values())
            .sort((a, b) => a.sort_order - b.sort_order)
            .map(loc => ({
                ...loc,
                isComplete: loc.totalCount > 0 && loc.touchedCount >= loc.totalCount
            }));
    });

    const locationOptions = computed(() => {
        const list = [{ label: 'Todos os Locais', value: 'all' }];
        routeLocations.value.forEach(b => {
            list.push({ label: b.name, value: String(b.id) });
        });
        return list;
    });

    const isExpanded = (locationId, productId) => expandedItemKey.value === makeKey(locationId, productId);

    const toggleItemExpansion = (locationId, item) => {
        const key = makeKey(locationId, item.id);
        expandedItemKey.value = expandedItemKey.value === key ? null : key;
    };

    const isTouched = (locationId, productId) => {
        const val = draftCounts.value[makeKey(locationId, productId)];
        return val !== undefined && val !== null;
    };

    const getDisplayValue = (locationId, productId) => {
        const val = draftCounts.value[makeKey(locationId, productId)];
        return (val !== undefined && val !== null) ? val : '--';
    };

    const getCountValue = (locationId, productId) => {
        const val = draftCounts.value[makeKey(locationId, productId)];
        return (val !== undefined && val !== null) ? Number(val) : null;
    };

    const getItemDelta = (locationId, item) => {
        const counted = getCountValue(locationId, item.id);
        if (counted === null) return 0;
        const expected = Number(item.local_expected_stock ?? 0);
        return Number((counted - expected).toFixed(3));
    };

    const formatDeltaLabel = (locationId, item) => {
        const delta = getItemDelta(locationId, item);
        if (delta < 0) return `${delta} (Falta)`;
        if (delta > 0) return `+${delta} (Sobra)`;
        return 'Exato';
    };

    const isSaving = (locationId, productId) => savingItemKey.value === makeKey(locationId, productId);

    // 🚀 SALVAMENTO ULTRA-RÁPIDO COM AXIOS (SEM RECARREGAR O INERTIA)
    const saveSingleCount = async (locationId, item, countedQty) => {
        const key = makeKey(locationId, item.id);
        savingItemKey.value = key;

        try {
            const response = await axios.post(route('operations.inventory.batch-adjust'), {
                items: [{
                    product_id: item.id,
                    location_id: locationId !== 0 ? locationId : null,
                    counted_stock: Number(countedQty)
                }],
                reason: `Balanço Unitário: ${item.name}`
            }, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            });

            if (response.data?.success) {
                // 1. Atualiza reativamente o rascunho com o valor confirmado
                draftCounts.value = {
                    ...draftCounts.value,
                    [key]: Number(countedQty)
                };

                // 2. Fecha o card expandido
                expandedItemKey.value = null;

                toast.add({
                    severity: 'success',
                    summary: 'Estoque Gravado',
                    detail: `${item.name}: conferido em ${countedQty} ${item.unit_of_measure}.`,
                    life: 2500
                });
            }
        } catch (err) {
            console.error("Erro no ajuste de estoque:", err);
            toast.add({
                severity: 'error',
                summary: 'Falha ao Gravar',
                detail: err.response?.data?.message || 'Erro ao processar balanço.',
                life: 3500
            });
        } finally {
            savingItemKey.value = null;
        }
    };

    const updateStatus = async (item, targetStatus) => {
        try {
            await axios.post(route('operations.availability.toggle'), {
                product_id: item.id,
                action: targetStatus,
                new_stock: targetStatus === 'out_of_stock' ? 0 : null
            }, {
                headers: { 'Accept': 'application/json' }
            });
            item.status = targetStatus;
        } catch (e) {
            console.error("Erro ao alterar status:", e);
        }
    };

    return {
        activeMode,
        selectedLocation,
        selectedType,
        searchQuery,
        modeOptions,
        typeFilters,
        hasActiveFilters,
        resetFilters,
        locationBlocks,
        routeLocations,
        locationOptions,
        isExpanded,
        toggleItemExpansion,
        isTouched,
        getDisplayValue,
        getItemDelta,
        formatDeltaLabel,
        isSaving,
        saveSingleCount,
        updateStatus
    };
}
```

## File: resources/js/Pages/Team/InventoryItemRow.vue
```vue
<template>
    <div
        class="flex flex-col transition-colors relative"
        :class="[
            isExpanded ? 'bg-surface-3/40 ring-1 ring-primary-500/30' : '',
            isTouched && !isExpanded ? 'bg-success-500/5' : ''
        ]"
    >
        <!-- ============================================== -->
        <!-- MODO A: INVENTÁRIO (CONTAGEM POR PONTO)         -->
        <!-- ============================================== -->
        <template v-if="activeMode === 'inventory'">
            <!-- 1. CABEÇALHO COMPACTO -->
            <div
                @click="$emit('toggle-expand')"
                class="p-3 flex items-center justify-between gap-3 cursor-pointer hover:bg-surface-1/40 active:bg-surface-1/60 transition-colors select-none"
            >
                <div class="flex-1 min-w-0">
                    <h4 class="font-bold text-surface-text text-sm leading-tight truncate">
                        {{ item.name }}
                    </h4>
                    <!-- 🏷️ Categoria + Quantidade Esperada -->
                    <span class="text-[10px] text-surface-muted truncate block mt-0.5">
                        {{ item.category?.name || 'Geral' }} <span class="opacity-40 mx-1">|</span> Esperado: <strong class="text-surface-text font-bold">{{ Number(item.local_expected_stock ?? 0) }}</strong> {{ item.unit_of_measure }}
                    </span>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <template v-if="isTouched">
                        <div class="flex flex-col items-end">
                            <span class="text-xs font-black text-surface-text flex items-center gap-1">
                                {{ displayValue }}
                                <span class="text-[9px] font-bold text-surface-muted">{{ item.unit_of_measure }}</span>
                            </span>
                            <span
                                class="text-[9px] font-black uppercase tracking-wider"
                                :class="delta < 0 ? 'text-danger-500' : (delta > 0 ? 'text-emerald-500' : 'text-surface-muted')"
                            >
                                {{ deltaLabel }}
                            </span>
                        </div>
                        <div class="w-6 h-6 rounded-full bg-emerald-500/15 text-emerald-500 flex items-center justify-center text-[10px] font-black">
                            <i class="pi pi-check"></i>
                        </div>
                    </template>

                    <template v-else>
                        <span class="px-2.5 py-1 rounded-lg bg-surface-3 text-surface-muted text-xs font-black border border-surface-border/50">
                            --
                        </span>
                        <i
                            class="pi pi-chevron-down text-[10px] text-surface-muted/60 transition-transform duration-200"
                            :class="{ 'rotate-180 text-primary-smart': isExpanded }"
                        ></i>
                    </template>
                </div>
            </div>

            <!-- 2. CORPO EXPANDIDO -->
            <div
                v-if="isExpanded"
                class="px-3 pb-3.5 pt-1 border-t border-surface-border/40 flex flex-col gap-2.5 bg-surface-1/40 animate-in fade-in slide-in-from-top-1 duration-150"
            >
                <!-- Linha Auxiliar (Exibida apenas se houver fardo ou diferença de estoque total) -->
                <div
                    v-if="(item.conversion_factor && item.conversion_factor > 1) || (item.total_system_stock !== item.local_expected_stock)"
                    class="flex items-center justify-between text-xs px-0.5"
                >
                    <span v-if="item.total_system_stock !== item.local_expected_stock" class="text-[10px] text-surface-muted">
                        Total na loja: <strong class="text-surface-text">{{ Number(item.total_system_stock) }}</strong> {{ item.unit_of_measure }}
                    </span>
                    <div v-else></div>

                    <button
                        v-if="item.conversion_factor && item.conversion_factor > 1"
                        type="button"
                        @click.stop="addPack(item.conversion_factor)"
                        class="h-6 px-2 rounded-lg bg-surface-3 hover:bg-surface-border text-surface-text border border-surface-border text-[9px] font-black uppercase tracking-wider flex items-center gap-1 cursor-pointer transition-all active:scale-95"
                        :title="`Adicionar 1 fardo com ${item.conversion_factor} unidades`"
                    >
                        <i class="pi pi-box text-[9px] text-amber-500"></i>
                        <span>+1 Fd ({{ item.conversion_factor }})</span>
                    </button>
                </div>

                <!-- 🚀 LINHA INTEGRADA: QTYSTEPPER + SLIDETOCONFIRM (MODO SUCCESS / "CONFIRMAR") -->
                <div class="flex items-center gap-2 w-full">
                    <!-- Stepper com input manual direto no centro -->
                    <QtyStepper
                        v-model="localCount"
                        :min="0"
                        :step="stepValue"
                        size="md"
                        class="bg-surface-2 border border-surface-border/80 shadow-inner w-32 shrink-0"
                    >
                        <template #value>
                            <input
                                type="text"
                                inputmode="decimal"
                                :value="localCount"
                                @input="handleManualInput($event.target.value)"
                                class="w-full text-center bg-transparent border-none text-sm font-black outline-none text-surface-text p-0 select-text cursor-text"
                                @click.stop
                            />
                        </template>
                    </QtyStepper>

                    <!-- Slider de Confirmação em Modo Success -->
                    <div class="flex-1 min-w-0">
                        <SlideToConfirm
                            text="Confirmar"
                            success-text="Confirmado!"
                            color="success"
                            icon="pi-check"
                            format="long"
                            :is-loading="isSaving"
                            class="!h-11 shadow-sm text-[11px]"
                            @confirm="onSlideConfirm"
                        />
                    </div>
                </div>
            </div>
        </template>

        <!-- ============================================== -->
        <!-- MODO B: STATUS (DISPONÍVEL / PAUSADO / ESGOT.) -->
        <!-- ============================================== -->
        <template v-else>
            <div class="p-3 flex items-center justify-between gap-3 w-full">
                <div class="min-w-0 flex-1">
                    <h4 class="font-bold text-surface-text leading-tight text-sm truncate">
                        {{ item.name }}
                    </h4>
                    <span class="text-[10px] text-surface-muted truncate block mt-0.5">
                        {{ item.category?.name || 'Geral' }} <span class="opacity-40 mx-1">|</span> Esperado: <strong class="text-surface-text font-bold">{{ Number(item.local_expected_stock ?? 0) }}</strong> {{ item.unit_of_measure }}
                    </span>
                </div>

                <div class="shrink-0 flex items-center bg-surface-1/80 p-0.5 rounded-full border border-surface-border/60 gap-0.5">
                    <button
                        type="button"
                        @click="$emit('update-status', 'available')"
                        class="w-7 h-7 rounded-full flex items-center justify-center transition-all cursor-pointer border-none outline-none active:scale-95"
                        :class="item.status === 'available' ? 'bg-emerald-500 text-white shadow-xs' : 'text-surface-muted hover:text-emerald-500 hover:bg-emerald-500/10'"
                        title="Disponível"
                    >
                        <i class="pi pi-check text-[10px] font-bold"></i>
                    </button>
                    <button
                        type="button"
                        @click="$emit('update-status', 'paused')"
                        class="w-7 h-7 rounded-full flex items-center justify-center transition-all cursor-pointer border-none outline-none active:scale-95"
                        :class="item.status === 'paused' ? 'bg-amber-500 text-white shadow-xs' : 'text-surface-muted hover:text-amber-500 hover:bg-amber-500/10'"
                        title="Pausar"
                    >
                        <i class="pi pi-pause text-[10px] font-bold"></i>
                    </button>
                    <button
                        type="button"
                        @click="$emit('update-status', 'out_of_stock')"
                        class="w-7 h-7 rounded-full flex items-center justify-center transition-all cursor-pointer border-none outline-none active:scale-95"
                        :class="item.status === 'out_of_stock' ? 'bg-red-500 text-white shadow-xs' : 'text-surface-muted hover:text-red-500 hover:bg-red-500/10'"
                        title="Esgotado"
                    >
                        <i class="pi pi-times text-[10px] font-bold"></i>
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import QtyStepper from '@/Components/UI/QtyStepper.vue';
import SlideToConfirm from '@/Components/UI/SlideToConfirm.vue';

const props = defineProps({
    item: { type: Object, required: true },
    locationId: { type: [Number, String], required: true },
    activeMode: { type: String, default: 'inventory' },
    isExpanded: { type: Boolean, default: false },
    isTouched: { type: Boolean, default: false },
    displayValue: { type: [String, Number], default: '--' },
    delta: { type: Number, default: 0 },
    deltaLabel: { type: String, default: 'Exato' },
    isSaving: { type: Boolean, default: false }
});

const emit = defineEmits(['toggle-expand', 'save', 'update-status']);

const localCount = ref(0);

watch(() => props.isExpanded, (expanded) => {
    if (expanded) {
        if (props.displayValue !== '--' && props.displayValue !== null && props.displayValue !== undefined) {
            localCount.value = Number(props.displayValue);
        } else {
            localCount.value = Number(props.item.local_expected_stock ?? 0);
        }
    }
});

const stepValue = computed(() => {
    const uom = (props.item.unit_of_measure || '').toLowerCase();
    return (uom === 'kg' || uom === 'l' || uom === 'litro' || uom === 'quilo') ? 0.5 : 1;
});

const handleManualInput = (val) => {
    if (val === '' || val === null || val === undefined) {
        localCount.value = 0;
        return;
    }
    const clean = Number(val.replace(',', '.'));
    if (!isNaN(clean)) {
        localCount.value = Math.max(0, clean);
    }
};

const addPack = (factor) => {
    localCount.value = Number((Number(localCount.value || 0) + Number(factor || 1)).toFixed(3));
};

const onSlideConfirm = () => {
    emit('save', Number(localCount.value || 0));
};
</script>
```

## File: app/Filament/Resources/Products/Tables/ProductTable.php
```php
<?php

namespace App\Filament\Resources\Products\Tables;


use App\Enums\ProductStatus;use App\Filament\Resources\Products\Actions\MergeProductsBulkAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Actions;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use App\Models\Category;
use App\Models\Product;
use App\Filament\Resources\Products\ProductResource;
use Illuminate\Database\Eloquent\Collection;
use Filament\Forms\Components as FormComponents;
use App\Services\Catalog\ProductCostingService;
use App\Services\Catalog\AvailabilityService;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

class ProductTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Product $record): string => ProductResource::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome do Produto')
                    ->searchable()
                    ->sortable()
                    ->grow(true)
                    ->wrap(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match (strtolower($state)) {
                        'dish'         => 'Prato',
                        'combo'        => 'Combo',
                        'resale'       => 'Revenda',
                        'ingredient'   => 'Insumo',
                        'manufactured' => 'Preparado',
                        default        => ucfirst($state),
                    })
                    ->color('gray')
                    ->grow(false),

                Tables\Columns\SelectColumn::make('category_id')
                    ->label('Categoria')
                    ->options(function () {
                        return Category::query()
                            ->with('parent')
                            ->get()
                            ->sortBy(fn ($cat) => ($cat->parent?->name ?? $cat->name) . ' ' . $cat->name)
                            ->mapWithKeys(function (Category $category) {
                                $label = $category->parent
                                    ? "{$category->parent->name} ➔ {$category->name}"
                                    : "📁 {$category->name}";

                                return [$category->id => $label];
                            })
                            ->toArray();
                    })
                    ->placeholder('Sem Categoria')
                    ->sortable()
                    ->selectablePlaceholder(false)
                    ->grow(true),

                Tables\Columns\TextInputColumn::make('dine_in_price')
                    ->label('Preço Salão')
                    ->type('number')
                    ->step('0.01')
                    ->rules(['required', 'numeric', 'min:0'])
                    ->sortable()
                    ->grow(false)
                    ->width('1%'),

                Tables\Columns\TextColumn::make('lucratividade')
                    ->label('Margem')
                    ->state(function (Product $record) {
                        if (in_array($record->type, ['ingredient', 'supply', 'internal'])) return null;

                        $data = ProductCostingService::getMarginData($record);
                        return $data['margin_percentage'] ?? 0;
                    })
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((float)$state, 2, ',', '.') . '%' : '-')
                    ->badge()
                    ->color(function ($state) {
                        if ($state === null) return 'gray';
                        $pct = (float) $state;
                        if ($pct >= 30) return 'success';
                        if ($pct >= 15) return 'warning';
                        return 'danger';
                    })
                    ->grow(false),

                Tables\Columns\SelectColumn::make('status')
                    ->label('Status')
                    ->options(ProductStatus::class)
                    ->selectablePlaceholder(false)
                    ->sortable()
                    ->rules(['required'])
                    ->grow(false)
                    ->width('155px')
                    ->updateStateUsing(function ($record, $state, $livewire) {
                        // 1. Barreira estrita: Disponível exige saldo físico positivo
                        if ($state === 'available' && $record->track_inventory && (float) $record->current_stock <= 0) {
                            Notification::make()
                                ->warning()
                                ->title('Estoque Zerado')
                                ->body("Você não pode colocar '{$record->name}' como disponível sem dar entrada no saldo.")
                                ->send();

                            $livewire->dispatch('$refresh');
                            return;
                        }

                        // 2. Dispara a máquina de estados completa do AvailabilityService
                        try {
                            app(AvailabilityService::class)->updateAvailability($record->id, $state);

                            Notification::make()
                                ->success()
                                ->title('Disponibilidade Atualizada')
                                ->body("Status de '{$record->name}' atualizado com cascata e propagação em tempo real.")
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->danger()
                                ->title('Erro ao Atualizar')
                                ->body($e->getMessage())
                                ->send();
                        }

                        $livewire->dispatch('$refresh');
                    }),

                Tables\Columns\TextColumn::make('connection_status')
                    ->label('Cadeia de custos')
                    ->state(function (Product $record) {
                        if ($record->type === 'dish') {
                            return $record->technicalSheets()->exists() ? 'Conectado' : 'Desconectado';
                        }

                        if ($record->type === 'resale') {
                            return $record->costHistories()->exists() ? 'Conectado' : 'Desconectado';
                        }

                        return 'Conectado';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Conectado'    => 'gray',
                        'Desconectado' => 'danger',
                        default        => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'Conectado'    => 'heroicon-m-link',
                        'Desconectado' => 'heroicon-m-link-slash',
                        default        => 'heroicon-m-link',
                    })
                    ->grow(false),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Categorias')
                    ->relationship('category', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->multiple()
                    ->options(ProductStatus::class),
            ])
            ->actions([])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    MergeProductsBulkAction::make(),

                    BulkAction::make('alterarCategoria')
                        ->label('Alterar Categoria')
                        ->icon('heroicon-o-tag')
                        ->form([
                            FormComponents\Select::make('category_id')
                                ->label('Nova Categoria')
                                ->options(fn () => Category::pluck('name', 'id'))
                                ->required(),
                        ])
                        ->action(fn(Collection $records, array $data) => $records->each->update(['category_id' => $data['category_id']])),

                    Actions\BulkAction::make('alterarStatus')
                        ->label('Alterar Status')
                        ->icon('heroicon-o-check-circle')
                        ->form([
                            FormComponents\Select::make('status')
                                ->label('Novo Status')
                                ->options(ProductStatus::class)
                                ->required(),
                        ])
                        ->action(function(Collection $records, array $data) {
                            $successCount = 0;
                            $skippedCount = 0;
                            $availabilityService = app(AvailabilityService::class);

                            foreach ($records as $record) {
                                if ($data['status'] === 'available' && $record->track_inventory && (float) $record->current_stock <= 0) {
                                    $skippedCount++;
                                    continue;
                                }

                                try {
                                    $availabilityService->updateAvailability($record->id, $data['status']);
                                    $successCount++;
                                } catch (\Throwable $e) {
                                    $skippedCount++;
                                }
                            }

                            if ($skippedCount > 0) {
                                Notification::make()
                                    ->warning()
                                    ->title('Atenção ao Estoque')
                                    ->body("{$successCount} produtos atualizados. {$skippedCount} foram ignorados por estarem sem estoque ou com pendências.")
                                    ->send();
                            } else {
                                Notification::make()
                                    ->success()
                                    ->title('Sucesso!')
                                    ->body("{$successCount} produtos atualizados com propagação em tempo real.")
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }
}
```

## File: app/Http/Controllers/Tenant/Operations/InventoryMovementController.php
```php
<?php

namespace App\Http\Controllers\Tenant\Operations;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Catalog\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InventoryMovementController extends Controller
{
    public function __construct(protected InventoryService $inventoryService) {}

    public function adjust(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'new_stock' => 'required|numeric|min:0',
            'reason'    => 'required|string|max:255',
        ]);

        $this->inventoryService->adjustStock(
            $product,
            (float) $validated['new_stock'],
            $validated['reason'],
            auth()->id()
        );

        return back()->with('success', 'Estoque ajustado com sucesso!');
    }

    public function batchAdjust(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'items'                 => 'required|array|min:1',
            'items.*.product_id'    => 'required|exists:products,id',
            'items.*.location_id'   => 'nullable|exists:storage_locations,id',
            'items.*.counted_stock' => 'required|numeric|min:0',
            'reason'                => 'nullable|string|max:255',
        ]);

        $reason = $validated['reason'] ?? 'Balanço Físico Operacional (TeamApp)';
        $result = $this->inventoryService->batchAdjust($validated['items'], $reason, (int) auth()->id());

        $msg = 'Balanço salvo com sucesso!';
        if ($result['losses_count'] > 0) {
            $msg .= " ({$result['losses_count']} divergências de quebra registradas em auditoria).";
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success'      => true,
                'message'      => $msg,
                'losses_count' => $result['losses_count'] ?? 0,
            ]);
        }

        return back()->with('success', $msg);
    }
}
```

## File: resources/js/Pages/Team/InventoryTab.vue
```vue
<template>
    <div
        class="flex flex-col h-full overflow-y-auto hide-scrollbar relative pb-28 select-none"
        :style="{ paddingTop: 'var(--app-header-height, 6rem)' }"
    >
        <!-- TELEPORT PARA O TEAM HEADER -->
        <Teleport to="#team-header-action-portal" v-if="isMounted">
            <div class="flex items-center gap-1.5 shrink-0">
                <div
                    class="transition-all duration-300 ease-out"
                    :class="searchQuery ? 'w-36 sm:w-44' : 'w-24 sm:w-32 focus-within:w-36 sm:focus-within:w-48'"
                >
                    <ExpandableSearch v-model="searchQuery" placeholder="Buscar..." class="!h-9 !w-full" />
                </div>

                <button
                    type="button"
                    @click="isFilterModalOpen = true"
                    class="w-9 h-9 rounded-full flex items-center justify-center transition-all cursor-pointer outline-none border relative active:scale-95 shrink-0 shadow-xs"
                    :class="hasActiveFilters
                        ? 'bg-primary-500 text-white border-primary-400 shadow-primary-500/20'
                        : 'bg-surface-2 text-surface-muted border-surface-border/60 hover:text-surface-text hover:bg-surface-3'"
                >
                    <i class="pi pi-sliders-h text-xs"></i>
                    <span v-if="hasActiveFilters" class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 bg-primary-smart rounded-full ring-2 ring-surface-1"></span>
                </button>
            </div>
        </Teleport>

        <!-- ROTA DE CAMINHADA (CARROSSEL FÍSICO) -->
        <div class="px-4 pt-3 pb-1 shrink-0 overflow-x-auto hide-scrollbar flex items-center gap-2 border-b border-surface-border/30 bg-surface-1/40">
            <button
                type="button"
                @click="selectedLocation = 'all'"
                class="h-8 px-3 rounded-full text-xs font-bold transition-all shrink-0 flex items-center gap-1.5 border cursor-pointer"
                :class="selectedLocation === 'all'
                    ? 'bg-primary-500 text-white border-primary-400 shadow-sm'
                    : 'bg-surface-2 text-surface-muted border-surface-border/60 hover:text-surface-text'"
            >
                <i class="pi pi-globe text-[10px]"></i>
                <span>Todos</span>
            </button>

            <button
                v-for="loc in routeLocations"
                :key="loc.id"
                type="button"
                @click="selectedLocation = String(loc.id)"
                class="h-8 px-3 rounded-full text-xs font-bold transition-all shrink-0 flex items-center gap-1.5 border cursor-pointer"
                :class="selectedLocation === String(loc.id)
                    ? 'bg-primary-500 text-white border-primary-400 shadow-sm'
                    : 'bg-surface-2 text-surface-muted border-surface-border/60 hover:text-surface-text'"
            >
                <i class="pi text-[10px]" :class="loc.type === 'display' ? 'pi-eye text-primary-smart' : 'pi-box text-amber-500'"></i>
                <span>{{ loc.name }}</span>
                <span
                    class="text-[10px] px-1.5 py-0.2 rounded-full font-black"
                    :class="loc.isComplete
                        ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30'
                        : (selectedLocation === String(loc.id) ? 'bg-white/20 text-white' : 'bg-surface-3 text-surface-muted')"
                >
                    <i v-if="loc.isComplete" class="pi pi-check text-[9px]"></i>
                    <template v-else>{{ loc.touchedCount }}/{{ loc.totalCount }}</template>
                </span>
            </button>
        </div>

        <!-- CONTEÚDO PRINCIPAL -->
        <div class="p-3 md:p-6 flex-1 flex flex-col gap-4">
            <!-- Pílula de Filtros Ativos -->
            <div v-if="hasActiveFilters" class="flex items-center justify-between gap-2 p-2 rounded-xl bg-surface-2/60 border border-surface-border/40 text-[10px]">
                <div class="flex items-center gap-1.5 overflow-x-auto hide-scrollbar text-surface-muted font-bold">
                    <span class="uppercase tracking-widest text-[9px] text-surface-muted/80">Ativos:</span>
                    <span v-if="activeMode === 'inventory'" class="px-2 py-0.5 rounded-full bg-amber-500/15 border border-amber-500/30 text-amber-500 font-black">Inventário</span>
                    <span v-if="selectedType !== 'all'" class="px-2 py-0.5 rounded-full bg-surface-3 border border-surface-border text-surface-text">
                        {{ typeFilters.find(t => t.value === selectedType)?.label }}
                    </span>
                    <span v-if="selectedLocation !== 'all'" class="px-2 py-0.5 rounded-full bg-surface-3 border border-surface-border text-surface-text">
                        {{ locationOptions.find(l => l.value === selectedLocation)?.label }}
                    </span>
                </div>
                <button type="button" @click="resetFilters" class="text-[10px] font-bold text-surface-muted hover:text-danger-500 underline border-none bg-transparent cursor-pointer shrink-0">
                    Limpar
                </button>
            </div>

            <!-- BLOCOS DE LOCAIS FÍSICOS -->
            <template v-if="locationBlocks.length > 0">
                <div
                    v-for="block in locationBlocks"
                    :key="block.location_id"
                    class="bg-surface-2 rounded-2xl border border-surface-border overflow-hidden shadow-sm flex flex-col"
                >
                    <div class="px-4 py-2.5 bg-surface-3/50 border-b border-surface-border flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2 min-w-0">
                            <i class="pi text-xs shrink-0" :class="block.type === 'display' ? 'pi-eye text-primary-500' : 'pi-box text-amber-500'"></i>
                            <h3 class="font-bold text-surface-text uppercase tracking-wider text-xs truncate">
                                {{ block.location_name }}
                            </h3>
                        </div>
                        <span class="bg-surface-border text-surface-text text-[10px] px-2 py-0.5 rounded-full font-black shrink-0">
                            {{ block.items.length }} {{ block.items.length === 1 ? 'item' : 'itens' }}
                        </span>
                    </div>

                    <!-- Lista com Separadores de Categoria (Prateleiras) -->
                    <div class="flex flex-col divide-y divide-surface-border/40">
                        <template
                            v-for="(item, idx) in block.items"
                            :key="`${block.location_id}_${item.id}`"
                        >
                            <div
                                v-if="idx === 0 || (item.category?.name || 'Geral') !== (block.items[idx - 1]?.category?.name || 'Geral')"
                                class="px-3.5 py-1.5 bg-surface-3/40 border-y border-surface-border/40 flex items-center justify-between text-[10px] font-black uppercase tracking-wider text-primary-smart select-none"
                            >
                                <span class="flex items-center gap-1.5">
                                    <i class="pi pi-tag text-[9px]"></i>
                                    {{ item.category?.name || 'Geral' }}
                                </span>
                                <span class="text-[9px] font-bold text-surface-muted lowercase">
                                    {{ block.items.filter(i => (i.category?.name || 'Geral') === (item.category?.name || 'Geral')).length }} itens
                                </span>
                            </div>

                            <InventoryItemRow
                                :item="item"
                                :location-id="block.location_id"
                                :active-mode="activeMode"
                                :is-expanded="isExpanded(block.location_id, item.id)"
                                :is-touched="isTouched(block.location_id, item.id)"
                                :display-value="getDisplayValue(block.location_id, item.id)"
                                :delta="getItemDelta(block.location_id, item)"
                                :delta-label="formatDeltaLabel(block.location_id, item)"
                                :is-saving="isSaving(block.location_id, item.id)"
                                @toggle-expand="toggleItemExpansion(block.location_id, item)"
                                @save="qty => saveSingleCount(block.location_id, item, qty)"
                                @update-status="target => updateStatus(item, target)"
                            />
                        </template>
                    </div>
                </div>
            </template>

            <div v-else class="flex flex-col items-center justify-center py-20 text-surface-muted opacity-60">
                <i class="pi pi-box text-5xl mb-3 text-primary-500/40"></i>
                <h3 class="text-lg font-bold text-center">Nenhum item encontrado</h3>
                <p class="text-xs mt-1 text-center max-w-xs">Ajuste os filtros ou escolha outro local.</p>
            </div>
        </div>

        <!-- MODAL DE FILTROS -->
        <AdaptiveModal v-model:visible="isFilterModalOpen">
            <div class="flex flex-col w-full text-surface-text select-none">
                <div class="flex items-center justify-between p-4 border-b border-surface-border/40">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-primary-500/10 text-primary-smart flex items-center justify-center font-black">
                            <i class="pi pi-sliders-h text-sm"></i>
                        </div>
                        <h3 class="text-sm font-black tracking-tight leading-tight">Configurações de Estoque</h3>
                    </div>
                    <button type="button" @click="isFilterModalOpen = false" class="w-8 h-8 rounded-full bg-surface-2 text-surface-muted hover:text-surface-text flex items-center justify-center border-none cursor-pointer">
                        <i class="pi pi-times text-xs"></i>
                    </button>
                </div>

                <div class="p-4 flex flex-col gap-5">
                    <div class="flex flex-col gap-2 items-start">
                        <span class="text-[10px] font-black uppercase tracking-widest text-surface-muted">Modo de Operação</span>
                        <SegmentedControl v-model="activeMode" :options="modeOptions" class="!h-9 text-xs w-fit !mx-0" />
                    </div>
                    <div class="flex flex-col gap-2 items-start w-full">
                        <span class="text-[10px] font-black uppercase tracking-widest text-surface-muted">Categoria Operacional</span>
                        <div class="w-full overflow-x-auto hide-scrollbar pb-1">
                            <SegmentedControl v-model="selectedType" :options="typeFilters" class="!h-9 text-xs w-fit shrink-0 !mx-0" />
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3 p-4 border-t border-surface-border/40 bg-surface-2/30">
                    <button v-if="hasActiveFilters" type="button" @click="resetFilters" class="text-xs font-bold text-surface-muted hover:text-surface-text underline bg-transparent border-none cursor-pointer">
                        Limpar Filtros
                    </button>
                    <div v-else></div>
                    <button type="button" @click="isFilterModalOpen = false" class="h-10 px-6 rounded-2xl bg-primary-500 text-white font-black text-xs uppercase tracking-wider border-none cursor-pointer">
                        Pronto
                    </button>
                </div>
            </div>
        </AdaptiveModal>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import ExpandableSearch from '@/Components/UI/ExpandableSearch.vue';
import SegmentedControl from '@/Components/UI/SegmentedControl.vue';
import AdaptiveModal from '@/Components/UI/AdaptiveModal.vue';

import InventoryItemRow from '@/Pages/Team/InventoryItemRow.vue';
import { useInventoryCount } from '@/Pages/Team/Composables/useInventoryCount';

const props = defineProps({
    catalog: { type: Array, default: () => [] },
    isActive: { type: Boolean, default: false }
});

const isMounted = ref(false);
const isFilterModalOpen = ref(false);

const catalogRef = computed(() => props.catalog);

const {
    activeMode,
    selectedLocation,
    selectedType,
    searchQuery,
    modeOptions,
    typeFilters,
    hasActiveFilters,
    resetFilters,
    locationBlocks,
    routeLocations,
    locationOptions,
    isExpanded,
    toggleItemExpansion,
    isTouched,
    getDisplayValue,
    getItemDelta,
    formatDeltaLabel,
    isSaving,
    saveSingleCount,
    updateStatus
} = useInventoryCount(catalogRef);

onMounted(() => {
    isMounted.value = true;
});
</script>
```
