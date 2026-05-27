<?php

namespace App\Services;

use App\Models\BankStatement;
use App\Models\AccountPayable;
use Illuminate\Support\Str;

class ReconciliationService
{
    public function findMatchesFor(BankStatement $statement)
    {
        // 🚀 Ampliamos a busca para 45 dias no passado para pegar atrasos longos
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

            if ($score > 30) {
                $matches->push([
                    "candidate" => $candidate,
                    "score" => $score,
                    "type" => "Contas a Pagar",
                ]);
            }
        }

        return $matches->sortByDesc("score")->take(5)->values();
    }

    private function calculateScore(
        BankStatement $statement,
        AccountPayable $candidate,
    ): int {
        $score = 0;

        $statementAmount = abs((float) ($statement->amount ?? 0));
        $candidateAmount = abs((float) ($candidate->amount ?? 0));
        $diff = abs($statementAmount - $candidateAmount);

        // ==========================================
        // A MÁGICA FINANCEIRA: CÁLCULO DE JUROS E MULTA
        // ==========================================
        $isLate =
            $statement->bank_date &&
            $candidate->due_date &&
            $statement->bank_date->greaterThan($candidate->due_date);
        $daysLate = $isLate
            ? $candidate->due_date->diffInDays($statement->bank_date)
            : 0;

        $lateMatch = false; // Flag para perdoar a pontuação de data depois
        $expectedLateValue = $candidateAmount;

        if ($daysLate > 0) {
            $multa = $candidateAmount * 0.02; // Multa padrão de 2%
            $juros = $candidateAmount * (0.01 / 30) * $daysLate; // Juros padrão de 1% a.m. pro-rata
            $expectedLateValue = $candidateAmount + $multa + $juros;
        }

        // ==========================================
        // REGRA 1: O VALOR (Máximo 50 pts)
        // ==========================================
        if ($diff < 0.01) {
            $score += 50;
        } elseif ($diff <= 1.0) {
            $score += 45;
        }
        // 🚀 A PROVA CRIPTOGRÁFICA: O valor cobrado a mais é exatamente a multa/juros?
        elseif (
            $daysLate > 0 &&
            abs($statementAmount - $expectedLateValue) <= 2.0
        ) {
            // Damos 2 reais de tolerância porque alguns bancos contam dias úteis ou arredondam diferente
            $score += 45;
            $lateMatch = true; // O atraso foi justificado matematicamente!
        } elseif (
            $statementAmount >= $candidateAmount * 0.98 &&
            $statementAmount <= $candidateAmount * 1.02
        ) {
            $score += 25;
        } else {
            return 0; // Diferença injustificável, aborta o match.
        }

        // ==========================================
        // REGRA 2: IDENTIDADE / "BALA DE PRATA" (Máximo 30 pts)
        // ==========================================
        $identityScore = 0;

        $stmtDesc = $statement->description ?? "";
        $stmtCounter = $statement->counterparty_name ?? "";
        $candDesc = $candidate->description ?? "";

        $bankText = Str::ascii(Str::lower($stmtDesc . " " . $stmtCounter));
        $bankDoc = preg_replace("/[^0-9]/", "", $statement->document ?? "");
        $bankNumbers = preg_replace("/[^0-9]/", "", $bankText);

        $candDescText = Str::ascii(Str::lower($candDesc));
        $candDescNumbers = preg_replace("/[^0-9]/", "", $candDesc);

        $bankCnpjRaiz = strlen($bankDoc) === 14 ? substr($bankDoc, 0, 8) : null;

        if ($candidate->employee) {
            $empName = Str::ascii(Str::lower($candidate->employee->name ?? ""));
            $empCpf = preg_replace(
                "/[^0-9]/",
                "",
                $candidate->employee->cpf ?? "",
            );

            if (
                !empty($empCpf) &&
                ($empCpf === $bankDoc ||
                    (!empty($bankNumbers) &&
                        str_contains($bankNumbers, $empCpf)))
            ) {
                $identityScore = 30;
            } elseif (!empty($empName) && str_contains($bankText, $empName)) {
                $identityScore = 30;
            } else {
                $nameParts = explode(" ", $empName);
                if (
                    count($nameParts) >= 2 &&
                    !empty($nameParts[0]) &&
                    str_contains($bankText, $nameParts[0]) &&
                    str_contains($bankText, end($nameParts))
                ) {
                    $identityScore = 20;
                }
            }
        }

        if ($candidate->supplier && $identityScore === 0) {
            $supName = Str::ascii(Str::lower($candidate->supplier->name ?? ""));
            $supTrade = Str::ascii(
                Str::lower($candidate->supplier->trade_name ?? ""),
            );
            $supDoc = preg_replace(
                "/[^0-9]/",
                "",
                $candidate->supplier->document ?? "",
            );

            $supCnpjRaiz =
                strlen($supDoc) === 14 ? substr($supDoc, 0, 8) : null;

            if (
                !empty($supDoc) &&
                ($supDoc === $bankDoc ||
                    (!empty($bankNumbers) &&
                        str_contains($bankNumbers, $supDoc)))
            ) {
                $identityScore = 30;
            } elseif (
                $bankCnpjRaiz &&
                $supCnpjRaiz &&
                $bankCnpjRaiz === $supCnpjRaiz
            ) {
                $identityScore = 30;
            } elseif (!empty($supName) && str_contains($bankText, $supName)) {
                $identityScore = 30;
            } elseif (!empty($supTrade) && str_contains($bankText, $supTrade)) {
                $identityScore = 30;
            }
        }

        if ($identityScore === 0 && !empty($candDescNumbers)) {
            if (!empty($bankDoc) && str_contains($candDescNumbers, $bankDoc)) {
                $identityScore = 30;
            } elseif (
                !empty($bankCnpjRaiz) &&
                str_contains($candDescNumbers, $bankCnpjRaiz)
            ) {
                $identityScore = 30;
            }
        }

        if ($identityScore === 0 && !empty($candDescText)) {
            $descWords = array_filter(
                explode(" ", $candDescText),
                fn($w) => strlen($w) > 3,
            );
            foreach ($descWords as $word) {
                if (!empty($word) && str_contains($bankText, $word)) {
                    $identityScore = 15;
                    break;
                }
            }
        }

        $score += $identityScore;

        // ==========================================
        // REGRA 3: A DATA (Decaimento Suave) (Máximo 20 pts)
        // ==========================================
        if ($statement->bank_date && $candidate->due_date) {
            $absDaysDiff = $statement->bank_date->diffInDays(
                $candidate->due_date,
            );

            // 🚀 SE A MATEMÁTICA PROVOU O ATRASO, PERDOAMOS A DATA!
            if ($lateMatch) {
                $score += 20;
            } elseif ($absDaysDiff == 0) {
                $score += 20;
            } elseif ($absDaysDiff == 1) {
                $score += 15;
            } elseif ($absDaysDiff == 2) {
                $score += 10;
            } elseif ($absDaysDiff <= 5) {
                $score += 5;
            }
        }

        return min($score, 100);
    }
}
