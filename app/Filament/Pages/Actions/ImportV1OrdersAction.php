<?php

namespace App\Filament\Pages\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;
use Carbon\Carbon;

// Models
use App\Models\LegacyOrder;
use App\Models\LegacyOrderItem;
use App\Models\LegacyCustomer;
use App\Models\LegacyNeighborhood;
use App\Models\LegacyProduct;
use App\Models\LegacyPaymentMethod;
use App\Models\LegacyOrderPayment;
use App\Models\LegacyImportLog;

class ImportV1OrdersAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return "importV1Orders";
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->modalHeading("Histórico de Pedidos Detalhados (CSV)")
            ->modalWidth("4xl")
            ->icon("heroicon-o-table-cells")
            ->color("info")
            ->steps([
                // 🚀 PASSO 1: UPLOAD & IDENTIFICAÇÃO
                Step::make("1. Upload")
                    ->description(
                        "Envie o seu ficheiro CSV e identifique o lote.",
                    )
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make("import_tag")
                                ->label("Identificador / Tag do Lote")
                                ->default("CSV_V1_" . now()->format("Ymd_His"))
                                ->required()
                                ->maxLength(255),

                            FileUpload::make("csv_file")
                                ->label("Ficheiro CSV")
                                ->acceptedFileTypes([
                                    "text/csv",
                                    "text/plain",
                                    "application/csv",
                                ])
                                ->disk("local")
                                ->directory("csv_v1_imports")
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (
                                    $state,
                                    Set $set,
                                ) {
                                    $headers = [];
                                    if ($state) {
                                        $file = is_array($state)
                                            ? array_values($state)[0]
                                            : $state;
                                        if (
                                            $file instanceof
                                            \Livewire\Features\SupportFileUploads\TemporaryUploadedFile
                                        ) {
                                            $handle = fopen(
                                                $file->getRealPath(),
                                                "r",
                                            );
                                            if ($handle) {
                                                $firstLine = fgets($handle);
                                                if ($firstLine) {
                                                    $delimiter =
                                                        strpos(
                                                            $firstLine,
                                                            ";",
                                                        ) !== false
                                                            ? ";"
                                                            : ",";
                                                    rewind($handle);
                                                    $rawHeaders = fgetcsv(
                                                        $handle,
                                                        0,
                                                        $delimiter,
                                                    );
                                                    if ($rawHeaders) {
                                                        foreach (
                                                            $rawHeaders
                                                            as $idx => $col
                                                        ) {
                                                            $headers[
                                                                (string) $idx
                                                            ] = trim($col);
                                                        }
                                                    }
                                                }
                                                fclose($handle);
                                            }
                                        }
                                    }
                                    $set("csv_headers", json_encode($headers));
                                }),
                        ]),
                        Hidden::make("csv_headers"),
                    ]),

                // 🚀 PASSO 2: MAPEAMENTO
                Step::make("2. Mapeamento")
                    ->description(
                        "Configure a estrutura de dados e as finanças.",
                    )
                    ->schema(function (Get $get) {
                        $headers = json_decode($get("csv_headers"), true) ?? [];
                        if (empty($headers)) {
                            return [
                                Placeholder::make("w")
                                    ->hiddenLabel()
                                    ->content(
                                        "⚠️ Faça o upload do arquivo no passo anterior.",
                                    ),
                            ];
                        }

                        $v3Payments = \App\Models\PaymentMethod::pluck(
                            "name",
                            "id",
                        )->toArray();

                        return [
                            Section::make("Estrutura do Pedido e dos Itens")
                                ->collapsible()
                                ->schema([
                                    Grid::make(3)->schema([
                                        Select::make("col_number")
                                            ->label("Nº do Pedido/Comanda")
                                            ->options($headers)
                                            ->required(),
                                        Select::make("col_date")
                                            ->label("Data do Pedido")
                                            ->options($headers)
                                            ->required(),
                                        Select::make("col_time")
                                            ->label("Hora do Pedido")
                                            ->options($headers)
                                            ->nullable(),
                                        Select::make("col_type")
                                            ->label("Tipo (Mesa/Delivery)")
                                            ->options($headers)
                                            ->nullable(),
                                        Select::make("col_customer")
                                            ->label("Nome do Cliente")
                                            ->options($headers)
                                            ->nullable(),
                                        Select::make("col_neighborhood")
                                            ->label("Bairro de Entrega")
                                            ->options($headers)
                                            ->nullable(),
                                    ]),
                                    Grid::make(4)->schema([
                                        Select::make("col_code")
                                            ->label("Código do Item (Opcional)")
                                            ->options($headers)
                                            ->nullable(),
                                        Select::make("col_product")
                                            ->label("Nome do Produto/Item")
                                            ->options($headers)
                                            ->required(),
                                        Select::make("col_qty")
                                            ->label("Quantidade Vendida")
                                            ->options($headers)
                                            ->required(),
                                        Select::make("col_price")
                                            ->label("Preço Unitário")
                                            ->options($headers)
                                            ->required(),
                                    ]),
                                ]),

                            Section::make(
                                "Conciliação Financeira (Formas de Pagamento)",
                            )
                                ->collapsible()
                                ->schema([
                                    Placeholder::make("payment_notice")
                                        ->hiddenLabel()
                                        ->content(
                                            new \Illuminate\Support\HtmlString(
                                                '<div class="p-3 bg-amber-50 border-l-4 border-amber-400 text-amber-700 rounded text-xs flex items-center gap-2"><span>💡</span><span><b>Aviso:</b> Deixe vazio se não possuir detalhes de pagamentos em colunas.</span></div>',
                                            ),
                                        ),

                                    Repeater::make("payment_mappings")
                                        ->label("Vincular Colunas Financeiras")
                                        ->addActionLabel("Adicionar Regra")
                                        ->schema([
                                            Select::make("csv_columns")
                                                ->label("Colunas no CSV")
                                                ->options($headers)
                                                ->multiple() // 🚀 Aceita múltiplas colunas para mapear para o mesmo destino
                                                ->required(),

                                            Select::make("v3_payment_id")
                                                ->label(
                                                    "Corresponde ao ERP (V3)",
                                                )
                                                ->options($v3Payments)
                                                ->searchable()
                                                ->required(),
                                        ])
                                        ->columns(2)
                                        ->columnSpanFull(),
                                ]),
                        ];
                    }),
            ])
            ->action(function (array $data) {
                set_time_limit(0);
                ini_set("memory_limit", "512M");

                // 🚀 DESLIGA O LOG DO ELOQUENT PARA SALVAR MEMÓRIA (Essencial para CSVs grandes)
                DB::disableQueryLog();

                $fileState = $data["csv_file"];
                $filePath = is_array($fileState)
                    ? array_values($fileState)[0]
                    : $fileState;
                $fullPath = Storage::disk("local")->path($filePath);
                $tag = trim($data["import_tag"]);

                if (!Storage::disk("local")->exists($filePath)) {
                    Notification::make()
                        ->title("Erro: Ficheiro não encontrado.")
                        ->danger()
                        ->send();
                    return;
                }

                $handle = fopen($fullPath, "r");
                $firstLine = fgets($handle);
                $delimiter = strpos($firstLine, ";") !== false ? ";" : ",";
                rewind($handle);
                fgetcsv($handle, 0, $delimiter); // Ignora cabeçalho

                $orders = [];
                $parseMoney = fn($v) => (float) str_replace(
                    ",",
                    ".",
                    preg_replace("/[^0-9,-]/", "", $v),
                );

                // 1. LER E AGRUPAR O CSV
                while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                    $numCol = $data["col_number"] ?? null;
                    $num = $numCol !== null ? trim($row[$numCol] ?? "") : "";

                    if (empty($num)) {
                        continue;
                    }

                    if (!isset($orders[$num])) {
                        $orders[$num] = [
                            "number" => $num,
                            "customer" => isset($data["col_customer"])
                                ? trim($row[$data["col_customer"]] ?? "")
                                : "",
                            "neighborhood" => isset($data["col_neighborhood"])
                                ? trim($row[$data["col_neighborhood"]] ?? "")
                                : "",
                            "date" => trim($row[$data["col_date"]] ?? ""),
                            "time" => isset($data["col_time"])
                                ? trim($row[$data["col_time"]] ?? "")
                                : "00:00",
                            "type" => isset($data["col_type"])
                                ? trim($row[$data["col_type"]] ?? "")
                                : "",
                            "items" => [],
                            "payments" => [],
                        ];

                        // 🚀 EXTRAÇÃO INTELIGENTE MULTICOLUNA (Agrupa várias colunas num só meio de pagamento)
                        if (!empty($data["payment_mappings"])) {
                            foreach ($data["payment_mappings"] as $mapping) {
                                $v3Id = $mapping["v3_payment_id"];
                                $csvCols = $mapping["csv_columns"] ?? [];

                                foreach ($csvCols as $colIndex) {
                                    $val = $parseMoney($row[$colIndex] ?? 0);
                                    if ($val > 0) {
                                        $orders[$num]["payments"][$v3Id] =
                                            ($orders[$num]["payments"][$v3Id] ??
                                                0) +
                                            $val;
                                    }
                                }
                            }
                        }
                    }

                    $orders[$num]["items"][] = [
                        "code" => isset($data["col_code"])
                            ? trim($row[$data["col_code"]] ?? "")
                            : "",
                        "name" => trim($row[$data["col_product"]] ?? ""),
                        "qty" => $parseMoney($row[$data["col_qty"]] ?? "0"),
                        "unit_price" => $parseMoney(
                            $row[$data["col_price"]] ?? "0",
                        ),
                    ];
                }
                fclose($handle);

                // 2. GRAVAR NO BANCO DE DADOS
                $totalImported = 0;
                $newItemsInDictionary = 0;
                $v3PaymentsMap = \App\Models\PaymentMethod::pluck(
                    "name",
                    "id",
                )->toArray();

                foreach (array_chunk($orders, 250, true) as $chunk) {
                    DB::beginTransaction();
                    try {
                        foreach ($chunk as $num => $d) {
                            $cleanCust = trim(
                                preg_replace(
                                    "/^\s*\d+\s*-\s*/",
                                    "",
                                    $d["customer"],
                                ),
                            );
                            $lCust = !empty($cleanCust)
                                ? LegacyCustomer::firstOrCreate([
                                    "legacy_name" => $cleanCust,
                                ])
                                : null;
                            $lNeigh = !empty($d["neighborhood"])
                                ? LegacyNeighborhood::firstOrCreate([
                                    "legacy_name" => $d["neighborhood"],
                                ])
                                : null;

                            $orderType = match (mb_strtolower($d["type"])) {
                                "mesa", "table" => "table",
                                "delivery", "entrega" => "delivery",
                                default => "counter",
                            };

                            try {
                                $timeStr = empty($d["time"])
                                    ? "00:00"
                                    : $d["time"];
                                $issueDate = Carbon::createFromFormat(
                                    "d/m/Y H:i",
                                    $d["date"] . " " . $timeStr,
                                )->format("Y-m-d H:i:s");
                            } catch (\Exception $e) {
                                try {
                                    $issueDate = Carbon::parse(
                                        $d["date"],
                                    )->format("Y-m-d H:i:s");
                                } catch (\Exception $e) {
                                    $issueDate = now()->format("Y-m-d H:i:s");
                                }
                            }

                            $orderModel = LegacyOrder::updateOrCreate(
                                ["number" => $num, "import_tag" => $tag],
                                [
                                    "number" => $num,
                                    "issue_date" => $issueDate,
                                    "order_type" => $orderType,
                                    "status" => "processed",
                                    "legacy_customer_id" => $lCust?->id,
                                    "legacy_neighborhood_id" => $lNeigh?->id,
                                    "total_value" => 0,
                                    "discount" => 0,
                                ],
                            );

                            // Limpa registos antigos para evitar duplicatas em re-importações
                            DB::table("legacy_order_items")
                                ->where("legacy_order_id", $orderModel->id)
                                ->delete();
                            DB::table("legacy_order_payments")
                                ->where("legacy_order_id", $orderModel->id)
                                ->delete();

                            $orderTotal = 0;

                            foreach ($d["items"] as $item) {
                                $name = $item["name"];
                                if (empty($name)) {
                                    continue;
                                }

                                $code = !empty($item["code"])
                                    ? $item["code"]
                                    : mb_strtoupper($name);

                                // 🚀 A BLINDAGEM DO DICIONÁRIO: Procura sempre pela âncora "name"!
                                $lProd = LegacyProduct::firstOrCreate(
                                    ["name" => $name],
                                    ["legacy_code" => $code],
                                );

                                if ($lProd->wasRecentlyCreated) {
                                    $newItemsInDictionary++;
                                }
                                if ($lProd->is_ignored) {
                                    continue;
                                }

                                $lineTotal = $item["qty"] * $item["unit_price"];
                                $orderTotal += $lineTotal;

                                LegacyOrderItem::create([
                                    "legacy_order_id" => $orderModel->id,
                                    "legacy_product_id" => $lProd->id,
                                    "quantity" => $item["qty"],
                                    "unit_price" => $item["unit_price"],
                                    "total_price" => $lineTotal,
                                ]);
                            }

                            foreach ($d["payments"] as $v3Id => $amt) {
                                $pName =
                                    $v3PaymentsMap[$v3Id] ?? "Forma " . $v3Id;
                                $lPay = LegacyPaymentMethod::firstOrCreate(
                                    ["name" => $pName],
                                    ["current_payment_method_id" => $v3Id],
                                );

                                LegacyOrderPayment::create([
                                    "legacy_order_id" => $orderModel->id,
                                    "legacy_payment_method_id" => $lPay->id,
                                    "amount" => $amt,
                                ]);
                            }

                            $orderTotalValue = $orderTotal;
                            if (
                                $orderType === "delivery" &&
                                $lNeigh?->neighborhood
                            ) {
                                $orderTotalValue +=
                                    (float) $lNeigh->neighborhood->delivery_fee;
                            }

                            $orderModel->update([
                                "total_value" => $orderTotalValue,
                            ]);
                            $totalImported++;
                        }
                        DB::commit();
                    } catch (\Exception $e) {
                        DB::rollBack();
                        Storage::disk("local")->delete($filePath);
                        throw $e;
                    }
                }

                Storage::disk("local")->delete($filePath);

                LegacyImportLog::create([
                    "import_tag" => $tag,
                    "type" => "v1_detailed",
                    "items_count" => $totalImported,
                ]);

                Notification::make()
                    ->title("Importação V1 Concluída!")
                    ->body(
                        "<b>{$totalImported}</b> pedidos salvos.<br><b>{$newItemsInDictionary}</b> novos produtos adicionados ao dicionário.",
                    )
                    ->success()
                    ->send();
            });
    }
}
