<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\HasTenants;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles; // <-- 1. IMPORTAMOS A BIBLIOTECA AQUI

class User extends Authenticatable implements HasTenants, FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles; // <-- 2. INJETAMOS NA CLASSE AQUI

    protected $fillable = [
        'name',
        'email',
        'password',
        'pin', // <-- 3. ADICIONADO O PIN AQUI (Evita erros no futuro)
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Permite o acesso ao painel
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /**
     * Define a quais Tenants (Lanchonetes) este usuário tem acesso.
     */
    public function getTenants(Panel $panel): array|Collection
    {
        return collect([tenant()]);
    }

    /**
     * Autorização: O usuário pode aceder a este Tenant específico?
     */
    public function canAccessTenant(Model $tenant): bool
    {
        return true; 
    }
}