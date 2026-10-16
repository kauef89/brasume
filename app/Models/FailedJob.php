<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Tenant;

class FailedJob extends Model
{
    protected $connection = 'mysql';
    protected $table = 'failed_jobs';
    public $timestamps = false;

    protected $casts = [
        'failed_at' => 'datetime',
        'payload' => 'array',
    ];

    /**
     * Tenta identificar o nome/ID do Tenant dentro do payload do Job.
     */
    public function getTenantIdentifierAttribute(): string
    {
        $payload = $this->payload;

        if (is_array($payload)) {
            // Tenta localizar o tenant_id no payload serializado
            if (isset($payload['tenant_id'])) {
                return $payload['tenant_id'];
            }

            // Se for um Job do Stancl Tenancy, o tenant_id costuma estar no comando serializado
            if (isset($payload['data']['command'])) {
                if (preg_match('/"tenant_id";s:\d+:"([^"]+)"/', $payload['data']['command'], $matches)) {
                    return $matches[1];
                }
            }
        }

        return 'Sistema / Central';
    }

    /**
     * Extrai o nome legível da classe do Job (ex: ProcessOrderPrintJob)
     */
    public function getJobNameAttribute(): string
    {
        $payload = $this->payload;

        if (is_array($payload)) {
            if (isset($payload['displayName'])) {
                return class_basename($payload['displayName']);
            }
        }

        return 'Job Desconhecido';
    }
}
