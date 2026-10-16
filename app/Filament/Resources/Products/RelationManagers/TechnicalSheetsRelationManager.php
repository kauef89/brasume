<?php

namespace App\Filament\Resources\Products\RelationManagers;


use App\Enums\OrderType;use Filament\Resources\RelationManagers\RelationManager;
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
                    ->label("Componente (Insumo, Preparado ou Material)")
                    ->options(function () {
                        return Product::query()
                            ->whereHas(
                                "category",
                                fn($query) => $query->whereIn("type", [
                                    "internal",
                                    "supply",
                                    "resale",
                                ]),
                            )
                            ->with("category")
                            ->get()
                            ->groupBy(function ($product) {
                                return $product->category?->name ??
                                    "Outros / Sem Categoria";
                            })
                            ->map(
                                fn($products) => $products->pluck("name", "id"),
                            )
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
                    ->suffix(function (Get $get) {
                        $productId = $get("ingredient_product_id");
                        if (!$productId) {
                            return null;
                        }

                        return Product::find($productId)?->unit_of_measure;
                    })
                    ->columnSpan(1),

                Select::make("order_type")
                    ->label("Aplicação (Canais)")
                    ->multiple()
                    ->options(OrderType::class)
                    ->default(["table", "counter", "delivery"])
                    ->required()
                    ->columnSpan(1)
                    ->visible($isFinalProduct),

                Toggle::make("is_active")
                    ->label("Ativo")
                    ->helperText("Desative insumos antigos em vez de apagá-los para não perder histórico.")
                    ->default(true)
                    ->columnSpan(1),
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
                    }),

                Tables\Columns\TextColumn::make("ingredient.name")
                    ->label("Ingrediente/Insumo")
                    ->searchable()
                    ->sortable()
                    ->weight("bold")
                    ->color(fn ($record) => $record->is_active ? null : 'gray')
                    ->description(fn ($record) => $record->is_active ? '' : 'Insumo desativado'),

                // A Fusão da Quantidade + Unidade de Medida
                Tables\Columns\TextColumn::make("quantity")
                    ->label("Quantidade")
                    ->state(function ($record) {
                        $val = (float) $record->quantity;
                        $um = strtolower(
                            $record->ingredient?->unit_of_measure ?? "",
                        );

                        if (floor($val) == $val) {
                            $numStr = number_format($val, 0, ",", ".");
                        } else {
                            $numStr = number_format($val, 4, ",", ".");
                            $numStr = rtrim(rtrim($numStr, "0"), ",");
                        }

                        return "{$numStr} {$um}";
                    })
                    ->badge()
                    ->color(fn ($record) => $record->is_active ? 'gray' : 'gray'),

                Tables\Columns\TextColumn::make("cost")
                    ->label("Custo Total")
                    ->state(function ($record) {
                        $ingredient = $record->ingredient;
                        if (!$ingredient) {
                            return 0;
                        }

                        $unitCost = ProductCostingService::calculateBaseCost(
                            $ingredient,
                        );
                        return (float) $record->quantity * $unitCost;
                    })
                    ->money("BRL")
                    ->weight("bold")
                    ->color(fn ($record) => $record->is_active ? 'danger' : 'gray'),

                Tables\Columns\TextColumn::make("order_type")
                    ->label("Aplicação")
                    ->badge()
                    ->formatStateUsing(
                        fn(string $state): string => match ($state) {
                            "table" => "Mesa",
                            "counter" => "Balcão",
                            "delivery" => "Delivery",
                            default => $state,
                        },
                    )
                    ->color(
                        fn(string $state, $record): string => !$record->is_active ? "gray" : match ($state) {
                            "table" => "info",
                            "counter" => "warning",
                            "delivery" => "success",
                            default => "gray",
                        },
                    )
                    ->visible($isFinalProduct),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status do Insumo')
                    ->placeholder('Todos')
                    ->trueLabel('Apenas Ativos')
                    ->falseLabel('Apenas Inativos')
                    ->default(true), // Por padrão exibe apenas ativos para não poluir a ficha
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->label("Adicionar Insumo")
                    ->after(
                        fn($livewire) => $livewire->dispatch(
                            "update-margin-widget",
                        ),
                    ),

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
                                ->options(function ($livewire) {
                                    $ownerId = $livewire->getOwnerRecord()->id;
                                    return Product::whereHas("technicalSheets")
                                        ->where("id", "!=", $ownerId)
                                        ->pluck("name", "id");
                                })
                                ->required(),

                            Toggle::make("clear_current")
                                ->label("Limpar ficha atual?")
                                ->helperText("Apagaremos todos os itens desta ficha antes de puxar a nova receita.",
                                )
                                ->default(false),
                        ])
                        ->action(function (array $data, $livewire) {
                            $ownerRecord = $livewire->getOwnerRecord();
                            $sourceProduct = Product::with(
                                "technicalSheets",
                            )->find($data["source_product_id"]);

                            if (
                                !$sourceProduct ||
                                $sourceProduct->technicalSheets->isEmpty()
                            ) {
                                Notification::make()
                                    ->warning()
                                    ->title("Ficha Vazia!")
                                    ->body("Origem sem receita.")
                                    ->send();
                                return;
                            }

                            if ($data["clear_current"]) {
                                DB::table("technical_sheets")
                                    ->where(
                                        "parent_product_id",
                                        $ownerRecord->id,
                                    )
                                    ->delete();
                            }

                            foreach ($sourceProduct->technicalSheets as $item) {
                                $encodedOrderType = is_array($item->order_type)
                                    ? json_encode($item->order_type)
                                    : $item->order_type;

                                if (!$data["clear_current"]) {
                                    $exists = DB::table("technical_sheets")
                                        ->where(
                                            "parent_product_id",
                                            $ownerRecord->id,
                                        )
                                        ->where(
                                            "ingredient_product_id",
                                            $item->ingredient_product_id,
                                        )
                                        ->where("order_type", $encodedOrderType)
                                        ->exists();

                                    if ($exists) {
                                        continue;
                                    }
                                }

                                DB::table("technical_sheets")->insert([
                                    "parent_product_id" => $ownerRecord->id,
                                    "ingredient_product_id" =>
                                        $item->ingredient_product_id,
                                    "quantity" => $item->quantity,
                                    "order_type" => $encodedOrderType,
                                    "is_active" => $item->is_active ?? true,
                                    "created_at" => now(),
                                    "updated_at" => now(),
                                ]);
                            }

                            $livewire->dispatch("update-margin-widget");

                            Notification::make()
                                ->success()
                                ->title("Importação Concluída!")
                                ->body("A receita foi importada.")
                                ->send();
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
                                ->options(function ($livewire) {
                                    $ownerId = $livewire->getOwnerRecord()->id;
                                    return Product::where("id", "!=", $ownerId)
                                        ->whereIn("type", [
                                            "dish",
                                            "combo",
                                            "manufactured",
                                            "ingredient",
                                        ])
                                        ->pluck("name", "id");
                                })
                                ->required(),

                            Toggle::make("clear_target")
                                ->label("Substituir ficha técnica existente?")
                                ->helperText("Apaga a receita atual dos produtos de destino antes de colar.",
                                )
                                ->default(false),
                        ])
                        ->action(function (array $data, $livewire) {
                            $ownerRecord = $livewire->getOwnerRecord();
                            $items = $ownerRecord->technicalSheets;

                            if ($items->isEmpty()) {
                                Notification::make()
                                    ->warning()
                                    ->title("Ficha Vazia")
                                    ->body("Adicione insumos antes de clonar.")
                                    ->send();
                                return;
                            }

                            foreach ($data["target_product_ids"] as $targetId) {
                                if ($data["clear_target"]) {
                                    DB::table("technical_sheets")
                                        ->where("parent_product_id", $targetId)
                                        ->delete();
                                }

                                foreach ($items as $item) {
                                    $encodedOrderType = is_array(
                                        $item->order_type,
                                    )
                                        ? json_encode($item->order_type)
                                        : $item->order_type;

                                    if (!$data["clear_target"]) {
                                        $exists = DB::table("technical_sheets")
                                            ->where(
                                                "parent_product_id",
                                                $targetId,
                                            )
                                            ->where(
                                                "ingredient_product_id",
                                                $item->ingredient_product_id,
                                            )
                                            ->where(
                                                "order_type",
                                                $encodedOrderType,
                                            )
                                            ->exists();

                                        if ($exists) {
                                            continue;
                                        }
                                    }

                                    DB::table("technical_sheets")->insert([
                                        "parent_product_id" => $targetId,
                                        "ingredient_product_id" =>
                                            $item->ingredient_product_id,
                                        "quantity" => $item->quantity,
                                        "order_type" => $encodedOrderType,
                                        "is_active" => $item->is_active ?? true,
                                        "created_at" => now(),
                                        "updated_at" => now(),
                                    ]);
                                }
                            }

                            Notification::make()
                                ->success()
                                ->title("Clonagem Concluída!")
                                ->body("A receita foi copiada.")
                                ->send();
                        }),
                ])
                    ->label("Ferramentas")
                    ->icon("heroicon-m-wrench-screwdriver")
                    ->button()
                    ->color("gray"),
            ])
            ->actions([
                Actions\EditAction::make()->after(
                    fn($livewire) => $livewire->dispatch(
                        "update-margin-widget",
                    ),
                ),

                Actions\DeleteAction::make()->after(
                    fn($livewire) => $livewire->dispatch(
                        "update-margin-widget",
                    ),
                ),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    // Adicionei Ações em Massa de Status
                    Actions\BulkAction::make('activate')
                        ->label('Ativar selecionados')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, $livewire) {
                            $records->each->update(['is_active' => true]);
                            $livewire->dispatch("update-margin-widget");
                        }),

                    Actions\BulkAction::make('deactivate')
                        ->label('Desativar selecionados')
                        ->icon('heroicon-o-x-circle')
                        ->color('gray')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, $livewire) {
                            $records->each->update(['is_active' => false]);
                            $livewire->dispatch("update-margin-widget");
                        }),

                    Actions\BulkAction::make("cloneToTarget")
                        ->label("Clonar Para...")
                        ->icon("heroicon-o-arrow-up-tray")
                        ->color("warning")
                        ->form([
                            Select::make("target_product_ids")
                                ->label(
                                    "Copiar itens selecionados para quais produtos?",
                                )
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->options(function ($livewire) {
                                    $ownerId = $livewire->getOwnerRecord()->id;
                                    return Product::where("id", "!=", $ownerId)
                                        ->whereIn("type", [
                                            "dish",
                                            "combo",
                                            "manufactured",
                                            "ingredient",
                                        ])
                                        ->pluck("name", "id");
                                })
                                ->required(),

                            Toggle::make("clear_target")
                                ->label("Substituir ficha técnica existente?")
                                ->helperText("Apaga a receita atual dos produtos de destino antes de colar os itens.",
                                )
                                ->default(false),
                        ])
                        ->action(function (
                            array $data,
                            \Illuminate\Database\Eloquent\Collection $records,
                            $livewire,
                        ) {
                            foreach ($data["target_product_ids"] as $targetId) {
                                if ($data["clear_target"]) {
                                    DB::table("technical_sheets")
                                        ->where("parent_product_id", $targetId)
                                        ->delete();
                                }

                                foreach ($records as $item) {
                                    $rawOrderType = $item->order_type ?: [
                                        "table",
                                        "counter",
                                        "delivery",
                                    ];

                                    $encodedOrderType = is_array($rawOrderType)
                                        ? json_encode($rawOrderType)
                                        : $rawOrderType;

                                    if (!$data["clear_target"]) {
                                        $exists = DB::table("technical_sheets")
                                            ->where(
                                                "parent_product_id",
                                                $targetId,
                                            )
                                            ->where(
                                                "ingredient_product_id",
                                                $item->ingredient_product_id,
                                            )
                                            ->where(
                                                "order_type",
                                                $encodedOrderType,
                                            )
                                            ->exists();

                                        if ($exists) {
                                            continue;
                                        }
                                    }

                                    DB::table("technical_sheets")->insert([
                                        "parent_product_id" => $targetId,
                                        "ingredient_product_id" =>
                                            $item->ingredient_product_id,
                                        "quantity" => $item->quantity,
                                        "order_type" => $encodedOrderType,
                                        "is_active" => $item->is_active ?? true,
                                        "created_at" => now(),
                                        "updated_at" => now(),
                                    ]);
                                }
                            }

                            Notification::make()
                                ->success()
                                ->title("Clonagem Concluída!")
                                ->body(
                                    "Os itens selecionados foram copiados para os produtos de destino.",
                                )
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    Actions\DeleteBulkAction::make()->after(
                        fn($livewire) => $livewire->dispatch(
                            "update-margin-widget",
                        ),
                    ),
                ]),
            ])
            ->contentFooter(
                view("filament.components.tech-sheet-footer", [
                    "ownerRecord" => $this->getOwnerRecord(),
                    "isFinalProduct" => $isFinalProduct,
                ]),
            )
            ->paginationPageOptions(["all"]);
    }

    public function getTableRecordClassesUsing(): ?\Closure
    {
        return fn ($record) => $record->is_active ? '' : 'bg-gray-50/50 opacity-60';
    }

    public static function canViewForRecord($ownerRecord, $pageClass): bool
    {
        $allowedTypes = ["dish", "combo", "manufactured"];
        return in_array($ownerRecord->type, $allowedTypes);
    }
}
