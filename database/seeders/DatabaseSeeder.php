<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Limpa o cache do Spatie para evitar conflitos ao rodar o seeder várias vezes
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ==========================================
        // 🛡️ 2. CRIAÇÃO DE PERMISSÕES
        // ==========================================
        $autorizarOperacoes = Permission::firstOrCreate(['name' => 'pos.autorizar_operacoes', 'guard_name' => 'web']);
        // Pode adicionar outras permissões aqui no futuro (ex: 'pos.sangria', 'pos.desconto')

        // ==========================================
        // 👥 3. CRIAÇÃO DE CARGOS (ROLES)
        // ==========================================
        // Liderança
        $admin   = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $gerente = Role::firstOrCreate(['name' => 'gerente', 'guard_name' => 'web']);
        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);

        // Operação de Caixa
        $caixa   = Role::firstOrCreate(['name' => 'caixa', 'guard_name' => 'web']);
        $pos     = Role::firstOrCreate(['name' => 'pos', 'guard_name' => 'web']);

        // Operação de Salão
        $garcom  = Role::firstOrCreate(['name' => 'garcom', 'guard_name' => 'web']);
        $waiter  = Role::firstOrCreate(['name' => 'waiter', 'guard_name' => 'web']);

        // ==========================================
        // 🔗 4. VÍNCULO DE PERMISSÕES AOS CARGOS
        // ==========================================
        $admin->givePermissionTo($autorizarOperacoes);
        $gerente->givePermissionTo($autorizarOperacoes);
        $manager->givePermissionTo($autorizarOperacoes);

        // ==========================================
        // 👤 5. UTILIZADOR PADRÃO DE TESTE/ADMIN
        // ==========================================
        $adminUser = User::firstOrCreate(
            ['email' => 'test@example.com'], // O e-mail que já estava no seu seeder
            [
                'name' => 'Test User (Admin)',
                'password' => Hash::make('password'),
                'pin' => Hash::make('1234'), // 🚀 PIN já encriptado corretamente com Bcrypt!
                'is_active' => true,
            ]
        );

        // Garante que o utilizador de teste seja admin para não ficar trancado de fora
        if (!$adminUser->hasRole('admin')) {
            $adminUser->assignRole($admin);
        }
    }
}
