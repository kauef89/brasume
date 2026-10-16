<?php

namespace App\Filament\Resources\BankStatements\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use App\Models\BankAccount;
use App\Models\BankStatement;
use App\Models\AccountPayable;
use App\Models\Supplier;
use App\Services\BankDictionaries\BankDictionaryFactory;
use App\Services\Accounting\BankMatchScoringService;
use Filament\Notifications\Notification;
use Carbon\Carbon;

class ImportOfxAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return "import_ofx";
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label("Importar OFX")
            ->icon("heroicon-o-arrow-up-tray")
            ->color("primary")
            ->form([
                Select::make("bank_account_id")
                    ->label("Conta Bancária de Destino")
                    ->options(BankAccount::pluck("name", "id"))
                    ->required()
                    ->searchable()
                    ->preload(),

                FileUpload::make("ofx_file")
                    ->label("Arquivo(s) OFX")
                    ->multiple() // 🚀 A MÁGICA AQUI: Agora aceita dezenas de arquivos de uma vez!
                    ->storeFiles(false)
                    ->required(),
            ])
            ->action(function (array $data) {
                $accountId = $data["bank_account_id"];
                $uploadedFiles = $data["ofx_file"];

                // Garante que é sempre um array para podermos fazer o loop
                if (!is_array($uploadedFiles)) {
                    $uploadedFiles = [$uploadedFiles];
                }

                $account = BankAccount::find($accountId);
                $dictionary = BankDictionaryFactory::make($account?->bank_code);
                $BankMatchScoringService = new BankMatchScoringService();

                $importedCount = 0;
                $autoFeesCount = 0;
                $autoMatchesCount = 0;
                $autoPersonalCount = 0;

                // 🚀 NOVO LOOP: Passa por todos os arquivos arrastados para a caixa!
                foreach ($uploadedFiles as $uploadedFile) {
                    $content = file_get_contents($uploadedFile->getRealPath());

                    $blocks = explode("<STMTTRN>", $content);
                    array_shift($blocks);

                    foreach ($blocks as $txn) {
                        preg_match("/<DTPOSTED>([^<]+)/i", $txn, $dRaw);
                        preg_match("/<TRNAMT>([^<]+)/i", $txn, $amt);
                        preg_match("/<FITID>([^<]+)/i", $txn, $fid);
                        preg_match('/<MEMO>([^<\r\n]+)/i', $txn, $mem);

                        if (!empty($fid[1])) {
                            $dateStr = substr(trim($dRaw[1]), 0, 8);
                            $date = Carbon::createFromFormat("Ymd", $dateStr)->format("Y-m-d");
                            $val = (float) trim($amt[1]);
                            $rawMemo = trim($mem[1] ?? "S/D");

                            $parsedData = $dictionary->parseMemo($rawMemo);
                            $counterparty = empty($parsedData["name"])
                                ? $account->name ?? "Desconhecido"
                                : $parsedData["name"];

                            // 🛡️ A BARREIRA ANTIDUPLICAÇÃO: O firstOrCreate trava repetições baseadas no FITID!
                            $stmt = BankStatement::firstOrCreate(
                                ["fitid" => trim($fid[1])],
                                [
                                    "bank_account_id" => $accountId,
                                    "bank_date" => $date,
                                    "description" => $parsedData["clean_description"],
                                    "document" => $parsedData["document"],
                                    "statement_identifier" => $parsedData["statement_identifier"] ?? null,
                                    "movement_type" => $parsedData["movement_type"] ?? "other",
                                    "counterparty_name" => $counterparty,
                                    "amount" => $val,
                                    "type" => $val >= 0 ? "credit" : "debit",
                                    "status" => "pending",
                                ],
                            );

                            // O sistema só incrementa contadores e faz conciliação se a linha for efetivamente NOVA
                            if ($stmt->wasRecentlyCreated) {
                                $importedCount++;

                                $matchedSupplier = null;
                                if ($stmt->document) {
                                    $matchedSupplier = Supplier::where("document", $stmt->document)->first();
                                }
                                if (!$matchedSupplier) {
                                    $matchedSupplier = Supplier::where("name", $stmt->counterparty_name)
                                        ->orWhere("document_name", $stmt->counterparty_name)
                                        ->first();
                                }

                                if ($matchedSupplier && $matchedSupplier->is_personal && $stmt->amount < 0) {
                                    $desc = "Uso Pessoal: " . ($stmt->description ?: $stmt->counterparty_name);
                                    $payable = AccountPayable::create([
                                        "description" => $desc,
                                        "supplier_id" => $matchedSupplier->id,
                                        "amount" => abs((float) $stmt->amount),
                                        "due_date" => $stmt->bank_date,
                                        "is_personal" => true,
                                        "accounting_category" => "socios",
                                        "accounting_subcategory" => "pessoal",
                                    ]);

                                    \App\Services\Accounting\BankReconciliationService::finalize($stmt, $payable, $matchedSupplier->id, $desc);
                                    $autoPersonalCount++;
                                }
                                elseif ($stmt->movement_type === "bank_fee" && $stmt->amount < 0) {
                                    $desc = $stmt->description ?: "Tarifa Bancária";
                                    $payable = AccountPayable::create([
                                        "description" => $desc,
                                        "amount" => abs((float) $stmt->amount),
                                        "due_date" => $stmt->bank_date,
                                        "accounting_category" => "adm",
                                        "accounting_subcategory" => "taxas",
                                    ]);

                                    \App\Services\Accounting\BankReconciliationService::finalize($stmt, $payable, null, $desc);
                                    $autoFeesCount++;
                                }
                                elseif ($stmt->amount < 0) {
                                    $matches = $BankMatchScoringService->findMatchesFor($stmt);
                                    if ($matches->isNotEmpty() && $matches->first()["score"] >= 95) {
                                        $payable = $matches->first()["candidate"];

                                        \App\Services\Accounting\BankReconciliationService::finalize($stmt, $payable, $payable->supplier_id);
                                        $autoMatchesCount++;
                                    }
                                }
                            }
                        }
                    }
                } // Fim do loop de arquivos

                if ($importedCount === 0) {
                    Notification::make()
                        ->title("Nenhuma transação nova!")
                        ->body("O sistema processou os ficheiros e todas as transações já existiam na base de dados.")
                        ->info()
                        ->send();
                    return;
                }

                $message = "Foram adicionadas {$importedCount} transações.";
                if ($autoFeesCount > 0 || $autoMatchesCount > 0 || $autoPersonalCount > 0) {
                    $message .= " O sistema auto-conciliou ";
                    if ($autoMatchesCount > 0) {
                        $message .= "{$autoMatchesCount} pagamentos exatos, ";
                    }
                    if ($autoPersonalCount > 0) {
                        $message .= "{$autoPersonalCount} despesas de sócios, ";
                    }
                    if ($autoFeesCount > 0) {
                        $message .= "{$autoFeesCount} taxas bancárias.";
                    }
                }

                Notification::make()
                    ->title("Importação Inteligente!")
                    ->body(trim($message, ", "))
                    ->success()
                    ->send();
            });
    }
}
