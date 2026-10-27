<?php

namespace App\Services\Fiscal;

use App\Models\FiscalDocument;
use App\Models\CompanySetting;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use ZipArchive;
use Carbon\Carbon;
use Exception;

class FiscalExportService
{
    public function generateXmlZip(string $startDate, string $endDate, bool $distributeFreight = false): string
    {
        $documents = FiscalDocument::whereBetween('issue_date', [$startDate, $endDate])
            ->where('status', 'authorized')
            ->whereNotNull('xml_content')
            ->get();

        if ($documents->isEmpty()) {
            throw new Exception("Nenhum documento fiscal autorizado encontrado no período especificado.");
        }

        $tempDir = storage_path('app/temp_fiscal_exports');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zipFileName = "XMLs_NFCe_{$startDate}_a_{$endDate}.zip";
        $zipFilePath = $tempDir . '/' . $zipFileName;

        $zip = new ZipArchive();
        if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            foreach ($documents as $doc) {
                $filename = "{$doc->access_key}-nfce.xml";
                $xml = $doc->xml_content;
                if ($distributeFreight) {
                    $xml = $this->distributeFreightInXml($xml);
                }
                $zip->addFromString($filename, $xml);
            }
            $zip->close();
        } else {
            throw new Exception("Falha ao criar o arquivo ZIP.");
        }

        return $zipFilePath;
    }

    private function getLogoBase64(?string $logoPath): ?string
    {
        if ($logoPath && Storage::disk('public')->exists($logoPath)) {
            $logoContent = Storage::disk('public')->get($logoPath);
            $logoMime = Storage::disk('public')->mimeType($logoPath);
            return 'data:' . $logoMime . ';base64,' . base64_encode($logoContent);
        }
        return null;
    }

    public function generateGeneralReportPdf(string $startDate, string $endDate): string
    {
        $settings = CompanySetting::first();

        // FILTRO: Apenas autorizadas
        $documents = FiscalDocument::with(['order.payments.paymentMethod'])
            ->whereBetween('issue_date', [$startDate, $endDate])
            ->where('status', 'authorized')
            ->orderBy('issue_date')
            ->get();

        $totalFaturado = $documents->sum('total_value');
        $logoBase64 = $this->getLogoBase64($settings->logo_path);

        $pdf = Pdf::loadView('reports.fiscal_general', [
            'settings' => $settings,
            'documents' => $documents,
            'startDate' => Carbon::parse($startDate)->format('d/m/Y'),
            'endDate' => Carbon::parse($endDate)->format('d/m/Y'),
            'totalFaturado' => $totalFaturado,
            'logoBase64' => $logoBase64,
        ])->setPaper('a4', 'landscape');

        $tempDir = storage_path('app/temp_fiscal_exports');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $filePath = $tempDir . "/Relatorio_Fiscal_Geral_{$startDate}_a_{$endDate}.pdf";
        $pdf->save($filePath);

        return $filePath;
    }

    public function generateMonophasicReportPdf(string $startDate, string $endDate): string
    {
        $settings = CompanySetting::first();

        $monophasicItems = \App\Models\OrderItem::whereHas('order.fiscalDocument', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('issue_date', [$startDate, $endDate])
                  ->where('status', 'authorized');
            })
            ->whereHas('product', function ($q) {
                $q->where('fiscal_ncm', 'like', '2201%')
                  ->orWhere('fiscal_ncm', 'like', '2202%')
                  ->orWhere('fiscal_ncm', 'like', '2203%');
            })
            ->with(['product', 'order.fiscalDocument'])
            ->get()
            ->groupBy('product.name')
            ->map(function ($items, $productName) {
                return [
                    'name' => $productName,
                    'total_qty' => $items->sum('quantity'),
                    'total_value' => $items->sum('total_price'),
                ];
            });

        $totalFaturado = $monophasicItems->sum('total_value');
        $logoBase64 = $this->getLogoBase64($settings->logo_path);

        $pdf = Pdf::loadView('reports.fiscal_monophasic', [
            'settings' => $settings,
            'items' => $monophasicItems,
            'startDate' => Carbon::parse($startDate)->format('d/m/Y'),
            'endDate' => Carbon::parse($endDate)->format('d/m/Y'),
            'totalFaturado' => $totalFaturado,
            'logoBase64' => $logoBase64,
        ]);

        $tempDir = storage_path('app/temp_fiscal_exports');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $filePath = $tempDir . "/Relatorio_Monofasicos_{$startDate}_a_{$endDate}.pdf";
        $pdf->save($filePath);

        return $filePath;
    }

    /**
     * Retrocompatibilidade Contábil: Incorpora o frete proporcionalmente aos produtos
     * e zera a tag <vFrete> no XML exportado, mantendo o total <vNF> idêntico.
     */
    public function distributeFreightInXml(string $xmlContent): string
    {
        try {
            $dom = new \DOMDocument('1.0', 'UTF-8');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;

            if (!@$dom->loadXML($xmlContent)) {
                return $xmlContent;
            }

            $xpath = new \DOMXPath($dom);
            $ns = $dom->documentElement->namespaceURI;
            $prefix = $ns ? 'nfe:' : '';
            if ($ns) {
                $xpath->registerNamespace('nfe', $ns);
            }

            $icmsTot = $xpath->query("//{$prefix}total/{$prefix}ICMSTot")->item(0);
            if (!$icmsTot) {
                return $xmlContent;
            }

            $vFreteNodes = $xpath->query("{$prefix}vFrete", $icmsTot);
            if ($vFreteNodes->length === 0) {
                return $xmlContent;
            }

            $totalFrete = (float) $vFreteNodes->item(0)->nodeValue;
            if ($totalFrete <= 0.001) {
                return $xmlContent;
            }

            $vProdNode = $xpath->query("{$prefix}vProd", $icmsTot)->item(0);
            $totalVProd = $vProdNode ? (float) $vProdNode->nodeValue : 0.0;

            $detNodes = $xpath->query("//{$prefix}infNFe/{$prefix}det");
            if ($detNodes->length === 0) {
                return $xmlContent;
            }

            $somaFreteDistribuido = 0.0;
            $detCount = $detNodes->length;

            for ($i = 0; $i < $detCount; $i++) {
                $det = $detNodes->item($i);
                $prod = $xpath->query("{$prefix}prod", $det)->item(0);
                if (!$prod) continue;

                $itemVProdNode = $xpath->query("{$prefix}vProd", $prod)->item(0);
                $itemVProd = $itemVProdNode ? (float) $itemVProdNode->nodeValue : 0.0;

                $isLast = ($i === $detCount - 1);
                $peso = $totalVProd > 0 ? ($itemVProd / $totalVProd) : (1.0 / $detCount);
                $itemFrete = round($totalFrete * $peso, 2);

                if ($isLast) {
                    $itemFrete = round($totalFrete - $somaFreteDistribuido, 2);
                } else {
                    $somaFreteDistribuido += $itemFrete;
                }

                $novoVProd = round($itemVProd + $itemFrete, 2);

                if ($itemVProdNode) {
                    $itemVProdNode->nodeValue = number_format($novoVProd, 2, '.', '');
                }

                $qComNode = $xpath->query("{$prefix}qCom", $prod)->item(0);
                $qCom = $qComNode ? (float) $qComNode->nodeValue : 1.0;
                if ($qCom <= 0) $qCom = 1.0;

                $vUnComNode = $xpath->query("{$prefix}vUnCom", $prod)->item(0);
                if ($vUnComNode) {
                    $vUnComNode->nodeValue = number_format($novoVProd / $qCom, 4, '.', '');
                }

                $qTribNode = $xpath->query("{$prefix}qTrib", $prod)->item(0);
                $qTrib = $qTribNode ? (float) $qTribNode->nodeValue : $qCom;
                if ($qTrib <= 0) $qTrib = $qCom;

                $vUnTribNode = $xpath->query("{$prefix}vUnTrib", $prod)->item(0);
                if ($vUnTribNode) {
                    $vUnTribNode->nodeValue = number_format($novoVProd / $qTrib, 4, '.', '');
                }

                $itemFreteNodes = $xpath->query("{$prefix}vFrete", $prod);
                foreach ($itemFreteNodes as $fNode) {
                    $prod->removeChild($fNode);
                }
            }

            if ($vProdNode) {
                $vProdNode->nodeValue = number_format($totalVProd + $totalFrete, 2, '.', '');
            }
            $vFreteNodes->item(0)->nodeValue = '0.00';

            return $dom->saveXML();
        } catch (\Throwable $e) {
            return $xmlContent;
        }
    }
}