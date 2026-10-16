<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use App\Models\Tenant;

echo "Iniciando mapeamento do banco de dados...\n";

// 1. Inicializa o Tenant para garantir que estamos a ler o banco de dados correto
$tenant = Tenant::find('nativa');
if ($tenant) {
    tenancy()->initialize($tenant);
    echo "Tenant 'nativa' inicializado com sucesso.\n";
} else {
    echo "Aviso: Tenant 'nativa' não encontrado. Lendo o banco padrão...\n";
}

// 2. Busca todas as tabelas do banco atual (MySQL)
$tables = DB::select('SHOW TABLES');
$map = [];

foreach ($tables as $table) {
    // Pega o nome da tabela dinamicamente (pois a chave do array depende do nome do DB)
    $tableName = ((array)$table)[array_key_first((array)$table)];

    // Busca a estrutura de colunas daquela tabela
    $columns = DB::select("SHOW COLUMNS FROM `{$tableName}`");

    $map[$tableName] = $columns;
}

// 3. Converte para JSON formatado e legível
$jsonContent = json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// 4. Define o caminho do arquivo
// O Stancl Tenancy geralmente usa storage/tenant_id/app/... mas vou respeitar o caminho exato que você pediu.
// Se "tenants/nativa/tenants/nativa" for um erro de digitação, você pode ajustar a string abaixo.
$filePath = base_path('storage/tenants/nativa/app/mapa_banco_de_dados.json');

// Garante que a pasta existe antes de salvar
File::ensureDirectoryExists(dirname($filePath));

// Salva o arquivo sobrescrevendo o antigo
File::put($filePath, $jsonContent);

echo "✅ Mapa do banco atualizado com sucesso com " . count($map) . " tabelas!\n";
echo "📂 Salvo em: {$filePath}\n";
