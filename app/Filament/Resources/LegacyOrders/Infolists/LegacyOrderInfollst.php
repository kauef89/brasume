<?php

namespace App\Filament\Resources\LegacyOrders\Infolists;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Schemas\Components\Actions;
use Filament\Actions\Action;

class LegacyOrderInfolist
{
    public static function build(Schema $schema): Schema
    {
        return $schema->components([
            Section::make("Resumo da Nota")
                ->compact()
                ->columns(4)
                ->columnSpanFull()
                ->schema([
                    // Esconde a chave da NFe se estiver vazia (típico da V1)
                    TextEntry::make("nfe_key")
                        ->label("Chave de Acesso")
                        ->columns(2)
                        ->copyable()
                        ->visible(fn($record) => filled($record->nfe_key)),

                    TextEntry::make("issue_date")
                        ->label("Data do Pedido")
                        ->dateTime("d/m/Y H:i"),

                    TextEntry::make("series")->label("Série")->default("N/A"),

                    TextEntry::make("number")->label("Número do Pedido/Nota"),

                    // 🚀 OS NOSSOS BOTÕES FISCAIS
                    Actions::make([
                        // Botão 1: Download Nativo do XML
                        Action::make('download_xml')
                            ->label('Baixar XML')
                            ->icon('heroicon-o-code-bracket')
                            // Só aparece se tiver chave E conteúdo XML salvo no banco
                            ->visible(fn($record) => filled($record->nfe_key) && filled($record->xml_content))
                            ->action(function ($record) {
                                return response()->streamDownload(function () use ($record) {
                                    echo $record->xml_content;
                                }, "{$record->nfe_key}.xml");
                            }),

                        // Botão 2: Imprimir DANFE
                        Action::make('print_danfe')
                            ->label('Imprimir Cupom (DANFE)')
                            ->icon('heroicon-o-printer')
                            // Só aparece se tiver chave
                            ->visible(fn($record) => filled($record->nfe_key))
                            ->url(fn ($record) => "/admin/legacy-orders/{$record->id}/print")
                            ->openUrlInNewTab(),
                    ])->columnSpanFull(),
                ]),

            Section::make("Dados do Pedido & Cliente")
                ->compact()
                ->columns(5)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make("import_tag")->label("Tag Lote")->badge(),

                    TextEntry::make("order_type")
                        ->label("Tipo")
                        ->badge()
                        ->state(function ($record) {
                            if ($record->order_type) {
                                return match ($record->order_type) {
                                    "delivery" => "Delivery",
                                    "counter" => "Balcão",
                                    "table" => "Mesa",
                                    default => $record->order_type,
                                };
                            }
                            return "-";
                        }),

                    // 🚀 VACINA 1: Nullsafe no Cliente
                    TextEntry::make("customer_name_display")
                        ->label("Nome do Cliente")
                        ->state(function ($record) {
                            if ($record->customer_id) {
                                return $record->customer?->name ??
                                    "Consumidor Final";
                            }
                            if ($record->legacy_customer_id) {
                                return $record->legacyCustomer?->legacy_name ??
                                    "Consumidor Final";
                            }
                            return "Consumidor Final";
                        }),

                    TextEntry::make("customer.cpf")
                        ->label("CPF/CNPJ")
                        ->default("Não informado"),

                    // 🚀 VACINA 2: Nullsafe Duplo no Bairro e Taxa
                    TextEntry::make("neighborhood_display")
                        ->label("Bairro de Entrega")
                        ->state(function ($record) {
                            if ($record->legacy_neighborhood_id) {
                                // Tenta o mapeado da V3, senão cai pro legado
                                $mapped =
                                    $record->legacyNeighborhood?->neighborhood;
                                return $mapped?->name ??
                                    ($record->legacyNeighborhood
                                        ?->legacy_name ??
                                        "Bairro Desconhecido");
                            }
                            return "-";
                        })
                        ->visible(
                            fn($record) => $record->order_type === "delivery",
                        ),
                ]),

            Section::make("Itens do Pedido")
                ->compact()
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make("items")
                        ->hiddenLabel() // Oculta o título padrão "Items" do relacionamento
                        ->columns(4) // Distribui os campos em 4 colunas horizontais
                        ->contained(false)
                        ->schema([
                            TextEntry::make("legacyProduct.name")
                                ->hiddenlabel()
                                ->weight("bold")
                                ->default("Produto Desconhecido"), // 🚀 Previne erro se o produto foi apagado

                            TextEntry::make("quantity")
                                ->hiddenlabel()
                                ->numeric(decimalPlaces: 3), // Removido o sufixo "un"

                            TextEntry::make("unit_price")
                                ->hiddenlabel()
                                ->money("BRL")
                                ->alignEnd(),

                            TextEntry::make("total_price")
                                ->hiddenlabel()
                                ->money("BRL")
                                ->alignEnd(),
                        ]),
                ]),

            Section::make("Financeiro & Pagamentos")
                ->compact()
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    // Lado Esquerdo: Resumo dos Valores
                    Section::make()
                        ->columnSpan(1)
                        ->schema([
                            // Calcula e mostra a taxa de entrega a partir do bairro mapeado
                            TextEntry::make("delivery_fee")
                                ->label("Taxa de Entrega")
                                ->money("BRL")
                                ->state(function ($record) {
                                    if (
                                        $record->order_type === "delivery" &&
                                        $record->legacy_neighborhood_id
                                    ) {
                                        // 🚀 VACINA 3: Nullsafe no relacionamento da Taxa
                                        return (float) ($record
                                            ->legacyNeighborhood?->neighborhood
                                            ?->delivery_fee ?? 0);
                                    }
                                    return 0;
                                })
                                ->visible(
                                    fn($record) => $record->order_type ===
                                        "delivery",
                                ),

                            TextEntry::make("discount")
                                ->label("Desconto")
                                ->money("BRL")
                                ->visible(fn($record) => $record->discount > 0),

                            // O Total Final já deve vir calculado da nossa importação, mas podemos recalcular visualmente por garantia
                            TextEntry::make("total_value")
                                ->label("Valor Total do Pedido")
                                ->weight("bold")
                                ->size("lg")
                                ->money("BRL"),
                        ]),

                    // Lado Direito: Métodos de Pagamento Utilizados
                    Section::make("Pagamentos Registados")
                        ->columnSpan(1)
                        ->schema([
                            RepeatableEntry::make("payments") // Precisa garantir que no Model a relação se chama 'payments'
                                ->label("")
                                ->columns(2)
                                ->contained(false)
                                ->schema([
                                    TextEntry::make("legacyPaymentMethod.name")
                                        ->hiddenLabel()
                                        ->default("Pgt. Desconhecido"), // 🚀 Previne erro

                                    TextEntry::make("amount")
                                        ->hiddenLabel()
                                        ->money("BRL")
                                        ->alignEnd(),
                                ]),
                        ]),
                ]),
        ]);
    }
}
