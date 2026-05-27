<?php

declare(strict_types=1);

use Stancl\Tenancy\Database\Models\Domain;

return [
    // ALTERADO: Agora aponta para o SEU model que tem o trait HasDatabase
    'tenant_model' => \App\Models\Tenant::class,

    'id_generator' => Stancl\Tenancy\UUIDGenerator::class,

    'domain_model' => Domain::class,

    /**
     * The list of domains hosting your central app.
     */
    'central_domains' => [
    'brasume.com.br',      // O domínio principal
    'app.brasume.com.br',
    ],

    /**
     * Tenancy bootstrappers.
     */
    'bootstrappers' => [
        Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
    ],

    /**
     * Database tenancy config.
     */
    'database' => [
        // AJUSTADO: Usa 'mysql' que é o que configuramos no seu .env
        'central_connection' => env('DB_CONNECTION', 'mysql'),

        'template_tenant_connection' => null,

        /**
         * Tenant database names: prefix + tenant_id + suffix.
         */
        'suffix_base' => 'tenants/',

        'managers' => [
            'sqlite' => Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager::class,
            'mysql' => Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager::class,
            'pgsql' => Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLDatabaseManager::class,
        ],
    ],

    'cache' => [
        'tag_base' => 'tenant',
    ],

    'filesystem' => [
        // A MÁGICA ESTÁ AQUI: Mudamos de 'tenant' para 'tenants/' (com a barra no final)
        'suffix_base' => 'tenants/',

        'disks' => [
            'local',
            'public',
        ],
        'root_override' => [
            'local' => '%storage_path%/app/',
            'public' => '%storage_path%/app/public/',
        ],
        'suffix_storage_path' => true,
        'asset_helper_tenancy' => false,
    ],

    'redis' => [
        'prefix_base' => 'tenant',
        'prefixed_connections' => [],
    ],

    'features' => [
        // Stancl\Tenancy\Features\ViteBundler::class, // Útil para o seu PDV no futuro
    ],

    'routes' => true,

    'migration_parameters' => [
        '--force' => true,
        '--path' => [database_path('migrations/tenant')],
        '--realpath' => true,
    ],

    'seeder_parameters' => [
        '--class' => 'DatabaseSeeder',
    ],
];
