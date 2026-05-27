<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use App\Models\CompanySetting;
use App\Models\ReceivedInvoice; // <-- CORRIGIDO AQUI
use Illuminate\Support\Facades\Storage;
use NFePHP\NFe\Tools;
use NFePHP\Common\Certificate;

class SyncSefazNotes extends Command
{
    protected $signature = 'sefaz:sync';
    protected $description = 'Sincronizador Avançado: Fila NSU + Auditoria Específica de Notas Manifestadas';

    public function handle()
    {
        $this->info("\n=======================================================");
        $this->info("🚀 INICIANDO SINCRONIZADOR SEFAZ (MODO ATIRADOR DE ELITE) 🚀");
        $this->info("=======================================================\n");

        Tenant::all()->each(function ($tenant) {
            tenancy()->initialize($tenant);
            
            $this->warn("▶ Verificando Empresa (Tenant): {$tenant->id}");

            $setting = CompanySetting::first();

            if ($setting && $setting->cnpj && $setting->certificate_path && $setting->certificate_password) {
                $this->syncForCompany($setting);
            } else {
                $this->error("  [!] Empresa sem certificado ou CNPJ configurado. Pulando...");
            }

            tenancy()->end();
        });

        $this->info("\n✅ SINCRONIZAÇÃO GERAL CONCLUÍDA!\n");
    }

    private function syncForCompany(CompanySetting $setting)
    {
        // ==========================================
        // O ESCUDO DO CRONJOB (1 Hora)
        // ==========================================
        if ($setting->updated_at) {
            $liberaEm = \Carbon\Carbon::parse($setting->updated_at)->addMinutes(60);
            if (now()->lessThan($liberaEm)) {
                $this->error("  [!] BLOQUEIO ATIVO: Período de espera (Cooldown Sefaz) até {$liberaEm->format('H:i')}.");
                return;
            }
        }

        try {
            $config = json_encode([
                "atualizacao" => date('Y-m-d H:i:s'),
                "tpAmb" => 1, 
                "razaosocial" => $setting->company_name,
                "cnpj" => preg_replace('/[^0-9]/', '', $setting->cnpj),
                "siglaUF" => $setting->state,
                "schemes" => "PL_009_V4",
                "versao" => '4.00',
            ]);

            $pfxContent = Storage::get($setting->certificate_path);
            $certificate = Certificate::readPfx($pfxContent, $setting->certificate_password);
            $tools = new Tools($config, $certificate);
            
            // =======================================================
            // PARTE 1: A FILA GERAL (NSU)
            // =======================================================
            $this->info("\n--- ETAPA 1: VERIFICANDO A FILA GERAL (NSU) ---");
            $lastNsu = $setting->last_nsu ?? 0;
            
            $this->line("📡 Perguntando à Sefaz por novos pacotes a partir do NSU {$lastNsu}...");
            $response = $tools->sefazDistDFe($lastNsu);

            $std = new \NFePHP\NFe\Common\Standardize();
            $arr = $std->toArray($response);

            $cStat = $arr['cStat'] ?? 'N/A';
            $xMotivo = $arr['xMotivo'] ?? 'Sem Resposta';
            $ultNSU = $arr['ultNSU'] ?? $lastNsu;

            $this->line("   📥 Resposta Sefaz: [{$cStat}] {$xMotivo}");

            if (isset($arr['ultNSU']) && $arr['ultNSU'] > $setting->last_nsu) {
                $setting->last_nsu = (int) $arr['ultNSU'];
            }

            if ($cStat == 138 && isset($arr['loteDistDFeInt']['docZip'])) {
                $this->info("   📦 Há ficheiros novos! Processando Lote NSU...");
                $docs = $arr['loteDistDFeInt']['docZip'];
                if (!is_array($docs) || !isset($docs[0])) { $docs = [$docs]; }

                foreach ($docs as $doc) {
                    $base64Data = is_array($doc) ? ($doc['_value'] ?? $doc['@value'] ?? '') : $doc;
                    if (empty($base64Data)) continue;

                    $xmlContent = gzdecode(base64_decode($base64Data));
                    $xml = simplexml_load_string($xmlContent);
                    $rootName = $xml->getName();
                    $nsuDoc = is_array($doc) ? ($doc['NSU'] ?? null) : null;

                    // Se for um Resumo de Nota (ResNFe)
                    if ($rootName === 'resNFe') {
                        $chave = (string) $xml->chNFe;
                        ReceivedInvoice::firstOrCreate(
                            ['nfe_key' => $chave],
                            [
                                'emitter_cnpj' => (string) $xml->CNPJ,  // Corrigido
                                'emitter_name' => (string) $xml->xNome, // Corrigido
                                'issue_date' => \Carbon\Carbon::parse((string) $xml->dhEmi)->format('Y-m-d H:i:s'), // Corrigido
                                'total_value' => (float) $xml->vNF,
                                'status' => 'pending',
                                'xml_content' => $xmlContent
                                // nsu removido pois não existe na migration
                            ]
                        );
                        $this->line("      [+] Nova Nota (Resumo) salva: {$chave}");
                    }
                    
                    // Se a Sefaz já mandar o XML Completo logo de cara (nfeProc)
                    elseif ($rootName === 'nfeProc') {
                        $chave = (string) $xml->NFe->infNFe['Id'];
                        $chave = str_replace('NFe', '', $chave);
                        
                        ReceivedInvoice::updateOrCreate(
                            ['nfe_key' => $chave],
                            [
                                'emitter_cnpj' => (string) $xml->NFe->infNFe->emit->CNPJ,  // Corrigido
                                'emitter_name' => (string) $xml->NFe->infNFe->emit->xNome, // Corrigido
                                'issue_date' => \Carbon\Carbon::parse((string) $xml->NFe->infNFe->ide->dhEmi)->format('Y-m-d H:i:s'), // Corrigido
                                'total_value' => (float) $xml->NFe->infNFe->total->ICMSTot->vNF,
                                'status' => 'ready', 
                                'xml_content' => $xmlContent
                                // nsu removido
                            ]
                        );
                        $this->line("      [+] Nova Nota (COMPLETA) salva: {$chave}");
                    }

                    // Se a Sefaz já mandar o XML Completo logo de cara (nfeProc)
                    elseif ($rootName === 'nfeProc') {
                        $chave = (string) $xml->NFe->infNFe['Id'];
                        $chave = str_replace('NFe', '', $chave);
                        
                        ReceivedInvoice::updateOrCreate(
                            ['nfe_key' => $chave],
                            [
                                'supplier_cnpj' => (string) $xml->NFe->infNFe->emit->CNPJ,
                                'supplier_name' => (string) $xml->NFe->infNFe->emit->xNome,
                                'emission_date' => \Carbon\Carbon::parse((string) $xml->NFe->infNFe->ide->dhEmi)->format('Y-m-d H:i:s'),
                                'total_value' => (float) $xml->NFe->infNFe->total->ICMSTot->vNF,
                                'status' => 'ready', // Já chegou completo!
                                'xml_content' => $xmlContent,
                                'nsu' => $nsuDoc
                            ]
                        );
                        $this->line("      [+] Nova Nota (COMPLETA) salva: {$chave}");
                    }
                }
            }
            
            // =======================================================
            // PARTE 2: O ATIRADOR DE ELITE (BUSCA POR CHAVES ESPECÍFICAS)
            // =======================================================
            $this->info("\n--- ETAPA 2: AUDITORIA DE NOTAS MANIFESTADAS (BUSCA NOMINAL) ---");
            
            // CORRIGIDO AQUI: ReceivedInvoice
            $notasPendentes = ReceivedInvoice::where('status', 'manifested')
                ->where(function($q) {
                    $q->whereNull('xml_content')->orWhere('xml_content', 'like', '%resNFe%');
                })->get();

            if ($notasPendentes->isEmpty()) {
                $this->line("✔️ Nenhuma nota no banco aguardando o XML Completo.");
            } else {
                $this->line("🎯 Encontramos {$notasPendentes->count()} notas aguardando XML. Vamos cobrar a Sefaz uma a uma:");

                foreach ($notasPendentes as $index => $nota) {
                    $this->line("\n-------------------------------------------------------");
                    $this->line("🔎 [".($index+1)."/".$notasPendentes->count()."] Solicitando EXATAMENTE a Chave: {$nota->nfe_key}");

                    try {
                        $respEspecifica = $tools->sefazDistDFe(0, 0, $nota->nfe_key);
                        $arrEspec = $std->toArray($respEspecifica);

                        $cStatEsp = $arrEspec['cStat'] ?? 'N/A';
                        $motivoEsp = $arrEspec['xMotivo'] ?? 'Sem Resposta';

                        $this->line("   📥 Resposta da Sefaz: [{$cStatEsp}] {$motivoEsp}");

                        if ($cStatEsp == 138 && isset($arrEspec['loteDistDFeInt']['docZip'])) {
                            $docs = $arrEspec['loteDistDFeInt']['docZip'];
                            if (!is_array($docs) || !isset($docs[0])) { $docs = [$docs]; }

                            $achouXMLCompleto = false;

                            foreach ($docs as $doc) {
                                $base64Data = is_array($doc) ? ($doc['_value'] ?? $doc['@value'] ?? '') : $doc;
                                if (empty($base64Data)) continue;

                                $xmlContent = gzdecode(base64_decode($base64Data));
                                $xml = simplexml_load_string($xmlContent);
                                $rootName = $xml->getName();

                                $this->line("      📄 Documento que a Sefaz enviou agora: <options=bold>{$rootName}</>");

                                if ($rootName === 'nfeProc') {
                                    $nota->update([
                                        'xml_content' => $xmlContent,
                                        'status' => 'ready',
                                    ]);
                                    $this->info("      -> 🚀 UPGRADE APLICADO! XML Completo salvo com sucesso. Botão Verde ativado!");
                                    $achouXMLCompleto = true;
                                }
                            }

                            if (!$achouXMLCompleto) {
                                $this->comment("      -> ⚠️ A Sefaz devolveu documentos, mas NENHUM deles é o XML completo (nfeProc) ainda.");
                            }

                        } elseif ($cStatEsp == 137) {
                            $this->comment("   ⏳ A Sefaz diz: 'Nenhum documento localizado'. Eles ainda não geraram o ficheiro após a sua Ciência.");
                        } else {
                            $this->warn("   ⚠️ Resposta inesperada ou Erro de Processamento na Sefaz.");
                        }

                    } catch (\Exception $e) {
                        $this->error("   [X] Falha na comunicação: " . $e->getMessage());
                    }

                    $this->line("   [Pausa de 2s para evitar bloqueio (Rejeição 656)...]");
                    sleep(2);
                }
            }

            $setting->touch();
            $this->info("\n🔒 Sincronização finalizada. Relógio de segurança de 1h ativado.");

        } catch (\Exception $e) {
            $this->error("\n❌ ERRO CRÍTICO NA COMUNICAÇÃO: " . $e->getMessage());
        }
    }
}