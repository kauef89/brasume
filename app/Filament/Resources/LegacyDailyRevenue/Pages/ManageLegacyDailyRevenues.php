<?php

namespace App\Filament\Resources\LegacyDailyRevenueResource\Pages;

use App\Filament\Resources\LegacyDailyRevenueResource\LegacyDailyRevenueResource;
use App\Models\LegacyDailyRevenue;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Get;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Carbon\Carbon;

class ManageLegacyDailyRevenues extends ManageRecords
{
    protected static string $resource = LegacyDailyRevenueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make("import_v0_wizard")
                ->label("Assistente de Importação (CSV)")
                ->icon("heroicon-o-sparkles")
                ->color("primary")
                ->modalWidth("4xl")
                ->steps([
                    // =====================================
                    // PASSO 1: CONFIGURAÇÃO E PERGUNTAS
                    // =====================================
                    Step::make("1. Estrutura")
                        ->description("O que o seu ficheiro contém?")
                        ->schema([
                            Placeholder::make("info")
                                ->hiddenLabel()
                                ->content(
                                    "As colunas de Data e Valor Total são obrigatórias. Indique abaixo o que mais o seu ficheiro contém:",
                                ),
                            Toggle::make("has_payments")
                                ->label(
                                    "Tem colunas de Formas de Pagamento? (Ex: Pix, Dinheiro, Cartão)",
                                )
                                ->default(false)
                                ->live(),
                            Toggle::make("has_types")
                                ->label(
                                    "Tem colunas de Tipos de Venda? (Ex: Salão, Delivery)",
                                )
                                ->default(false)
                                ->live(),
                            Toggle::make("has_order_count")
                                ->label(
                                    "Tem uma coluna com a Quantidade de Pedidos do dia?",
                                )
                                ->default(false)
                                ->live(),
                            Toggle::make("has_expenses")
                                ->label(
                                    "Tem uma coluna de Despesas/Custos do dia?",
                                )
                                ->default(false)
                                ->live(),
                        ])
                        ->columns(2),

                    // =====================================
                    // PASSO 2: UPLOAD DO FICHEIRO
                    // =====================================
                    Step::make("2. Upload")
                        ->description("Carregue o CSV consolidado")
                        ->schema([
                            TextInput::make("import_tag")
                                ->label("Tag de Importação")
                                ->required()
                                ->default(now()->format("Y-m-d_H:i")),

                            FileUpload::make("csv_file")
                                ->label("Ficheiro CSV")
                                ->acceptedFileTypes([
                                    "text/csv",
                                    "text/plain",
                                    "application/csv",
                                ])
                                ->disk("local")
                                ->directory("csv_v0_imports")
                                ->required()
                                ->live(), // 🚀 Crucial: Faz o ficheiro ficar disponível para o Passo 3 instantaneamente
                        ]),

                    // =====================================
                    // PASSO 3: MAPEAMENTO DINÂMICO!
                    // =====================================
                    Step::make("3. Mapeamento")
                        ->description("Cruze os dados do CSV")
                        ->schema(function (Get $get) {
                            $fileState = $get("csv_file");
                            $headers = [];

                            // A Mágica: Vai ler o cabeçalho do ficheiro que acabou de ser feito o upload
                            if ($fileState) {
                                // O FileUpload pode retornar um array ou string dependendo da configuração
                                $path = is_array($fileState)
                                    ? reset($fileState)
                                    : $fileState;
                                $fullPath = Storage::disk("local")->path($path);

                                if (file_exists($fullPath)) {
                                    $handle = fopen($fullPath, "r");
                                    if ($handle) {
                                        $firstLine = fgets($handle);
                                        $delimiter =
                                            strpos($firstLine, ";") !== false
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
                                                as $index => $col
                                            ) {
                                                // O índice do CSV será a chave (value) do Select, o texto será a Label
                                                $headers[
                                                    (string) $index
                                                ] = trim($col);
                                            }
                                        }
                                        fclose($handle);
                                    }
                                }
                            }

                            if (empty($headers)) {
                                return [
                                    Placeholder::make("warning")
                                        ->hiddenLabel()
                                        ->content(
                                            "⚠️ Faça o upload do ficheiro no Passo 2 para ver as opções.",
                                        ),
                                ];
                            }

                            $schema = [
                                Select::make("col_date")
                                    ->label("Coluna de Data")
                                    ->options($headers)
                                    ->required(),
                                Select::make("col_total")
                                    ->label("Coluna de Total Faturado")
                                    ->options($headers)
                                    ->required(),
                            ];

                            if ($get("has_order_count")) {
                                $schema[] = Select::make("col_order_count")
                                    ->label("Coluna de Qtd de Pedidos")
                                    ->options($headers)
                                    ->required();
                            }

                            if ($get("has_expenses")) {
                                $schema[] = Select::make("col_expenses")
                                    ->label("Coluna de Despesas")
                                    ->options($headers)
                                    ->required();
                            }

                            // Usamos CheckboxList para ele poder escolher VÁRIAS colunas que são formas de pagamento!
                            if ($get("has_payments")) {
                                $schema[] = CheckboxList::make("col_payments")
                                    ->label(
                                        "Selecione TODAS as colunas que são Formas de Pagto",
                                    )
                                    ->options($headers)
                                    ->columns(3)
                                    ->columnSpanFull();
                            }

                            if ($get("has_types")) {
                                $schema[] = CheckboxList::make("col_types")
                                    ->label(
                                        "Selecione TODAS as colunas que são Tipos de Venda",
                                    )
                                    ->options($headers)
                                    ->columns(3)
                                    ->columnSpanFull();
                            }

                            return $schema;
                        })
                        ->columns(2),
                ])
                // =====================================
                // AÇÃO DE GRAVAÇÃO (O MOTOR DA V0)
                // =====================================
                ->action(function (array $data) {
                    $path = is_array($data["csv_file"])
                        ? reset($data["csv_file"])
                        : $data["csv_file"];
                    $fullPath = Storage::disk("local")->path($path);

                    $handle = fopen($fullPath, "r");
                    $firstLine = fgets($handle);
                    $delimiter = strpos($firstLine, ";") !== false ? ";" : ",";
                    rewind($handle);

                    // Lemos o cabeçalho para poder cruzar o index numérico do CSV com o nome da coluna no JSON
                    $rawHeaders = fgetcsv($handle, 0, $delimiter);
                    $headers = [];
                    foreach ($rawHeaders as $index => $col) {
                        $headers[(string) $index] = trim($col);
                    }

                    $parseMoney = function ($value) {
                        if (empty($value)) {
                            return 0;
                        }
                        $clean = preg_replace("/[^0-9,-]/", "", $value);
                        return (float) str_replace(",", ".", $clean);
                    };

                    $count = 0;

                    // Lemos as linhas de dados
                    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                        $rawDate = trim($row[$data["col_date"]] ?? "");
                        if (empty($rawDate)) {
                            continue;
                        }

                        try {
                            // Converte inteligentemente datas do Brasil (d/m/Y) ou Standard (Y-m-d)
                            $date = Carbon::parse(
                                str_replace("/", "-", $rawDate),
                            )->format("Y-m-d");
                        } catch (\Exception $e) {
                            continue; // Pula a linha se a data for inválida
                        }

                        $total = $parseMoney($row[$data["col_total"]] ?? 0);

                        $orderCount = isset($data["col_order_count"])
                            ? (int) ($row[$data["col_order_count"]] ?? 0)
                            : null;
                        $expenses = isset($data["col_expenses"])
                            ? $parseMoney($row[$data["col_expenses"]] ?? 0)
                            : null;

                        // Agrupa as Formas de Pagamento Num Array JSON!
                        // O nome da chave será o nome da coluna do CSV
                        $paymentsBreakdown = [];
                        if (!empty($data["col_payments"])) {
                            foreach ($data["col_payments"] as $colIndex) {
                                $val = $parseMoney($row[$colIndex] ?? 0);
                                if ($val > 0) {
                                    $paymentsBreakdown[
                                        $headers[$colIndex]
                                    ] = $val;
                                }
                            }
                        }

                        // Agrupa os Tipos
                        $typesBreakdown = [];
                        if (!empty($data["col_types"])) {
                            foreach ($data["col_types"] as $colIndex) {
                                $val = $parseMoney($row[$colIndex] ?? 0);
                                if ($val > 0) {
                                    $typesBreakdown[$headers[$colIndex]] = $val;
                                }
                            }
                        }

                        // Grava ou Atualiza a Data
                        LegacyDailyRevenue::updateOrCreate(
                            ["date" => $date],
                            [
                                "total_revenue" => $total,
                                "order_count" => $orderCount,
                                "expenses" => $expenses,
                                "payments_breakdown" => empty(
                                    $paymentsBreakdown
                                )
                                    ? null
                                    : $paymentsBreakdown,
                                "types_breakdown" => empty($typesBreakdown)
                                    ? null
                                    : $typesBreakdown,
                                "import_tag" => trim($data["import_tag"]),
                            ],
                        );
                        $count++;
                    }
                    fclose($handle);
                    Storage::disk("local")->delete($path);

                    Notification::make()
                        ->title("Mapeamento Concluído!")
                        ->body(
                            "<b>{$count} dias</b> de faturamento consolidados importados com sucesso.",
                        )
                        ->success()
                        ->send();
                }),

            CreateAction::make()->label("Novo Faturamento"),
        ];
    }
}
