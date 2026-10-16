<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Tenant;
use App\Models\Customer;
use App\Models\User;
use App\Models\LegacyCustomer;
use App\Models\LegacyImportLog;
use App\Models\Street;
use App\Models\Neighborhood;
use App\Models\StreetSegment;
use App\Models\Address;
use Carbon\Carbon;

class ImportWpCustomers extends Command
{
    protected $signature = 'import:wp-customers {tenant=nativa}';
    protected $description = 'Suga os clientes da V1 (WP), decodifica endereços e vincula estritamente ao mapa existente.';

    public function handle()
    {
        $tenantId = $this->argument('tenant');
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            $this->error("❌ Lanchonete '{$tenantId}' não encontrada!");
            return;
        }

        tenancy()->initialize($tenant);
        $this->info("🔌 Conectando ao Banco V1 (WordPress) e achatando wp_users / wp_usermeta...");

        $wp = DB::connection('wordpress');
        $tag = 'WP_CUST_' . now()->format('Ymd_His');

        $query = "
            SELECT
                u.ID as wp_user_id,
                u.display_name as name,
                u.user_email as email,
                MAX(CASE WHEN m.meta_key IN ('nativa_user_phone', 'billing_phone') THEN m.meta_value END) as phone,
                MAX(CASE WHEN m.meta_key IN ('nativa_user_cpf', 'billing_cpf') THEN m.meta_value END) as cpf,
                MAX(CASE WHEN m.meta_key = 'nativa_user_dob' THEN m.meta_value END) as birth_date,
                MAX(CASE WHEN m.meta_key = 'nativa_user_points' THEN m.meta_value END) as loyalty_points,
                MAX(CASE WHEN m.meta_key IN ('nativa_saved_addresses', 'nativa_user_addresses') THEN m.meta_value END) as raw_addresses
            FROM wp_users u
            LEFT JOIN wp_usermeta m ON u.ID = m.user_id
            GROUP BY u.ID, u.display_name, u.user_email
        ";

        try {
            $wpCustomers = $wp->select($query);
        } catch (\Exception $e) {
            $this->error("❌ Erro ao ler tabelas do WordPress: " . $e->getMessage());
            return;
        }

        $totalCustomers = count($wpCustomers);
        $this->info("👥 {$totalCustomers} clientes encontrados na V1.");

        $importedCount = 0;
        $addressesCount = 0;
        $skippedAddresses = 0;
        $autoMatchedCount = 0;
        $pointsRecovered = 0;
        $usersCreated = 0;

        $bar = $this->output->createProgressBar($totalCustomers);
        $validNicknames = ['Casa', 'Trabalho', 'Amor', 'Pai/Mãe', 'Outro'];

        DB::beginTransaction();
        try {
            foreach ($wpCustomers as $c) {
                $name = trim($c->name ?? '');

                if (empty($name)) {
                    $bar->advance();
                    continue;
                }

                $email = trim($c->email ?? '');
                $phone = preg_replace('/[^0-9]/', '', (string) ($c->phone ?? ''));
                $cpf = preg_replace('/[^0-9]/', '', (string) ($c->cpf ?? ''));
                $loyaltyPoints = (int) ($c->loyalty_points ?? 0);

                $birthDate = null;
                if (!empty($c->birth_date)) {
                    try {
                        $birthDate = Carbon::parse($c->birth_date)->format('Y-m-d');
                    } catch (\Exception $e) {
                        $birthDate = null;
                    }
                }

                // CRIAÇÃO / VÍNCULO DO USUÁRIO DE APP
                $user = null;
                if (!empty($email)) {
                    $user = User::where('email', $email)->first();
                    if (!$user) {
                        $user = User::create([
                            'name' => $name,
                            'email' => $email,
                            'password' => Hash::make(Str::random(24)),
                            'is_active' => true,
                        ]);
                        $usersCreated++;
                    }
                }

                // CRIAÇÃO / ATUALIZAÇÃO DO CLIENTE
                $customer = null;

                if (!empty($cpf)) {
                    $customer = Customer::withTrashed()->where('cpf', $cpf)->first();
                }

                if (!$customer) {
                    $customer = Customer::withTrashed()->where('name', $name)->first();
                }

                if ($customer) {
                    if ($customer->trashed()) $customer->restore();

                    $updateData = [];
                    if (empty($customer->email) && !empty($email)) $updateData['email'] = $email;
                    if (empty($customer->phone) && !empty($phone)) $updateData['phone'] = $phone;
                    if (empty($customer->birth_date) && !empty($birthDate)) $updateData['birth_date'] = $birthDate;
                    if (empty($customer->user_id) && $user) $updateData['user_id'] = $user->id;

                    if ($loyaltyPoints > 0) {
                        $updateData['loyalty_points'] = DB::raw("loyalty_points + {$loyaltyPoints}");
                        $pointsRecovered += $loyaltyPoints;
                    }

                    if (empty($customer->cpf) && !empty($cpf)) {
                        $cpfInUse = Customer::withTrashed()->where('cpf', $cpf)->where('id', '!=', $customer->id)->exists();
                        if (!$cpfInUse) $updateData['cpf'] = $cpf;
                    }

                    if (!empty($updateData)) {
                        $customer->update($updateData);
                    }

                } else {
                    $customer = Customer::create([
                        'name' => $name,
                        'email' => $email ?: null,
                        'phone' => $phone ?: null,
                        'cpf' => $cpf ?: null,
                        'birth_date' => $birthDate,
                        'loyalty_points' => $loyaltyPoints,
                        'user_id' => $user?->id,
                    ]);

                    $pointsRecovered += $loyaltyPoints;
                }

                $importedCount++;

                // ==========================================
                // 🚀 DECODIFICAÇÃO E MAPEAMENTO ESTRITO
                // ==========================================
                if (!empty($c->raw_addresses)) {
                    $addressArray = @unserialize($c->raw_addresses);

                    if (is_array($addressArray)) {
                        foreach ($addressArray as $addr) {
                            // Decodifica a URL (ex: Am%C3%A2ndio -> Amândio)
                            $streetName = trim(urldecode($addr['street'] ?? ''));
                            if (empty($streetName)) continue;

                            // Tenta encontrar a rua exata na base de dados (Sem Create!)
                            $street = Street::where('name', 'LIKE', $streetName)->first();
                            if (!$street) {
                                $skippedAddresses++;
                                continue;
                            }

                            $districtName = trim(urldecode($addr['district'] ?? $addr['bairro'] ?? ''));
                            $neighborhood = null;

                            if (!empty($districtName)) {
                                $neighborhood = Neighborhood::where('name', 'LIKE', $districtName)->first();
                            }

                            // Se não achou pelo nome, tenta deduzir pela tabela street_segments
                            if (!$neighborhood) {
                                $segment = StreetSegment::where('street_id', $street->id)->first();
                                if ($segment) {
                                    $neighborhood = Neighborhood::find($segment->neighborhood_id);
                                }
                            }

                            // Se definitivamente não achou o bairro no mapa atual, pula o endereço
                            if (!$neighborhood) {
                                $skippedAddresses++;
                                continue;
                            }

                            $number = trim(urldecode($addr['number'] ?? 'S/N'));
                            if (empty($number)) $number = 'S/N';

                            $nickname = trim(urldecode($addr['nickname'] ?? $addr['apelido'] ?? 'Casa'));
                            if (!in_array($nickname, $validNicknames)) {
                                $nickname = 'Outro';
                            }

                            $customer->addresses()->updateOrCreate(
                                [
                                    'street_id' => $street->id,
                                    'number' => $number,
                                ],
                                [
                                    'neighborhood_id' => $neighborhood->id,
                                    'nickname' => $nickname,
                                    'complement' => urldecode($addr['complement'] ?? null),
                                    'reference' => urldecode($addr['reference'] ?? null),
                                    'is_active' => true,
                                ]
                            );

                            $addressesCount++;
                        }
                    }
                }

                $updated = LegacyCustomer::whereRaw('LOWER(legacy_name) = ?', [strtolower($name)])
                    ->whereNull('customer_id')
                    ->update(['customer_id' => $customer->id]);

                $autoMatchedCount += $updated;

                $bar->advance();
            }

            LegacyImportLog::create([
                "import_tag" => $tag,
                "type" => "v1_customers_db",
                "items_count" => $importedCount,
            ]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("\n❌ Erro durante a importação: " . $e->getMessage());
            return;
        }

        $bar->finish();

        $this->info("\n\n🎉 Importação de Clientes concluída: {$tag}");
        $this->info("=================================================");
        $this->info("Clientes na V3 (Novos/Atualizados): {$importedCount}");
        $this->info("Contas de App Criadas (Users):      {$usersCreated}");
        $this->info("E-mails e Pontos transferidos:      " . number_format($pointsRecovered, 0, ',', '.') . " pontos!");
        $this->info("Endereços 100% compátiveis:         {$addressesCount}");
        $this->info("Endereços ignorados (Fora do mapa): {$skippedAddresses}");
        $this->info("Órfãos (Legacy) Auto-Vinculados:    {$autoMatchedCount}");
        $this->info("=================================================\n");

        tenancy()->end();
    }
}
