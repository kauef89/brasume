<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ExpireLoyaltyPoints extends Command
{
    protected $signature = 'loyalty:expire';
    protected $description = 'Zera os pontos de fidelidade que ultrapassaram a data limite de expiração.';

    public function handle(): int
    {
        $now = Carbon::now();

        // 🚀 Atualização atômica direta no banco do tenant ativo
        $affected = Customer::whereNotNull('loyalty_points_expires_at')
            ->where('loyalty_points_expires_at', '<', $now)
            ->where('loyalty_points', '>', 0)
            ->update([
                'loyalty_points' => 0,
                'loyalty_points_expires_at' => null,
            ]);

        if ($affected > 0) {
            $msg = "Expiração de Fidelidade: {$affected} cliente(s) tiveram o saldo zerado.";
            Log::info($msg);
            $this->info($msg);
        }

        return self::SUCCESS;
    }
}
