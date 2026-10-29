<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\Tenant;
use App\Models\CompanySetting;
use App\Models\ReceivedInvoice;
use NFePHP\NFe\Tools;
use NFePHP\Common\Certificate;
use Carbon\Carbon;

class SyncTenantSefazJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tenantId;
    public $force;
    public $tries = 3;
    public $timeout = 300;

    public function __construct($tenantId, $force = false)
    {
        $this->tenantId = $tenantId;
        $this->force = $force;
    }

    public function handle()
    {
        Log::info("==========================================");
        Log::info("👻 [Job Sefaz] Fila acordou! Tenant ID: " . $this->tenantId);

        $tenant = Tenant::find($this->tenantId);
        if (!$tenant) return;

        $wasInitialized = tenancy()->initialized;
        $previousTenant = $wasInitialized ? tenant() : null;

        if (!$wasInitialized || tenant('id') !== $tenant->id) {
            tenancy()->initialize($tenant);
        }

        $setting = CompanySetting::first();

        if ($setting && $setting->cnpj && $setting->certificate_path && $setting->certificate_password) {
            $this->syncForCompany($setting);
        } else {
            Log::warning("⚠️ [Job Sefaz] Credenciais incompletas.");
        }

        if (!$wasInitialized) {
            tenancy()->end();
        } elseif ($previousTenant && tenant('id') !== $previousTenant->id) {
            tenancy()->initialize($previousTenant);
        }

        Log::info("==========================================");
    }

    private function syncForCompany(CompanySetting $setting)
    {
        try {
            $config = json_encode([
                "atualizacao" => date('Y-m-d H:i:s'),
                "tpAmb"       => 1,
                "razaosocial" => $setting->company_name,
                "cnpj"        => preg_replace('/[^0-9]/', '', $setting->cnpj),
                "siglaUF"     => $setting->state,
                "schemes"     => "PL_009_V4",
                "versao"      => '4.00',
            ]);

            $pfxContent = Storage::get($setting->certificate_path);
            $certificate = Certificate::readPfx($pfxContent, $setting->certificate_password);
            $tools = new Tools($config, $certificate);
            $std = new \NFePHP\NFe\Common\Standardize();

            $isOnCooldown = false;

            if (!$this->force && $setting->sefaz_last_sync_at) {
                $liberaEm = Carbon::parse($setting->sefaz_last_sync_at)->addMinutes(60);
                if (now()->lessThan($liberaEm)) {
                    $isOnCooldown = true;
                    Log::warning("⏳ [Sefaz] BLOQUEIO ATIVO até {$liberaEm->format('H:i')}.");
                }
            }

            if (!$isOnCooldown) {
                $continuarBuscando = true;
                $currentNsu = (int) ($setting->last_nsu ?? 0);

                while ($continuarBuscando) {
                    Log::info("🛸 [Sefaz] Consultando distDFeInt a partir do NSU: {$currentNsu}");
                    $response = $tools->sefazDistDFe($currentNsu);
                    $arr = $std->toArray($response);

                    $cStat   = (int) ($arr['cStat'] ?? 0);
                    $xMotivo = $arr['xMotivo'] ?? 'N/A';
                    $ultNSU  = (int) ($arr['ultNSU'] ?? 0);
                    $maxNSU  = (int) ($arr['maxNSU'] ?? 0);

                    Log::info("🛸 [Sefaz] Retorno: cStat={$cStat} ({$xMotivo}) | ultNSU={$ultNSU} | maxNSU={$maxNSU}");

                    if ($cStat == 656) {
                        Log::warning("⚠️ [Sefaz] Consumo Indevido (656). ultNSU informado pela SEFAZ: {$ultNSU} | NSU local: {$currentNsu}");
                        if ($ultNSU > $currentNsu) {
                            Log::info("🚀 [Sefaz Assessor] Iniciando recuperação cirúrgica individual dos NSUs {$currentNsu} -> {$ultNSU}...");
                            $this->recoverGapViaConsNsu($tools, $std, $setting, $currentNsu, $ultNSU);
                        }
                        break;
                    }

                    // 137: Nenhum documento novo localizado
                    if ($cStat == 137) {
                        if ($ultNSU > $currentNsu) {
                            $setting->last_nsu = $ultNSU;
                            $setting->saveQuietly();
                            $this->clearSettingsCache();
                        }
                        $continuarBuscando = false;
                        break;
                    }

                    // 138: Documentos localizados
                    if ($cStat == 138 && isset($arr['loteDistDFeInt']['docZip'])) {
                        $docs = $arr['loteDistDFeInt']['docZip'];

                        if (!is_array($docs) || (isset($docs['_value']) || isset($docs['@value']))) {
                            $docs = [$docs];
                        }

                        $salvasHoje = 0;
                        $tableColumns = \Illuminate\Support\Facades\Schema::getColumnListing('received_invoices');

                        foreach ($docs as $doc) {
                            $base64Data = '';
                            if (is_string($doc)) {
                                $base64Data = $doc;
                            } elseif (is_array($doc)) {
                                $base64Data = $doc['_value'] ?? $doc['@value'] ?? '';
                            }

                            if (empty($base64Data)) continue;

                            $xmlContent = gzdecode(base64_decode($base64Data));
                            if (!$xmlContent) continue;

                            $xml = @simplexml_load_string($xmlContent);
                            if (!$xml) continue;

                            $rootName = $xml->getName();

                            if ($rootName === 'resNFe') {
                                $chave = (string) $xml->chNFe;
                                $cnpjEmitente = preg_replace('/[^0-9]/', '', (string) $xml->CNPJ);

                                $supplier = \App\Models\Supplier::where('document', $cnpjEmitente)->first();
                                $isTrusted = $supplier && (bool) $supplier->is_trusted;
                                $targetStatus = 'pending';

                                if ($isTrusted) {
                                    try {
                                        Log::info("🤝 [Fornecedor Confiável] Disparando Ciência da Emissão para NF-e {$chave} ({$supplier->name})");
                                        $respManifesto = $tools->sefazManifesta($chave, '210210');
                                        $arrManif = $std->toArray($respManifesto);
                                        $retEvento = $arrManif['retEvento']['infEvento'] ?? $arrManif['retEvento'][0]['infEvento'] ?? null;
                                        $cStatEvento = (int) ($retEvento['cStat'] ?? ($arrManif['cStat'] ?? 0));

                                        if (in_array($cStatEvento, [135, 136, 573])) {
                                            $targetStatus = 'manifested';
                                            Log::info("✔ [Sefaz] Ciência da Emissão autorizada para: {$chave}");
                                        } else {
                                            Log::warning("⚠️ [Sefaz] Manifestação não homologada (cStat: {$cStatEvento}): " . ($retEvento['xMotivo'] ?? 'N/A'));
                                        }
                                    } catch (\Throwable $e) {
                                        Log::error("❌ [Sefaz] Erro ao manifestar automaticamente: " . $e->getMessage());
                                    }
                                }

                                $invoice = ReceivedInvoice::firstOrNew(['nfe_key' => $chave]);
                                $invoice->emitter_cnpj = (string) $xml->CNPJ;
                                $invoice->emitter_name = (string) $xml->xNome;
                                $invoice->issue_date   = Carbon::parse((string) $xml->dhEmi)->format('Y-m-d H:i:s');
                                $invoice->total_value  = (float) $xml->vNF;
                                $docNsu = is_array($doc) ? ($doc['@attributes']['NSU'] ?? $doc['NSU'] ?? null) : null;
                                if ($docNsu && in_array('nsu', $tableColumns ?? [])) { $invoice->nsu = $docNsu; }
                                $invoice->has_full_xml = false;
                                $invoice->xml_content  = $xmlContent;
                                if (!in_array($invoice->status, ['ready', 'processed', 'manifested'])) {
                                    $invoice->status = $targetStatus;
                                }
                                $invoice->save();
                                $salvasHoje++;
                            } elseif ($rootName === 'nfeProc') {
                                $chave = str_replace('NFe', '', (string) $xml->NFe->infNFe['Id']);

                                $finNFe = (int) $xml->NFe->infNFe->ide->finNFe;
                                $natOp = strtoupper((string) $xml->NFe->infNFe->ide->natOp);
                                $isDevolucao = ($finNFe === 4) || str_contains($natOp, 'DEVOLUCAO') || str_contains($natOp, 'RETORNO');

                                $targetStatus = 'ready';

                                if ($isDevolucao) {
                                    $targetStatus = 'return_receipt';
                                    if (isset($xml->NFe->infNFe->ide->NFref->refNFe)) {
                                        $chaveOriginal = (string) $xml->NFe->infNFe->ide->NFref->refNFe;
                                        if (substr($chaveOriginal, 20, 2) === '65') {
                                            $targetStatus = 'processed';
                                        } else {
                                            ReceivedInvoice::where('nfe_key', $chaveOriginal)->update(['status' => 'return_pending']);
                                        }
                                    }
                                }

                                $invoice = ReceivedInvoice::firstOrNew(['nfe_key' => $chave]);
                                $invoice->emitter_cnpj = (string) $xml->NFe->infNFe->emit->CNPJ;
                                $invoice->emitter_name = (string) $xml->NFe->infNFe->emit->xNome;
                                $invoice->issue_date   = Carbon::parse((string) $xml->NFe->infNFe->ide->dhEmi)->format('Y-m-d H:i:s');
                                $invoice->total_value  = (float) $xml->NFe->infNFe->total->ICMSTot->vNF;
                                $invoice->xml_content  = $xmlContent;
                                $invoice->has_full_xml = true;

                                if (!in_array($invoice->status, ['processed', 'rejected', 'canceled_by_supplier', 'returned'])) {
                                    $invoice->status = $targetStatus;
                                }
                                $invoice->save();
                                $salvasHoje++;

                                if ($targetStatus === 'ready') {
                                    app(\App\Services\Purchases\InvoiceImportService::class)->attemptAutoApproval($invoice);
                                }
                            } elseif ($rootName === 'resEvento' || $rootName === 'procEventoNFe') {
                                $tpEvento = $rootName === 'resEvento' ? (string) $xml->tpEvento : (string) $xml->evento->infEvento->tpEvento;
                                $chave = $rootName === 'resEvento' ? (string) $xml->chNFe : (string) $xml->evento->infEvento->chNFe;
                                if ($tpEvento == '110111') {
                                    ReceivedInvoice::where('nfe_key', $chave)->update(['status' => 'canceled_by_supplier']);
                                }
                            }
                        }

                        Log::info("✅ Salvas ou atualizadas {$salvasHoje} notas na base de dados neste lote.");
                    }

                    // Avança o ponteiro de leitura local e persiste
                    if ($ultNSU > $currentNsu) {
                        $currentNsu = $ultNSU;
                        $setting->last_nsu = $ultNSU;
                        $setting->saveQuietly();
                        $this->clearSettingsCache();
                    }

                    // Se a SEFAZ indicou que ainda há mais documentos à frente no lote
                    if ($ultNSU > 0 && $ultNSU < $maxNSU && $cStat == 138) {
                        sleep(2);
                    } else {
                        $continuarBuscando = false;
                    }
                }

                $setting->sefaz_last_sync_at = now();
                $setting->saveQuietly();
                $this->clearSettingsCache();
            }

            // =======================================================
            // PARTE 2: DOWNLOAD ESPECÍFICO DE XMLS MANIFESTADOS
            // =======================================================
            $notasPendentes = ReceivedInvoice::where('status', 'manifested')
                ->where('has_full_xml', false)
                ->get();

            foreach ($notasPendentes as $nota) {
                try {
                    $respEspecifica = $tools->sefazDistDFe(0, 0, $nota->nfe_key);
                    $arrEspec = $std->toArray($respEspecifica);
                    $cStatEsp = (int) ($arrEspec['cStat'] ?? 0);

                    if ($cStatEsp == 138 && isset($arrEspec['loteDistDFeInt']['docZip'])) {
                        $docs = $arrEspec['loteDistDFeInt']['docZip'];
                        if (!is_array($docs) || (isset($docs['_value']) || isset($docs['@value']))) { $docs = [$docs]; }

                        foreach ($docs as $doc) {
                            $base64Data = is_string($doc) ? $doc : ($doc['_value'] ?? $doc['@value'] ?? '');
                            if (empty($base64Data)) continue;

                            $xmlContent = gzdecode(base64_decode($base64Data));
                            if (!$xmlContent) continue;

                            $xml = @simplexml_load_string($xmlContent);
                            if (!$xml || $xml->getName() !== 'nfeProc') continue;

                            $finNFe = (int) $xml->NFe->infNFe->ide->finNFe;
                            $natOp = strtoupper((string) $xml->NFe->infNFe->ide->natOp);
                            $isDevolucao = ($finNFe === 4) || str_contains($natOp, 'DEVOLUCAO') || str_contains($natOp, 'RETORNO');
                            $targetStatus = 'ready';

                            if ($isDevolucao) {
                                $targetStatus = 'return_receipt';
                                if (isset($xml->NFe->infNFe->ide->NFref->refNFe)) {
                                    $chaveOriginal = (string) $xml->NFe->infNFe->ide->NFref->refNFe;
                                    if (substr($chaveOriginal, 20, 2) === '65') {
                                        $targetStatus = 'processed';
                                    } else {
                                        ReceivedInvoice::where('nfe_key', $chaveOriginal)->update(['status' => 'return_pending']);
                                    }
                                }
                            }

                            $nota->update([
                                'xml_content'  => $xmlContent,
                                'status'       => $targetStatus,
                                'has_full_xml' => true,
                            ]);

                            Log::info("✅ Download de XML pendente feito: {$nota->nfe_key}");

                            if ($targetStatus === 'ready') {
                                app(\App\Services\Purchases\InvoiceImportService::class)->attemptAutoApproval($nota);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("❌ Erro no XML da NF {$nota->nfe_key}: " . $e->getMessage());
                }
                sleep(2);
            }

        } catch (\Exception $e) {
            Log::error("❌ ERRO CRÍTICO NA COMUNICAÇÃO SEFAZ: " . $e->getMessage());
            throw $e;
        }
    }    /**
     * Assessor de Recuperação Cirúrgica:
     * Busca individualmente via consNSU cada documento pendente caso ocorra consumo indevido por gap de ponteiro.
     */
    private function recoverGapViaConsNsu(Tools $tools, $std, CompanySetting $setting, int $startNsu, int $endNsu): void
    {
        $recoveredCount = 0;

        for ($nsu = $startNsu + 1; $nsu <= $endNsu; $nsu++) {
            try {
                $resp = $tools->sefazDistDFe(0, $nsu);
                $arr = $std->toArray($resp);
                $cStat = (int) ($arr['cStat'] ?? 0);

                if ($cStat !== 138 || !isset($arr['loteDistDFeInt']['docZip'])) {
                    continue;
                }

                $doc = $arr['loteDistDFeInt']['docZip'];
                $base64Data = is_string($doc) ? $doc : ($doc['_value'] ?? $doc['@value'] ?? '');
                if (empty($base64Data)) continue;

                $xmlContent = gzdecode(base64_decode($base64Data));
                if (!$xmlContent) continue;

                $xml = @simplexml_load_string($xmlContent);
                if (!$xml) continue;

                $rootName = $xml->getName();

                if ($rootName === 'resNFe') {
                    $chave = (string) $xml->chNFe;
                    $cnpjEmitente = preg_replace('/[^0-9]/', '', (string) $xml->CNPJ);
                    $supplier = \App\Models\Supplier::where('document', $cnpjEmitente)->first();
                    $isTrusted = $supplier && (bool) $supplier->is_trusted;
                    $targetStatus = 'pending';

                    if ($isTrusted) {
                        try {
                            $respManif = $tools->sefazManifesta($chave, '210210');
                            $arrManif = $std->toArray($respManif);
                            $retEvento = $arrManif['retEvento']['infEvento'] ?? $arrManif['retEvento'][0]['infEvento'] ?? null;
                            $cStatEvento = (int) ($retEvento['cStat'] ?? ($arrManif['cStat'] ?? 0));
                            if (in_array($cStatEvento, [135, 136, 573])) {
                                $targetStatus = 'manifested';
                            }
                        } catch (\Throwable $e) {
                            Log::error("❌ [Assessor Sefaz] Falha ao manifestar confiavel: " . $e->getMessage());
                        }
                    }

                    $invoice = ReceivedInvoice::firstOrNew(['nfe_key' => $chave]);
                    $invoice->nsu          = $nsu;
                    $invoice->emitter_cnpj = (string) $xml->CNPJ;
                    $invoice->emitter_name = (string) $xml->xNome;
                    $invoice->issue_date   = Carbon::parse((string) $xml->dhEmi)->format('Y-m-d H:i:s');
                    $invoice->total_value  = (float) $xml->vNF;
                    $invoice->has_full_xml = false;
                    $invoice->xml_content  = $xmlContent;
                    if (!in_array($invoice->status, ['ready', 'processed', 'manifested'])) {
                        $invoice->status = $targetStatus;
                    }
                    $invoice->save();
                    $recoveredCount++;

                } elseif ($rootName === 'nfeProc') {
                    $chave = str_replace('NFe', '', (string) ($xml->NFe->infNFe['Id'] ?? ''));
                    $finNFe = (int) $xml->NFe->infNFe->ide->finNFe;
                    $natOp = strtoupper((string) $xml->NFe->infNFe->ide->natOp);
                    $isDevolucao = ($finNFe === 4) || str_contains($natOp, 'DEVOLUCAO') || str_contains($natOp, 'RETORNO');
                    $targetStatus = $isDevolucao ? 'return_receipt' : 'ready';

                    $invoice = ReceivedInvoice::firstOrNew(['nfe_key' => $chave]);
                    $invoice->nsu          = $nsu;
                    $invoice->emitter_cnpj = (string) $xml->NFe->infNFe->emit->CNPJ;
                    $invoice->emitter_name = (string) $xml->NFe->infNFe->emit->xNome;
                    $invoice->issue_date   = Carbon::parse((string) $xml->NFe->infNFe->ide->dhEmi)->format('Y-m-d H:i:s');
                    $invoice->total_value  = (float) $xml->NFe->infNFe->total->ICMSTot->vNF;
                    $invoice->xml_content  = $xmlContent;
                    $invoice->has_full_xml = true;
                    if (!in_array($invoice->status, ['processed', 'rejected', 'canceled_by_supplier', 'returned'])) {
                        $invoice->status = $targetStatus;
                    }
                    $invoice->save();
                    $recoveredCount++;

                    if ($targetStatus === 'ready') {
                        app(\App\Services\Purchases\InvoiceImportService::class)->attemptAutoApproval($invoice);
                    }
                } elseif ($rootName === 'resEvento' || $rootName === 'procEventoNFe') {
                    $tpEvento = $rootName === 'resEvento' ? (string) $xml->tpEvento : (string) $xml->evento->infEvento->tpEvento;
                    $chave = $rootName === 'resEvento' ? (string) $xml->chNFe : (string) $xml->evento->infEvento->chNFe;
                    if ($tpEvento == '110111') {
                        ReceivedInvoice::where('nfe_key', $chave)->update(['status' => 'canceled_by_supplier']);
                    }
                }

                usleep(250000); // 250ms de intervalo preventivo
            } catch (\Throwable $e) {
                Log::error("❌ [Assessor Sefaz] Erro no NSU {$nsu}: " . $e->getMessage());
            }
        }

        // Atualiza e alinha o ponteiro local com a SEFAZ
        $setting->last_nsu = $endNsu;
        $setting->sefaz_last_sync_at = now();
        $setting->saveQuietly();
        $this->clearSettingsCache();

        Log::info("✔ [Assessor Sefaz] Concluído com sucesso! {$recoveredCount} documento(s) recuperados. NSU atualizado para {$endNsu}.");
    }


    private function clearSettingsCache(): void
    {
        Cache::forget('company_settings');
        Cache::forget('tenant_company_settings');
    }
}
