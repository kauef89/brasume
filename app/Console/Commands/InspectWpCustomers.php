<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InspectWpCustomers extends Command
{
    protected $signature = 'wp:inspect-customers';
    protected $description = 'Investiga onde estão os dados dos clientes e e-mails no banco do WordPress';

    public function handle()
    {
        $wp = DB::connection('wordpress');

        $this->info("🔍 1. Analisando a tabela wp_users e wp_usermeta (Clientes Registrados)...\n");

        try {
            // Pega 3 usuários aleatórios para amostra
            $users = $wp->table('users')->limit(3)->get();

            if ($users->isNotEmpty()) {
                foreach ($users as $user) {
                    $this->warn("\n👤 Usuário ID: {$user->ID} | display_name: '{$user->display_name}' | user_email: '{$user->user_email}'");

                    $metas = $wp->table('usermeta')
                        ->where('user_id', $user->ID)
                        ->pluck('meta_value', 'meta_key')
                        ->toArray();

                    foreach ($metas as $key => $val) {
                        // Ignora lixo do sistema do WP
                        if (str_contains($key, 'capabilities') || str_contains($key, 'session_tokens') || str_contains($key, 'user_level') || str_contains($key, 'nav_menu')) continue;

                        $valStr = is_string($val) ? substr($val, 0, 80) : json_encode($val);
                        $this->line("   - {$key}: " . $valStr);
                    }
                }
            } else {
                $this->error("Tabela 'wp_users' está vazia.");
            }
        } catch (\Exception $e) {
            $this->error("Erro ao ler tabela 'users': " . $e->getMessage());
        }

        $this->line("\n" . str_repeat("=", 50) . "\n");

        $this->info("🔍 2. Buscando Metadados em Pedidos (Possíveis Guest Checkouts em wp_postmeta)...\n");

        try {
            // Procura metas de clientes (nome, email, telefone) atrelados a pedidos
            $guestMetas = $wp->table('postmeta')
                ->whereIn('meta_key', ['billing_email', '_billing_email', 'billing_first_name', '_billing_first_name', 'billing_phone', '_billing_phone', 'nativa_user_cpf'])
                ->whereNotNull('meta_value')
                ->where('meta_value', '!=', '')
                ->limit(10) // Pega algumas amostras
                ->get();

            if ($guestMetas->isNotEmpty()) {
                $groupedByPost = $guestMetas->groupBy('post_id');

                foreach ($groupedByPost as $postId => $metas) {
                    $this->warn("\n🛒 Pedido ID (Post ID): {$postId}");
                    foreach ($metas as $meta) {
                        $this->line("   - {$meta->meta_key}: {$meta->meta_value}");
                    }
                }
            } else {
                $this->line("Nenhum dado de cliente encontrado atrelado diretamente aos pedidos.");
            }
        } catch (\Exception $e) {
             $this->error("Erro ao ler tabela 'postmeta': " . $e->getMessage());
        }
    }
}
