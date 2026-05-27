<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\LegacyOrder;
use NFePHP\DA\NFe\Danfe;
use NFePHP\DA\Legacy\FilesFolders;

class ReportController extends Controller
{
    public function downloadLegacyDanfe($id)
    {
        $order = LegacyOrder::findOrFail($id);

        if (empty($order->xml_content)) {
            abort(404, "XML não encontrado para este pedido.");
        }

        try {
            // Inicializa a classe DANFE do NFePHP com a string do XML
            $danfe = new Danfe($order->xml_content);
            $danfe->debugMode(false);
            $danfe->creditsIntegratorFooter("Gerado por Brasume ERP");

            // Gera o PDF na memória
            $pdfContent = $danfe->render();

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
