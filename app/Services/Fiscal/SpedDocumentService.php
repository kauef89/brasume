<?php

namespace App\Services\Fiscal;

use NFePHP\DA\NFe\Danfe;
use Exception;

class SpedDocumentService
{
    /**
     * Recebe o conteúdo XML e retorna a string binária do PDF da DANFE gerado.
     */
    public function renderDanfePdf(string $xmlContent): string
    {
        if (empty($xmlContent)) {
            throw new Exception("XML não fornecido para geração do PDF.");
        }

        // Inicializa a classe DANFE do NFePHP com a string do XML
        $danfe = new Danfe($xmlContent);
        $danfe->debugMode(false);
        $danfe->creditsIntegratorFooter("Gerado por Brasume ERP");

        // Retorna o PDF gerado na memória
        return $danfe->render();
    }
}
