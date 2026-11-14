<?php
$configFile = 'config/backup.php';
$content = file_get_contents($configFile);

if (!str_contains($content, "Stancl\Tenancy\Tenancy")) {
    $tenantCode = <<<CODE
        /*
         * As conexões de banco de dados nativas que o pacote deve fazer backup.
         * Injetado automaticamente os tenants pelo stancl/tenancy.
         */
        'databases' => array_merge(
            [
                'mysql', // O Banco de Dados Central
            ],
            array_map(function (\$tenant) {
                return 'tenant'; // Ou gere os nomes dinâmicos conforme sua config do stancl
            }, \App\Models\Tenant::all()->toArray() ?? [])
        ),
CODE;

    // Substituir a configuração estática 'databases' => ['mysql'] pelo array dinâmico
    $content = preg_replace(
        "/'databases' => \[\s*'mysql',\s*\],/",
        $tenantCode,
        $content
    );
    file_put_contents($configFile, $content);
    echo "✔ Configuração multi-tenant injetada no config/backup.php\n";
} else {
    echo "✔ Configuração multi-tenant já existe no backup.php\n";
}
