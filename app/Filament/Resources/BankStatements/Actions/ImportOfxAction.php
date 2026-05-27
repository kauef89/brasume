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
use App\Services\ReconciliationService;
use Illuminate\Support\Facades\Storage;
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
                    ->label("Arquivo OFX")
                    ->disk("public")
                    ->directory("temp_ofx_imports")
                    ->required(),
            ])
            ->action(function (array $data) {
                $filePath = $data["ofx_file"];
                $accountId = $data["bank_account_id"];

                $account = BankAccount::find($accountId);
                $dictionary = BankDictionaryFactory::make($account?->bank_code);
                $reconciliationService = new ReconciliationService();

                $content = Storage::disk("public")->get($filePath);
                $blocks = explode("<STMTTRN>", $content);
                array_shift($blocks);

                $importedCount = 0;
                $autoFeesCount = 0;
                $autoMatchesCount = 0;
                $autoPersonalCount = 0; // Contador de sócios

                foreach ($blocks as $txn) {
                    preg_match("/<DTPOSTED>([^<]+)/i", $txn, $dRaw);
                    preg_match("/<TRNAMT>([^<]+)/i", $txn, $amt);
                    preg_match("/<FITID>([^<]+)/i", $txn, $fid);
                    preg_match('/<MEMO>([^<\r\n]+)/i', $txn, $mem);

                    if (!empty($fid[1])) {
                        $dateStr = substr(trim($dRaw[1]), 0, 8);
                        $date = Carbon::createFromFormat(
                            "Ymd",
                            $dateStr,
                        )->format("Y-m-d");
                        $val = (float) trim($amt[1]);
                        $rawMemo = trim($mem[1] ?? "S/D");

                        $parsedData = $dictionary->parseMemo($rawMemo);
                        $counterparty = empty($parsedData["name"])
                            ? $account->name ?? "Desconhecido"
                            : $parsedData["name"];

                        $stmt = BankStatement::firstOrCreate(
                            ["fitid" => trim($fid[1])],
                            [
                                "bank_account_id" => $accountId,
                                "bank_date" => $date,
                                "description" =>
                                    $parsedData["clean_description"],
                                "document" => $parsedData["document"],
                                "statement_identifier" =>
                                    $parsedData["statement_identifier"] ?? null,
                                "movement_type" =>
                                    $parsedData["movement_type"] ?? "other",
                                "counterparty_name" => $counterparty,
                                "amount" => $val,
                                "type" => $val >= 0 ? "credit" : "debit",
                                "status" => "pending",
                            ],
                        );

                        if ($stmt->wasRecentlyCreated) {
                            $importedCount++;

                            // Busca no banco de dados se esse nome do extrato bate com um Fornecedor de USO PESSOAL
                            $matchedSupplier = null;
                            if ($stmt->document) {
                                $matchedSupplier = Supplier::where(
                                    "document",
                                    $stmt->document,
                                )->first();
                            }
                            // Tenta bater pelo nome
                            if (!$matchedSupplier) {
                                $matchedSupplier = Supplier::where(
                                    "name",
                                    $stmt->counterparty_name,
                                )
                                    ->orWhere(
                                        "document_name",
                                        $stmt->counterparty_name,
                                    )
                                    ->first();
                            }

                            // ==========================================
                            // 🚀 ZERO-TOUCH 1: USO PESSOAL (SÓCIOS)
                            // ==========================================
                            if (
                                $matchedSupplier &&
                                $matchedSupplier->is_personal &&
                                $stmt->amount < 0
                            ) {
                                $payable = AccountPayable::create([
                                    "description" =>
                                        "Uso Pessoal: " .
                                        ($stmt->description ?:
                                            $stmt->counterparty_name),
                                    "supplier_id" => $matchedSupplier->id,
                                    "amount" => abs((float) $stmt->amount),
                                    "due_date" => $stmt->bank_date,
                                    "payment_date" => $stmt->bank_date,
                                    "status" => "paid",
                                    "accounting_category" => "socios", // 🚀 DRE Perfeito
                                    "accounting_subcategory" => "pessoal",
                                ]);

                                $stmt->update([
                                    "status" => "reconciled",
                                    "conciliable_id" => $payable->id,
                                    "conciliable_type" => AccountPayable::class,
                                ]);
                                $autoPersonalCount++;
                            }
                            // ==========================================
                            // 🚀 ZERO-TOUCH 2: TAXAS BANCÁRIAS
                            // ==========================================
                            elseif (
                                $stmt->movement_type === "bank_fee" &&
                                $stmt->amount < 0
                            ) {
                                $payable = AccountPayable::create([
                                    "description" =>
                                        $stmt->description ?: "Tarifa Bancária",
                                    "amount" => abs((float) $stmt->amount),
                                    "due_date" => $stmt->bank_date,
                                    "payment_date" => $stmt->bank_date,
                                    "status" => "paid",
                                    "accounting_category" => "adm", // 🚀 DRE Perfeito
                                    "accounting_subcategory" => "taxas",
                                ]);

                                $stmt->update([
                                    "status" => "reconciled",
                                    "conciliable_id" => $payable->id,
                                    "conciliable_type" => AccountPayable::class,
                                ]);
                                $autoFeesCount++;
                            }
                            // ==========================================
                            // 🚀 ZERO-TOUCH 3: MATCHES PERFEITOS (>= 95%)
                            // ==========================================
                            elseif ($stmt->amount < 0) {
                                $matches = $reconciliationService->findMatchesFor(
                                    $stmt,
                                );
                                if (
                                    $matches->isNotEmpty() &&
                                    $matches->first()["score"] >= 95
                                ) {
                                    $payable = $matches->first()["candidate"];
                                    $stmt->update([
                                        "status" => "reconciled",
                                        "conciliable_id" => $payable->id,
                                        "conciliable_type" =>
                                            AccountPayable::class,
                                    ]);
                                    $payable->update([
                                        "status" => "paid",
                                        "payment_date" => $stmt->bank_date,
                                    ]);
                                    $autoMatchesCount++;
                                }
                            }
                        }
                    }
                }

                Storage::disk("public")->delete($filePath);

                $message = "Foram adicionadas {$importedCount} transações.";
                if (
                    $autoFeesCount > 0 ||
                    $autoMatchesCount > 0 ||
                    $autoPersonalCount > 0
                ) {
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
