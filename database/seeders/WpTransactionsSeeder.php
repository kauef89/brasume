<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Transaction;
use App\Models\BankAccount;
use App\Models\PaymentMethod;
use App\Models\Order;

class WpTransactionsSeeder extends Seeder
{
    public function run(): void
    {
        Transaction::unguard();

        // Limpeza de segurança
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('transactions')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $csvFile = base_path('storage/app/cash-movements.csv');
        
        if (!file_exists($csvFile)) {
            $this->command->error("Arquivo {$csvFile} não encontrado!");
            return;
        }

        // Descobre o Cofre para jogar o dinheiro físico
        $cofre = BankAccount::where('type', 'vault')->first();

        $file = fopen($csvFile, 'r');
        fgetcsv($file); // Pula o cabeçalho
        $count = 0;

        while (($row = fgetcsv($file)) !== false) {
            // fgetcsv lida perfeitamente com vírgulas dentro de textos entre aspas!
            if (!isset($row[0])) continue;

            $dataHora = trim($row[1]);
            $operador = trim($row[2]);
            $sessaoCaixa = trim($row[3]);
            $tipoMovimento = trim($row[5]);
            $formaPagamento = trim($row[6]);
            $valor = (float) trim($row[7]);
            $descricao = trim($row[8]);
            $pedidoId = trim($row[9]);

            // Define se é Entrada (in) ou Saída (out)
            $type = in_array($tipoMovimento, ['sale', 'supply']) ? 'in' : 'out';

            // Define a Conta Bancária e o Método de Pagamento
            $bankAccountId = null;
            $paymentMethodId = null;

            if ($formaPagamento === 'money') {
                $bankAccountId = $cofre ? $cofre->id : null;
            } elseif (is_numeric($formaPagamento)) {
                $paymentMethodId = (int) $formaPagamento;
                $method = PaymentMethod::find($paymentMethodId);
                if ($method) {
                    $bankAccountId = $method->destination_account_id;
                }
            }

            // Verifica se o pedido existe na base de dados para fazer a ligação
            $orderId = (is_numeric($pedidoId) && Order::where('id', $pedidoId)->exists()) ? (int) $pedidoId : null;

            Transaction::create([
                'bank_account_id' => $bankAccountId,
                'payment_method_id' => $paymentMethodId,
                'order_id' => $orderId,
                'type' => $type,
                'amount' => $valor,
                'description' => $descricao,
                'operator_name' => $operador,
                'legacy_session_id' => $sessaoCaixa,
                'transaction_date' => $dataHora,
            ]);
            
            $count++;
        }
        fclose($file);

        $this->command->info("==== SUCESSO ====");
        $this->command->line("- {$count} Movimentações injetadas com sucesso nas Contas Bancárias!");
    }
}