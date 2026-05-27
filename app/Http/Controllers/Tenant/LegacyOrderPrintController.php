<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\LegacyOrder;
use App\Services\Fiscal\SpedDocumentService;

class LegacyOrderPrintController extends Controller
{
    public function print($id, SpedDocumentService $spedService)
    {
        $order = LegacyOrder::findOrFail($id);

        if (empty($order->xml_content)) {
            return back()->with('error', 'O conteúdo do XML não foi encontrado para este pedido legado.');
        }

        try {
            $pdf = $spedService->renderPdfFromXml($order->xml_content);

            return response($pdf)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="DANFE_' . $order->nfe_key . '.pdf"');

        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao gerar PDF: ' . $e->getMessage());
        }
    }
}
