<?php

namespace App\Services\Fiscal;

use App\Models\FiscalDocument;
use App\Models\LegacyProduct;
use App\Models\LegacyPaymentMethod;
use App\Models\LegacyCustomer;
use App\Models\LegacyOrder;
use App\Models\LegacyOrderItem;
use App\Models\LegacyOrderPayment;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LegacyFiscalImportService
{
    public function processV2XmlContent(string $xmlContent, string $tag): string
    {
        // 1. Remove os Null Bytes (\x00) e o BOM (Byte Order Mark) que corrompem o parser
        $xmlContent = str_replace("\x00", "", $xmlContent);
        $xmlContent = preg_replace('/^[\xef\xbb\xbf]+/', '', $xmlContent);

        // 🚀 2. A BUSCA CIRÚRGICA (Sem estourar a memória do PHP)
        // Usa o PREG_OFFSET_CAPTURE apenas para encontrar a POSIÇÃO exata onde o XML real começa.
        if (preg_match('/(<\?xml|<nfeProc|<NFe)/i', $xmlContent, $matches, PREG_OFFSET_CAPTURE)) {
            // Corta o arquivo a partir dessa posição até ao fim (ignora 100% do lixo binário anterior)
            $xmlContent = substr($xmlContent, $matches[0][1]);
        } else {
            throw new \Exception("Nenhuma estrutura XML válida encontrada no arquivo.");
        }

        // 3. Remove os namespaces e prefixos que atrapalham o SimpleXML do PHP
        $cleanXml = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $xmlContent);
        $cleanXml = preg_replace('/<[a-zA-Z0-9]+:([a-zA-Z0-9]+)/i', '<$1', $cleanXml);
        $cleanXml = preg_replace('/<\/[a-zA-Z0-9]+:([a-zA-Z0-9]+)>/i', '</$1>', $cleanXml);

        libxml_use_internal_errors(true);
        $xml = @simplexml_load_string($cleanXml);

        if (!$xml) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            $firstError = $errors[0]->message ?? 'Erro de formatação estrutural';
            throw new \Exception("XML Malformado ({$firstError})");
        }

        $rootName = $xml->getName();

        // 🚀 DIAGNÓSTICO: Identificar Eventos (Cancelamentos, Cartas de Correção)
        if (in_array($rootName, ['procEventoNFe', 'evento'])) {
            $desc = $xml->evento->infEvento->detEvento->descEvento ?? 'Evento Fiscal Indefinido';
            throw new \Exception("Ignorado: XML de {$desc}");
        }

        // 🚀 DIAGNÓSTICO: Identificar Inutilização
        if (in_array($rootName, ['procInutNFe', 'inutNFe'])) {
            throw new \Exception("Ignorado: XML de Inutilização de Numeração");
        }

        // 🚀 DIAGNÓSTICO: Identificar SAT (Caso usem CFe em SP/CE)
        if ($rootName === 'CFe') {
            throw new \Exception("Ignorado: Cupom SAT (CFe) não suportado neste parser");
        }

        $infNFe = $xml->NFe->infNFe ?? $xml->infNFe ?? null;
        if (!$infNFe) {
            throw new \Exception("Tag <infNFe> ausente. Raiz encontrada: <{$rootName}>");
        }

        $fullKey = (string) $infNFe->attributes()->Id;
        $accessKey = preg_replace("/[^0-9]/", "", $fullKey);

        if (strlen($accessKey) !== 44) throw new \Exception("Chave de acesso inválida ({$accessKey})");
        if (FiscalDocument::where("access_key", $accessKey)->exists()) return "duplicate";

        $model = (string) $infNFe->ide->mod;
        $series = (string) $infNFe->ide->serie;
        $number = (string) $infNFe->ide->nNF;
        $issueDate = (string) $infNFe->ide->dhEmi;
        $totalValue = (float) $infNFe->total->ICMSTot->vNF;

        DB::transaction(function () use ($infNFe, $accessKey, $model, $series, $number, $issueDate, $totalValue, $xmlContent, $tag) {
            // 1. Clientes
            if (isset($infNFe->dest)) {
                $nome = (string) ($infNFe->dest->xNome ?? "");
                if (!empty($nome) && !str_contains(strtoupper($nome), "CONSUMIDOR")) {
                    LegacyCustomer::firstOrCreate(["legacy_name" => trim($nome)]);
                }
            }

            // 2. Pagamentos
            if (isset($infNFe->pag->detPag)) {
                $paymentNames = ["01" => "Dinheiro", "02" => "Cheque", "03" => "Cartão de Crédito", "04" => "Cartão de Débito", "05" => "Crédito Loja", "15" => "Boleto", "17" => "PIX", "90" => "Sem Pagamento", "99" => "Outros"];
                foreach ($infNFe->pag->detPag as $detPag) {
                    $tPag = (string) $detPag->tPag;
                    if (!empty($tPag)) {
                        LegacyPaymentMethod::firstOrCreate(
                            ["sefaz_code" => $tPag],
                            ["name" => $paymentNames[$tPag] ?? "Sefaz Código $tPag"]
                        );
                    }
                }
            }

            // 3. Produtos
            if (isset($infNFe->det)) {
                foreach ($infNFe->det as $det) {
                    $prod = $det->prod;
                    $cEAN = (string) $prod->cEAN;
                    LegacyProduct::firstOrCreate(
                        ["name" => trim((string) $prod->xProd)],
                        ["legacy_code" => (string) $prod->cProd, "ean" => $cEAN === "SEM GTIN" || empty($cEAN) ? null : $cEAN]
                    );
                }
            }

            // 4. Staging
            FiscalDocument::create([
                "import_tag" => $tag, "model" => $model ?: '65', "series" => $series,
                "number" => $number, "access_key" => $accessKey, "issue_date" => Carbon::parse($issueDate),
                "total_value" => $totalValue, "status" => "processing", "xml_content" => $xmlContent
            ]);
        });

        return "imported";
    }

    public function mapFiscalDocuments(string $tag): array
    {
        $docs = FiscalDocument::where('import_tag', $tag)->where('status', 'processing')->get();
        if ($docs->isEmpty()) return ['matched' => 0, 'created' => 0];

        $matchedCount = 0; $createdCount = 0;

        DB::transaction(function () use ($docs, &$matchedCount, &$createdCount) {
            foreach ($docs as $doc) {
                $match = LegacyOrder::whereDate('issue_date', $doc->issue_date->toDateString())
                    ->where('total_value', $doc->total_value)->whereNull('nfe_key')->first();

                if ($match) {
                    $match->update(['nfe_key' => $doc->access_key, 'series' => $doc->series, 'number' => $doc->number, 'xml_content' => $doc->xml_content]);
                    $doc->update(['status' => 'authorized']);
                    $matchedCount++;
                } else {
                    $xml = @simplexml_load_string(str_replace("xmlns=", "ns=", $doc->xml_content));
                    if (!$xml) continue;

                    $infNFe = $xml->NFe->infNFe ?? $xml->infNFe;
                    $newOrder = LegacyOrder::create([
                        'import_tag' => $doc->import_tag, 'nfe_key' => $doc->access_key, 'series' => $doc->series,
                        'number' => $doc->number, 'issue_date' => $doc->issue_date, 'total_value' => $doc->total_value,
                        'discount' => (float) ($infNFe->total->ICMSTot->vDesc ?? 0), 'status' => 'completed', 'xml_content' => $doc->xml_content,
                    ]);

                    if (isset($infNFe->pag->detPag)) {
                        foreach ($infNFe->pag->detPag as $detPag) {
                            $legacyPayment = LegacyPaymentMethod::where('sefaz_code', (string) $detPag->tPag)->first();
                            if ($legacyPayment) {
                                LegacyOrderPayment::create(['legacy_order_id' => $newOrder->id, 'legacy_payment_method_id' => $legacyPayment->id, 'amount' => (float) $detPag->vPag]);
                            }
                        }
                    }

                    if (isset($infNFe->det)) {
                        foreach ($infNFe->det as $det) {
                            $legacyProd = LegacyProduct::where('name', trim((string) $det->prod->xProd))->first();
                            if ($legacyProd && !$legacyProd->is_ignored) {
                                LegacyOrderItem::create(['legacy_order_id' => $newOrder->id, 'legacy_product_id' => $legacyProd->id, 'quantity' => (float) $det->prod->qCom, 'unit_price' => (float) $det->prod->vUnCom, 'total_price' => (float) $det->prod->vProd]);
                            }
                        }
                    }
                    $doc->update(['status' => 'authorized']);
                    $createdCount++;
                }
            }
        });

        return ['matched' => $matchedCount, 'created' => $createdCount];
    }
}
