<?php

use App\Models\Tenant;
use App\Models\CompanySetting;
use Illuminate\Support\Facades\File;

echo "\n=========================================================\n";
echo "⚙️  EXPORTANDO COMPANY SETTINGS (TENANT: NATIVA)\n";
echo "=========================================================\n\n";

// 1. Encontra e inicializa a Lanchonete 'nativa'
$tenant = Tenant::find('nativa');

if ($tenant) {
    tenancy()->initialize($tenant);

    // 2. Busca o registro único de configurações
    $settings = CompanySetting::getCached();

    if ($settings) {
        // 3. Converte para JSON bonitinho e fácil de ler
        $jsonContent = json_encode($settings->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // 4. Define o caminho para salvar na RAIZ do projeto
        $filePath = base_path('company_settings_snapshot.json');

        // 5. Salva o arquivo sobrescrevendo o anterior
        File::put($filePath, $jsonContent);

        echo "✅ Snapshot das configurações gerado com sucesso!\n";
        echo "📂 Salvo em: {$filePath}\n\n";
    } else {
        echo "⚠️ Aviso: A tabela company_settings está vazia neste tenant.\n\n";
    }

    tenancy()->end();
} else {
    echo "❌ Erro: Tenant 'nativa' não encontrado no banco de dados.\n\n";
}
