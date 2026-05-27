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
use Filament\Notifications\Notification;
use Carbon\Carbon;
use Illuminate\Support\Str;
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
                    ->label("Data da Compra")
                    ->state(function ($record) {
                        $invoice = ReceivedInvoice::where(
                            "nfe_key",
                            $record->nfe_key,
                        )->first();
                        $dataCompra = $invoice ? $invoice->issue_date : null;

                        return $dataCompra
                            ? Carbon::parse($dataCompra)->format("d/m/Y")
                            : "Desconhecida";
                    }),

                Tables\Columns\TextColumn::make("supplier.name")
                    ->label("Fornecedor")
                    ->state(function ($record) {
                        if (!$record->supplier) {
                            return "Desconhecido / Avulso";
                        }
                        return $record->supplier->name;
                    })
                    ->searchable(["supplier.name", "supplier.document_name"])
                    ->sortable()
                    ->weight("bold")
                    ->color("primary"),

                Tables\Columns\TextColumn::make("xml_name")
                    ->label("Produto na Sefaz")
                    ->limit(35)
                    ->tooltip(fn($state) => $state),

                Tables\Columns\TextColumn::make("quantity")
                    ->label("Qtd Comprada")
                    ->state(function ($record) {
                        $factor = $record->conversion_factor ?: 1;
                        $originalQty = (float) ($record->quantity / $factor);
                        $uom = strtolower(
                            $record->original_unit_of_measure ?? "un",
                        );

                        $formattedQty =
                            $originalQty == floor($originalQty)
                                ? number_format($originalQty, 0, ",", ".")
                                : number_format($originalQty, 2, ",", ".");

                        return "{$formattedQty} {$uom}";
                    }),

                Tables\Columns\TextColumn::make("unit_cost")
                    ->label("Custo Unitário")
                    ->state(function ($record) {
                        $cost =
                            $record->original_unit_cost ??
                            $record->unit_cost *
                                ($record->conversion_factor ?: 1);
                        $uom = $record->original_unit_of_measure ?? "un";

                        return 'R$ ' .
                            number_format((float) $cost, 2, ",", ".") .
                            " /" .
                            strtolower($uom);
                    })
                    ->color("success")
                    ->weight("bold")
                    ->sortable(),

                Tables\Columns\TextColumn::make("nfe_key")
                    ->label("Chave NFe")
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->copyable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Action::make("addManualPurchase")
                    ->label("Adicionar Compra Manual")
                    ->icon("heroicon-o-plus-circle")
                    ->color("primary")
                    ->modalHeading("Registar Compra Avulsa")
                    ->modalDescription(
                        "Use esta opção para registar compras sem arquivo XML (ex: mercado, feira, compras de emergência).",
                    )
                    ->form([
                        Grid::make(2)->schema([
                            DatePicker::make("purchase_date")
                                ->label("Data da Compra")
                                ->default(now())
                                ->required()
                                ->columnSpan(1),

                            Select::make("supplier_id")
                                ->label("Fornecedor (Opcional)")
                                ->options(fn() => Supplier::pluck("name", "id"))
                                ->searchable()
                                ->createOptionForm(
                                    \App\Filament\Resources\Suppliers\Schemas\SupplierForm::getFormSchema(),
                                )
                                ->createOptionUsing(function (array $data) {
                                    return Supplier::create(
                                        array_merge($data, [
                                            "type" => "physical",
                                        ]),
                                    )->id;
                                })
                                ->columnSpan(1),
                        ]),

                        Grid::make(2)->schema([
                            TextInput::make("quantity")
                                ->label("Quantidade Comprada")
                                ->numeric()
                                ->inputMode("decimal")
                                ->required()
                                ->suffix(
                                    fn($livewire) => $livewire->getOwnerRecord()
                                        ->unit_of_measure ?? "UN",
                                )
                                ->columnSpan(1),

                            TextInput::make("unit_cost")
                                ->label("Custo Unitário")
                                ->numeric()
                                ->prefix('R$')
                                ->required()
                                ->columnSpan(1),
                        ]),

                        Grid::make(2)
                            ->schema([
                                Toggle::make("update_stock")
                                    ->label("Dar entrada no estoque?")
                                    ->helperText(
                                        "Soma a quantidade comprada ao estoque atual.",
                                    )
                                    ->default(true),

                                Toggle::make("update_cost")
                                    ->label("Atualizar custo base?")
                                    ->helperText(
                                        "Substitui o custo de referência do produto por este novo valor.",
                                    )
                                    ->default(true),
                            ])
                            ->extraAttributes(["class" => "mt-4"]),
                    ])
                    ->action(function (array $data, $livewire) {
                        $product = $livewire->getOwnerRecord();

                        // 1. Gera uma chave amigável para o contador: MANUAL-YYYYMMDD-HHMMSS
                        $dateStr = Carbon::parse(
                            $data["purchase_date"],
                        )->format("Ymd");
                        $timeStr = now()->format("His"); // Hora exata do cadastro para garantir exclusividade
                        $fakeNfeKey = "MANUAL-{$dateStr}-{$timeStr}";

                        $supplierName = $data["supplier_id"]
                            ? Supplier::find($data["supplier_id"])->name
                            : "Compra Manual / Avulsa";

                        // 2. Cria uma Nota Fiscal "Virtual" para guardar a data e servir de âncora
                        ReceivedInvoice::create([
                            "nfe_key" => $fakeNfeKey,
                            "emitter_name" => $supplierName,
                            "emitter_cnpj" => "00000000000000", // CNPJ genérico
                            "total_value" =>
                                $data["quantity"] * $data["unit_cost"],
                            "issue_date" => Carbon::parse(
                                $data["purchase_date"],
                            ),
                            "status" => "processed",
                        ]);

                        // 3. Cria o histórico de custo amarrado à nota virtual
                        $product->costHistories()->create([
                            "supplier_id" => $data["supplier_id"] ?? null,
                            "nfe_key" => $fakeNfeKey,
                            "xml_name" =>
                                $product->name . " (Lançamento Manual)",
                            "quantity" => $data["quantity"],
                            "conversion_factor" => 1,
                            "unit_cost" => $data["unit_cost"],
                            "original_quantity" => $data["quantity"],
                            "original_unit_of_measure" =>
                                $product->unit_of_measure,
                            "original_unit_cost" => $data["unit_cost"],
                        ]);

                        // 4. Atualiza o Produto (Estoque e Custo)
                        if ($data["update_stock"]) {
                            $product->current_stock += $data["quantity"];
                        }
                        if ($data["update_cost"]) {
                            $product->base_cost = $data["unit_cost"];
                        }

                        if ($data["update_stock"] || $data["update_cost"]) {
                            $product->save();
                        }

                        Notification::make()
                            ->title("Compra manual registada!")
                            ->body("Estoque e custos atualizados com sucesso.")
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                // 🚀 O NOVO BOTÃO DE EDITAR
                Action::make("editar")
                    ->label("Editar")
                    ->icon("heroicon-o-pencil")
                    ->color("warning")
                    ->tooltip("Corrigir fator de conversão ou quantidade")
                    ->modalHeading("Editar Registro de Compra")
                    ->modalDescription(
                        "Corrija as quantidades ou o fator de conversão desta entrada. O sistema ajustará o seu estoque e os custos médios automaticamente.",
                    )
                    ->modalWidth("2xl")
                    ->fillForm(
                        fn($record): array => [
                            "original_quantity" => $record->original_quantity,
                            "original_unit_cost" => $record->original_unit_cost,
                            "conversion_factor" =>
                                $record->conversion_factor ?: 1,
                        ],
                    )
                    ->form([
                        Grid::make(3)->schema([
                            TextInput::make("original_quantity")
                                ->label("Qtd. na Nota")
                                ->numeric()
                                ->required(),

                            TextInput::make("original_unit_cost")
                                ->label("Custo Total/Unit. (Nota)")
                                ->numeric()
                                ->prefix('R$')
                                ->required(),

                            TextInput::make("conversion_factor")
                                ->label("Fator de Conversão")
                                ->numeric()
                                ->gt(0) // Não permite fator zero para evitar erro de divisão
                                ->required(),
                        ]),
                    ])
                    ->action(function (array $data, $record, $livewire) {
                        $product = $livewire->getOwnerRecord();

                        // 1. Pega a quantidade interna antiga (antes da edição)
                        $oldInternalQty = (float) $record->quantity;

                        // 2. Extrai os novos valores digitados
                        $newOriginalQty = (float) $data["original_quantity"];
                        $newOriginalCost = (float) $data["original_unit_cost"];
                        $newFactor = (float) $data["conversion_factor"];

                        // 3. Refaz a matemática (Como o sistema enxerga para dentro de casa)
                        $newInternalQty = $newOriginalQty * $newFactor;
                        $newInternalCost =
                            $newFactor > 0 ? $newOriginalCost / $newFactor : 0;

                        // 4. Calcula a diferença (positiva ou negativa) para consertar o estoque atual
                        $qtyDifference = $newInternalQty - $oldInternalQty;

                        // 5. Salva no histórico da nota
                        $record->update([
                            "original_quantity" => $newOriginalQty,
                            "original_unit_cost" => $newOriginalCost,
                            "conversion_factor" => $newFactor,
                            "quantity" => $newInternalQty,
                            "unit_cost" => $newInternalCost,
                        ]);

                        // 6. Conserta o estoque do produto
                        if ($product) {
                            $product->current_stock += $qtyDifference;
                            $product->save();
                        }

                        // Dispara evento para o Widget de Lucratividade atualizar na tela, se houver
                        $livewire->dispatch("update-margin-widget");

                        Notification::make()
                            ->title("Histórico corrigido!")
                            ->body(
                                "O custo e o estoque foram recalculados com sucesso.",
                            )
                            ->success()
                            ->send();
                    }),

                // O BOTÃO ORIGINAL DE DESFAZER
                Action::make("desvincular")
                    ->label("Desfazer")
                    ->icon("heroicon-o-arrow-uturn-left")
                    ->color("danger")
                    ->tooltip("Desfazer importação deste item")
                    ->requiresConfirmation()
                    ->modalHeading("Desfazer importação deste item?")
                    ->modalDescription(
                        "Esta ação irá subtrair a quantidade importada do estoque e devolverá o item para a tela de importação da Nota Fiscal.",
                    )
                    ->action(function ($record, $livewire) {
                        // 1. Estorna o estoque
                        $product = Product::find($record->product_id);
                        if ($product) {
                            $product->current_stock -= $record->quantity;
                            $product->save();
                        }

                        // 2. Tira a trava estrita e volta o status para o padrão
                        $invoice = ReceivedInvoice::where(
                            "nfe_key",
                            $record->nfe_key,
                        )->first();

                        if ($invoice) {
                            $invoice->update(["status" => "ready"]);
                        }

                        // 3. Deleta o histórico para "renascer" o item na tela de XML
                        $record->delete();

                        // Dispara evento pro Widget atualizar
                        $livewire->dispatch("update-margin-widget");

                        Notification::make()
                            ->title("Importação desfeita com sucesso!")
                            ->body(
                                "O item voltou para a Nota Fiscal original e o estoque foi revertido.",
                            )
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                //
            ])
            ->defaultSort("created_at", "desc");
    }

    public static function canViewForRecord($ownerRecord, $pageClass): bool
    {
        // Tipos que NÃO são comprados prontos (são fabricados/montados internamente)
        $disallowedTypes = ["dish", "combo", "manufactured"];

        // Se o produto for de um tipo não permitido, esconde a aba
        if (in_array($ownerRecord->type, $disallowedTypes)) {
            return false;
        }

        // Caso contrário, mostra a aba (pode manter a sua restrição de página se quiser)
        return in_array($pageClass, [
            \App\Filament\Resources\Products\Pages\ViewProduct::class,
            \App\Filament\Resources\Ingredients\Pages\ViewIngredient::class,
            \App\Filament\Resources\Supplies\Pages\ViewSupply::class,
        ]);
    }
}
