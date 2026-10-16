<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property array<array-key, mixed>|null $data
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Stancl\Tenancy\Database\Models\Domain> $domains
 * @property-read int|null $domains_count
 * @method static \Stancl\Tenancy\Database\TenantCollection<int, static> all($columns = ['*'])
 * @method static \Stancl\Tenancy\Database\TenantCollection<int, static> get($columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tenant newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tenant newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tenant query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tenant whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tenant whereData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tenant whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tenant whereUpdatedAt($value)
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains, SoftDeletes;

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        // Este evento dispara automaticamente LOGO APÓS um novo tenant ser criado no banco
        static::created(function ($tenant) {

            // Pega o ID da lanchonete recém-criada (ex: 'burguer')
            $tenantId = $tenant->id;

            $caminhos = [
                storage_path("tenants/{$tenantId}/framework/cache"),
                storage_path("tenants/{$tenantId}/framework/views"),
                storage_path("tenants/{$tenantId}/framework/sessions"),
                storage_path("tenants/{$tenantId}/app/livewire-tmp"),
                // Pode adicionar a pasta de certificados aqui também, se quiser!
                storage_path("tenants/{$tenantId}/app/certificates"),
            ];

            foreach ($caminhos as $caminho) {
                if (!file_exists($caminho)) {
                    // Cria a pasta, dá a permissão 775 e true (para criar as subpastas recursivamente)
                    mkdir($caminho, 0775, true);
                }
            }
        });
    }
}
