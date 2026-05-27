<?php

namespace App\Filament\Pages\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ZipArchive;
use Carbon\Carbon;

// Models
use App\Models\FiscalDocument;
use App\Models\LegacyProduct;
use App\Models\LegacyPaymentMethod;
use App\Models\LegacyCustomer;
use App\Models\LegacyImportLog;

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
            ->modalDescription(
                "Fase 1 (ELT): O sistema irá extrair os dados dos arquivos para popular os dicionários de conciliação e estacionar as notas na área de processamento (Staging).",
            )
            ->form([
                Section::make("Configurações")->schema([
                    TextInput::make("import_tag")
                        ->label("Identificador / Tag")
                        ->placeholder("Ex: Notas_2026")
                        ->default("XML_" . now()->format("Ymd_His"))
                        ->required()
                        ->maxLength(255),
                ]),
                Section::make("Arquivos")->schema([
                    FileUpload::make("fiscal_files")
                        ->label("Arquivos (XML ou ZIP)")
                        ->multiple()
                        ->disk("local")
                        ->directory("xml_v2_imports")
                        ->maxSize(131072) // 128MB
                        ->required(),
                ]),
            ])
            ->action(function (array $data) {
                set_time_limit(0);
                ini_set("memory_limit", "1024M");
                DB::disableQueryLog(); // Blindagem de memória

                $files = $data["fiscal_files"] ?? [];
                $tag = $data["import_tag"] ?? null;

                $importedCount = 0;
                $errorCount = 0;
                $duplicateCount = 0;

                foreach ($files as $filePath) {
                    $fullPath = Storage::disk("local")->path($filePath);
                    $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

                    if ($extension === "zip") {
                        $zip = new ZipArchive();
                        if ($zip->open($fullPath) === true) {
                            for ($i = 0; $i < $zip->numFiles; $i++) {
                                $filename = $zip->getNameIndex($i);
                                if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === "xml") {
                                    $xmlContent = $zip->getFromIndex($i);
                                    try {
                                        $status = $this->processXmlContent($xmlContent, $tag);
                                        if ($status === "imported") $importedCount++;
                                        elseif ($status === "duplicate") $duplicateCount++;
                                    } catch (\Exception $e) {
                                        $errorCount++;
                                        Log::error("Erro ZIP XML [{$filename}]: " . $e->getMessage());
                                    }
                                }
                            }
                            $zip->close();
                        } else {
                            $errorCount++;
                            Notification::make()->title("Falha ao abrir ZIP")->danger()->send();
                        }
                    } else {
                        // Arquivo XML solto
                        $xmlContent = Storage::disk("local")->get($filePath);
                        if (!empty($xmlContent)) {
                            try {
                                $status = $this->processXmlContent($xmlContent, $tag);
                                if ($status === "imported") $importedCount++;
                                elseif ($status === "duplicate") $duplicateCount++;
                            } catch (\Exception $e) {
                                $errorCount++;
                                Log::error("Erro XML Avulso: " . $e->getMessage());
                            }
                        }
                    }

                    Storage::disk("local")->delete($filePath);
                }

                if ($importedCount > 0) {
                    LegacyImportLog::create([
                        "import_tag" => $tag,
                        "type" => "v2_fiscal",
                        "items_count" => $importedCount,
                    ]);

                    Notification::make()
                        ->title("{$importedCount} notas preparadas com sucesso!")
                        ->body("Os dicionários foram populados. Proceda para o Mapeamento de Pedidos.")
                        ->success()
                        ->send();
                }

                if ($duplicateCount > 0) {
                    Notification::make()->title("{$duplicateCount} notas ignoradas (já estavam em staging).")->info()->send();
                }
                if ($errorCount > 0) {
                    Notification::make()->title("{$errorCount} arquivos falharam.")->warning()->send();
                }
            });
    }

    /**
     * O motor ELT de processamento do XML
     */
    private function processXmlContent(string $xmlContent, string $tag): string
    {
        $cleanXml = str_replace("xmlns=", "ns=", $xmlContent);
        $xml = @simplexml_load_string($cleanXml);

        if (!$xml) throw new \Exception("XML inválido.");

        $infNFe = isset($xml->NFe->infNFe) ? $xml->NFe->infNFe : $xml->infNFe ?? null;
        if (!$infNFe) throw new \Exception("Tag <infNFe> não encontrada.");

        $fullKey = (string) $infNFe->attributes()->Id;
        $accessKey = preg_replace("/[^0-9]/", "", $fullKey);

        if (strlen($accessKey) !== 44) throw new \Exception("Chave de acesso inválida.");

        // 🚀 VERIFICAÇÃO DE DUPLICIDADE NA ÁREA DE STAGING (Tabela fiscal_documents)
        if (FiscalDocument::where("access_key", $accessKey)->exists()) {
            return "duplicate";
        }

        $model = (string) $infNFe->ide->mod; // 55 (NFe) ou 65 (NFCe)
        $series = (string) $infNFe->ide->serie;
        $number = (string) $infNFe->ide->nNF;
        $issueDate = (string) $infNFe->ide->dhEmi;
        $totalValue = (float) $infNFe->total->ICMSTot->vNF;

        DB::transaction(function () use ($infNFe, $accessKey, $model, $series, $number, $issueDate, $totalValue, $xmlContent, $tag) {

            // 1. POPULAR DICIONÁRIO: Clientes
            if (isset($infNFe->dest)) {
                $nome = (string) ($infNFe->dest->xNome ?? "");
                if (!empty($nome) && !str_contains(strtoupper($nome), "CONSUMIDOR")) {
                    LegacyCustomer::firstOrCreate(["legacy_name" => trim($nome)]);
                }
            }

            // 2. POPULAR DICIONÁRIO: Formas de Pagamento
            if (isset($infNFe->pag->detPag)) {
                $paymentNames = [
                    "01" => "Dinheiro", "02" => "Cheque", "03" => "Cartão de Crédito",
                    "04" => "Cartão de Débito", "05" => "Crédito Loja", "10" => "Vale Alimentação",
                    "11" => "Vale Refeição", "15" => "Boleto Bancário", "17" => "PIX",
                    "18" => "Transferência Bancária", "90" => "Sem Pagamento", "99" => "Outros",
                ];

                foreach ($infNFe->pag->detPag as $detPag) {
                    $tPag = (string) $detPag->tPag;
                    if (!empty($tPag)) {
                        $paymentName = $paymentNames[$tPag] ?? "Sefaz Código $tPag";
                        LegacyPaymentMethod::firstOrCreate(
                            ["sefaz_code" => $tPag],
                            ["name" => $paymentName]
                        );
                    }
                }
            }

            // 3. POPULAR DICIONÁRIO: Produtos
            if (isset($infNFe->det)) {
                foreach ($infNFe->det as $det) {
                    $prod = $det->prod;
                    $cProd = (string) $prod->cProd;
                    $xProd = trim((string) $prod->xProd);
                    $cEAN = (string) $prod->cEAN;
                    $cEAN = $cEAN === "SEM GTIN" || empty($cEAN) ? null : $cEAN;

                    // 🚀 A BLINDAGEM DO DICIONÁRIO V1/V2 APLICADA AQUI (Ancora pelo Nome)
                    LegacyProduct::firstOrCreate(
                        ["name" => $xProd],
                        ["legacy_code" => $cProd, "ean" => $cEAN]
                    );
                }
            }

            // 4. EXTRAÇÃO E LOAD (STAGING)
            // Estaciona o XML bruto na tabela de documentos fiscais para ser cruzado depois
            FiscalDocument::create([
                "import_tag" => $tag,
                "model" => $model ?: '65',
                "series" => $series,
                "number" => $number,
                "access_key" => $accessKey,
                "issue_date" => Carbon::parse($issueDate),
                "total_value" => $totalValue,
                "status" => "processing", // Fica pendente de processamento/match
                "xml_content" => $xmlContent,
                "order_id" => null // Ainda não sabemos a qual pedido pertence
            ]);

        });

        return "imported";
    }
}
