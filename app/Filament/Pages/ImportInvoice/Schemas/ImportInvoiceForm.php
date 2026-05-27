<?php

namespace App\Filament\Pages\ImportInvoice\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Notifications\Notification;
use App\Models\Product;
use App\Models\Supplier;
use Filament\Schemas\Components\Actions;
use Filament\Actions\Action; // Mantido para botões gerais da tela
use Illuminate\Support\HtmlString;
use App\Services\InvoiceItemProcessor;

class ImportInvoiceForm
{
    public static function schema(): array
    {
        return [
            Hidden::make("invoice_key"),
            Hidden::make("received_invoice_id"),
            Hidden::make("xml_total_amount"),
            Hidden::make("financial_already_launched"),
            Hidden::make("xml_installments"),
            Hidden::make("xml_supplier_cnpj"),
            Hidden::make("xml_supplier_name"),
            Hidden::make("factor_source")->default("manual"),

            Wizard::make([
                Step::make("Identificação")
                    ->description("Emitente da Nota")
                    ->icon("heroicon-m-building-storefront")
                    ->schema([
                        Section::make("Dados Lidos")
                            ->schema([
                                TextInput::make("xml_supplier_cnpj")
                                    ->label("CNPJ")
                                    ->readOnly(),
                                TextInput::make("xml_supplier_name")
                                    ->label("Razão Social")
                                    ->readOnly(),
                            ])
                            ->columns(2),

                        Section::make("Vínculo")->schema([
                            Select::make("supplier_id")
                                ->label("Fornecedor no Sistema")
                                ->options(Supplier::pluck("name", "id"))
                                ->searchable()
                                ->required()
                                ->createOptionForm(
                                    \App\Filament\Resources\Suppliers\Schemas\SupplierForm::getFormSchema(),
                                )

                                // 🚀 A SOLUÇÃO DEFINITIVA: Nenhuma tipagem estrita aqui!
                                ->createOptionAction(function ($action) {
                                    return $action->mountUsing(function (
                                        $form,
                                        $livewire,
                                    ) {
                                        $cnpj =
                                            $livewire->data[
                                                "xml_supplier_cnpj"
                                            ] ?? null;
                                        $razaoSocial =
                                            $livewire->data[
                                                "xml_supplier_name"
                                            ] ?? null;

                                        $form->fill([
                                            "is_formal" => !empty($cnpj),
                                            "document" => $cnpj,
                                            "document_name" => $razaoSocial,
                                        ]);
                                    });
                                })

                                ->createOptionUsing(function (array $data, $livewire) {

                                                                    // 🚀 Como fornecedores de NFe são sempre formais, pegamos o documento direto
                                                                    // Se o usuário apagou no form por algum motivo, fazemos fallback pro XML original
                                                                    $document = $data["document"] ?? $livewire->data["xml_supplier_cnpj"] ?? null;
                                                                    $documentName = $data["document_name"] ?? $livewire->data["xml_supplier_name"] ?? null;

                                                                    $name = $data["name"] ?? null;

                                                                    if (empty($name)) {
                                                                        // Tenta pegar o nome fantasia do XML, se não tiver, usa a Razão Social
                                                                        $name = !empty($livewire->data["xml_supplier_trade_name"])
                                                                            ? $livewire->data["xml_supplier_trade_name"]
                                                                            : ($livewire->data["xml_supplier_name"] ?? "S/ Fornecedor");
                                                                    }

                                                                    return \App\Models\Supplier::create([
                                                                        "name" => $name,
                                                                        "document_name" => $documentName,
                                                                        "document" => $document,
                                                                        "type" => $data["type"] ?? "physical", // Respeita o tipo selecionado ou assume físico
                                                                        "parent_id" => $data["parent_id"] ?? null,
                                                                    ])->id;
                                                                }),
                        ]),
                    ]),

                Step::make("Mapeamento")
                    ->description("Vincular Produtos")
                    ->icon("heroicon-m-arrows-right-left")
                    ->schema([
                        // BOTÃO DE AÇÃO EM LOTE PARA O FORMULÁRIO (MARCAR TODOS)
                        Actions::make([
                            Action::make("marcar_todos_pessoal")
                                ->label("Marcar Tudo como Despesa Pessoal")
                                ->icon("heroicon-m-banknotes")
                                ->color("warning")
                                ->requiresConfirmation()
                                ->modalHeading("Ação em Lote")
                                ->modalDescription(
                                    'Isto irá marcar TODOS os itens pendentes da lista abaixo como "Uso Pessoal", limpando qualquer produto associado. Deseja continuar?',
                                )
                                ->action(function ($get, $set) {
                                    $items = $get("xml_items") ?? [];
                                    if (empty($items)) {
                                        return;
                                    }

                                    foreach ($items as $key => $item) {
                                        $items[$key]["is_personal"] = true;
                                        $items[$key]["product_id"] = null;
                                        $items[$key]["confirmation_step"] = 0;
                                    }

                                    $set("xml_items", $items);

                                    Notification::make()
                                        ->title(
                                            "Todos os itens marcados como Pessoal!",
                                        )
                                        ->success()
                                        ->send();
                                }),
                        ])->alignEnd(),

                        Repeater::make("xml_items")
                            ->label("Itens Pendentes")
                            ->addable(false)
                            ->reorderable(false)
                            ->deletable(false)
                            ->columns(6)
                            ->schema([
                                TextInput::make("xml_name")
                                    ->label("Item da Nota")
                                    ->readOnly()
                                    ->hint(function ($get) {
                                        $cost = (float) $get("xml_cost");

                                        // Formata com 5 casas, remove zeros no final, e remove vírgula solta
                                        $formattedCost = rtrim(
                                            rtrim(
                                                number_format(
                                                    $cost,
                                                    5,
                                                    ",",
                                                    ".",
                                                ),
                                                "0",
                                            ),
                                            ",",
                                        );

                                        // Limpa a unidade de medida só para a exibição
                                        $cleanUom = strtoupper(
                                            preg_replace(
                                                "/[^A-Z]/",
                                                "",
                                                $get("xml_uom") ?? "",
                                            ),
                                        );

                                        return "Original: R$ {$formattedCost} /{$cleanUom}";
                                    })
                                    ->columnSpan(3),

                                TextInput::make("xml_qty")
                                    ->label("Quantidade")
                                    ->numeric()
                                    ->required()
                                    ->hint(
                                        fn($get) => "Max: " .
                                            $get("available_qty"),
                                    )
                                    ->columnSpan(1),

                                TextInput::make("conversion_factor")
                                    ->label(function ($get) {
                                        $xmlUom = strtoupper(
                                            preg_replace(
                                                "/[^A-Z]/",
                                                "",
                                                $get("xml_uom") ?? "",
                                            ),
                                        );

                                        return match ($xmlUom) {
                                            "CX",
                                            "CX1"
                                                => "Qtd. de itens na caixa",
                                            "FD",
                                            "FDO"
                                                => "Qtd. de itens no fardo",
                                            "PC" => "Qtd. de itens na peça",
                                            "PCT" => "Qtd. de itens no pacote",
                                            "SC" => "Qtd. de itens no saco",
                                            default => "Fator de Conversão",
                                        };
                                    })
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->extraInputAttributes(function ($get) {
                                        return match ($get("factor_source")) {
                                            "history", "auto" => [
                                                "style" =>
                                                    "background-color: rgba(34, 197, 94, 0.15) !important;",
                                            ],
                                            "identical" => [
                                                "style" =>
                                                    "background-color: rgba(59, 130, 246, 0.15) !important;",
                                            ],
                                            "auto_math" => [
                                                "style" =>
                                                    "background-color: rgba(234, 179, 8, 0.15) !important;",
                                            ],
                                            "assistant_used" => [
                                                "style" =>
                                                    "background-color: rgba(168, 85, 247, 0.15) !important;",
                                            ],
                                            default => [],
                                        };
                                    })
                                    ->suffixAction(
                                        Action::make("assistente")
                                            ->icon("heroicon-m-sparkles")
                                            ->tooltip(
                                                "Clique para chamar o Assistente de Conversão",
                                            )
                                            ->color("primary")
                                            ->visible(
                                                fn($get) => filled(
                                                    $get("product_id"),
                                                ),
                                            )
                                            ->modalHeading(
                                                "Assistente de Conversão",
                                            )
                                            ->modalDescription(
                                                "Não se preocupe em fazer contas. Diga-nos os dados e o sistema descobre o fator correto!",
                                            )
                                            ->form(function ($get) {
                                                $xmlUom = strtoupper(
                                                    preg_replace(
                                                        "/[^A-Z]/",
                                                        "",
                                                        $get("xml_uom") ?? "",
                                                    ),
                                                );
                                                $internalUom =
                                                    Product::find(
                                                        $get("product_id"),
                                                    )->unit_of_measure ?? "UN";

                                                $packageTypes = [
                                                    "CX" => "Caixa",
                                                    "FD" => "Fardo",
                                                    "FDO" => "Fardo",
                                                    "PC" => "Peça",
                                                    "PCT" => "Pacote",
                                                    "SC" => "Saco",
                                                ];
                                                $isPackage = array_key_exists(
                                                    $xmlUom,
                                                    $packageTypes,
                                                );
                                                $packageName = $isPackage
                                                    ? $packageTypes[$xmlUom]
                                                    : $xmlUom;

                                                $fields = [
                                                    \Filament\Forms\Components\Placeholder::make(
                                                        "info",
                                                    )
                                                        ->hiddenLabel()
                                                        ->content(
                                                            new HtmlString(
                                                                "Na nota consta <strong>{$xmlUom}</strong>. Mas no seu estoque, controlamos em <strong>{$internalUom}</strong>.",
                                                            ),
                                                        ),
                                                ];

                                                if ($isPackage) {
                                                    $fields[] = TextInput::make(
                                                        "qtd_pacote",
                                                    )
                                                        ->label(
                                                            "Quantos itens vêm em 1 {$packageName}?",
                                                        )
                                                        ->numeric()
                                                        ->required();
                                                }

                                                if (
                                                    in_array($xmlUom, [
                                                        "KG",
                                                        "KIL",
                                                        "L",
                                                        "LT",
                                                        "LIT",
                                                    ]) &&
                                                    $internalUom === "UN"
                                                ) {
                                                    $fields[] = TextInput::make(
                                                        "peso_in",
                                                    )
                                                        ->label(
                                                            "Qual o peso/volume médio de 1 UNidade (em {$xmlUom})?",
                                                        )
                                                        ->helperText(
                                                            "Ex: Se 1 limão pesa 65g, digite 0.065.",
                                                        )
                                                        ->numeric()
                                                        ->required();
                                                }

                                                if (
                                                    ($xmlUom === "UN" ||
                                                        $isPackage) &&
                                                    in_array($internalUom, [
                                                        "KG",
                                                        "G",
                                                        "L",
                                                        "ML",
                                                    ])
                                                ) {
                                                    $fields[] = TextInput::make(
                                                        "peso_out",
                                                    )
                                                        ->label(
                                                            "Qual o peso/volume exato de 1 UNidade (em {$internalUom})?",
                                                        )
                                                        ->helperText(
                                                            "Ex: Se for um refrigerante de lata que tem 350ml, digite 350.",
                                                        )
                                                        ->numeric()
                                                        ->required();
                                                }

                                                return $fields;
                                            })
                                            ->action(function (
                                                array $data,
                                                $set,
                                                $get,
                                            ) {
                                                $xmlUom = strtoupper(
                                                    preg_replace(
                                                        "/[^A-Z]/",
                                                        "",
                                                        $get("xml_uom") ?? "",
                                                    ),
                                                );
                                                $internalUom =
                                                    Product::find(
                                                        $get("product_id"),
                                                    )->unit_of_measure ?? "UN";

                                                $isPackage = in_array($xmlUom, [
                                                    "CX",
                                                    "FD",
                                                    "FDO",
                                                    "PC",
                                                    "PCT",
                                                    "SC",
                                                ]);
                                                $qtd =
                                                    (float) ($data[
                                                        "qtd_pacote"
                                                    ] ?? 1);
                                                $pesoIn =
                                                    (float) ($data["peso_in"] ??
                                                        0);
                                                $pesoOut =
                                                    (float) ($data[
                                                        "peso_out"
                                                    ] ?? 0);

                                                $factor = 1;

                                                if (
                                                    $xmlUom === $internalUom ||
                                                    (in_array($xmlUom, [
                                                        "KG",
                                                        "KIL",
                                                    ]) &&
                                                        $internalUom ===
                                                            "KG") ||
                                                    (in_array($xmlUom, [
                                                        "L",
                                                        "LT",
                                                        "LIT",
                                                    ]) &&
                                                        $internalUom === "LT")
                                                ) {
                                                    $factor = 1;
                                                } elseif (
                                                    in_array($xmlUom, [
                                                        "KG",
                                                        "KIL",
                                                    ]) &&
                                                    $internalUom === "G"
                                                ) {
                                                    $factor = 1000;
                                                } elseif (
                                                    $xmlUom === "G" &&
                                                    in_array($internalUom, [
                                                        "KG",
                                                        "KIL",
                                                    ])
                                                ) {
                                                    $factor = 0.001;
                                                } elseif (
                                                    in_array($xmlUom, [
                                                        "L",
                                                        "LT",
                                                        "LIT",
                                                    ]) &&
                                                    $internalUom === "ML"
                                                ) {
                                                    $factor = 1000;
                                                } elseif (
                                                    $xmlUom === "ML" &&
                                                    in_array($internalUom, [
                                                        "L",
                                                        "LT",
                                                        "LIT",
                                                    ])
                                                ) {
                                                    $factor = 0.001;
                                                } elseif (
                                                    $isPackage &&
                                                    $internalUom === "UN"
                                                ) {
                                                    $factor = $qtd;
                                                } elseif (
                                                    in_array($xmlUom, [
                                                        "KG",
                                                        "KIL",
                                                        "L",
                                                        "LT",
                                                        "LIT",
                                                    ]) &&
                                                    $internalUom === "UN" &&
                                                    $pesoIn > 0
                                                ) {
                                                    $factor = 1 / $pesoIn;
                                                } elseif (
                                                    $isPackage &&
                                                    in_array($internalUom, [
                                                        "KG",
                                                        "G",
                                                        "L",
                                                        "ML",
                                                    ]) &&
                                                    $pesoOut > 0
                                                ) {
                                                    $factor = $qtd * $pesoOut;
                                                } elseif (
                                                    $xmlUom === "UN" &&
                                                    in_array($internalUom, [
                                                        "KG",
                                                        "G",
                                                        "L",
                                                        "ML",
                                                    ]) &&
                                                    $pesoOut > 0
                                                ) {
                                                    $factor = $pesoOut;
                                                }

                                                $set(
                                                    "conversion_factor",
                                                    round($factor, 4),
                                                );
                                                $set(
                                                    "factor_source",
                                                    "assistant_used",
                                                );

                                                Notification::make()
                                                    ->title(
                                                        "Fator de Conversão Aplicado!",
                                                    )
                                                    ->success()
                                                    ->send();
                                            }),
                                    )
                                    ->columnSpan(1),

                                Toggle::make("is_personal")
                                    ->label("Uso Pessoal?")
                                    ->live()
                                    ->inline(false)
                                    ->columnSpan(1)
                                    ->afterStateUpdated(function ($set) {
                                        $set("product_id", null);
                                        $set("confirmation_step", 0);
                                    }),

                                Select::make("product_id")
                                    ->label("Produto Interno")
                                    ->options(
                                        fn() => Product::whereIn("type", [
                                            "ingredient",
                                            "resale",
                                            "packaging",
                                            "consumable",
                                        ])->pluck("name", "id"),
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(
                                        fn($state, $set, $get) => app(
                                            InvoiceItemProcessor::class,
                                        )->applySmartConversion(
                                            $state,
                                            $set,
                                            $get,
                                        ),
                                    )
                                    ->hint(function ($get) {
                                        $productId = $get("product_id");
                                        return $productId
                                            ? "U.M Interna: " .
                                                    Product::where(
                                                        "id",
                                                        $productId,
                                                    )->value("unit_of_measure")
                                            : null;
                                    })
                                    ->disabled(fn($get) => $get("is_personal"))
                                    ->required(fn($get) => !$get("is_personal"))
                                    ->columnSpan(5)
                                    ->createOptionForm([
                                        TextInput::make("name")
                                            ->label("Nome do Produto")
                                            ->default(
                                                fn($get) => $get("xml_name"),
                                            )
                                            ->required(),

                                        Select::make("type")
                                            ->label("Tipo de Produto")
                                            ->options([
                                                "ingredient" =>
                                                    "Insumo Base (Estoque/Cozinha)",
                                                "resale" =>
                                                    "Revenda (Pronto para Venda)",
                                                "packaging" =>
                                                    "Embalagem (Caixas, Sacos)",
                                                "consumable" =>
                                                    "Uso Interno (Limpeza, etc)",
                                            ])
                                            ->default("ingredient")
                                            ->live()
                                            ->afterStateUpdated(
                                                fn($set) => $set(
                                                    "category_id",
                                                    null,
                                                ),
                                            )
                                            ->required(),

                                        Select::make("category_id")
                                            ->label("Categoria")
                                            ->options(function ($get) {
                                                $type = $get("type");
                                                if (!$type) {
                                                    return [];
                                                }

                                                $targetType = match ($type) {
                                                    "resale" => "external",
                                                    "ingredient" => "internal",
                                                    "packaging",
                                                    "consumable"
                                                        => "supply",
                                                    default => "internal",
                                                };
                                                return \App\Models\Category::where(
                                                    "type",
                                                    $targetType,
                                                )->pluck("name", "id");
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->required(),

                                        Select::make("unit_of_measure")
                                            ->label("Unidade")
                                            ->options([
                                                "UN" => "Unidade (UN)",
                                                "CX" => "Caixa (CX)",
                                                "KG" => "Quilograma (KG)",
                                                "G" => "Grama (G)",
                                                "LT" => "Litro (LT)",
                                                "ML" => "Mililitro (ML)",
                                            ])
                                            ->default("UN")
                                            ->required(),

                                        Toggle::make("track_inventory")
                                            ->label("Controlar Estoque?")
                                            ->default(true)
                                            ->inline(false),
                                    ])
                                    ->createOptionUsing(function (array $data, $livewire, Get $get) {
                                        // Pega o custo que veio do XML para já criar o produto com o custo base correto
                                        $data["current_stock"] = 0;
                                        $data["base_cost"] = (float) $get("xml_cost") ?: 0;

                                        $product = \App\Models\Product::create($data);

                                        // Força a tela a atualizar para reconhecer o novo produto
                                        $livewire->dispatch('$refresh');

                                        return $product->id;
                                    }),

                                Actions::make([
                                    Action::make("confirmItem")
                                        ->label(function ($get) {
                                            if (
                                                $get("confirmation_step") == 1
                                            ) {
                                                return "Confirmar";
                                            }
                                            return $get("is_personal")
                                                ? "Gerar Despesa"
                                                : "Associar";
                                        })
                                        ->icon(function ($get) {
                                            if (
                                                $get("confirmation_step") == 1
                                            ) {
                                                return "heroicon-m-check-badge";
                                            }
                                            return $get("is_personal")
                                                ? "heroicon-m-banknotes"
                                                : "heroicon-m-link";
                                        })
                                        ->color(function ($get) {
                                            if (
                                                $get("confirmation_step") == 1
                                            ) {
                                                return "success";
                                            }
                                            return $get("is_personal")
                                                ? "warning"
                                                : "primary";
                                        })
                                        ->disabled(
                                            fn($get) => !$get("is_personal") &&
                                                empty($get("product_id")),
                                        )
                                        ->action(
                                            fn($get, $set, $livewire) => app(
                                                InvoiceItemProcessor::class,
                                            )->processConfirmedItem(
                                                $get,
                                                $set,
                                                $livewire,
                                            ),
                                        ),
                                ])
                                    ->extraAttributes([
                                        "style" => "margin-top: 32px",
                                    ])
                                    ->columnSpan(1),

                                Hidden::make("xml_cost"),
                                Hidden::make("xml_uom"),
                                Hidden::make("available_qty"),
                                Hidden::make("is_matched"),
                                Hidden::make("item_uuid"),
                                Hidden::make("confirmation_step")->default(0),
                            ])
                            ->columnSpanFull(),
                    ]),
            ])
                ->submitAction(
                    \Filament\Actions\Action::make("finalizar_nota")
                        ->label("🎉 Concluir e Finalizar Nota")
                        ->color("success")
                        ->size("lg")
                        ->extraAttributes(["wire:click.prevent" => "submit"]),
                )
                ->cancelAction(
                    \Filament\Actions\Action::make("voltar_lista")
                        ->label("Voltar Para a Lista")
                        ->color("gray")
                        ->size("lg")
                        ->url(
                            fn() => \App\Filament\Resources\ReceivedInvoices\ReceivedInvoiceResource::getUrl(
                                "index",
                            ),
                        ),
                )
                ->columnSpanFull(),
        ];
    }
}
