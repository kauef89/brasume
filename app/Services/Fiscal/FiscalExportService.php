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
    public function generateXmlZip(string $startDate, string $endDate): string
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
                $zip->addFromString($filename, $doc->xml_content);
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
}
