<?php

namespace App\Filament\Pages\ImportInvoice\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\DatePicker;
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
            // ==========================================
            // LINHA 1: DADOS BÁSICOS DO XML E DESTINO (6 COLS)
            // ==========================================
            self::getXmlNameField(),         // 3 colunas
            self::getXmlQtyField(),          // 1 coluna
            self::getDestinationTypeField(), // 2 colunas (3 Toggle Buttons)

            // ==========================================
            // LINHA 2: CONTEXTO ESTOQUE / INSUMO
            // ==========================================
            self::getConversionFactorField(), // 1 coluna
            self::getProductSelectField(),    // 2 ou 3 colunas
            self::getGoodsReceiptMatchField(),// 1 coluna (se houver produto)
            self::getRealEntryField(),        // 1 coluna

            // ==========================================
            // LINHA 2: CONTEXTO PATRIMÔNIO / IMOBILIZADO
            // ==========================================
            self::getAssetNameField(),        // 2 colunas
            self::getAssetLocationField(),    // 1 coluna
            self::getAssetTagField(),         // 1 coluna
            self::getWarrantyUntilField(),     // 1 coluna

            // ==========================================
            // LINHA 2: CONTEXTO USO PESSOAL
            // ==========================================
            self::getPersonalNoticeField(),   // 5 colunas

            // BOTÃO DE CONFIRMAÇÃO (SEMPRE A ÚLTIMA COLUNA DA LINHA 2)
            self::getConfirmItemAction(),     // 1 coluna

            // Controles Ocultos
            Hidden::make("xml_cost"),
            Hidden::make("xml_uom"),
            Hidden::make("xml_cfop"),
            Hidden::make("available_qty"),
            Hidden::make("is_matched"),
            Hidden::make("item_uuid"),
            Hidden::make("is_personal")->default(false),
            Hidden::make("confirmation_step")->default(0),
            Hidden::make("factor_source")->default("manual"),
        ];
    }

    private static function getXmlNameField(): TextInput
    {
        return TextInput::make("xml_name")
            ->label("Item da Nota (XML)")
            ->readOnly()
            ->hint(function (Get $get) {
                $cost = (float) $get("xml_cost");
                $formattedCost = rtrim(rtrim(number_format($cost, 5, ",", "."), "0"), ",");
                $cleanUom = strtoupper(preg_replace("/[^A-Z]/", "", (string) ($get("xml_uom") ?? "")));
                $cfop = $get("xml_cfop") ? " | CFOP: " . $get("xml_cfop") : "";
                return "Original: R$ {$formattedCost} /{$cleanUom}{$cfop}";
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

    /**
     * 🚀 3 TOGGLE BUTTONS: Alternância imediata com 1 clique
     */
    private static function getDestinationTypeField(): ToggleButtons
    {
        return ToggleButtons::make("destination_type")
            ->label("Destino do Item")
            ->options([
                'stock'    => 'Estoque',
                'asset'    => 'Patrimônio',
                'personal' => 'Pessoal',
            ])
            ->icons([
                'stock'    => 'heroicon-m-archive-box',
                'asset'    => 'heroicon-m-building-storefront',
                'personal' => 'heroicon-m-user',
            ])
            ->colors([
                'stock'    => 'primary',
                'asset'    => 'warning',
                'personal' => 'danger',
            ])
            ->inline()
            ->grouped()
            ->default('stock')
            ->required()
            ->live()
            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                $set("is_personal", $state === 'personal');
                if ($state !== 'stock') {
                    $set("product_id", null);
                    $set("goods_receipt_item_id", null);
                }
                if ($state === 'asset') {
                    $set("asset_name", $get("xml_name"));
                }
                $set("confirmation_step", 0);
            })
            ->columnSpan(2);
    }

    // ----------------------------------------------------
    // CAMPOS DE ESTOQUE
    // ----------------------------------------------------

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
                    default     => "Fator Conversão",
                };
            })
            ->numeric()
            ->default(1)
            ->required()
            ->live(debounce: 500)
            ->visible(fn(Get $get) => ($get("destination_type") ?? 'stock') === 'stock')
            ->suffixAction(self::getAssistantAction())
            ->columnSpan(1);
    }

    private static function getProductSelectField(): Select
    {
        return Select::make("product_id")
            ->label("Produto / Insumo / Suprimento")
            ->searchable()
            ->allowHtml()
            ->visible(fn(Get $get) => ($get("destination_type") ?? 'stock') === 'stock')
            ->required(fn(Get $get) => ($get("destination_type") ?? 'stock') === 'stock')
            ->getSearchResultsUsing(function (string $search): array {
                $products = Product::with('category')
                    ->whereIn('type', [\App\Enums\ProductType::INGREDIENT->value, \App\Enums\ProductType::RESALE->value, \App\Enums\ProductType::PACKAGING->value, \App\Enums\ProductType::CONSUMABLE->value])
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
            })
            ->hint(fn(Get $get) => $get("product_id") ? "U.M: " . Product::where("id", $get("product_id"))->value("unit_of_measure") : null)
            ->columnSpan(fn(Get $get) => empty($get('product_id')) ? 3 : 2)
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
            ->createOptionAction(fn(Action $action) => $action->modalHeading('Cadastro Rápido de Insumo')->modalWidth('sm'));
    }

    private static function getGoodsReceiptMatchField(): Select
    {
        return Select::make('goods_receipt_item_id')
            ->label('Conciliar Romaneio')
            ->placeholder('Nenhum')
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
                    $receiptNum = $item->goodsReceipt?->receipt_number ?: "#{$item->goods_receipt_id}";
                    $uom = $item->product?->unit_of_measure ?: 'UN';
                    return [$item->id => "Canhoto {$receiptNum} ({$item->quantity} {$uom})"];
                })->toArray();
            })
            ->visible(fn(Get $get) => ($get("destination_type") ?? 'stock') === 'stock' && filled($get("product_id")))
            ->columnSpan(1);
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
                $badge = $receiptId ? "<span class='text-[9px] font-black uppercase text-amber-500 bg-amber-500/10 px-1 py-0.5 rounded'>Conciliação</span>" : "";

                return new HtmlString("<div class='flex flex-col gap-0.5 mt-1'><div class='flex items-baseline gap-1'><span class='text-sm font-black text-primary-600 dark:text-primary-400'>{$formattedTotal}</span><span class='text-xs font-medium text-gray-500'>{$uom}</span></div>{$badge}</div>");
            })
            ->visible(fn(Get $get) => ($get("destination_type") ?? 'stock') === 'stock')
            ->columnSpan(1);
    }

    // ----------------------------------------------------
    // CAMPOS DE PATRIMÔNIO / IMOBILIZADO
    // ----------------------------------------------------

    private static function getAssetNameField(): TextInput
    {
        return TextInput::make("asset_name")
            ->label("Descrição do Ativo")
            ->placeholder("Ex: Fachada em ACM com LED")
            ->required(fn(Get $get) => $get("destination_type") === 'asset')
            ->visible(fn(Get $get) => $get("destination_type") === 'asset')
            ->columnSpan(2);
    }

    private static function getAssetLocationField(): TextInput
    {
        return TextInput::make("asset_location")
            ->label("Localização Física")
            ->placeholder("Ex: Fachada Externa, Salão...")
            ->visible(fn(Get $get) => $get("destination_type") === 'asset')
            ->columnSpan(1);
    }

    private static function getAssetTagField(): TextInput
    {
        return TextInput::make("asset_tag")
            ->label("Plaqueta / Tombamento")
            ->placeholder("Ex: PAT-001")
            ->visible(fn(Get $get) => $get("destination_type") === 'asset')
            ->columnSpan(1);
    }

    private static function getWarrantyUntilField(): DatePicker
    {
        return DatePicker::make("warranty_until")
            ->label("Garantia Até")
            ->placeholder("Opcional")
            ->visible(fn(Get $get) => $get("destination_type") === 'asset')
            ->columnSpan(1);
    }

    // ----------------------------------------------------
    // CAMPOS DE USO PESSOAL
    // ----------------------------------------------------

    private static function getPersonalNoticeField(): Placeholder
    {
        return Placeholder::make("personal_notice")
            ->hiddenLabel()
            ->content(new HtmlString("
                <div class='p-3 text-xs rounded-xl bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-500/20'>
                    <strong>Retirada de Sócios / Uso Pessoal:</strong> Este item será alocado como despesa particular dos proprietários e não impactará o Kardex nem o CMV da empresa.
                </div>
            "))
            ->visible(fn(Get $get) => $get("destination_type") === 'personal')
            ->columnSpan(5);
    }

    // ----------------------------------------------------
    // AÇÃO DE CONFIRMAÇÃO REATIVA
    // ----------------------------------------------------

    private static function getConfirmItemAction(): Actions
    {
        return Actions::make([
            Action::make("confirmItem")
                ->label(function (Get $get) {
                    return match ($get("destination_type")) {
                        'asset'    => 'Tombá-lo',
                        'personal' => 'Gerar Despesa',
                        default    => 'Associar',
                    };
                })
                ->icon(function (Get $get) {
                    return match ($get("destination_type")) {
                        'asset'    => 'heroicon-m-building-storefront',
                        'personal' => 'heroicon-m-banknotes',
                        default    => 'heroicon-m-link',
                    };
                })
                ->color(function (Get $get) {
                    return match ($get("destination_type")) {
                        'asset'    => 'warning',
                        'personal' => 'danger',
                        default    => 'primary',
                    };
                })
                ->size('sm')
                ->extraAttributes(['class' => 'whitespace-nowrap'])
                ->disabled(function (Get $get) {
                    $dest = $get("destination_type") ?? 'stock';
                    if ($dest === 'stock') {
                        return empty($get("product_id"));
                    }
                    if ($dest === 'asset') {
                        return empty($get("asset_name"));
                    }
                    return false;
                })
                ->action(function (Get $get, $livewire) {
                    $destination = $get('destination_type') ?? 'stock';
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

                    $productId = $get('product_id') ? (int) $get('product_id') : null;
                    if ($destination === 'personal') {
                        $productId = null;
                    }

                    $payload = [
                        'item_uuid'             => (string) ($get('item_uuid') ?? ''),
                        'xml_name'              => (string) ($get('xml_name') ?? ''),
                        'xml_qty'               => $processQty,
                        'available_qty'         => $availableQty,
                        'xml_cost'              => (float) ($get('xml_cost') ?? 0),
                        'xml_uom'               => (string) ($get('xml_uom') ?? ''),
                        'destination_type'      => $destination,
                        'is_personal'           => $destination === 'personal',
                        'conversion_factor'     => (float) ($get('conversion_factor') ?? 1),
                        'product_id'            => $productId,
                        'goods_receipt_item_id' => $get('goods_receipt_item_id') ? (int) $get('goods_receipt_item_id') : null,
                        'supplier_id'           => (int) $supplierId,
                        'invoice_key'           => $invoice?->nfe_key ?? $invoice?->access_key ?? ($livewire->data['invoice_key'] ?? null),
                        // Campos de Ativo Imobilizado
                        'asset_name'            => (string) ($get('asset_name') ?? $get('xml_name')),
                        'asset_location'        => $get('asset_location'),
                        'asset_tag'             => $get('asset_tag'),
                        'warranty_until'        => $get('warranty_until'),
                    ];

                    try {
                        $result = $livewire->confirmItem($payload);
                        $autoCount = $result['auto_processed_count'] ?? 0;
                        $targetXmlName = $payload['xml_name'] ?: 'Item';

                        $body = match ($destination) {
                            'asset'    => "Ativo Imobilizado '{$payload['asset_name']}' registrado no Patrimônio sem gerar estoque físico.",
                            'personal' => "Despesa pessoal gerada para '{$targetXmlName}'.",
                            default    => "Item '{$targetXmlName}' integrado ao estoque com sucesso!",
                        };

                        if ($destination === 'stock' && $payload['goods_receipt_item_id']) {
                            $body .= " (Estoque conciliado com o romaneio anterior).";
                        }

                        if ($autoCount > 0) {
                            $body .= " Efeito dominó: +{$autoCount} item(ns) idêntico(s) associado(s).";
                        }

                        $title = match ($destination) {
                            'asset'    => 'Patrimônio Registrado',
                            'personal' => 'Despesa Registrada',
                            default    => 'Estoque Conciliado',
                        };

                        Notification::make()
                            ->title($title)
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
        ->columnSpan(1)
        ->extraAttributes(["style" => "margin-top: 32px"]);
    }

    private static function getAssistantAction(): Action
    {
        return Action::make("assistente")
            ->icon("heroicon-m-sparkles")
            ->tooltip("Assistente de Conversão")
            ->color("primary")
            ->visible(fn(Get $get) => filled($get("product_id")) && ($get("destination_type") ?? 'stock') === 'stock')
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

                Notification::make()->title("Fator de Conversão Aplicado!")->success()->send();
            });
    }
}
