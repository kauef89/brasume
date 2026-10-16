<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Models\Tenant;

class ImportWpFiscal extends Command
{
    // Passamos o ano como argumento e o IP da RunCloud por padrão
    protected $signature = 'import:wp-fiscal {tenant=nativa} {year=2026} {--ip=162.243.184.101} {--domain=pastelarianativa.com.br}';
    protected $description = 'Puxa o arquivo fiscal.zip do MVP (RunCloud) e salva intocado no novo servidor.';

    public function handle()
    {
        $tenantId = $this->argument('tenant');
        $year = $this->argument('year');
        $ip = $this->option('ip');
        $domain = $this->option('domain');

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            $this->error("❌ Lanchonete '{$tenantId}' não encontrada!");
            return;
        }

        tenancy()->initialize($tenant);
        $this->info("🔌 Conectando à RunCloud (IP: {$ip}) para resgatar o ZIP de {$year}...");

        $url = "http://{$ip}/wp-content/uploads/nativa_fiscal/{$year}/fiscal.zip";
        $this->info("📥 Baixando de: {$url}");

        // Define a pasta onde será salvo dentro do disco do Tenant
        $directory = "fiscal/{$year}";
        $zipRelativePath = "{$directory}/fiscal.zip";

        // Garante que a pasta existe no disco antes de baixar
        Storage::disk('tenant')->makeDirectory($directory);

        $fullZipPath = Storage::disk('tenant')->path($zipRelativePath);

        // O 'sink' é a mágica: ele escreve direto no arquivo, evitando estouro de memória (RAM)
        $response = Http::withHeaders([
            'Host' => $domain
        ])->timeout(300) // 5 minutos de tolerância para arquivos grandes
          ->sink($fullZipPath)
          ->get($url);

        if ($response->successful()) {
            $this->info("🎉 Sucesso! O arquivo ZIP foi salvo em:");
            $this->comment($fullZipPath);
            $this->info("Agora você pode baixá-lo via SFTP/Painel da Hostinger para o seu computador!");
        } else {
            // Se der erro, excluímos o arquivo corrompido criado pelo sink
            Storage::disk('tenant')->delete($zipRelativePath);
            $this->error("❌ Falha no download. Status HTTP: " . $response->status());
        }

        tenancy()->end();
    }
}
