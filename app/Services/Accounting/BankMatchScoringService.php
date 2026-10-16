<?php

namespace App\Services\Accounting;

use App\Models\BankStatement;
use App\Models\AccountPayable;
use App\Models\Supplier;

class BankMatchScoringService
{
    private array $familyDocsCache = [];

    public function findMatchesFor(BankStatement $statement)
    {
        $candidates = AccountPayable::with(["supplier", "employee"])
            ->where("status", "pending")
            ->whereBetween("due_date", [
                $statement->bank_date->copy()->subDays(45),
                $statement->bank_date->copy()->addDays(15),
            ])
            ->get();

        $matches = collect();

        foreach ($candidates as $candidate) {
            $score = $this->calculateScore($statement, $candidate);

            if ($score >= 30) {
                $matches->push([
                    "candidate" => $candidate,
                    "score" => $score,
                    "type" => "Contas a Pagar",
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

        // 🚀 CORREÇÃO DA ORDEM LÓGICA
        // 1. O Match Perfeito com Multa: Adivinha o Juros com precisão?
        if ($daysLate > 0 && abs($statementAmount - $expectedLateValue) <= 2.0) {
            $score = 35;
            $lateMatch = true;
        }
        // 2. O Match Perfeito Seco (Pago em dia na vírgula)
        elseif ($diff < 0.01) {
            $score = 40;
        }
        // 3. Arredondamentos e Descontos pequenos
        elseif ($diff <= 1.0) {
            $score = 35;
        }
        // 4. Aceita diferença de 2% (Ex: Desconto por antecipação)
        elseif ($statementAmount >= $candidateAmount * 0.98 && $statementAmount <= $candidateAmount * 1.02) {
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
