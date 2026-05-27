<?php

namespace App\Filament\Pages\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Carbon\Carbon;
use App\Models\LegacyDailyRevenue;
use App\Models\LegacyImportLog;

class ImportV0ConsolidatedAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return "importConsolidated";
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->modalHeading("Faturamento Consolidado Diário")
            ->modalWidth("4xl")
            ->steps([
                Step::make("1. Estrutura")
                    ->schema([
                        Toggle::make("has_payments")
                            ->label("Tem colunas de Pagamento?")
                            ->live(),
                        Toggle::make("has_order_count")
                            ->label("Tem coluna de Qtd Pedidos?")
                            ->live(),
                        Toggle::make("has_expenses")
                            ->label("Tem coluna de Despesas?")
                            ->live(),
                    ])
                    ->columns(2),

                Step::make("2. Upload")->schema([
                    TextInput::make("import_tag")
                        ->label("Tag Única do Lote")
                        ->required()
                        ->default("Consolidado_" . now()->format("Ymd_His")),
                    FileUpload::make("csv_file")
                        ->label("Arquivo CSV")
                        ->disk("local")
                        ->directory("csv_v0_imports")
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set) {
                            $headers = [];
                            if ($state) {
                                $file = is_array($state)
                                    ? array_values($state)[0]
                                    : $state;
                                if (
                                    $file instanceof
                                    \Livewire\Features\SupportFileUploads\TemporaryUploadedFile
                                ) {
                                    $handle = fopen($file->getRealPath(), "r");
                                    if ($handle) {
                                        $firstLine = fgets($handle);
                                        if ($firstLine) {
                                            $delimiter =
                                                strpos($firstLine, ";") !==
                                                false
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
                                                };
                                            }
                                        }
                                        fclose($handle);
                                    }
                                }
                            }
                            $set("csv_headers", json_encode($headers));
                        }),
                    Hidden::make("csv_headers"),
                ]),

                Step::make("3. Mapeamento")
                    ->schema(function (Get $get) {
                        $headers = json_decode($get("csv_headers"), true) ?? [];
                        if (empty($headers)) {
                            return [
                                Placeholder::make("w")
                                    ->hiddenLabel()
                                    ->content(
                                        "⚠️ Faça o upload do arquivo no Passo 2.",
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
                                ->label("Coluna de Qtd Pedidos")
                                ->options($headers)
                                ->required();
                        }
                        if ($get("has_expenses")) {
                            $schema[] = Select::make("col_expenses")
                                ->label("Coluna de Despesas")
                                ->options($headers)
                                ->required();
                        }

                        if ($get("has_payments")) {
                            $v3Payments = \App\Models\PaymentMethod::pluck(
                                "name",
                                "id",
                            )->toArray();
                            $schema[] = Repeater::make("payment_mappings")
                                ->label("Mapeamento de Formas de Pagamento")
                                ->addActionLabel(
                                    "Adicionar Coluna de Pagamento",
                                )
                                ->columnSpanFull()
                                ->schema([
                                    Select::make("csv_column")
                                        ->label("Coluna no CSV")
                                        ->options($headers)
                                        ->required(),
                                    Select::make("v3_payment_id")
                                        ->label("Forma no ERP (V3)")
                                        ->options($v3Payments)
                                        ->searchable()
                                        ->required(),
                                ])
                                ->columns(2);
                        }
                        return $schema;
                    })
                    ->columns(2),
            ])
            ->action(function (array $data) {
                $fileState = $data["csv_file"];
                $file = is_array($fileState)
                    ? array_values($fileState)[0]
                    : $fileState;
                $disk = Storage::disk("local");

                if (!$disk->exists($file)) {
                    return;
                }
                $handle = $disk->readStream($file);
                $firstLine = fgets($handle);
                $delimiter = strpos($firstLine, ";") !== false ? ";" : ",";
                rewind($handle);
                $headers = [];
                foreach (fgetcsv($handle, 0, $delimiter) as $idx => $col) {
                    $headers[(string) $idx] = trim($col);
                }

                $parseMoney = fn($v) => (float) str_replace(
                    ",",
                    ".",
                    preg_replace("/[^0-9,-]/", "", $v),
                );
                $count = 0;

                while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                    $rawDate = trim($row[$data["col_date"]] ?? "");
                    if (empty($rawDate)) {
                        continue;
                    }
                    try {
                        $date = Carbon::parse(
                            str_replace("/", "-", $rawDate),
                        )->format("Y-m-d");
                    } catch (\Exception $e) {
                        continue;
                    }

                    $payments = [];
                    if (
                        !empty($data["has_payments"]) &&
                        !empty($data["payment_mappings"])
                    ) {
                        foreach ($data["payment_mappings"] as $mapping) {
                            $val = $parseMoney(
                                $row[$mapping["csv_column"]] ?? 0,
                            );
                            if ($val > 0) {
                                $payments[$mapping["v3_payment_id"]] =
                                    ($payments[$mapping["v3_payment_id"]] ??
                                        0) +
                                    $val;
                            }
                        }
                    }

                    LegacyDailyRevenue::updateOrCreate(
                        ["date" => $date],
                        [
                            "total_revenue" => $parseMoney(
                                $row[$data["col_total"]] ?? 0,
                            ),
                            "order_count" => isset($data["col_order_count"])
                                ? (int) $row[$data["col_order_count"]]
                                : null,
                            "expenses" => isset($data["col_expenses"])
                                ? $parseMoney($row[$data["col_expenses"]])
                                : null,
                            "payments_breakdown" => empty($payments)
                                ? null
                                : $payments,
                            "import_tag" => trim($data["import_tag"]),
                        ],
                    );
                    $count++;
                }
                fclose($handle);
                $disk->delete($file);

                LegacyImportLog::create([
                    "import_tag" => trim($data["import_tag"]),
                    "type" => "v0_consolidated",
                    "items_count" => $count,
                ]);
                Notification::make()
                    ->title("Importação V0 concluída: {$count} dias salvos!")
                    ->success()
                    ->send();
            });
    }
}
