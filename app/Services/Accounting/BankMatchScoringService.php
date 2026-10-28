<?php

namespace App\Services\Accounting;

use App\Models\BankStatement;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Supplier;
use Carbon\Carbon;

class BankMatchScoringService
{
    private array $familyDocsCache = [];

    public function findMatchesFor(BankStatement $statement)
    {
        $matches = collect();

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
        // 1. Identidade Canônica (Max: 45 pts)
        $identityData = $this->calculateIdentityScore($statement, $candidate);
        if ($identityData['score'] === 0) return 0;

        // 2. Valor (Max: 45 pts)
        $amountData = $this->calculateAmountScore($statement, $candidate);
        if ($amountData['score'] === 0) return 0;

        // 3. Data com regra bancária de fins de semana (Max: 15 pts)
        $dateScore = $this->calculateDateScore($statement, $candidate, $amountData['is_late_match']);

        $rawScore = $identityData['score'] + $amountData['score'] + $dateScore;

        // Teto de 85% para valores que não batem no centavo (< R$ 0,02)
        if (!$amountData['is_exact']) {
            return min($rawScore, 85);
        }

        // Bônus de sinergia: Valor 100% idêntico no centavo + Identidade Forte
        $effectiveDiff = $this->getEffectiveBankingDaysDiff($statement, $candidate);
        if ($identityData['score'] >= 35) {
            if ($effectiveDiff <= 3) {
                $rawScore = max($rawScore, 98);
            } elseif ($effectiveDiff <= 7) {
                $rawScore = max($rawScore, 95);
            }
        }

        if ($identityData['doc_exact'] && $effectiveDiff <= 3) {
            $rawScore = 100;
        }

        return min($rawScore, 100);
    }

    private function calculateAmountScore(BankStatement $statement, AccountPayable $candidate): array
    {
        $statementAmount = abs((float) ($statement->amount ?? 0));
        $candidateAmount = abs((float) ($candidate->amount ?? 0));
        $diff = abs($statementAmount - $candidateAmount);

        $isExact = ($diff < 0.02);
        $score = 0;
        $lateMatch = false;

        if ($isExact) {
            $score = 45;
        } elseif ($diff <= 0.50) {
            $score = 42;
        } elseif ($diff <= 2.00) {
            $score = 35;
        } else {
            $isPastDue = $statement->bank_date && $candidate->due_date && $statement->bank_date->greaterThan($candidate->due_date);
            $daysLate = $isPastDue ? $candidate->due_date->diffInDays($statement->bank_date) : 0;

            if ($isPastDue && $daysLate > 0 && $statementAmount > $candidateAmount) {
                $multa = $candidateAmount * 0.02;
                $juros = $candidateAmount * (0.01 / 30) * $daysLate;
                $expectedLateValue = $candidateAmount + $multa + $juros;

                if (abs($statementAmount - $expectedLateValue) <= 2.50) {
                    $score = 35;
                    $lateMatch = true;
                } elseif ($statementAmount <= $candidateAmount * 1.05) {
                    $score = 20;
                }
            } elseif ($statementAmount < $candidateAmount) {
                $discountPct = ($candidateAmount - $statementAmount) / $candidateAmount;
                if ($discountPct <= 0.03) {
                    $score = 30;
                } elseif ($discountPct <= 0.05) {
                    $score = 20;
                }
            }
        }

        return [
            'score'         => $score,
            'is_exact'      => $isExact,
            'is_late_match' => $lateMatch
        ];
    }

    private function calculateIdentityScore(BankStatement $statement, AccountPayable $candidate): array
    {
        $bankDoc = preg_replace("/[^0-9]/", "", (string) ($statement->document ?? ""));
        $stmtDesc = (string) ($statement->description ?? "");
        $stmtCounter = (string) ($statement->counterparty_name ?? "");

        $cleanStr = fn($s) => preg_replace("/[^a-z0-9]/", "", strtolower((string) $s));
        $bankText = $cleanStr($stmtDesc . " " . $stmtCounter);

        if ($candidate->supplier) {
            return $this->scoreSupplierIdentity($candidate->supplier, $bankDoc, $bankText);
        } elseif ($candidate->employee) {
            return $this->scoreEmployeeIdentity($candidate->employee, $bankDoc, $bankText);
        }

        $candDesc = $cleanStr($candidate->description ?? "");
        if (!empty($candDesc) && strlen($candDesc) >= 5 && (str_contains($bankText, $candDesc) || str_contains($candDesc, $bankText))) {
            return ['score' => 30, 'doc_exact' => false];
        }

        return ['score' => 0, 'doc_exact' => false];
    }

    /**
     * Validação Canônica Estrita: Apenas Documento (CNPJ/CPF) ou Cadeia Contínua Inteira.
     * NENHUMA quebra em palavras avulsas ou stop words.
     */
    private function scoreSupplierIdentity(Supplier $supplier, string $bankDoc, string $bankText): array
    {
        $cleanStr = fn($s) => preg_replace("/[^a-z0-9]/", "", strtolower((string) $s));

        $supDoc = preg_replace("/[^0-9]/", "", (string) ($supplier->document ?? ""));
        $bankDocLen = strlen($bankDoc);
        $supDocLen = strlen($supDoc);

        // 1. Correspondência de Documento (CNPJ / Raiz de 8 dígitos para Matriz e Filiais)
        if (in_array($bankDocLen, [11, 14]) && in_array($supDocLen, [11, 14])) {
            if ($bankDoc === $supDoc) return ['score' => 45, 'doc_exact' => true];
            if ($bankDocLen === 14 && $supDocLen === 14 && substr($bankDoc, 0, 8) === substr($supDoc, 0, 8)) {
                return ['score' => 45, 'doc_exact' => true];
            }

            $familyDocs = $this->getFamilyDocs($supplier);
            if (in_array($bankDoc, $familyDocs)) return ['score' => 45, 'doc_exact' => true];
        }

        // 2. Correspondência Canônica por Cadeia Contínua (Nome Fantasia ou Razão Social)
        // Só compara strings representativas (mínimo de 5 caracteres alfanuméricos)
        $names = array_filter([
            (string) ($supplier->name ?? ''),
            (string) ($supplier->document_name ?? ''),
            (string) ($supplier->trade_name ?? '')
        ], fn($n) => strlen(trim($n)) >= 3);

        foreach ($names as $nameCandidate) {
            $cleanCandidate = $cleanStr($nameCandidate);
            if (strlen($cleanCandidate) >= 5) {
                // Se a razão social/nome estiver contido no texto do banco ou vice-versa
                if (str_contains($bankText, $cleanCandidate) || str_contains($cleanCandidate, $bankText)) {
                    return ['score' => 40, 'doc_exact' => false];
                }
            }
        }

        return ['score' => 0, 'doc_exact' => false];
    }

    private function scoreEmployeeIdentity($employee, string $bankDoc, string $bankText): array
    {
        $cleanStr = fn($s) => preg_replace("/[^a-z0-9]/", "", strtolower((string) $s));

        $empDoc = preg_replace("/[^0-9]/", "", (string) ($employee->cpf ?? ""));
        if (!empty($empDoc) && !empty($bankDoc) && $bankDoc === $empDoc) {
            return ['score' => 45, 'doc_exact' => true];
        }

        $empName = $cleanStr($employee->name ?? "");
        if (!empty($empName) && strlen($empName) >= 5 && (str_contains($bankText, $empName) || str_contains($empName, $bankText))) {
            return ['score' => 40, 'doc_exact' => false];
        }

        return ['score' => 0, 'doc_exact' => false];
    }

    private function calculateDateScore(BankStatement $statement, AccountPayable $candidate, bool $lateMatch): int
    {
        if (!$statement->bank_date || !$candidate->due_date) return 0;
        if ($lateMatch) return 12;

        $effectiveDiff = $this->getEffectiveBankingDaysDiff($statement, $candidate);

        if ($effectiveDiff === 0) return 15;
        if ($effectiveDiff <= 2) return 14;
        if ($effectiveDiff <= 5) return 12;
        if ($effectiveDiff <= 10) return 8;
        if ($effectiveDiff <= 20) return 4;

        return 2;
    }

    private function getEffectiveBankingDaysDiff(BankStatement $statement, AccountPayable $candidate): int
    {
        $stmtDate = $statement->bank_date->copy()->startOfDay();
        $dueDate = $candidate->due_date->copy()->startOfDay();

        $effectiveDueDate = $dueDate->copy();
        if ($effectiveDueDate->isSaturday()) {
            $effectiveDueDate->addDays(2);
        } elseif ($effectiveDueDate->isSunday()) {
            $effectiveDueDate->addDay();
        }

        $calendarDiff = abs($stmtDate->diffInDays($dueDate));
        $effectiveDiff = abs($stmtDate->diffInDays($effectiveDueDate));

        return min($calendarDiff, $effectiveDiff);
    }

    public function calculateReceivableScore(BankStatement $statement, AccountReceivable $candidate): int
    {
        $statementAmount = abs((float) ($statement->amount ?? 0));
        $targetAmount = (float) ($candidate->net_amount > 0 ? $candidate->net_amount : $candidate->gross_amount);
        $diff = abs($statementAmount - $targetAmount);

        $amountScore = 0;
        if ($diff < 0.02) {
            $amountScore = 45;
        } elseif ($diff <= 1.0) {
            $amountScore = 40;
        } elseif ($statementAmount >= $targetAmount * 0.98 && $statementAmount <= $targetAmount * 1.02) {
            $amountScore = 25;
        }

        if ($amountScore === 0) return 0;

        $cleanStr = fn($s) => preg_replace("/[^a-z0-9]/", "", strtolower((string) $s));
        $bankText = $cleanStr(($statement->description ?? '') . ' ' . ($statement->counterparty_name ?? ''));
        $candDesc = $cleanStr($candidate->description ?? '');
        $custName = $cleanStr($candidate->customer?->name ?? '');

        $identityScore = 0;
        if (!empty($candDesc) && (str_contains($bankText, $candDesc) || str_contains($candDesc, $bankText))) {
            $identityScore = 40;
        } elseif (!empty($custName) && str_contains($bankText, $custName)) {
            $identityScore = 40;
        }

        $dateScore = 0;
        if ($statement->bank_date && $candidate->due_date) {
            $days = abs($statement->bank_date->diffInDays($candidate->due_date));
            if ($days <= 1) $dateScore = 15;
            elseif ($days <= 3) $dateScore = 12;
            elseif ($days <= 7) $dateScore = 8;
        }

        return min($amountScore + $identityScore + $dateScore, 100);
    }

    private function getFamilyDocs(Supplier $supplier): array
    {
        $supplierId = $supplier->id;

        if (isset($this->familyDocsCache[$supplierId])) {
            return $this->familyDocsCache[$supplierId];
        }

        $familyIds = $supplier->family_ids ?? [$supplierId];

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