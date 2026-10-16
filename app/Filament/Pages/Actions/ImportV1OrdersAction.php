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
use Illuminate\Support\HtmlString;
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
use App\Models\FiscalDocument;

class ImportV1OrdersAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return "importV1Orders";
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->modalHeading("Histórico de Pedidos Detalhados (CSV em Lote)")
            ->modalWidth("5xl")
            ->icon("heroicon-o-table-cells")
            ->color("info")
            ->steps([
                // 🚀 PASSO 1: UPLOAD & INJEÇÃO DE SESSÃO
                Step::make("1. Upload")
                    ->description("Envie um ou vários ficheiros CSV e identifique o lote.")
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make("import_tag")
                                ->label("Identificador / Tag do Lote")
                                ->default("CSV_V1_" . now()->format("Ymd_His"))
                                ->required()
                                ->maxLength(255),

                            FileUpload::make("csv_file")
                                ->label("Ficheiros CSV")
                                ->multiple()
                                ->disk(config('livewire.temporary_file_upload.disk') ?: 'local')
                                ->directory("csv_v1_imports")
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set) {
                                    $headers = [];
                                    if ($state && is_array($state) && count($state) > 0) {
                                        $file = array_values($state)[0];

                                        if ($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                                            $handle = fopen($file->getRealPath(), "r");
                                            if ($handle) {
                                                $firstLine = fgets($handle);
                                                $firstLine = preg_replace('/^[\xef\xbb\xbf]+/', '', $firstLine);

                                                if ($firstLine) {
                                                    $delimiter = strpos($firstLine, ";") !== false ? ";" : ",";
                                                    rewind($handle);
                                                    $rawHeaders = fgetcsv($handle, 0, $delimiter);

                                                    if ($rawHeaders) {
                                                        $rawHeaders[0] = preg_replace('/^[\xef\xbb\xbf]+/', '', $rawHeaders[0]);
                                                        foreach ($rawHeaders as $idx => $col) {
                                                            $colName = trim($col);
                                                            if ($colName === '') {
                                                                $numeroColuna = $idx + 1;
                                                                $colName = "⚠️ Coluna Sem Nome ({$numeroColuna})";
                                                            }
                                                            $headers[(string) $idx] = $colName;
                                                        }
                                                    }
                                                }
                                                fclose($handle);
                                            }
                                        }
                                    }
                                    $set("csv_headers", json_encode($headers));

                                    // Injeção da Sessão
                                    $fields = ['col_number', 'col_date', 'col_type', 'col_customer', 'col_neighborhood', 'col_total_order', 'col_code', 'col_product', 'col_qty', 'col_price'];
                                    foreach ($fields as $field) {
                                        if ($val = session()->get("csv_map_{$field}")) {
                                            $set($field, $val);
                                        }
                                    }
                                    if ($payments = session()->get('csv_map_payments')) {
                                        $set('payment_mappings', $payments);
                                    }
                                }),
                        ]),
                        Hidden::make("csv_headers"),
                    ]),

                // 🚀 PASSO 2: MAPEAMENTO
                Step::make("2. Mapeamento")
                    ->description("Configure a estrutura de dados. O sistema salvará essas escolhas ao confirmar.")
                    ->schema(function (Get $get) {
                        $headers = json_decode($get("csv_headers"), true) ?? [];
                        if (empty($headers)) {
                            return [Placeholder::make("w")->hiddenLabel()->content("⚠️ Faça o upload dos arquivos no passo anterior.")];
                        }

                        $v3Payments = \App\Models\PaymentMethod::pluck("name", "id")->toArray();

                        $makeSelect = function($name, $label, $required = false, $helper = null) use ($headers) {
                            $field = Select::make($name)->label($label)->options($headers);
                            if ($required) $field->required();
                            if ($helper) $field->helperText($helper);
                            return $field;
                        };

                        return [
                            Section::make("Estrutura do Pedido e dos Itens")
                                ->collapsible()
                                ->schema([
                                    Grid::make(3)->schema([
                                        $makeSelect("col_number", "Nº do Pedido/Comanda", true),
                                        $makeSelect("col_date", "Data e Hora do Pedido", true),
                                        $makeSelect("col_type", "Tipo (Mesa/Delivery)"),
                                    ]),
                                    Grid::make(3)->schema([
                                        $makeSelect("col_customer", "Nome do Cliente"),
                                        $makeSelect("col_neighborhood", "Bairro de Entrega"),
                                        $makeSelect("col_total_order", "Total do Pedido (Acumulado)", true, "A coluna que contém o valor final."),
                                    ]),
                                    Grid::make(4)->schema([
                                        $makeSelect("col_code", "Código (Opcional)"),
                                        $makeSelect("col_product", "Nome do Produto", true),
                                        $makeSelect("col_qty", "Qtd", true),
                                        $makeSelect("col_price", "Preço Unit.", true),
                                    ]),
                                ]),

                            Section::make("Conciliação Financeira (Formas de Pagamento)")
                                ->collapsible()
                                ->schema([
                                    Placeholder::make("payment_notice")->hiddenLabel()->content(new HtmlString('<div class="p-3 bg-amber-50 border-l-4 border-amber-400 text-amber-700 rounded text-xs flex items-center gap-2"><span>💡</span><span><b>Aviso:</b> Deixe vazio se não possuir detalhes de pagamentos em colunas separadas.</span></div>')),
                                    Repeater::make("payment_mappings")
                                        ->label("Vincular Colunas Financeiras")
                                        ->addActionLabel("Adicionar Regra")
                                        ->schema([
                                            Select::make("csv_columns")->label("Colunas no CSV")->options($headers)->multiple()->required(),
                                            Select::make("v3_payment_id")->label("Corresponde ao ERP (V3)")->options($v3Payments)->searchable()->required(),
                                        ])
                                        ->columns(2)
                                        ->columnSpanFull(),
                                ]),
                        ];
                    }),

                // 🚀 PASSO 3: SIMULAÇÃO COM LOG DE AUDITORIA (DRY RUN)
                Step::make("3. Simulação (Dry Run)")
                    ->description("Analise as discrepâncias e os matches encontrados antes de gravar.")
                    ->schema(function (Get $get) {
                        $fileState = $get("csv_file");
                        if (empty($fileState) || $get("col_number") === null) return [Placeholder::make("w")->hiddenLabel()->content("Preencha o mapeamento para ver o resumo.")];

                        $disk = config('livewire.temporary_file_upload.disk') ?: 'local';
                        $files = is_array($fileState) ? array_values($fileState) : [$fileState];

                        $orders = [];
                        $logEntries = [];
                        $tag = $get("import_tag");
                        $logEntries[] = "=== RELATÓRIO DE AUDITORIA DO LOTE CSV: {$tag} ===";
                        $logEntries[] = "Gerado em: " . now()->format('d/m/Y H:i:s');
                        $logEntries[] = "Arquivos em processamento: " . count($files);
                        $logEntries[] = str_repeat("-", 50);

                        $parseMoney = fn($v) => (float) str_replace(",", ".", preg_replace("/[^0-9,-]/", "", str_replace("\t", ",", (string) $v)));

                        foreach ($files as $file) {
                            $fullPath = null;
                            if ($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                                $fullPath = $file->getRealPath();
                            } elseif (is_string($file) && !empty($file)) {
                                $fullPath = Storage::disk($disk)->path($file);
                            }

                            if (!$fullPath || !file_exists($fullPath)) continue;

                            $handle = fopen($fullPath, "r");
                            $firstLine = fgets($handle);
                            $firstLine = preg_replace('/^[\xef\xbb\xbf]+/', '', $firstLine);
                            $delimiter = strpos($firstLine, ";") !== false ? ";" : ",";
                            rewind($handle);
                            fgetcsv($handle, 0, $delimiter); // Pula cabeçalho

                            $lineNumber = 1;
                            $fileName = basename($fullPath);

                            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                                $lineNumber++;
                                $num = trim($row[$get("col_number")] ?? "");

                                if (empty($num)) {
                                    $logEntries[] = "[AVISO - {$fileName}] Linha {$lineNumber}: Ignorada. Falta o Número do Pedido.";
                                    continue;
                                }

                                if (!isset($orders[$num])) {
                                    $orders[$num] = [
                                        "items_sum" => 0.0,
                                        "final_total" => 0.0,
                                        "date" => trim($row[$get("col_date")] ?? ""),
                                    ];
                                }

                                $qty = $parseMoney($row[$get("col_qty")] ?? "0");
                                $price = $parseMoney($row[$get("col_price")] ?? "0");

                                $orders[$num]["items_sum"] += ($qty * $price);
                                $orders[$num]["final_total"] = $parseMoney($row[$get("col_total_order")] ?? "0");
                            }
                            fclose($handle);
                        }

                        $totalOrders = count($orders);
                        $fretesRecuperados = 0;
                        $valorRecuperado = 0.0;
                        $faturamentoTotal = 0.0;
                        $notasEncontradas = 0;

                        $fretesConhecidos = [6.00, 8.00, 9.00, 11.00, 15.00];

                        foreach ($orders as $num => $data) {
                            $faturamentoTotal += $data["final_total"];
                            $diff = round($data["final_total"] - $data["items_sum"], 2);

                            if ($diff > 0 && in_array($diff, $fretesConhecidos)) {
                                $fretesRecuperados++;
                                $valorRecuperado += $diff;
                                $logEntries[] = "[TAXA DE ENTREGA] Pedido {$num}: Frete oculto de R$ " . number_format($diff, 2, ',', '.') . " identificado.";
                            } elseif ($diff > 0) {
                                $logEntries[] = "[DISCREPÂNCIA] Pedido {$num}: Total R$ " . number_format($data["final_total"], 2, ',', '.') . " vs Soma dos Itens R$ " . number_format($data["items_sum"], 2, ',', '.') . " (Diferença não mapeada de R$ {$diff}).";
                            }

                            // Verifica se existe nota na Staging Area
                            try {
                                $issueDate = Carbon::createFromFormat("d/m/Y - H:i", $data["date"])->format("Y-m-d H:i:s");
                            } catch (\Exception $e) {
                                try {
                                    $issueDate = Carbon::parse(str_replace(' - ', ' ', $data["date"]))->format("Y-m-d H:i:s");
                                } catch (\Exception $e) {
                                    $issueDate = now()->format("Y-m-d H:i:s");
                                }
                            }

                            $matchXml = FiscalDocument::where('status', 'processing')
                                ->whereBetween('issue_date', [
                                    Carbon::parse($issueDate)->subHours(12),
                                    Carbon::parse($issueDate)->addHours(12)
                                ])
                                ->where(function($query) use ($data) {
                                    $query->whereBetween('total_value', [$data["final_total"] - 1.0, $data["final_total"] + 1.0])
                                          ->orWhereBetween('total_value', [$data["items_sum"] - 1.0, $data["items_sum"] + 1.0]);
                                })
                                ->exists();

                            if ($matchXml) {
                                $notasEncontradas++;
                                $logEntries[] = "[MATCH FISCAL] Pedido {$num}: Nota Fiscal (V2) correspondente encontrada.";
                            } else {
                                $logEntries[] = "[NÃO-FISCAL] Pedido {$num}: Será guardado no histórico sem XML correspondente.";
                            }
                        }

                        $fatStr = number_format($faturamentoTotal, 2, ',', '.');
                        $valRecStr = number_format($valorRecuperado, 2, ',', '.');

                        $logContent = implode("\r\n", $logEntries);
                        $base64Log = base64_encode($logContent);

                        return [
                            Placeholder::make("staging_results")
                                ->hiddenLabel()
                                ->content(new HtmlString("
                                    <div class='flex flex-col gap-4 p-6 bg-surface-2 rounded-xl border border-surface-border'>
                                        <h3 class='text-lg font-black text-primary-600 flex items-center gap-2'>
                                            <svg class='w-6 h-6' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'></path></svg>
                                            Auditoria de Lote (Sem gravação)
                                        </h3>

                                        <div class='grid grid-cols-2 md:grid-cols-5 gap-4 mb-2'>
                                            <div class='p-4 bg-surface-1 rounded-lg border border-surface-border text-center'>
                                                <span class='block text-[10px] text-surface-muted uppercase tracking-wider'>Pedidos Únicos</span>
                                                <span class='block text-2xl font-black text-surface-text mt-1'>{$totalOrders}</span>
                                            </div>
                                            <div class='p-4 bg-success-500/10 rounded-lg border border-success-500/20 text-center'>
                                                <span class='block text-[10px] text-success-700 uppercase tracking-wider'>Faturamento Bruto</span>
                                                <span class='block text-xl font-black text-success-700 mt-1'>R$ {$fatStr}</span>
                                            </div>
                                            <div class='p-4 bg-warning-500/10 rounded-lg border border-warning-500/20 text-center'>
                                                <span class='block text-[10px] text-warning-700 uppercase tracking-wider'>Fretes Fantasmas</span>
                                                <span class='block text-2xl font-black text-warning-700 mt-1'>{$fretesRecuperados}</span>
                                            </div>
                                            <div class='p-4 bg-info-500/10 rounded-lg border border-info-500/20 text-center'>
                                                <span class='block text-[10px] text-info-700 uppercase tracking-wider'>R$ Oculto Recuperado</span>
                                                <span class='block text-xl font-black text-info-700 mt-1'>+ R$ {$valRecStr}</span>
                                            </div>
                                            <div class='p-4 bg-primary-500/10 rounded-lg border border-primary-500/20 text-center'>
                                                <span class='block text-[10px] text-primary-700 uppercase tracking-wider'>Notas Casadas</span>
                                                <span class='block text-2xl font-black text-primary-700 mt-1'>{$notasEncontradas}</span>
                                            </div>
                                        </div>

                                        <div class='flex items-center justify-between bg-surface-1 p-4 rounded-lg border border-surface-border'>
                                            <p class='text-sm text-surface-muted m-0'>Se os dados baterem, baixe o log para conferência e clique em <b>Confirmar</b>.</p>

                                            <a href='data:text/plain;base64,{$base64Log}' download='Auditoria_{$tag}.txt' class='inline-flex items-center justify-center px-4 py-2 bg-primary-600 text-white text-xs font-bold uppercase tracking-wider rounded-lg hover:bg-primary-700 transition-colors shadow-sm cursor-pointer whitespace-nowrap shrink-0'>
                                                <svg class='w-4 h-4 mr-2' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4'></path></svg>
                                                Baixar Log de Auditoria
                                            </a>
                                        </div>
                                    </div>
                                "))
                        ];
                    }),
            ])
            ->action(function (array $data) {
                // 🚀 GRAVAÇÃO DA SESSÃO AO SUBMETER
                $fieldsToSave = ['col_number', 'col_date', 'col_type', 'col_customer', 'col_neighborhood', 'col_total_order', 'col_code', 'col_product', 'col_qty', 'col_price'];
                foreach ($fieldsToSave as $f) {
                    if (isset($data[$f])) session()->put("csv_map_{$f}", $data[$f]);
                }
                if (isset($data['payment_mappings'])) {
                    session()->put('csv_map_payments', $data['payment_mappings']);
                }

                set_time_limit(0);
                ini_set("memory_limit", "1024M");
                DB::disableQueryLog();

                $fileState = $data["csv_file"] ?? null;
                if (empty($fileState)) {
                    Notification::make()->title("Ficheiros não recebidos. Tente novamente.")->danger()->send();
                    return;
                }

                $files = is_array($fileState) ? array_values($fileState) : [$fileState];
                $disk = config('livewire.temporary_file_upload.disk') ?: 'local';
                $tag = trim($data["import_tag"]);

                $orders = [];
                $parseMoney = fn($v) => (float) str_replace(",", ".", preg_replace("/[^0-9,-]/", "", str_replace("\t", ",", (string) $v)));
                $fretesConhecidos = [6.00, 8.00, 9.00, 11.00, 15.00];
                $filesToDelete = [];

                foreach ($files as $file) {
                    $fullPath = null;
                    $relativePath = null;

                    if ($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                        $fullPath = $file->getRealPath();
                    } elseif (is_string($file) && !empty($file)) {
                        $fullPath = Storage::disk($disk)->path($file);
                        $relativePath = $file;
                    }

                    if (!$fullPath || !file_exists($fullPath)) continue;
                    if ($relativePath) $filesToDelete[] = $relativePath;

                    $handle = fopen($fullPath, "r");
                    $firstLine = fgets($handle);
                    $firstLine = preg_replace('/^[\xef\xbb\xbf]+/', '', $firstLine);
                    $delimiter = strpos($firstLine, ";") !== false ? ";" : ",";
                    rewind($handle);
                    fgetcsv($handle, 0, $delimiter);

                    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                        $num = trim($row[$data["col_number"]] ?? "");
                        if (empty($num)) continue;

                        if (!isset($orders[$num])) {
                            $orders[$num] = [
                                "number" => $num,
                                "customer" => isset($data["col_customer"]) && isset($row[$data["col_customer"]]) ? trim($row[$data["col_customer"]]) : "",
                                "neighborhood" => isset($data["col_neighborhood"]) && isset($row[$data["col_neighborhood"]]) ? trim($row[$data["col_neighborhood"]]) : "",
                                "date" => isset($data["col_date"]) && isset($row[$data["col_date"]]) ? trim($row[$data["col_date"]]) : "",
                                "type" => isset($data["col_type"]) && isset($row[$data["col_type"]]) ? trim($row[$data["col_type"]]) : "",
                                "items_sum" => 0.0,
                                "final_total" => 0.0,
                                "hidden_fee" => 0.0,
                                "items" => [],
                                "payments" => [],
                            ];

                            if (!empty($data["payment_mappings"])) {
                                foreach ($data["payment_mappings"] as $mapping) {
                                    $v3Id = $mapping["v3_payment_id"];
                                    $csvCols = $mapping["csv_columns"] ?? [];

                                    foreach ($csvCols as $colIndex) {
                                        $val = $parseMoney($row[$colIndex] ?? 0);
                                        if ($val > 0) {
                                            $orders[$num]["payments"][$v3Id] = ($orders[$num]["payments"][$v3Id] ?? 0) + $val;
                                        }
                                    }
                                }
                            }
                        }

                        $qty = $parseMoney($row[$data["col_qty"]] ?? "0");
                        $unitPrice = $parseMoney($row[$data["col_price"]] ?? "0");
                        $lineTotal = $qty * $unitPrice;

                        $orders[$num]["items_sum"] += $lineTotal;
                        $orders[$num]["final_total"] = $parseMoney($row[$data["col_total_order"]] ?? "0");

                        $orders[$num]["items"][] = [
                            "code" => isset($data["col_code"]) && isset($row[$data["col_code"]]) ? trim($row[$data["col_code"]]) : "",
                            "name" => isset($data["col_product"]) && isset($row[$data["col_product"]]) ? trim($row[$data["col_product"]]) : "",
                            "qty" => $qty,
                            "unit_price" => $unitPrice,
                            "total_price" => $lineTotal,
                        ];
                    }
                    fclose($handle);
                }

                $totalImported = 0;
                $fretesCriados = 0;
                $notasCasadas = 0;
                $v3PaymentsMap = \App\Models\PaymentMethod::pluck("name", "id")->toArray();

                foreach (array_chunk($orders, 250, true) as $chunk) {
                    DB::beginTransaction();
                    try {
                        foreach ($chunk as $num => $d) {

                            $diff = round($d["final_total"] - $d["items_sum"], 2);
                            if ($diff > 0 && in_array($diff, $fretesConhecidos)) {
                                $d["hidden_fee"] = $diff;
                                $d["type"] = "Delivery";
                            }

                            $cleanCust = trim(preg_replace("/^\s*\d+\s*-\s*/", "", $d["customer"]));
                            $lCust = !empty($cleanCust) ? LegacyCustomer::firstOrCreate(["legacy_name" => $cleanCust]) : null;
                            $lNeigh = !empty($d["neighborhood"]) ? LegacyNeighborhood::firstOrCreate(["legacy_name" => $d["neighborhood"]]) : null;

                            $orderType = match (mb_strtolower($d["type"])) {
                                "mesa", "table" => "table",
                                "delivery", "entrega" => "delivery",
                                default => "counter",
                            };

                            try {
                                $issueDate = Carbon::createFromFormat("d/m/Y - H:i", $d["date"])->format("Y-m-d H:i:s");
                            } catch (\Exception $e) {
                                try {
                                    $issueDate = Carbon::parse(str_replace(' - ', ' ', $d["date"]))->format("Y-m-d H:i:s");
                                } catch (\Exception $e) {
                                    $issueDate = now()->format("Y-m-d H:i:s");
                                }
                            }

                            $matchXml = FiscalDocument::where('status', 'processing')
                                ->whereBetween('issue_date', [
                                    Carbon::parse($issueDate)->subHours(12),
                                    Carbon::parse($issueDate)->addHours(12)
                                ])
                                ->where(function($query) use ($d) {
                                    $query->whereBetween('total_value', [$d["final_total"] - 1.0, $d["final_total"] + 1.0])
                                          ->orWhereBetween('total_value', [$d["items_sum"] - 1.0, $d["items_sum"] + 1.0]);
                                })
                                ->first();

                            $orderModel = LegacyOrder::updateOrCreate(
                                ["number" => $num, "import_tag" => $tag],
                                [
                                    "number" => $matchXml ? $matchXml->number : $num,
                                    "issue_date" => $issueDate,
                                    "order_type" => $orderType,
                                    "status" => "processed",
                                    "legacy_customer_id" => $lCust?->id,
                                    "legacy_neighborhood_id" => $lNeigh?->id,
                                    "total_value" => $d["final_total"],
                                    "delivery_fee" => $d["hidden_fee"],
                                    "discount" => 0,
                                    "nfe_key" => $matchXml ? $matchXml->access_key : null,
                                    "series" => $matchXml ? $matchXml->series : null,
                                    "xml_content" => $matchXml ? $matchXml->xml_content : null,
                                ]
                            );

                            if ($matchXml) {
                                // 🚀 CORREÇÃO AQUI: Removemos o order_id para evitar o erro de chave estrangeira
                                $matchXml->update(['status' => 'authorized']);
                                $notasCasadas++;
                            }

                            DB::table("legacy_order_items")->where("legacy_order_id", $orderModel->id)->delete();
                            DB::table("legacy_order_payments")->where("legacy_order_id", $orderModel->id)->delete();

                            foreach ($d["items"] as $item) {
                                $name = $item["name"];
                                if (empty($name)) continue;

                                $code = !empty($item["code"]) ? $item["code"] : mb_strtoupper($name);
                                $lProd = LegacyProduct::firstOrCreate(["name" => $name], ["legacy_code" => $code]);

                                if ($lProd->is_ignored) continue;

                                LegacyOrderItem::create([
                                    "legacy_order_id" => $orderModel->id,
                                    "legacy_product_id" => $lProd->id,
                                    "quantity" => $item["qty"],
                                    "unit_price" => $item["unit_price"],
                                    "total_price" => $item["total_price"],
                                ]);
                            }

                            foreach ($d["payments"] as $v3Id => $amt) {
                                $pName = $v3PaymentsMap[$v3Id] ?? "Forma " . $v3Id;
                                $lPay = LegacyPaymentMethod::firstOrCreate(
                                    ["name" => $pName],
                                    ["current_payment_method_id" => $v3Id]
                                );

                                LegacyOrderPayment::create([
                                    "legacy_order_id" => $orderModel->id,
                                    "legacy_payment_method_id" => $lPay->id,
                                    "amount" => $amt,
                                ]);
                            }

                            if ($d["hidden_fee"] > 0) {
                                $fretesCriados++;
                            }

                            $totalImported++;
                        }
                        DB::commit();
                    } catch (\Exception $e) {
                        DB::rollBack();
                        foreach($filesToDelete as $pathToDelete) {
                             Storage::disk($disk)->delete($pathToDelete);
                        }
                        throw $e;
                    }
                }

                foreach($filesToDelete as $pathToDelete) {
                     Storage::disk($disk)->delete($pathToDelete);
                }

                LegacyImportLog::create([
                    "import_tag" => $tag,
                    "type" => "v1_detailed_with_freight_match",
                    "items_count" => $totalImported,
                ]);

                Notification::make()
                    ->title("Importação V1 em Lote Concluída!")
                    ->body("<b>{$totalImported}</b> pedidos processados.<br><b>{$notasCasadas}</b> notas fiscais vinculadas.<br><b>{$fretesCriados}</b> taxas recuperadas.")
                    ->success()
                    ->send();
            });
    }
}
