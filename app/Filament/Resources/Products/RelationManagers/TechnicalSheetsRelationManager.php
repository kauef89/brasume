<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Product;
use Filament\Schemas\Components\Utilities\Get;
use App\Services\Catalog\ProductCostingService;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;
use Filament\Tables\Filters\TernaryFilter;

class TechnicalSheetsRelationManager extends RelationManager
{
    protected static string $relationship = "technicalSheets";
    protected static ?string $title = "Ficha Técnica";
    protected static ?string $modelLabel = "Item da Ficha Técnica";
    protected static ?string $pluralModelLabel = "Itens da Ficha Técnica";

    public function form(Schema $schema): Schema
    {
        $isFinalProduct = in_array($this->getOwnerRecord()->type, [
            "dish",
            "combo",
            "resale",
        ]);

        return $schema
            ->components([
                Select::make("ingredient_product_id")
                    ->label("Componente (Insumo, Preparado ou Embalagem)")
                    ->options(function () {
                        return Product::query()
                            ->whereIn("type", [
                                \App\Enums\ProductType::INGREDIENT->value,
                                \App\Enums\ProductType::MANUFACTURED->value,
                                \App\Enums\ProductType::PACKAGING->value,
                                \App\Enums\ProductType::CONSUMABLE->value,
                                \App\Enums\ProductType::RESALE->value,
                            ])
                            ->with("category")
                            ->get()
                            ->groupBy(fn($p) => $p->category?->name ?? "Outros / Sem Categoria")
                            ->map(fn($prods) => $prods->pluck("name", "id"))
                            ->toArray();
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->columnSpan(1),

                TextInput::make("quantity")
                    ->label("Quantidade")
                    ->numeric()
                    ->inputMode("decimal")
                    ->required()
                    ->suffix(fn(Get $get) => Product::find($get("ingredient_product_id"))?->unit_of_measure)
                    ->columnSpan(1),

                // 🚀 O MODO DE CONSUMO REAL:
                Toggle::make("is_dine_in")
                    ->label("Consumo no Local (Salão)")
                    ->default(true)
                    ->helperText("Ative se este item é servido no consumo dentro da loja.")
                    ->visible($isFinalProduct)
                    ->columnSpan(1),

                Toggle::make("is_takeaway")
                    ->label("Para Viagem (Embalagem)")
                    ->default(true)
                    ->helperText("Ative se este item compõe a entrega ou retirada.")
                    ->visible($isFinalProduct)
                    ->columnSpan(1),

                Toggle::make("is_active")
                    ->label("Ativo na Receita")
                    ->default(true)
                    ->columnSpan(2),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        $isFinalProduct = in_array($this->getOwnerRecord()->type, [
            "dish",
            "combo",
            "resale",
        ]);

        return $table
            ->recordTitleAttribute("ingredient_product_id")
            ->columns([
                Tables\Columns\ToggleColumn::make("is_active")
                    ->label("Ativo")
                    ->afterStateUpdated(function ($record, $state, $livewire) {
                        $livewire->dispatch("update-margin-widget");
                        \App\Services\Catalog\ProductCostingService::recordPriceSnapshot($livewire->getOwnerRecord(), 'recipe_update');
                    }),

                Tables\Columns\TextColumn::make("ingredient.name")
                    ->label("Ingrediente / Material")
                    ->searchable()
                    ->sortable()
                    ->weight("bold")
                    ->color(fn ($record) => $record->is_active ? null : 'gray')
                    ->description(fn ($record) => $record->is_active ? '' : 'Insumo desativado'),

                Tables\Columns\TextColumn::make("quantity")
                    ->label("Quantidade")
                    ->state(function ($record) {
                        $val = (float) $record->quantity;
                        $um = strtolower($record->ingredient?->unit_of_measure ?? "");
                        $numStr = (floor($val) == $val) ? number_format($val, 0, ",", ".") : rtrim(rtrim(number_format($val, 4, ",", "."), "0"), ",");
                        return "{$numStr} {$um}";
                    })
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make("cost")
                    ->label("Custo Total")
                    ->state(function ($record) {
                        $ingredient = $record->ingredient;
                        if (!$ingredient) return 0;
                        return (float) $record->quantity * ProductCostingService::calculateBaseCost($ingredient);
                    })
                    ->money("BRL")
                    ->weight("bold")
                    ->color(fn ($record) => $record->is_active ? 'danger' : 'gray'),

                // 🚀 COLUNA DE MODO DE CONSUMO:
                Tables\Columns\TextColumn::make("consumption_mode")
                    ->label("Aplicação")
                    ->badge()
                    ->state(function ($record) {
                        if ($record->is_dine_in && $record->is_takeaway) return 'Salão & Viagem';
                        if ($record->is_dine_in) return 'Apenas Salão';
                        if ($record->is_takeaway) return 'Apenas Viagem';
                        return 'Inativo';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Salão & Viagem' => 'gray',
                        'Apenas Salão'   => 'info',
                        'Apenas Viagem'  => 'warning',
                        default          => 'danger',
                    })
                    ->visible($isFinalProduct),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status do Insumo')
                    ->placeholder('Todos')
                    ->trueLabel('Apenas Ativos')
                    ->falseLabel('Apenas Inativos')
                    ->default(true),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->label("Adicionar Insumo")
                    ->after(function($livewire) {
                        $livewire->dispatch("update-margin-widget");
                        \App\Services\Catalog\ProductCostingService::recordPriceSnapshot($livewire->getOwnerRecord(), 'recipe_update');
                    }),

                Actions\ActionGroup::make([
                    Actions\Action::make("cloneFrom")
                        ->label("Clonar de...")
                        ->icon("heroicon-o-arrow-down-tray")
                        ->color("info")
                        ->form([
                            Select::make("source_product_id")
                                ->label("Puxar receita de qual produto?")
                                ->searchable()
                                ->preload()
                                ->options(fn($livewire) => Product::whereHas("technicalSheets")
                                    ->where("id", "!=", $livewire->getOwnerRecord()->id)
                                    ->pluck("name", "id"))
                                ->required(),

                            Toggle::make("clear_current")
                                ->label("Limpar ficha atual?")
                                ->default(false),
                        ])
                        ->action(function (array $data, $livewire) {
                            $ownerRecord = $livewire->getOwnerRecord();
                            $sourceProduct = Product::with("technicalSheets")->find($data["source_product_id"]);

                            if (!$sourceProduct || $sourceProduct->technicalSheets->isEmpty()) {
                                Notification::make()->warning()->title("Ficha Vazia!")->send();
                                return;
                            }

                            if ($data["clear_current"]) {
                                DB::table("technical_sheets")->where("parent_product_id", $ownerRecord->id)->delete();
                            }

                            foreach ($sourceProduct->technicalSheets as $item) {
                                if (!$data["clear_current"]) {
                                    $exists = DB::table("technical_sheets")
                                        ->where("parent_product_id", $ownerRecord->id)
                                        ->where("ingredient_product_id", $item->ingredient_product_id)
                                        ->exists();
                                    if ($exists) continue;
                                }

                                DB::table("technical_sheets")->insert([
                                    "parent_product_id"     => $ownerRecord->id,
                                    "ingredient_product_id" => $item->ingredient_product_id,
                                    "quantity"              => $item->quantity,
                                    "is_dine_in"            => $item->is_dine_in ?? true,
                                    "is_takeaway"           => $item->is_takeaway ?? true,
                                    "order_type"            => $item->order_type,
                                    "is_active"             => $item->is_active ?? true,
                                    "created_at"            => now(),
                                    "updated_at"            => now(),
                                ]);
                            }

                            $livewire->dispatch("update-margin-widget");
                            \App\Services\Catalog\ProductCostingService::recordPriceSnapshot($ownerRecord, 'recipe_update');

                            Notification::make()->success()->title("Receita Importada!")->send();
                        }),

                    Actions\Action::make("cloneSheet")
                        ->label("Clonar para...")
                        ->icon("heroicon-o-arrow-up-tray")
                        ->color("warning")
                        ->form([
                            Select::make("target_product_ids")
                                ->label("Copiar para quais produtos?")
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->options(fn($livewire) => Product::where("id", "!=", $livewire->getOwnerRecord()->id)
                                    ->whereIn("type", ["dish", "combo", "manufactured", "ingredient"])
                                    ->pluck("name", "id"))
                                ->required(),

                            Toggle::make("clear_target")
                                ->label("Substituir ficha existente nos destinos?")
                                ->default(false),
                        ])
                        ->action(function (array $data, $livewire) {
                            $ownerRecord = $livewire->getOwnerRecord();
                            $items = $ownerRecord->technicalSheets;

                            if ($items->isEmpty()) {
                                Notification::make()->warning()->title("Ficha Vazia")->send();
                                return;
                            }

                            foreach ($data["target_product_ids"] as $targetId) {
                                if ($data["clear_target"]) {
                                    DB::table("technical_sheets")->where("parent_product_id", $targetId)->delete();
                                }

                                foreach ($items as $item) {
                                    if (!$data["clear_target"]) {
                                        $exists = DB::table("technical_sheets")
                                            ->where("parent_product_id", $targetId)
                                            ->where("ingredient_product_id", $item->ingredient_product_id)
                                            ->exists();
                                        if ($exists) continue;
                                    }

                                    DB::table("technical_sheets")->insert([
                                        "parent_product_id"     => $targetId,
                                        "ingredient_product_id" => $item->ingredient_product_id,
                                        "quantity"              => $item->quantity,
                                        "is_dine_in"            => $item->is_dine_in ?? true,
                                        "is_takeaway"           => $item->is_takeaway ?? true,
                                        "order_type"            => $item->order_type,
                                        "is_active"             => $item->is_active ?? true,
                                        "created_at"            => now(),
                                        "updated_at"            => now(),
                                    ]);
                                }

                                $targetProduct = Product::find($targetId);
                                if ($targetProduct && in_array($targetProduct->type, ['dish', 'combo', 'resale'])) {
                                    \App\Services\Catalog\ProductCostingService::recordPriceSnapshot($targetProduct, 'recipe_update');
                                }
                            }

                            Notification::make()->success()->title("Receita Copiada!")->send();
                        }),
                ])
                    ->label("Ferramentas")
                    ->icon("heroicon-m-wrench-screwdriver")
                    ->button()
                    ->color("gray"),
            ])
            ->actions([
                Actions\EditAction::make()->after(function($livewire) {
                    $livewire->dispatch("update-margin-widget");
                    \App\Services\Catalog\ProductCostingService::recordPriceSnapshot($livewire->getOwnerRecord(), 'recipe_update');
                }),
                Actions\DeleteAction::make()->after(function($livewire) {
                    $livewire->dispatch("update-margin-widget");
                    \App\Services\Catalog\ProductCostingService::recordPriceSnapshot($livewire->getOwnerRecord(), 'recipe_update');
                }),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('activate')
                        ->label('Ativar selecionados')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, $livewire) {
                            $records->each->update(['is_active' => true]);
                            $livewire->dispatch("update-margin-widget");
                            \App\Services\Catalog\ProductCostingService::recordPriceSnapshot($livewire->getOwnerRecord(), 'recipe_update');
                        }),

                    Actions\BulkAction::make('deactivate')
                        ->label('Desativar selecionados')
                        ->icon('heroicon-o-x-circle')
                        ->color('gray')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, $livewire) {
                            $records->each->update(['is_active' => false]);
                            $livewire->dispatch("update-margin-widget");
                            \App\Services\Catalog\ProductCostingService::recordPriceSnapshot($livewire->getOwnerRecord(), 'recipe_update');
                        }),

                    Actions\DeleteBulkAction::make()->after(function($livewire) {
                        $livewire->dispatch("update-margin-widget");
                        \App\Services\Catalog\ProductCostingService::recordPriceSnapshot($livewire->getOwnerRecord(), 'recipe_update');
                    }),
                ]),
            ])
            ->contentFooter(fn() => view("filament.components.tech-sheet-footer", [
                "ownerRecord"    => $this->getOwnerRecord(),
                "isFinalProduct" => $isFinalProduct,
            ]))
            ->paginationPageOptions(["all"]);
    }

    public static function canViewForRecord($ownerRecord, $pageClass): bool
    {
        return in_array($ownerRecord->type, ["dish", "combo", "manufactured"]);
    }
}
