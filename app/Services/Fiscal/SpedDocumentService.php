<?php

namespace App\Services\Fiscal;

use App\Models\Order;
use App\Models\CompanySetting;
use App\Models\FiscalDocument;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use NFePHP\DA\NFe\Danfe;
use NFePHP\NFe\Make;
use NFePHP\NFe\Tools;
use NFePHP\Common\Certificate;
use NFePHP\NFe\Complements;
use NFePHP\NFe\Common\Standardize;
use stdClass;
use Exception;

class SpedDocumentService
{
    public function renderDanfePdf(string $xmlContent): string
    {
        if (empty($xmlContent)) {
            throw new Exception("XML não fornecido para geração do PDF.");
        }

        $danfe = new Danfe($xmlContent);
        $danfe->debugMode(false);
        $danfe->creditsIntegratorFooter("Gerado por Brasume ERP");

        return $danfe->render();
    }

    /**
     * Emite NFC-e para o pedido informado.
     *
     * @param Order $order
     * @param string|null $customerDocument
     * @param bool $shouldPrint
     * @param \App\Models\Printer|null $printer
     * @param bool $force Se true, ignora a flag auto_fiscal_emission da forma de pagamento
     */
    public function emitNfce(
        Order $order,
        ?string $customerDocument = null,
        bool $shouldPrint = false,
        ?\App\Models\Printer $printer = null,
        bool $force = false
    ) {
        $order->loadMissing(['items.product', 'customer', 'payments.paymentMethod']);

        // 🚀 1. BLINDAGEM DE EMISSÃO AUTOMÁTICA / CONTINGÊNCIA
        if (!$force && !$this->shouldEmitAutomatically($order)) {
            $order->update([
                'fiscal_status' => 'skipped',
                'fiscal_error'  => null,
            ]);

            return [
                'success' => true,
                'status'  => 'skipped',
                'message' => 'Emissão fiscal automática dispensada pelas regras das formas de pagamento.',
            ];
        }

        $settings = CompanySetting::first();
        if (!$settings) {
            throw new Exception("Configurações da empresa não encontradas.");
        }

        // 🚀 FILTRO CRÍTICO: Isola apenas os itens válidos
        $activeItems = $order->items->filter(function ($item) {
            return !in_array($item->status, ['cancelled', 'voided', 'refunded']) && (float) $item->quantity > 0;
        })->values();

        if ($activeItems->isEmpty()) {
            throw new Exception("Não é possível emitir NFC-e para um pedido sem itens ativos.");
        }

        // 🚀 REGRA FISCAL: Isola pagamentos de Cashback para convertê-los em Desconto Incondicional
        $cashbackPayments = $order->payments->filter(function ($payment) {
            $engine = $payment->paymentMethod?->engine_type;
            $name = strtolower($payment->method ?? '');
            return $engine === 'internal_loyalty'
                || str_contains($name, 'cashback')
                || str_contains($name, 'pontos');
        });

        $cashbackDiscount = (float) $cashbackPayments->sum('amount');
        $totalDescontoFiscal = round((float) $order->discount + $cashbackDiscount, 2);

        $nfe = new Make();

        $stdInfNFe = new stdClass();
        $stdInfNFe->versao = '4.00';
        $nfe->taginfNFe($stdInfNFe);

        // 🚀 RESOLVE DOCUMENTO ANTES DO <IDE> PARA VALIDAR FALLBACK DO indPres
        $doc = $customerDocument ?: ($order->customer?->cpf ?? null);
        $cleanDoc = preg_replace('/[^0-9]/', '', (string) $doc);
        $hasValidDoc = in_array(strlen($cleanDoc), [11, 14]);

        $stdIde = new stdClass();
        $stdIde->cUF = $this->getUfCode($settings->state);
        $stdIde->cNF = rand(11111111, 99999999);
        $stdIde->natOp = 'VENDA CONSUMIDOR FINAL';
        $stdIde->mod = 65;
        $stdIde->serie = (int) ($settings->nfce_series ?? 2);
        $stdIde->nNF = (int) ($settings->last_nfce_number ?? 0) + 1;
        $stdIde->dhEmi = date('Y-m-d\TH:i:sP');

        $stdIde->tpNF = 1;
        $stdIde->idDest = 1;
        $stdIde->cMunFG = preg_replace('/[^0-9]/', '', (string) ($settings->ibge_code ?? '4202008'));
        $stdIde->tpImp = 4;
        $stdIde->tpEmis = 1;
        $stdIde->tpAmb = (int) ($settings->environment ?? 1);
        $stdIde->finNFe = 1;
        $stdIde->indFinal = 1;

        // 🚀 SEFAZ (Regra 787): Delivery exige <dest>. Se não houver CPF, força indPres = 1
        $stdIde->indPres = ($order->type === 'delivery' && $hasValidDoc) ? 4 : 1;
        $stdIde->indIntermed = 0;
        $stdIde->procEmi = 0;
        $stdIde->verProc = '1.0.0';
        $nfe->tagide($stdIde);

        // Dados do Emitente
        $stdEmit = new stdClass();
        $stdEmit->xNome = (string) $settings->company_name;
        $stdEmit->xFant = (string) ($settings->trade_name ?? $settings->company_name);
        $stdEmit->CNPJ = preg_replace('/[^0-9]/', '', (string) $settings->cnpj);
        $stdEmit->IE = preg_replace('/[^0-9]/', '', (string) $settings->ie);
        $stdEmit->CRT = in_array($settings->tax_regime, ['simples', 'mei']) ? 1 : 3;
        $nfe->tagemit($stdEmit);

        $stdEnderEmit = new stdClass();
        $stdEnderEmit->xLgr = (string) ($settings->street ?? 'Rua Principal');
        $stdEnderEmit->nro = (string) ($settings->number ?? 'S/N');
        $stdEnderEmit->xBairro = (string) ($settings->neighborhood ?? 'Centro');
        $stdEnderEmit->cMun = preg_replace('/[^0-9]/', '', (string) ($settings->ibge_code ?? '4202008'));
        $stdEnderEmit->xMun = (string) ($settings->city ?? 'Balneário Camboriú');
        $stdEnderEmit->UF = (string) ($settings->state ?? 'SC');
        $stdEnderEmit->CEP = preg_replace('/[^0-9]/', '', (string) $settings->zip_code);
        $nfe->tagenderEmit($stdEnderEmit);

        // Destinatário (Somente se CPF/CNPJ existir)
        if ($hasValidDoc) {
            $stdDest = new stdClass();
            if (strlen($cleanDoc) === 14) {
                $stdDest->CNPJ = $cleanDoc;
            } else {
                $stdDest->CPF = $cleanDoc;
            }

            $stdDest->xNome = substr(strtoupper($order->customer_name ?? $order->customer?->name ?? 'CONSUMIDOR FINAL'), 0, 60);
            $stdDest->indIEDest = 9;
            $nfe->tagdest($stdDest);

            if ($order->type === 'delivery' && ($order->address || $order->address_id)) {
                $addr = $order->address;

                $street = is_array($addr) ? ($addr['street'] ?? '') : ($addr->street->name ?? $addr->street ?? '');
                $number = is_array($addr) ? ($addr['number'] ?? 'S/N') : ($addr->number ?? 'S/N');
                $complement = is_array($addr) ? ($addr['complement'] ?? '') : ($addr->complement ?? '');
                $neighborhood = is_array($addr) ? ($addr['neighborhood'] ?? '') : ($addr->neighborhood->name ?? $addr->neighborhood ?? '');
                $zipCode = is_array($addr) ? ($addr['zip_code'] ?? null) : ($addr->zip_code ?? null);

                $stdEnderDest = new stdClass();
                $stdEnderDest->xLgr = substr(strtoupper($street ?: 'RUA NAO INFORMADA'), 0, 60);
                $stdEnderDest->nro = substr(strtoupper($number ?: 'S/N'), 0, 60);
                if (!empty($complement)) {
                    $stdEnderDest->xCpl = substr(strtoupper($complement), 0, 60);
                }

                $stdEnderDest->xBairro = substr(strtoupper($neighborhood ?: 'BAIRRO NAO INFORMADO'), 0, 60);
                $stdEnderDest->cMun = preg_replace('/[^0-9]/', '', (string) ($settings->ibge_code ?? '4202008'));
                $stdEnderDest->xMun = strtoupper($settings->city ?? 'Balneário Camboriú');
                $stdEnderDest->UF = strtoupper($settings->state ?? 'SC');
                $stdEnderDest->CEP = preg_replace('/[^0-9]/', '', (string) ($zipCode ?: ($settings->zip_code ?? '00000000')));

                $nfe->tagenderDest($stdEnderDest);
            }
        }

        $stdRespTec = new stdClass();
        $stdRespTec->CNPJ = env('SOFTWARE_HOUSE_CNPJ', '26908287000146');
        $stdRespTec->xContato = env('SOFTWARE_HOUSE_CONTACT', 'Pastelaria Nativa Ltda');
        $stdRespTec->email = env('SOFTWARE_HOUSE_EMAIL', 'contato@brasume.com.br');
        $stdRespTec->fone = env('SOFTWARE_HOUSE_PHONE', '11985071770');
        $nfe->taginfRespTec($stdRespTec);

        $totalProdutos = (float) $activeItems->sum('total_price');
        $somaFreteDistribuido = 0;
        $somaDescontoDistribuido = 0;
        $somaOutrosDistribuido = 0;
        $totalActiveCount = $activeItems->count();

        foreach ($activeItems as $index => $item) {
            $isLastItem = ($index === $totalActiveCount - 1);
            $peso = $totalProdutos > 0 ? ($item->total_price / $totalProdutos) : 0;

            $freteItem = round((float) $order->delivery_fee * $peso, 2);
            $descontoItem = round($totalDescontoFiscal * $peso, 2);
            $outroItem = round((float) $order->service_fee * $peso, 2);

            if ($isLastItem) {
                $freteItem = round((float) $order->delivery_fee - $somaFreteDistribuido, 2);
                $descontoItem = round($totalDescontoFiscal - $somaDescontoDistribuido, 2);
                $outroItem = round((float) $order->service_fee - $somaOutrosDistribuido, 2);
            } else {
                $somaFreteDistribuido += $freteItem;
                $somaDescontoDistribuido += $descontoItem;
                $somaOutrosDistribuido += $outroItem;
            }

            $stdProd = new stdClass();
            $stdProd->item = $index + 1;
            $stdProd->cProd = $item->product_id ?? 'DIV';
            $stdProd->cEAN = 'SEM GTIN';
            $stdProd->xProd = $item->product_name;
            $stdProd->NCM = preg_replace('/[^0-9]/', '', (string) ($item->product->fiscal_ncm ?? '00000000'));
            $stdProd->CFOP = preg_replace('/[^0-9]/', '', (string) ($item->product->fiscal_cfop ?? '5102'));
            $stdProd->uCom = strtoupper($item->product->unit_of_measure ?? 'UN');
            $stdProd->qCom = $item->quantity;
            $stdProd->vUnCom = $this->format($item->unit_price, 4);
            $stdProd->vProd = $this->format($item->total_price);
            $stdProd->cEANTrib = 'SEM GTIN';
            $stdProd->uTrib = strtoupper($item->product->unit_of_measure ?? 'UN');
            $stdProd->qTrib = $item->quantity;
            $stdProd->vUnTrib = $this->format($item->unit_price, 4);
            $stdProd->indTot = 1;

            if ($freteItem > 0) $stdProd->vFrete = $this->format($freteItem);
            if ($descontoItem > 0) $stdProd->vDesc = $this->format($descontoItem);
            if ($outroItem > 0) $stdProd->vOutro = $this->format($outroItem);

            $nfe->tagprod($stdProd);

            $stdImposto = new stdClass();
            $stdImposto->item = $index + 1;
            $nfe->tagimposto($stdImposto);

            $stdICMS = new stdClass();
            $stdICMS->item = $index + 1;
            $stdICMS->orig = $item->product->fiscal_origin ?? 0;
            $stdICMS->CSOSN = preg_replace('/[^0-9]/', '', (string) ($item->product->fiscal_csosn ?? '102'));
            $nfe->tagICMSSN($stdICMS);

            $stdPIS = new stdClass();
            $stdPIS->item = $index + 1;
            $stdPIS->CST = str_pad((string) ($item->product->fiscal_pis_cst ?? '49'), 2, '0', STR_PAD_LEFT);
            $stdPIS->vBC = 0.00;
            $stdPIS->pPIS = 0.00;
            $stdPIS->vPIS = 0.00;
            $nfe->tagPIS($stdPIS);

            $stdCOFINS = new stdClass();
            $stdCOFINS->item = $index + 1;
            $stdCOFINS->CST = str_pad((string) ($item->product->fiscal_cofins_cst ?? '49'), 2, '0', STR_PAD_LEFT);
            $stdCOFINS->vBC = 0.00;
            $stdCOFINS->pCOFINS = 0.00;
            $stdCOFINS->vCOFINS = 0.00;
            $nfe->tagCOFINS($stdCOFINS);
        }

        $vProd  = round($totalProdutos, 2);
        $vFrete = round((float) $order->delivery_fee, 2);
        $vOutro = round((float) $order->service_fee, 2);
        $vDesc  = round($totalDescontoFiscal, 2);

        $vNF = round($vProd + $vFrete + $vOutro - $vDesc, 2);

        $vPag = 0;
        $sefazPayments = [];
        $pagamentosAudit = [];

        // 🚀 EXCLUI PAGAMENTOS DE CASHBACK DA RELAÇÃO DE PAGAMENTOS SEFAZ (Pois já entraram como vDesc)
        $nonCashbackPayments = $order->payments->reject(function ($payment) use ($cashbackPayments) {
            return $cashbackPayments->contains('id', $payment->id);
        });

        if ($vNF <= 0) {
            $sefazPayments[] = ['tPag' => '90', 'vPag' => 0.00];
        } else {
            foreach ($nonCashbackPayments as $payment) {
                $code = $this->resolveSefazPaymentCode($payment);
                $sefazPayments[] = [
                    'tPag' => $code,
                    'vPag' => (float) $payment->amount,
                    'xPag' => $payment->method
                ];
                $vPag += (float) $payment->amount;
                $pagamentosAudit[] = "{$payment->method} [{$code}] (R$ {$payment->amount})";
            }

            if ($vPag < $vNF && !empty($order->payment_intent_methods)) {
                $promises = is_string($order->payment_intent_methods) ? json_decode($order->payment_intent_methods, true) : $order->payment_intent_methods;
                if (is_array($promises)) {
                    foreach ($promises as $promise) {
                        $amt = !empty($promise['amount']) ? (float) $promise['amount'] : round($vNF - $vPag, 2);
                        $changeTarget = !empty($promise['change_target']) ? (float) $promise['change_target'] : $amt;

                        $vPagReal = max($amt, $changeTarget);
                        $methodName = $promise['method_name'] ?? 'Dinheiro';

                        $sefazPayments[] = [
                            'tPag' => $this->mapPaymentToSefaz($methodName),
                            'vPag' => $vPagReal,
                            'xPag' => $methodName
                        ];
                        $vPag += $vPagReal;
                        $pagamentosAudit[] = "{$methodName} [Promessa Motoboy] (R$ {$vPagReal})";
                    }
                }
            }

            if ($vPag < $vNF) {
                $missing = round($vNF - $vPag, 2);
                $sefazPayments[] = [
                    'tPag' => '99',
                    'vPag' => $missing,
                    'xPag' => 'Outros'
                ];
                $vPag += $missing;
            }

            $vPag = round($vPag, 2);

            if ($vPag < $vNF) {
                $strPagamentos = empty($pagamentosAudit) ? "Nenhum pagamento registrado" : implode(", ", $pagamentosAudit);
                $audit  = "A NF-e custa R$ " . number_format($vNF, 2, ',', '.') . " ";
                $audit .= "[Produtos: {$vProd} | Frete: {$vFrete} | Taxas: {$vOutro} | Desc: -{$vDesc}]. ";
                $audit .= "No entanto, os pagamentos recebidos somam R$ " . number_format($vPag, 2, ',', '.') . " [{$strPagamentos}]. ";
                $audit .= "O sistema bloqueou a emissão pois a SEFAZ rejeitaria (Erro 865). Feche a conta pagando o valor completo.";
                throw new Exception($audit);
            }
        }

        $stdTransp = new stdClass();
        $stdTransp->modFrete = 9;
        $nfe->tagtransp($stdTransp);

        $stdPag = new stdClass();
        $stdPag->vTroco = $vPag > $vNF ? $this->format($vPag - $vNF) : 0.00;
        $nfe->tagpag($stdPag);

        foreach ($sefazPayments as $p) {
            $stdDetPag = new stdClass();
            $stdDetPag->tPag = $p['tPag'];
            $stdDetPag->vPag = $this->format($p['vPag']);

            if ($stdDetPag->tPag === '99') {
                $stdDetPag->xPag = mb_substr(trim($p['xPag'] ?? 'Outros'), 0, 60);
                if (empty($stdDetPag->xPag) || $stdDetPag->xPag === '99') {
                    $stdDetPag->xPag = 'Outros';
                }
            }

            if (in_array($p['tPag'], ['03', '04', '17'])) {
                $stdDetPag->tpIntegra = 2;
            }

            $nfe->tagdetPag($stdDetPag);
        }

        $infCplParts = ["Pedido #{$order->id}"];
        if ($cashbackDiscount > 0) {
            $infCplParts[] = "Desconto Fidelidade (Cashback): R$ " . number_format($cashbackDiscount, 2, ',', '.');
        }
        if ($order->type === 'delivery' && $order->address) {
            $fullAddr = is_array($order->address) ? ($order->address['full_address'] ?? '') : ($order->address->full_address ?? '');
            if ($fullAddr) {
                $infCplParts[] = "Entrega: {$fullAddr}";
            }
        }
        if (!empty($order->notes)) {
            $infCplParts[] = "Obs: {$order->notes}";
        }

        $stdInfAdic = new stdClass();
        $stdInfAdic->infCpl = substr(implode(' | ', $infCplParts), 0, 5000);
        $nfe->taginfAdic($stdInfAdic);

        $xml = $nfe->getXML();

        $isHomologation = (string) $settings->environment === '2';
        $cscCode = $isHomologation ? $settings->csc_code_homologation : $settings->csc_code;
        $cscId = $isHomologation ? $settings->csc_id_homologation : $settings->csc_id;

        if (!$cscCode || !$cscId) {
            throw new Exception("Configurações NFC-e Incompletas: CSC (Token) não preenchido para o ambiente atual.");
        }

        if (!$settings->certificate_path || !Storage::disk('local')->exists($settings->certificate_path)) {
            throw new Exception("Certificado Digital A1 não encontrado no cofre do servidor.");
        }

        $configJson = json_encode([
            "atualizacao" => now()->format('Y-m-d H:i:s'),
            "tpAmb" => (int) $settings->environment,
            "razaosocial" => $settings->company_name,
            "siglaUF" => $settings->state,
            "cnpj" => preg_replace('/[^0-9]/', '', (string) $settings->cnpj),
            "schemes" => "PL_009_V4",
            "versao" => "4.00",
            "tokenIBPT" => "",
            "CSC" => $cscCode,
            "CSCid" => str_pad((string) $cscId, 6, "0", STR_PAD_LEFT)
        ]);

        $certificadoDigital = Storage::disk('local')->get($settings->certificate_path);
        $tools = new Tools($configJson, Certificate::readPfx($certificadoDigital, $settings->certificate_password));
        $tools->model('65');

        $xmlAssinado = $tools->signNFe($xml);
        $idLote = str_pad((string) $stdIde->nNF, 15, '0', STR_PAD_LEFT);

        $respostaSefaz = $tools->sefazEnviaLote([$xmlAssinado], $idLote, 1);

        $standardizer = new Standardize();
        $stdResponse = $standardizer->toStd($respostaSefaz);

        if ($stdResponse->cStat != 104) {
            throw new Exception("Erro de Comunicação Sefaz: {$stdResponse->xMotivo} (cStat: {$stdResponse->cStat})");
        }

        $protNFe = $stdResponse->protNFe ?? null;
        if (!$protNFe) {
            throw new Exception("Resposta Sefaz Inesperada: Protocolo não retornado.");
        }

        $statusAutorizacao = $protNFe->infProt->cStat;

        if ($statusAutorizacao != 100 && $statusAutorizacao != 150) {
            throw new Exception("Rejeição SEFAZ: {$protNFe->infProt->xMotivo} (cStat: {$statusAutorizacao})");
        }

        $xmlAutorizado = Complements::toAuthorize($xmlAssinado, $respostaSefaz);

        FiscalDocument::create([
            'order_id'    => $order->id,
            'model'       => '65',
            'series'      => $stdIde->serie,
            'number'      => $stdIde->nNF,
            'access_key'  => $protNFe->infProt->chNFe,
            'protocol'    => $protNFe->infProt->nProt,
            'total_value' => $vNF,
            'issue_date'  => now(),
            'status'      => 'authorized',
            'xml_content' => $xmlAutorizado
        ]);

        // 🚀 Atualiza o estado do pedido no banco de dados
        $order->update([
            'fiscal_status' => 'authorized',
            'fiscal_error'  => null,
        ]);

        $settings->last_nfce_number = $stdIde->nNF;
        $settings->save();

        Cache::forget('company_settings');
        Cache::forget('tenant_company_settings');

        if ($shouldPrint && $printer) {
            $dispatcher = app(\App\Services\Printing\PrintDispatcher::class);
            $document = new \App\Services\Printing\Documents\FiscalReceipt($order, $xmlAutorizado);
            $dispatcher->dispatch($printer, $document);
        }

        return true;
    }

    public function cancelNfce(FiscalDocument $document, string $justification)
    {
        $settings = CompanySetting::first();
        if (!$settings || !$settings->certificate_path) {
            throw new Exception("Certificado Digital A1 não encontrado no cofre do servidor.");
        }

        $configJson = json_encode([
            "atualizacao" => now()->format('Y-m-d H:i:s'),
            "tpAmb" => (int) $settings->environment,
            "razaosocial" => $settings->company_name,
            "siglaUF" => $settings->state,
            "cnpj" => preg_replace('/[^0-9]/', '', (string) $settings->cnpj),
            "schemes" => "PL_009_V4",
            "versao" => "4.00",
        ]);

        $certificadoDigital = Storage::disk('local')->get($settings->certificate_path);
        $tools = new Tools($configJson, Certificate::readPfx($certificadoDigital, $settings->certificate_password));
        $tools->model($document->model);

        $response = $tools->sefazCancela(
            $document->access_key,
            $document->protocol,
            $justification,
            1
        );

        $std = new Standardize();
        $arr = $std->toArray($response);

        if (isset($arr['cStat']) && $arr['cStat'] == 128) {
            $retEvento = $arr['retEvento'][0] ?? $arr['retEvento'] ?? [];
            $infEvento = $retEvento['infEvento'] ?? $retEvento['infRet'] ?? null;
            $cStatEvento = $infEvento['cStat'] ?? null;

            if (in_array($cStatEvento, [135, 155])) {
                $document->update(['status' => 'canceled']);
                return true;
            }

            throw new Exception("Sefaz negou o cancelamento: " . ($infEvento['xMotivo'] ?? 'Motivo desconhecido'));
        }

        throw new Exception("Erro Sefaz: " . ($arr['xMotivo'] ?? 'Sem retorno do Governo.'));
    }

    /**
     * Avalia se todas as formas de pagamento do pedido autorizam a emissão fiscal automática.
     */
    public function shouldEmitAutomatically(Order $order): bool
    {
        $order->loadMissing('payments.paymentMethod');

        if ($order->payments->isEmpty()) {
            return false;
        }

        foreach ($order->payments as $payment) {
            if ($payment->paymentMethod && $payment->paymentMethod->auto_fiscal_emission === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Mapeia o pagamento para o código oficial da SEFAZ,
     * priorizando o campo fiscal_code cadastrado no banco.
     */
    private function resolveSefazPaymentCode($payment): string
    {
        if (!empty($payment->paymentMethod?->fiscal_code)) {
            return str_pad((string) $payment->paymentMethod->fiscal_code, 2, '0', STR_PAD_LEFT);
        }

        return $this->mapPaymentToSefaz($payment->method ?? '');
    }

    private function format($value, $decimals = 2): string
    {
        return number_format((float)$value, $decimals, '.', '');
    }

    private function mapPaymentToSefaz(string $paymentMethodName): string
    {
        $name = strtolower($paymentMethodName);
        if (str_contains($name, 'dinheiro') || str_contains($name, 'money')) return '01';
        if (str_contains($name, 'crédito') || str_contains($name, 'credito')) return '03';
        if (str_contains($name, 'débito') || str_contains($name, 'debito')) return '04';
        if (str_contains($name, 'pix')) return '17';
        if (str_contains($name, 'vale') || str_contains($name, 'alimentação') || str_contains($name, 'refeição')) return '10';

        return '99';
    }

    private function getUfCode(?string $uf): string
    {
        $ufs = [
            'RO'=>11,'AC'=>12,'AM'=>13,'RR'=>14,'PA'=>15,'AP'=>16,'TO'=>17,
            'MA'=>21,'PI'=>22,'CE'=>23,'RN'=>24,'PB'=>25,'PE'=>26,'AL'=>27,
            'SE'=>28,'BA'=>29,'MG'=>31,'ES'=>32,'RJ'=>33,'SP'=>35,'PR'=>41,
            'SC'=>42,'RS'=>43,'MS'=>50,'MT'=>51,'GO'=>52,'DF'=>53
        ];
        return (string) ($ufs[strtoupper($uf ?? 'SC')] ?? 42);
    }
}
