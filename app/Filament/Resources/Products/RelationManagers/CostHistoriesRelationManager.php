<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Models\ReceivedInvoice;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Catalog\InventoryService;
use Filament\Notifications\Notification;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;

class CostHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = "costHistories";
    protected static ?string $title = "Histórico de Compras";

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute("nfe_key")
            ->columns([
                Tables\Columns\TextColumn::make("data_compra")
                    ->label("Data")
                    ->state(function ($record) {
                        $invoice = ReceivedInvoice::where("nfe_key", $record->nfe_key)->first();
                        $dataCompra = $invoice ? $invoice->issue_date : null;
                        return $dataCompra ? Carbon::parse($dataCompra)->format("d/m/Y") : "Desconhecida";
                    }),

                Tables\Columns\TextColumn::make("supplier.name")
                    ->label("Fornecedor")
                    ->state(fn ($record) => $record->supplier?->name ?? "Desconhecido / Avulso")
                    ->searchable(["supplier.name", "supplier.document_name"])
                    ->sortable(),

                Tables\Columns\TextColumn::make("xml_name")
                    ->label("Produto")
                    ->limit(35)
                    ->tooltip(fn($state) => $state),

                Tables\Columns\TextColumn::make("quantity")
                    ->label("Qtd.")
                    ->state(function ($record) {
                        $factor = $record->conversion_factor ?: 1;
                        $originalQty = (float) ($record->quantity / $factor);
                        $uom = strtolower($record->original_unit_of_measure ?? "un");
                        $formattedQty = $originalQty == floor($originalQty)
                            ? number_format($originalQty, 0, ",", ".")
                            : number_format($originalQty, 2, ",", ".");
                        return "{$formattedQty} {$uom}";
                    }),

                Tables\Columns\TextColumn::make("unit_cost")
                    ->label("Custo")
                    ->state(function ($record) {
                        $cost = $record->original_unit_cost ?? ($record->unit_cost * ($record->conversion_factor ?: 1));
                        $uom = $record->original_unit_of_measure ?? "un";
                        return 'R$ ' . number_format((float) $cost, 2, ",", ".") . " /" . strtolower($uom);
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make("internal_cost")
                    ->label("Custo Unit.")
                    ->state(function ($record) {
                        $cost = $record->unit_cost;
                        $uom = $record->product?->unit_of_measure ?? "un";
                        return 'R$ ' . number_format((float) $cost, 2, ",", ".") . " /" . strtolower($uom);
                    }),
            ])
            ->headerActions([
                Action::make("addManualPurchase")
                    ->label("Adicionar Compra Manual")
                    ->icon("heroicon-o-plus-circle")
                    ->color("primary")
                    ->modalHeading("Registar Compra Avulsa")
                    ->form([
                        Grid::make(2)->schema([
                            DatePicker::make("purchase_date")->label("Data")->default(now())->required(),
                            Select::make("supplier_id")
                                ->label("Fornecedor (Opcional)")
                                ->options(fn() => Supplier::where('is_active', true)->pluck("name", "id"))
                                ->searchable(),
                        ]),
                        Grid::make(2)->schema([
                            TextInput::make("quantity")->label("Qtd.")->numeric()->required()
                                ->suffix(fn($livewire) => $livewire->getOwnerRecord()->unit_of_measure ?? "UN"),
                            TextInput::make("unit_cost")->label("Custo Unitário")->numeric()->prefix('R$')->required(),
                        ]),
                        Grid::make(2)->schema([
                            Toggle::make("update_stock")->label("Dar entrada no estoque?")->default(true),
                            Toggle::make("update_cost")->label("Atualizar custo base?")->default(true),
                        ])
                    ])
                    ->action(function (array $data, $livewire) {
                        $product = $livewire->getOwnerRecord();
                        $dateStr = Carbon::parse($data["purchase_date"])->format("Ymd");
                        $timeStr = now()->format("His");
                        $fakeNfeKey = "MANUAL-{$dateStr}-{$timeStr}";

                        $supplierName = $data["supplier_id"]
                            ? Supplier::find($data["supplier_id"])->name
                            : "Compra Manual / Avulsa";

                        ReceivedInvoice::create([
                            "nfe_key"      => $fakeNfeKey,
                            "emitter_name" => $supplierName,
                            "emitter_cnpj" => "00000000000000",
                            "total_value"  => $data["quantity"] * $data["unit_cost"],
                            "issue_date"   => Carbon::parse($data["purchase_date"]),
                            "status"       => "processed",
                        ]);

                        $product->costHistories()->create([
                            "supplier_id"              => $data["supplier_id"] ?? null,
                            "nfe_key"                  => $fakeNfeKey,
                            "xml_name"                 => $product->name . " (Lançamento Manual)",
                            "quantity"                 => $data["quantity"],
                            "conversion_factor"        => 1,
                            "unit_cost"                => $data["unit_cost"],
                            "original_quantity"        => $data["quantity"],
                            "original_unit_of_measure" => $product->unit_of_measure,
                            "original_unit_cost"       => $data["unit_cost"],
                        ]);

                        if ($data["update_cost"]) {
                            $product->update(['base_cost' => $data["unit_cost"]]);
                        }

                        // 🚀 ENTRADA COM CASCATA E WEBSOCKET VIA INVENTORY SERVICE
                        if ($data["update_stock"]) {
                            app(InventoryService::class)->addStock($product, (float) $data["quantity"], 'compra_manual');
                        }

                        Notification::make()
                            ->title("Compra manual registrada!")
                            ->body("Estoque adicionado e cardápio sincronizado em tempo real.")
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort("created_at", "desc");
    }

    public static function canViewForRecord($ownerRecord, $pageClass): bool
    {
        return !in_array($ownerRecord->type, ["dish", "combo"]);
    }
}
