<?php

namespace App\Filament\Pages\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use ZipArchive;

class ImportV2XmlAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return "import_v2_xml";
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label("Importar Documentos (XML / ZIP)")
            ->icon("heroicon-o-document-text")
            ->color("success")
            ->modalHeading("Importação de Documentos Fiscais (V2)")
            ->modalDescription("Fase 1 (ELT): O sistema irá extrair os dados dos arquivos para popular os dicionários e estacionar as notas na área de processamento (Staging).")
            ->form([
                Section::make("Configurações")->schema([
                    TextInput::make("import_tag")
                        ->label("Identificador / Tag")
                        ->default("XML_" . now()->format("Ymd_His"))
                        ->required(),
                ]),
                Section::make("Arquivos")->schema([
                    FileUpload::make("fiscal_files")
                        ->label("Arquivos (XML ou ZIP)")
                        ->multiple()
                        ->disk(config('livewire.temporary_file_upload.disk') ?: 'local')
                        ->maxSize(131072) // 128MB
                        ->required(),
                ]),
            ])
            ->action(function (array $data) {
                set_time_limit(0);
                ini_set("memory_limit", "1024M");
                DB::disableQueryLog();

                $service = app(\App\Services\Fiscal\LegacyFiscalImportService::class);
                $importedCount = 0;
                $duplicateCount = 0;
                $errorCount = 0;
                $errorReasons = [];

                $disk = config('livewire.temporary_file_upload.disk') ?: 'local';

                $processFile = function($content, $tag) use ($service, &$importedCount, &$duplicateCount, &$errorCount, &$errorReasons) {
                    try {
                        $status = $service->processV2XmlContent($content, $tag);
                        if ($status === "imported") $importedCount++;
                        elseif ($status === "duplicate") $duplicateCount++;
                    } catch (\Exception $e) {
                        $errorCount++;
                        $msg = $e->getMessage();
                        $errorReasons[$msg] = ($errorReasons[$msg] ?? 0) + 1;
                    }
                };

                foreach ($data["fiscal_files"] as $filePath) {
                    $fullPath = Storage::disk($disk)->path($filePath);

                    if (strtolower(pathinfo($fullPath, PATHINFO_EXTENSION)) === "zip") {
                        $zip = new \ZipArchive();
                        if ($zip->open($fullPath) === true) {
                            for ($i = 0; $i < $zip->numFiles; $i++) {
                                $fileName = $zip->getNameIndex($i);

                                // 🚀 A VACINA ANTI-FANTASMAS (Ignora metadados do Mac/Linux)
                                if (str_contains($fileName, '__MACOSX') || str_starts_with(basename($fileName), '.')) {
                                    continue;
                                }

                                if (strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) === "xml") {
                                    $processFile($zip->getFromIndex($i), $data["import_tag"]);
                                }
                            }
                            $zip->close();
                        }
                    } else {
                        $processFile(Storage::disk($disk)->get($filePath), $data["import_tag"]);
                    }

                    Storage::disk($disk)->delete($filePath);
                }

                $errorDetails = "";
                if (!empty($errorReasons)) {
                    $errorDetails = "<br><br><div style='padding:10px; background:rgba(255,0,0,0.1); border-radius:8px;'><b>Detalhamento das Rejeições:</b><br>";
                    arsort($errorReasons);
                    foreach ($errorReasons as $reason => $count) {
                        $errorDetails .= "<li><b>{$count}x</b> : {$reason}</li>";
                    }
                    $errorDetails .= "</div>";
                }

                if ($importedCount > 0 || $duplicateCount > 0 || $errorCount > 0) {
                    if ($importedCount > 0) {
                        \App\Models\LegacyImportLog::create(["import_tag" => $data["import_tag"], "type" => "v2_fiscal", "items_count" => $importedCount]);
                    }

                    Notification::make()
                        ->title("Auditoria de Importação V2")
                        ->body(new HtmlString("
                            <b class='text-success-600'>{$importedCount}</b> notas importadas para a Staging Area.<br>
                            <b class='text-warning-600'>{$duplicateCount}</b> notas ignoradas (já existem no banco).<br>
                            <b class='text-danger-600'>{$errorCount}</b> arquivos ignorados ou inválidos.
                            {$errorDetails}
                        "))
                        ->success()
                        ->persistent()
                        ->send();
                }
            });
    }
}
