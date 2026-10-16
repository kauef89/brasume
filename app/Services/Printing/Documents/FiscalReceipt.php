<?php

namespace App\Services\Printing\Documents;

use App\Models\Order;
use App\Models\CompanySetting;
use App\Services\Printing\BlueprintBuilder;
use App\Services\Printing\ReceiptFormatter;
use App\Services\Printing\OrderTableBuilder;
use Illuminate\Support\Str;
use Exception;

class FiscalReceipt
{
    public Order $order;
    public string $xmlContent;

    public function __construct(Order $order, string $xmlContent)
    {
        $this->order = $order;
        $this->xmlContent = $xmlContent;
    }

    public function getJobType(): string
    {
        return 'fiscal_receipt';
    }

    public function getOrderId(): ?int
    {
        return $this->order?->id;
    }

    private function cleanText(?string $text): string
    {
        if (!$text) return '';
        return trim(Str::ascii(mb_strtoupper($text)));
    }

    public function build(): array
    {
        $b = new BlueprintBuilder();

        try {
            $cleanXml = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $this->xmlContent);
            $xml = simplexml_load_string($cleanXml);

            if (!$xml) throw new Exception("XML INVALIDO PARA IMPRESSAO.");

            $nfeNode = $xml->getName() === 'nfeProc' ? $xml->NFe : $xml;
            $infNFe = $nfeNode->infNFe;
            $protNFe = $xml->getName() === 'nfeProc' ? $xml->protNFe : null;

            if (!$infNFe) throw new Exception("TAG <INFNFE> NAO ENCONTRADA NO XML.");

            $emit = $infNFe->emit;
            $dest = $infNFe->dest ?? null;
            $ide = $infNFe->ide;
            $total = $infNFe->total->ICMSTot;
            $pag = $infNFe->pag;

            $settings = CompanySetting::getCached();
            $tenant = function_exists('tenant') ? tenant() : null;
            $domain = $tenant?->domains?->first()?->domain
                ?? ($tenant ? $tenant->id . '.brasume.com.br' : request()->getHost());

            $logoUrl = !empty($settings?->receipt_logo_url)
                ? $settings->receipt_logo_url
                : (!empty($settings?->logo_path) ? "https://{$domain}/media/file?path=" . $settings->logo_path : null);

            $b->alignCenter();
            if ($logoUrl) {
                $b->image($logoUrl)->space();
            }

            $b->blueprint[] = ['cmd' => 'font', 'val' => 'B'];

            $b->alignCenter()
              ->text($this->cleanText((string) $emit->xNome), true)
              ->text($this->cleanText("CNPJ: " . $this->formatCnpjCpf((string) $emit->CNPJ) . "  IE: " . (string) $emit->IE))
              ->text($this->cleanText((string) $emit->enderEmit->xLgr . ", " . (string) $emit->enderEmit->nro . " - " . (string) $emit->enderEmit->xBairro))
              ->text($this->cleanText((string) $emit->enderEmit->xMun . " - " . (string) $emit->enderEmit->UF))
              ->text(ReceiptFormatter::divider('B'));

            $b->text("DOCUMENTO AUXILIAR DA NOTA FISCAL", true)
              ->text("DE CONSUMIDOR ELETRONICA", true)
              ->text("NAO PERMITE APROVEITAMENTO DE CREDITO DE ICMS")
              ->text(ReceiptFormatter::divider('B'));

            $b->alignLeft();
            OrderTableBuilder::buildFiscal($b, $infNFe->det, 'B');

            $qtdItens = (string) count($infNFe->det);
            $valTotal = number_format((float) $total->vNF, 2, ',', '.');

            $b->text(ReceiptFormatter::columns(['QTD. TOTAL DE ITENS:', $qtdItens], [40, 0], ['L', 'R'], 'B'))
              ->text(ReceiptFormatter::columns(['VALOR TOTAL R$:', $valTotal], [40, 0], ['L', 'R'], 'B'), true)
              ->text(ReceiptFormatter::divider('B'))
              ->text(ReceiptFormatter::columns(['FORMA DE PAGAMENTO', 'VALOR PAGO R$'], [40, 0], ['L', 'R'], 'B'), true);

            foreach ($pag->detPag as $detPag) {
                $tPagMap = [
                    '01' => 'DINHEIRO',
                    '03' => 'CARTAO CREDITO',
                    '04' => 'CARTAO DEBITO',
                    '17' => 'PIX',
                    '99' => 'OUTROS'
                ];
                $tPag = (string) $detPag->tPag;
                $forma = $tPagMap[$tPag] ?? 'OUTROS';
                $vPag = number_format((float) $detPag->vPag, 2, ',', '.');
                $b->text(ReceiptFormatter::columns([$forma, $vPag], [40, 0], ['L', 'R'], 'B'));
            }

            if (isset($pag->vTroco) && (float) $pag->vTroco > 0) {
                $vTroco = number_format((float) $pag->vTroco, 2, ',', '.');
                $b->text(ReceiptFormatter::columns(['TROCO R$:', $vTroco], [40, 0], ['L', 'R'], 'B'));
            }

            $urlChave = isset($nfeNode->infNFeSupl->urlChave)
                ? (string) $nfeNode->infNFeSupl->urlChave
                : "CONSULTE NO SITE DA SEFAZ";

            $b->alignCenter()
              ->text(ReceiptFormatter::divider('B'))
              ->text("CONSULTE PELA CHAVE DE ACESSO EM:", true)
              ->text($this->cleanText($urlChave))
              ->space()
              ->text("CHAVE DE ACESSO:", true)
              ->text($this->formatChave((string) $infNFe->attributes()->Id), true)
              ->text(ReceiptFormatter::divider('B'));

            $hasCpfCnpj = false;
            if ($dest) {
                if (isset($dest->CPF) || isset($dest->CNPJ)) $hasCpfCnpj = true;
            }

            if ($hasCpfCnpj) {
                $doc = isset($dest->CPF) ? $this->formatCnpjCpf((string) $dest->CPF) : $this->formatCnpjCpf((string) $dest->CNPJ);
                $nome = isset($dest->xNome) ? (string) $dest->xNome : 'CONSUMIDOR';
                $b->text($this->cleanText("CONSUMIDOR: {$doc} - {$nome}"));
            } else {
                $b->text("CONSUMIDOR NAO IDENTIFICADO");
            }

            if (isset($nfeNode->infNFeSupl->qrCode)) {
                $b->space()->qrCode((string) $nfeNode->infNFeSupl->qrCode, 4)->space();
            }

            $b->text(ReceiptFormatter::divider('B'))
              ->text($this->cleanText("NFC-E N: " . (string) $ide->nNF . "  SERIE: " . (string) $ide->serie . "  EMISSAO: " . date('d/m/Y H:i:s', strtotime((string) $ide->dhEmi))));

            if ($protNFe) {
                $b->text($this->cleanText("PROTOCOLO DE AUTORIZACAO: " . (string) ($protNFe->infProt->nProt ?? '')))
                  ->text($this->cleanText("AUTORIZACAO: " . date('d/m/Y H:i:s', strtotime((string) ($protNFe->infProt->dhRecbto ?? '')))));
            }

            if (isset($total->vTotTrib) && (float) $total->vTotTrib > 0) {
                $b->text(ReceiptFormatter::divider('B'))
                  ->text("Tributos Totais Incidentes (Lei 12.741/2012): R$ " . number_format((float) $total->vTotTrib, 2, ',', '.'));
            }

            if ($settings && !empty($settings->receipt_footer_text)) {
                $b->text(ReceiptFormatter::divider('B'))
                  ->text($this->cleanText($settings->receipt_footer_text));
            }

            $b->blueprint[] = ['cmd' => 'font', 'val' => 'A'];

        } catch (\Throwable $t) {
            $b->text("ERRO AO LER O XML DA NFC-E", true)->text($this->cleanText($t->getMessage()));
        }

        return $b->cut()->get();
    }

    private function formatCnpjCpf(string $doc): string
    {
        $doc = preg_replace('/[^0-9]/', '', $doc);
        if (strlen($doc) === 11) return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $doc);
        if (strlen($doc) === 14) return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $doc);
        return $doc;
    }

    private function formatChave(string $chave): string
    {
        $chave = preg_replace('/[^0-9]/', '', $chave);
        return trim(chunk_split($chave, 4, ' '));
    }
}
