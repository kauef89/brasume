<?php

namespace App\Services\Accounting;

use App\Models\BankStatement;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Supplier;

class BankMatchScoringService
{
    private array $familyDocsCache = [];

    public function findMatchesFor(BankStatement $statement)
    {
        $matches = collect();

        // 🚀 CENÁRIO 1: CRÉDITO NO BANCO (Busca em Contas a Receber)
        if ($statement->type === 'in') {
            $receivables = AccountReceivable::with(['customer', 'order'])
                ->where('status', 'pending')
                ->whereBetween('due_date', [
                    $statement->bank_date->copy()->subDays(15),
                    $statement->bank_date->copy()->addDays(15),
                ])
                ->get();

            foreach ($receivables as $candidate) {
                $score = $this->calculateReceivableScore($statement, $candidate);
                if ($score >= 30) {
                    $matches->push([
                        'candidate' => $candidate,
                        'score'     => $score,
                        'type'      => 'Contas a Receber',
                    ]);
                }
            }

            return $matches->sortByDesc('score')->values();
        }

        // 🚀 CENÁRIO 2: DÉBITO NO BANCO (Busca em Contas a Pagar)
        $candidates = AccountPayable::with(["supplier", "employee"])
            ->where("status", "pending")
            ->whereBetween("due_date", [
                $statement->bank_date->copy()->subDays(45),
                $statement->bank_date->copy()->addDays(15),
            ])
            ->get();

        foreach ($candidates as $candidate) {
            $score = $this->calculateScore($statement, $candidate);

            if ($score >= 30) {
                $matches->push([
                    "candidate" => $candidate,
                    "score"     => $score,
                    "type"      => "Contas a Pagar",
                ]);
            }
        }

        return $matches->sortByDesc("score")->values();
    }

    public function calculateScore(BankStatement $statement, AccountPayable $candidate): int
    {
        $amountData = $this->calculateAmountScore($statement, $candidate);
        if ($amountData['score'] === 0) return 0;

        $identityScore = $this->calculateIdentityScore($statement, $candidate);
        if ($amountData['is_late_match'] && $identityScore === 0) return 0;

        $dateScore = $this->calculateDateScore($statement, $candidate, $amountData['is_late_match']);

        return min($amountData['score'] + $identityScore + $dateScore, 100);
    }

    /**
     * Pontuação para Contas a Receber (Depósitos, TEFs e Maquininhas)
     */
    public function calculateReceivableScore(BankStatement $statement, AccountReceivable $candidate): int
    {
        $statementAmount = abs((float) ($statement->amount ?? 0));
        // Compara preferencialmente com o valor líquido da maquininha
        $targetAmount = (float) ($candidate->net_amount > 0 ? $candidate->net_amount : $candidate->gross_amount);
        $diff = abs($statementAmount - $targetAmount);

        $amountScore = 0;
        if ($diff < 0.01) {
            $amountScore = 40;
        } elseif ($diff <= 1.0) {
            $amountScore = 35;
        } elseif ($statementAmount >= $targetAmount * 0.98 && $statementAmount <= $targetAmount * 1.02) {
            $amountScore = 20;
        }

        if ($amountScore === 0) return 0;

        // Identidade: Texto do extrato vs Descrição do recebível ou Cliente
        $cleanStr = fn($s) => preg_replace("/[^a-z0-9]/", "", strtolower(trim($s)));
        $bankText = $cleanStr(($statement->description ?? '') . ' ' . ($statement->counterparty_name ?? ''));
        $candDesc = $cleanStr($candidate->description ?? '');
        $custName = $cleanStr($candidate->customer?->name ?? '');

        $identityScore = 0;
        if (!empty($candDesc) && (str_contains($bankText, $candDesc) || str_contains($candDesc, $bankText))) {
            $identityScore = 40;
        } elseif (!empty($custName) && str_contains($bankText, $custName)) {
            $identityScore = 40;
        } else {
            // Detecção de termos genéricos de adquirentes
            foreach (['stone', 'cielo', 'sicredi', 'pagseguro', 'rede', 'ifood', 'getnet'] as $gateway) {
                if (str_contains($bankText, $gateway) && str_contains(strtolower($candidate->description ?? ''), $gateway)) {
                    $identityScore = 30;
                    break;
                }
            }
        }

        // Data de liquidação prevista vs Data real no banco
        $dateScore = 0;
        if ($statement->bank_date && $candidate->due_date) {
            $days = abs($statement->bank_date->diffInDays($candidate->due_date));
            if ($days === 0) $dateScore = 20;
            elseif ($days <= 1) $dateScore = 15;
            elseif ($days <= 3) $dateScore = 10;
        }

        return min($amountScore + $identityScore + $dateScore, 100);
    }

    private function calculateAmountScore(BankStatement $statement, AccountPayable $candidate): array
    {
        $statementAmount = abs((float) ($statement->amount ?? 0));
        $candidateAmount = abs((float) ($candidate->amount ?? 0));
        $diff = abs($statementAmount - $candidateAmount);

        $isLate = $statement->bank_date && $candidate->due_date && $statement->bank_date->greaterThan($candidate->due_date);
        $daysLate = $isLate ? $candidate->due_date->diffInDays($statement->bank_date) : 0;

        $lateMatch = false;
        $expectedLateValue = $candidateAmount;

        if ($daysLate > 0) {
            $multa = $candidateAmount * 0.02;
            $juros = $candidateAmount * (0.01 / 30) * $daysLate;
            $expectedLateValue = $candidateAmount + $multa + $juros;
        }

        $score = 0;
        if ($daysLate > 0 && abs($statementAmount - $expectedLateValue) <= 2.0) {
            $score = 35;
            $lateMatch = true;
        } elseif ($diff < 0.01) {
            $score = 40;
        } elseif ($diff <= 1.0) {
            $score = 35;
        } elseif ($statementAmount >= $candidateAmount * 0.98 && $statementAmount <= $candidateAmount * 1.02) {
            $score = 20;
        }

        return ['score' => $score, 'is_late_match' => $lateMatch];
    }

    private function calculateIdentityScore(BankStatement $statement, AccountPayable $candidate): int
    {
        $bankDoc = preg_replace("/[^0-9]/", "", (string) ($statement->document ?? ""));
        $stmtDesc = (string) ($statement->description ?? "");
        $stmtCounter = (string) ($statement->counterparty_name ?? "");

        $cleanStr = fn($s) => preg_replace("/[^a-z0-9]/", "", strtolower(trim($s)));
        $bankText = $cleanStr($stmtDesc . " " . $stmtCounter);

        if ($candidate->supplier) {
            return $this->scoreSupplierIdentity($candidate->supplier, $bankDoc, $bankText);
        } elseif ($candidate->employee) {
            return $this->scoreEmployeeIdentity($candidate->employee, $bankDoc, $bankText);
        }

        $candDesc = $cleanStr($candidate->description ?? "");
        if (!empty($candDesc) && str_contains($bankText, $candDesc)) return 20;

        return 0;
    }

    private function scoreSupplierIdentity(Supplier $supplier, string $bankDoc, string $bankText): int
    {
        $supDoc = preg_replace("/[^0-9]/", "", (string) ($supplier->document ?? ""));
        $bankDocLen = strlen($bankDoc);
        $supDocLen = strlen($supDoc);

        if (in_array($bankDocLen, [11, 14]) && in_array($supDocLen, [11, 14])) {
            if ($bankDoc === $supDoc) return 40;
            if ($bankDocLen === 14 && $supDocLen === 14 && substr($bankDoc, 0, 8) === substr($supDoc, 0, 8)) return 40;

            $familyDocs = $this->getFamilyDocs($supplier);
            if (in_array($bankDoc, $familyDocs)) return 40;

            return 0;
        }

        $cleanStr = fn($s) => preg_replace("/[^a-z0-9]/", "", strtolower(trim($s)));
        $supName = $cleanStr($supplier->name ?? "");
        $supTrade = $cleanStr($supplier->trade_name ?? "");

        if (!empty($supName) && str_contains($bankText, $supName)) return 40;
        if (!empty($supTrade) && str_contains($bankText, $supTrade)) return 40;

        return 0;
    }

    private function scoreEmployeeIdentity($employee, string $bankDoc, string $bankText): int
    {
        $empDoc = preg_replace("/[^0-9]/", "", (string) ($employee->cpf ?? ""));
        $bankDocLen = strlen($bankDoc);
        $empDocLen = strlen($empDoc);

        if (in_array($bankDocLen, [11, 14]) && in_array($empDocLen, [11, 14])) {
            if ($bankDoc !== $empDoc) return 0;
            return 40;
        }

        $cleanStr = fn($s) => preg_replace("/[^a-z0-9]/", "", strtolower(trim($s)));
        $empName = $cleanStr($employee->name ?? "");

        if (!empty($empName) && str_contains($bankText, $empName)) return 40;

        return 0;
    }

    private function calculateDateScore(BankStatement $statement, AccountPayable $candidate, bool $lateMatch): int
    {
        if (!$statement->bank_date || !$candidate->due_date) return 0;

        $absDaysDiff = (int) $statement->bank_date->diffInDays($candidate->due_date);

        if ($lateMatch) return 20;
        if ($absDaysDiff === 0) return 20;
        if ($absDaysDiff === 1) return 15;
        if ($absDaysDiff === 2) return 10;
        if ($absDaysDiff <= 5) return 5;

        return 0;
    }

    private function getFamilyDocs(Supplier $supplier): array
    {
        $supplierId = $supplier->id;

        if (isset($this->familyDocsCache[$supplierId])) {
            return $this->familyDocsCache[$supplierId];
        }

        $familyIds = $supplier->family_ids;

        $familyDocs = Supplier::whereIn('id', $familyIds)
            ->pluck('document')
            ->map(fn($d) => preg_replace("/[^0-9]/", "", (string) $d))
            ->filter()
            ->toArray();

        foreach ($familyIds as $famId) {
            $this->familyDocsCache[$famId] = $familyDocs;
        }

        return $familyDocs;
    }
}
