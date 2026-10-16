<?php

namespace App\Http\Controllers\Tenant\Fiscal;

use App\Http\Controllers\Controller;
use App\Models\LegacyOrder;
use App\Services\Fiscal\SpedDocumentService;

class FiscalDocumentController extends Controller
{
    /**
     * Faz o download do PDF (DANFE) de um pedido legado
     */
    public function downloadLegacyDanfe($id, SpedDocumentService $spedService)
    {
        $order = LegacyOrder::findOrFail($id);

        if (empty($order->xml_content)) {
            abort(404, "XML não encontrado para este pedido.");
        }

        try {
            // Delega a geração pesada do PDF para o Service
            $pdfContent = $spedService->renderDanfePdf($order->xml_content);

            // Força o navegador a fazer o download
            return response()->streamDownload(
                function () use ($pdfContent) {
                    echo $pdfContent;
                },
                "DANFE_{$order->nfe_key}.pdf",
                [
                    "Content-Type" => "application/pdf",
                ],
            );
        } catch (\Exception $e) {
            abort(500, "Erro ao gerar o PDF da DANFE: " . $e->getMessage());
        }
    }
}
