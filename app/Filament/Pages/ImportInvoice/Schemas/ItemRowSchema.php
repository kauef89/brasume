<?php

namespace App\Filament\Pages\ImportInvoice\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Actions\Action;

use App\Models\Product;
use App\Models\GoodsReceiptItem;
use App\Services\Purchases\InvoiceItemProcessor;
use App\Support\ProductFormatter;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;
use Throwable;

class ItemRowSchema
{
    public static function schema(): array
    {
        return [
            self::getXmlNameField(),
            self::getXmlQtyField(),
            self::getConversionFactorField(),
            self::getIsPersonalField(),
            self::getProductSelectField(),
            self::getGoodsReceiptMatchField(),
            self::getRealEntryField(),
            self::getConfirmItemAction(),

            // Controles Ocultos
            Hidden::make("xml_cost"),
            Hidden::make("xml_uom"),
            Hidden::make("available_qty"),
            Hidden::make("is_matched"),
            Hidden::make("item_uuid"),
            Hidden::make("confirmation_step")->default(0),
        ];
    }

    private static function getXmlNameField(): TextInput
    {
        return TextInput::make("xml_name")
            ->label("Item da Nota")
            ->readOnly()
            ->hint(function (Get $get) {
                $cost = (float) $get("xml_cost");
                $formattedCost = rtrim(rtrim(number_format($cost, 5, ",", "."), "0"), ",");
                $cleanUom = strtoupper(preg_replace("/[^A-Z]/", "", (string) ($get("xml_uom") ?? "")));
                return "Original: R$ {$formattedCost} /{$cleanUom}";
            })
            ->columnSpan(3);
    }

    private static function getXmlQtyField(): TextInput
    {
        return TextInput::make("xml_qty")
            ->label("Qtd.")
            ->numeric()
            ->required()
            ->live(debounce: 500)
            ->hint(fn(Get $get) => "Max: " . $get("available_qty"))
            ->columnSpan(1);
    }

    private static function getConversionFactorField(): TextInput
    {
        return TextInput::make("conversion_factor")
            ->label(function (Get $get) {
                $xmlUom = strtoupper(preg_replace("/[^A-Z]/", "", (string) ($get("xml_uom") ?? "")));
                return match ($xmlUom) {
                    "CX", "CX1" => "Qtd. na caixa",
                    "FD", "FDO" => "Qtd. no fardo",
                    "PC"        => "Qtd. na peça",
                    "PCT"       => "Qtd. no pacote",
                    "SC"        => "Qtd. no saco",
                    default     => "Fator de Conversão",
                };
            })
            ->numeric()
            ->default(1)
            ->required()
            ->live(debounce: 500)
            ->extraInputAttributes(fn(Get $get) => match ($get("factor_source")) {
                "history", "auto" => ["style" => "background-color: rgba(34, 197, 94, 0.15) !important;"],
                "identical"       => ["style" => "background-color: rgba(59, 130, 246, 0.15) !important;"],
                "auto_math"       => ["style" => "background-color: rgba(234, 179, 8, 0.15) !important;"],
                "assistant_used"  => ["style" => "background-color: rgba(168, 85, 247, 0.15) !important;"],
                default           => [],
            })
            ->suffixAction(self::getAssistantAction())
            ->columnSpan(1);
    }

    private static function getIsPersonalField(): Toggle
    {
        return Toggle::make("is_personal")
            ->label("Uso Pessoal?")
            ->live()
            ->inline(false)
            ->columnSpan(1)
            ->afterStateUpdated(function (Set $set) {
                $set("product_id", null);
                $set("goods_receipt_item_id", null);
                $set("confirmation_step", 0);
            });
    }

    private static function getProductSelectField(): Select
    {
        return Select::make("product_id")
            ->label("Produto / Insumo / Suprimento")
            ->searchable()
            ->allowHtml()
            ->getSearchResultsUsing(function (string $search): array {
                $products = Product::with('category')
                    ->whereIn('type', ['ingredient', 'resale', 'packaging', 'consumable'])
                    ->where('name', 'like', "%{$search}%")
                    ->whereIn('status', ['available', 'out_of_stock', 'active'])
                    ->limit(50)
                    ->get();

                return $products->mapWithKeys(function ($product) {
                    return [$product->id => ProductFormatter::getHtmlLabel($product)];
                })->toArray();
            })
            ->getOptionLabelUsing(function ($value): ?string {
                $product = Product::with('category')->find($value);
                return $product ? ProductFormatter::getCleanLabel($product) : null;
            })
            ->live()
            ->afterStateUpdated(function ($state, Set $set, Get $get, $livewire) {
                if (!$state) return;

                $invoice = method_exists($livewire, 'getInvoiceRecord') ? $livewire->getInvoiceRecord() : null;
                $supplierId = $invoice?->supplier_id ?? ($livewire->data['supplier_id'] ?? null);

                $processor = app(InvoiceItemProcessor::class);
                $conversion = $processor->calculateSmartConversion(
                    productId: (int) $state,
                    supplierId: $supplierId ? (int) $supplierId : null,
                    currentXmlName: (string) $get('xml_name'),
                    xmlUomRaw: (string) $get('xml_uom')
                );

                $set('conversion_factor', $conversion['factor']);
                $set('factor_source', $conversion['source']);

                if (!empty($conversion['notification'])) {
                    $type = $conversion['notification']['type'] ?? 'info';
                    Notification::make()
                        ->title($conversion['notification']['title'])
                        ->body($conversion['notification']['body'])
                        ->{$type}()
                        ->send();
                }
            })
            ->hint(fn(Get $get) => $get("product_id") ? "U.M Interna: " . Product::where("id", $get("product_id"))->value("unit_of_measure") : null)
            ->hidden(fn(Get $get) => $get("is_personal"))
            ->required(fn(Get $get) => !$get("is_personal"))
            // 🚀 Dinâmico: 4 colunas quando vazio, 2 quando divide espaço com o romaneio
            ->columnSpan(fn(Get $get) => empty($get('product_id')) ? 4 : 2)
            // 🚀 QuickCreate Restaurado
            ->createOptionForm(
                \App\Filament\Resources\Products\Schemas\ProductForm::getQuickCreateSchema()
            )
            ->createOptionUsing(function (array $data, $livewire, Get $get) {
                $data["current_stock"] = 0;
                $data["base_cost"] = (float) $get("xml_cost") ?: 0;
                $product = Product::create($data);
                $livewire->dispatch('$refresh');
                return $product->id;
            })
            ->createOptionAction(function (Action $action) {
                return $action
                    ->modalHeading('Cadastro Rápido de Insumo')
                    ->modalWidth('sm');
            });
    }

    private static function getGoodsReceiptMatchField(): Select
    {
        return Select::make('goods_receipt_item_id')
            ->label('Conciliar Romaneio / Canhoto')
            ->placeholder('Nenhum (Entrada Nova no Estoque)')
            ->options(function (Get $get, $livewire) {
                $productId = $get('product_id');
                if (!$productId) return [];

                $supplierId = $livewire->data['supplier_id'] ?? null;

                $query = GoodsReceiptItem::with('goodsReceipt')
                    ->where('product_id', $productId)
                    ->where('status', 'pending');

                if ($supplierId) {
                    $query->whereHas('goodsReceipt', fn ($q) => $q->where('supplier_id', $supplierId));
                }

                return $query->get()->mapWithKeys(function ($item) {
                    $receiptNum = $item->goodsReceipt?->receipt_number ?: "ID #{$item->goods_receipt_id}";
                    $date = $item->goodsReceipt?->received_at ? $item->goodsReceipt->received_at->format('d/m') : '';
                    $uom = $item->product?->unit_of_measure ?: 'UN';
                    return [$item->id => "Canhoto {$receiptNum} ({$date}) - Qtd: {$item->quantity} {$uom}"];
                })->toArray();
            })
            ->helperText('Selecione caso este item já tenha sido entregue fisicamente para não duplicar o estoque.')
            ->hidden(fn(Get $get) => $get("is_personal") || empty($get("product_id")))
            ->columnSpan(2);
    }

    private static function getRealEntryField(): Placeholder
    {
        return Placeholder::make("total_entrada")
            ->label("Entrada Real")
            ->content(function (Get $get) {
                if (!$get("product_id")) {
                    return new HtmlString('<span class="text-gray-400">-</span>');
                }
                $total = ((float) ($get("xml_qty") ?: 0)) * ((float) ($get("conversion_factor") ?: 1));
                $uom = Product::find($get("product_id"))?->unit_of_measure ?? "UN";
                $formattedTotal = Number::format($total, maxPrecision: 4);

                $receiptId = $get('goods_receipt_item_id');
                $badge = $receiptId ? "<span class='text-[10px] font-black uppercase text-amber-500 bg-amber-500/10 px-1.5 py-0.5 rounded'>Conciliação</span>" : "";

                return new HtmlString("<div class='flex flex-col gap-0.5 mt-1'><div class='flex items-baseline gap-1'><span class='text-base font-black text-primary-600 dark:text-primary-400'>{$formattedTotal}</span><span class='text-xs font-medium text-gray-500'>{$uom}</span></div>{$badge}</div>");
            })
            ->hidden(fn(Get $get) => $get("is_personal"))
            ->columnSpan(1);
    }

    private static function getConfirmItemAction(): Actions
    {
        return Actions::make([
            Action::make("confirmItem")
                ->label(fn(Get $get) => $get("is_personal") ? "Gerar Despesa" : "Associar")
                ->icon(fn(Get $get) => $get("is_personal") ? "heroicon-m-banknotes" : "heroicon-m-link")
                ->color(fn(Get $get) => $get("is_personal") ? "warning" : "primary")
                ->size('sm')
                ->extraAttributes(['class' => 'whitespace-nowrap'])
                ->disabled(fn(Get $get) => !$get("is_personal") && empty($get("product_id")))
                ->action(function (Get $get, $livewire) {
                    $processQty = (float) ($get('xml_qty') ?? 0);
                    $availableQty = (float) ($get('available_qty') ?? 0);

                    if ($processQty <= 0 || $processQty > $availableQty) {
                        Notification::make()
                            ->title('Quantidade Inválida')
                            ->body("A quantidade deve ser maior que zero e menor ou igual a {$availableQty}.")
                            ->danger()
                            ->send();
                        return;
                    }

                    $invoice = method_exists($livewire, 'getInvoiceRecord') ? $livewire->getInvoiceRecord() : null;
                    $supplierId = $invoice?->supplier_id ?? ($livewire->data['supplier_id'] ?? null);

                    if (!$supplierId) {
                        Notification::make()
                            ->title('Fornecedor Obrigatório')
                            ->body('Não foi possível identificar o fornecedor vinculado à nota.')
                            ->danger()
                            ->send();
                        return;
                    }

                    $isPersonal = (bool) $get('is_personal');
                    $productId = $get('product_id') ? (int) $get('product_id') : null;

                    $payload = [
                        'item_uuid'             => (string) ($get('item_uuid') ?? ''),
                        'xml_name'              => (string) ($get('xml_name') ?? ''),
                        'xml_qty'               => $processQty,
                        'available_qty'         => $availableQty,
                        'xml_cost'              => (float) ($get('xml_cost') ?? 0),
                        'xml_uom'               => (string) ($get('xml_uom') ?? ''),
                        'conversion_factor'     => (float) ($get('conversion_factor') ?? 1),
                        'is_personal'           => $isPersonal,
                        'product_id'            => $productId,
                        'goods_receipt_item_id' => $get('goods_receipt_item_id') ? (int) $get('goods_receipt_item_id') : null,
                        'supplier_id'           => (int) $supplierId,
                        'invoice_key'           => $invoice?->nfe_key ?? $invoice?->access_key ?? ($livewire->data['invoice_key'] ?? null),
                    ];

                    try {
                        $result = $livewire->confirmItem($payload);
                        $autoCount = $result['auto_processed_count'] ?? 0;

                        $targetXmlName = $payload['xml_name'] ?: 'Item';
                        $body = $isPersonal
                            ? "Despesa gerada para '{$targetXmlName}'."
                            : "Item '{$targetXmlName}' integrado com sucesso!";

                        if ($payload['goods_receipt_item_id']) {
                            $body .= " (Estoque conciliado com o romaneio anterior sem duplicação).";
                        }

                        if ($autoCount > 0) {
                            $body .= " Efeito dominó: +{$autoCount} item(ns) idêntico(s) associado(s) automaticamente.";
                        }

                        Notification::make()
                            ->title($isPersonal ? 'Despesa Registrada' : 'Estoque Conciliado')
                            ->body($body)
                            ->success()
                            ->send();

                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Erro ao Processar Item')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ])
        ->alignEnd()
        ->columnSpan(fn(Get $get) => $get("is_personal") ? 6 : 1)
        ->extraAttributes(fn(Get $get) => $get("is_personal") ? [] : ["style" => "margin-top: 32px"]);
    }

    private static function getAssistantAction(): Action
    {
        return Action::make("assistente")
            ->icon("heroicon-m-sparkles")
            ->tooltip("Assistente de Conversão")
            ->color("primary")
            ->visible(fn(Get $get) => filled($get("product_id")))
            ->modalHeading("Assistente de Conversão")
            ->form(function (Get $get) {
                $xmlUom = strtoupper(preg_replace("/[^A-Z]/", "", (string) ($get("xml_uom") ?? "")));
                $internalUom = Product::find($get("product_id"))->unit_of_measure ?? "UN";
                $isPackage = in_array($xmlUom, ["CX", "FD", "FDO", "PC", "PCT", "SC"]);

                $fields = [Placeholder::make("info")->hiddenLabel()->content(new HtmlString("Nota: <strong>{$xmlUom}</strong> | Estoque: <strong>{$internalUom}</strong>"))];
                if ($isPackage) {
                    $fields[] = TextInput::make("qtd_pacote")->label("Itens por pacote/caixa")->numeric()->required();
                }
                if (in_array($xmlUom, ["KG", "KIL", "L", "LT", "LIT"]) && $internalUom === "UN") {
                    $fields[] = TextInput::make("peso_in")->label("Peso/Volume de 1 {$internalUom} (em {$xmlUom})")->numeric()->required();
                }
                if (($xmlUom === "UN" || $isPackage) && in_array($internalUom, ["KG", "G", "L", "ML"])) {
                    $fields[] = TextInput::make("peso_out")->label("Peso/Volume de 1 UN (em {$internalUom})")->numeric()->required();
                }

                return $fields;
            })
            ->action(function (array $data, Set $set, Get $get) {
                $xmlUom = strtoupper(preg_replace("/[^A-Z]/", "", (string) ($get("xml_uom") ?? "")));
                $internalUom = Product::find($get("product_id"))->unit_of_measure ?? "UN";
                $isPackage = in_array($xmlUom, ["CX", "FD", "FDO", "PC", "PCT", "SC"]);

                $qtd = (float) ($data["qtd_pacote"] ?? 1);
                $pesoIn = (float) ($data["peso_in"] ?? 0);
                $pesoOut = (float) ($data["peso_out"] ?? 0);

                $factor = 1.0;
                if ($xmlUom === $internalUom || (in_array($xmlUom, ["KG", "KIL"]) && $internalUom === "KG") || (in_array($xmlUom, ["L", "LT", "LIT"]) && $internalUom === "LT")) {
                    $factor = 1.0;
                } elseif (in_array($xmlUom, ["KG", "KIL"]) && $internalUom === "G") {
                    $factor = 1000.0;
                } elseif ($xmlUom === "G" && in_array($internalUom, ["KG", "KIL"])) {
                    $factor = 0.001;
                } elseif (in_array($xmlUom, ["L", "LT", "LIT"]) && $internalUom === "ML") {
                    $factor = 1000.0;
                } elseif ($xmlUom === "ML" && in_array($internalUom, ["L", "LT", "LIT"])) {
                    $factor = 0.001;
                } elseif ($isPackage && $internalUom === "UN") {
                    $factor = $qtd;
                } elseif (in_array($xmlUom, ["KG", "KIL", "L", "LT", "LIT"]) && $internalUom === "UN" && $pesoIn > 0) {
                    $factor = 1 / $pesoIn;
                } elseif ($isPackage && in_array($internalUom, ["KG", "G", "L", "ML"]) && $pesoOut > 0) {
                    $factor = $qtd * $pesoOut;
                } elseif ($xmlUom === "UN" && in_array($internalUom, ["KG", "G", "L", "ML"]) && $pesoOut > 0) {
                    $factor = $pesoOut;
                }

                $set("conversion_factor", round($factor, 4));
                $set("factor_source", "assistant_used");

                Notification::make()
                    ->title("Fator de Conversão Aplicado!")
                    ->success()
                    ->send();
            });
    }
}
