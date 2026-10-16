<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\CompanySetting;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class GenerateReportsFromXmlFolder extends Command
{
    // Exemplo de uso: php artisan fiscal:xml-reports xml_setembro 2026-09-01 2026-09-30
    protected $signature = 'fiscal:xml-reports {folder} {start_date} {end_date}';
    protected $description = 'Gera relatórios PDF a partir de uma pasta com arquivos XML brutos (Transição de Sistema)';

    public function handle()
    {
        $folder = $this->argument('folder');
        $startDate = $this->argument('start_date');
        $endDate = $this->argument('end_date');

        // Força a busca na pasta global (ignorando o isolamento do tenant)
        $absolutePath = base_path("storage/app/{$folder}");

        if (!is_dir($absolutePath)) {
            $this->error("A pasta {$absolutePath} não existe.");
            return;
        }

        $files = glob($absolutePath . '/*.xml');
        $this->info("Encontrados " . count($files) . " arquivos na pasta.");

        $documents = [];
        $monophasicItems = collect();

        $this->output->progressStart(count($files));

        foreach ($files as $file) {
            // Lê o conteúdo usando a função nativa do PHP
            $content = file_get_contents($file);

            // Hack para remover namespaces e facilitar o SimpleXML
            $content = preg_replace('/xmlns="[^"]+"/', '', $content);
            $xml = @simplexml_load_string($content);

            if (!$xml) {
                $this->output->progressAdvance();
                continue;
            }

            // Localiza o nó principal da nota (depende se tem a tag envolvente <nfeProc> ou não)
            $infNFe = $xml->NFe->infNFe ?? $xml->infNFe ?? null;
            if (!$infNFe) {
                $this->output->progressAdvance();
                continue;
            }

            $ide = $infNFe->ide;
            $dest = $infNFe->dest ?? null;
            $total = (float) ($infNFe->total->ICMSTot->vNF ?? 0);
            $prot = $xml->protNFe->infProt ?? null;
            $status = $prot ? 'authorized' : 'canceled'; // Assumimos autorizada se tiver protocolo

            if ($status === 'authorized') {
                $documents[] = (object) [
                    'issue_date' => (string) $ide->dhEmi,
                    'model' => (string) $ide->mod,
                    'series' => (string) $ide->serie,
                    'number' => (string) $ide->nNF,
                    'status' => $status,
                    'access_key' => str_replace('NFe', '', (string) $infNFe['Id']),
                    'protocol' => $prot ? (string) $prot->nProt : 'S/N',
                    'total_value' => $total,
                    'order' => (object) [
                        'customer' => (object) [
                            'cpf' => $dest ? ((string) ($dest->CPF ?? $dest->CNPJ ?? '')) : null,
                        ],
                        'customer_name' => $dest ? (string) ($dest->xNome ?? 'Consumidor Final') : 'Consumidor Final',
                    ]
                ];

                foreach ($infNFe->det as $det) {
                    $ncm = (string) $det->prod->NCM;

                    if (str_starts_with($ncm, '2201') || str_starts_with($ncm, '2202') || str_starts_with($ncm, '2203')) {
                        $monophasicItems->push([
                            'name' => (string) $det->prod->xProd,
                            'qty' => (float) $det->prod->qCom,
                            'value' => (float) $det->prod->vProd,
                        ]);
                    }
                }
            }

            $this->output->progressAdvance();
        }

        $this->output->progressFinish();

        $documentsCollection = collect($documents)->sortBy('issue_date');

        $monophasicGrouped = $monophasicItems->groupBy('name')->map(function ($items, $name) {
            return [
                'name' => $name,
                'total_qty' => $items->sum('qty'),
                'total_value' => $items->sum('value'),
            ];
        });

        $settings = CompanySetting::first() ?? new CompanySetting();
        $logoBase64 = $this->getLogoBase64($settings->logo_path);

        $this->info("Gerando PDF Geral...");
        $pdfGeneral = Pdf::loadView('reports.fiscal_general', [
            'settings' => $settings,
            'documents' => $documentsCollection,
            'startDate' => Carbon::parse($startDate)->format('d/m/Y'),
            'endDate' => Carbon::parse($endDate)->format('d/m/Y'),
            'totalFaturado' => $documentsCollection->sum('total_value'),
            'logoBase64' => $logoBase64,
        ])->setPaper('a4', 'landscape');

        // Salva diretamente na raiz de onde o script foi chamado
        $generalPath = base_path("Relatorio_Geral_XML_{$startDate}_a_{$endDate}.pdf");
        $pdfGeneral->save($generalPath);

        $this->info("Gerando PDF Monofásico...");
        $pdfMonophasic = Pdf::loadView('reports.fiscal_monophasic', [
            'settings' => $settings,
            'items' => $monophasicGrouped,
            'startDate' => Carbon::parse($startDate)->format('d/m/Y'),
            'endDate' => Carbon::parse($endDate)->format('d/m/Y'),
            'totalFaturado' => $monophasicGrouped->sum('total_value'),
            'logoBase64' => $logoBase64,
        ]);

        $monoPath = base_path("Relatorio_Monofasicos_XML_{$startDate}_a_{$endDate}.pdf");
        $pdfMonophasic->save($monoPath);

        $this->info("✅ Relatórios gerados com sucesso!");
        $this->line("Geral: " . $generalPath);
        $this->line("Monofásico: " . $monoPath);
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
}
