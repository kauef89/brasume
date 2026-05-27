<?php

namespace App\Filament\Resources\LegacyOrders\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\Storage;
use App\Models\LegacyCustomer;
use App\Models\LegacyNeighborhood;
use App\Models\LegacyProduct;
use App\Models\LegacyPaymentMethod;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\Facades\Log;

class ScanV1CsvAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'scan_v1_csv';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('1. Varrer CSV V1 (Mapeamento)')
            ->icon('heroicon-o-magnifying-glass-circle')
            ->color('info')
            ->modalHeading('Scanner Preparatório (Fase 1)')
            ->modalDescription('Faça o upload do CSV da V1. O sistema irá varrer todas as linhas para descobrir Clientes, Bairros, Produtos e Formas de Pagamento únicos.')
            ->modalSubmitActionLabel('Iniciar Varredura')
            ->modalFooterActionsAlignment(Alignment::End)
            ->form([
                FileUpload::make('csv_file')
                    ->label('Ficheiro CSV (V1)')
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv'])
                    ->disk('local')
                    ->directory('csv_imports')
                    ->required(),
            ])
            ->action(function (array $data) {
                // Previne problemas com quebras de linha do Mac/Windows antigo
                ini_set('auto_detect_line_endings', true);

                $filePath = Storage::disk('local')->path($data['csv_file']);

                $handle = fopen($filePath, 'r');
                if (!$handle) {
                    Notification::make()->title('Erro ao abrir o ficheiro.')->danger()->send();
                    return;
                }

                // Deteta automaticamente se o separador é vírgula ou ponto-e-vírgula
                $firstLine = fgets($handle);
                $delimiter = strpos($firstLine, ';') !== false ? ';' : ',';
                rewind($handle); // Volta ao início do ficheiro

                // Lê o cabeçalho
                $header = fgetcsv($handle, 0, $delimiter);

                $uniqueCustomers = [];
                $uniqueNeighborhoods = [];
                $uniqueProducts = [];
                $uniquePayments = [];

                // As formas de pagamento começam no índice 14 ("CC SIC")
                $paymentStartIndex = 14;
                for ($i = $paymentStartIndex; $i < count($header); $i++) {
                    $paymentName = trim($header[$i]);
                    if (!empty($paymentName)) {
                        $uniquePayments[$paymentName] = true;
                    }
                }

                // Varrer as Linhas
                $lineNumber = 1;
                while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                    $lineNumber++;

                    // Se a linha não tem colunas suficientes para chegar ao Item (índice 7), ignora
                    if (count($row) < 8) {
                        continue;
                    }

                    // Usa exatamente os índices que você definiu no seu mapa:
                    $customer = trim($row[1] ?? ''); // Coluna 2
                    $neighborhood = trim($row[2] ?? ''); // Coluna 3
                    $product = trim($row[7] ?? ''); // Coluna 8

                    if (!empty($customer)) {
                        $uniqueCustomers[$customer] = true;
                    }
                    if (!empty($neighborhood)) {
                        $uniqueNeighborhoods[$neighborhood] = true;
                    }
                    if (!empty($product)) {
                        $uniqueProducts[$product] = true;
                    }
                }

                fclose($handle);

                // ==========================================
                // SALVAR NA BASE DE DADOS E CONTAR
                // ==========================================
                $counters = [
                    'customers_new' => 0, 'customers_total' => count($uniqueCustomers),
                    'neighborhoods_new' => 0, 'neighborhoods_total' => count($uniqueNeighborhoods),
                    'products_new' => 0, 'products_total' => count($uniqueProducts),
                    'payments_new' => 0, 'payments_total' => count($uniquePayments),
                ];

                foreach (array_keys($uniqueCustomers) as $c) {
                    if (LegacyCustomer::firstOrCreate(['legacy_name' => $c])->wasRecentlyCreated) $counters['customers_new']++;
                }

                foreach (array_keys($uniqueNeighborhoods) as $n) {
                    if (LegacyNeighborhood::firstOrCreate(['legacy_name' => $n])->wasRecentlyCreated) $counters['neighborhoods_new']++;
                }

                foreach (array_keys($uniqueProducts) as $p) {
                    if (LegacyProduct::firstOrCreate(['legacy_code' => $p], ['name' => $p])->wasRecentlyCreated) $counters['products_new']++;
                }

                foreach (array_keys($uniquePayments) as $pay) {
                    if (LegacyPaymentMethod::firstOrCreate(['name' => $pay])->wasRecentlyCreated) $counters['payments_new']++;
                }

                Storage::disk('local')->delete($data['csv_file']);

                // Relatório Rico
                Notification::make()
                    ->title('Varredura Concluída!')
                    ->body(
                        "Foram lidas <b>" . ($lineNumber - 1) . " linhas de dados</b>.<br><br>" .
                        "<b>Clientes:</b> {$counters['customers_new']} novos (Lidos: {$counters['customers_total']})<br>" .
                        "<b>Bairros:</b> {$counters['neighborhoods_new']} novos (Lidos: {$counters['neighborhoods_total']})<br>" .
                        "<b>Produtos:</b> {$counters['products_new']} novos (Lidos: {$counters['products_total']})<br>" .
                        "<b>Pagamentos:</b> {$counters['payments_new']} novos (Lidos: {$counters['payments_total']})"
                    )
                    ->success()
                    ->duration(15000)
                    ->send();
            });
    }
}
