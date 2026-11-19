<?php

namespace App\Services\BankDictionaries;

use App\Enums\BankMovementType;

class SicrediDictionary implements BankDictionaryInterface
{
    public function parseMemo(string $memo): array
    {
        $result = [
            "document"          => null,
            "name"              => null,
            "clean_description" => trim($memo),
            "movement_type"     => "other",
        ];

        $upperMemo = strtoupper($memo);

        // 1. Depósito em Dinheiro ATM (Sangria do Caixa Físico para o Banco)
        if (str_contains($upperMemo, "DEP DINHEIRO ATM") || str_contains($upperMemo, "DEP DINHEIRO")) {
            $result["name"] = "Depósito em Dinheiro (ATM)";
            $result["clean_description"] = "Depósito ATM em Dinheiro";
            $result["movement_type"] = BankMovementType::ATM_IN->value;
            return $result;
        }

                // 2.1 Compras Nacionais / Cartão de Débito (Extrai o Estabelecimento)
        if (str_starts_with($upperMemo, "COMPRAS NACIONAIS") || str_contains($upperMemo, "COMPRA DEBITO")) {
            // Remove prefixo operacional (ex: COMPRAS NACIONAIS-VE0059839 )
            $clean = preg_replace('/^COMPRAS NACIONAIS-[A-Z0-9]+\s+/i', '', $memo);
            // Remove sufixo geográfico comum (ex: BALNEARIO BARBR, JOINVILLE BR, BR)
            $clean = preg_replace('/\s+(BALNEARIO\s+BARBR|JOINVILLE\s+BR|ARAQUARI\s+BR|SAO\s+FRANCISCOBR|OSASCO\s+BR|BALNE\s+BR|BR)$/i', '', $clean);
            $cleanName = trim($clean);

            $result["name"] = $cleanName ?: "Compra no Débito";
            $result["clean_description"] = "Débito: " . ($cleanName ?: $memo);
            $result["movement_type"] = BankMovementType::DEBIT_CARD->value;
            return $result;
        }

        // 2. Fatura de Cartão de Crédito Sicredi PJ
        if (str_contains($upperMemo, "DEB.CTA.FATURA") || str_contains($upperMemo, "DEB.FATURA")) {
            $result["name"] = "Cartão Sicredi PJ";
            $result["clean_description"] = "Pagamento de Fatura de Cartão";
            $result["movement_type"] = BankMovementType::CREDIT_CARD_PAYMENT->value;
            return $result;
        }

        // 3. Empréstimos, Capital de Giro e Amortização / Liquidação de Parcelas (Contraparte = Próprio Banco)
        if (str_contains($upperMemo, "LIBERACAO CREDITO") || str_contains($upperMemo, "PRONAMPE") || str_contains($upperMemo, "GIRO")) {
            $result["name"] = "Banco Sicredi (Giro / Crédito)";
            $result["clean_description"] = "Liberação de Capital de Giro / Empréstimo";
            $result["movement_type"] = BankMovementType::LOAN_IN->value;
            return $result;
        }

        if (
            (
                str_contains($upperMemo, "LIQUIDACAO DE PARCELA") ||
                str_contains($upperMemo, "LIQUIDACAO PARCELA") ||
                str_contains($upperMemo, "LIQ. PARCELA") ||
                str_contains($upperMemo, "AMORTIZACAO EMPRESTIMO") ||
                str_contains($upperMemo, "PARCELA CREDITO")
            ) &&
            !preg_match('/\b[0-9]{11,14}\b/', $upperMemo) // Garante que não há CPF/CNPJ de terceiros (contraparte é o próprio banco)
        ) {
            $contractCode = null;
            if (preg_match('/(?:PARCELA|EMPRESTIMO|CREDITO)[-\s]+([A-Z0-9]{4,20})/i', $memo, $mContract)) {
                $contractCode = strtoupper(trim($mContract[1]));
            }

            $result["name"] = "Banco Sicredi (Empréstimo / Capital de Giro)";
            $result["statement_identifier"] = $contractCode;
            $result["clean_description"] = $contractCode
                ? "Liquidação de Parcela (Empréstimo / Giro - {$contractCode})"
                : "Liquidação de Parcela (Empréstimo / Giro)";
            $result["movement_type"] = BankMovementType::LOAN_OUT->value;
            return $result;
        }

        // 4. Pix, Boletos, TEDs, Transferências (com CPF/CNPJ)
        if (
            preg_match(
                "/(?:PAGAMENTO PIX.*?|RECEBIMENTO PIX.*?|DEVOLUCAO PIX|LIQUIDACAO BOLETO.*?|DEBITO TED\/IB|TED|TRANSF ENTRE CONTAS).*?\s+([0-9]{11,14}|\*\*\*[0-9]{6}\*\*)\s+(.+)/i",
                $memo,
                $matches,
            )
        ) {
            $doc = trim($matches[1]);
            $result["document"] = str_contains($doc, "*") ? null : $doc;
            $result["name"] = trim($matches[2]);

            if (str_contains($upperMemo, "RECEBIMENTO PIX")) {
                $result["movement_type"] = BankMovementType::PIX_IN->value;
            } elseif (str_contains($upperMemo, "DEVOLUCAO PIX") || str_contains($upperMemo, "ESTORNO")) {
                $result["movement_type"] = BankMovementType::REFUND->value;
            } elseif (str_contains($upperMemo, "LIQUIDACAO BOLETO")) {
                $result["movement_type"] = BankMovementType::BOLETO_OUT->value;
            } elseif (
                str_contains($upperMemo, "TED") ||
                str_contains($upperMemo, "TRANSF")
            ) {
                $result["movement_type"] = BankMovementType::TRANSFER->value;
            } else {
                $result["movement_type"] = BankMovementType::PIX_OUT->value;
            }
            $result["clean_description"] = $result["name"];
            return $result;
        }

        // 5. Maquininha de Cartão (Repasses de Débito, Crédito e Antecipações)
        if (
            preg_match(
                "/SICREDI (DEBITO|CREDITO|ANTECIPACAO|ANTEC) (VISA|MASTER|ELO|AMEX)/i",
                $memo,
                $matches,
            )
        ) {
            $result["name"] = "Repasse " . ucfirst(strtolower($matches[1])) . " " . $matches[2];
            $result["clean_description"] = $result["name"];
            $result["movement_type"] = BankMovementType::CARD_IN->value;
            return $result;
        }

        // 6. Maquininha Física (Reembolso de Aluguel POS)
        if (str_starts_with($upperMemo, "REEMBOLSO POS SICREDI")) {
            $result["name"] = "Banco Sicredi";
            $result["clean_description"] = "Reembolso Maquininha POS";
            $result["movement_type"] = BankMovementType::CARD_IN->value;
            return $result;
        }

        // 7. Contas de Consumo / Faturas Recorrentes (CASAN, TIM, CELESC)
        if (preg_match("/^DEBITO CONVENIOS-(.+)$/i", trim($memo), $mConv)) {
            $tail = trim($mConv[1]);

            $doc = null;
            if (preg_match("/\b([0-9]{11,14})\b/", $tail, $mDoc)) {
                $doc = $mDoc[1];
            }

            $identifier = null;
            if (preg_match("/\b(?:ID|UC)\s*[:\-]?\s*([A-Z0-9\-\/]{4,25})/i", $tail, $mId)) {
                $identifier = strtoupper(trim($mId[1]));
            }

            $cleanName = $tail;
            if ($doc !== null) {
                $cleanName = str_replace($doc, ' ', $cleanName);
            }
            $cleanName = preg_replace("/\b(?:ID|UC)\s*[:\-]?\s*[A-Z0-9\-\/]{4,25}/i", ' ', $cleanName);
            $cleanName = trim((string) preg_replace("/\s+/", " ", $cleanName));

            // Remove duplicação do nome da concessionária antes e depois do CNPJ (ex: "CASAN CASAN")
            $words = explode(' ', $cleanName);
            if (count($words) >= 2 && count($words) % 2 === 0) {
                $half = (int) (count($words) / 2);
                if (array_slice($words, 0, $half) === array_slice($words, $half)) {
                    $cleanName = implode(' ', array_slice($words, 0, $half));
                }
            }

            $result["name"] = $cleanName ?: "Convênio / Débito Automático";
            $result["document"] = $doc;
            $result["statement_identifier"] = $identifier;
            $result["clean_description"] = "Convênio / Fatura - " . $result["name"]
                . ($identifier ? " (ID {$identifier})" : "");
            $result["movement_type"] = BankMovementType::FIXED_BILL->value;
            return $result;
        }

        // 8. Tributos e Arrecadação Fiscal (DAS, DARF, GPS)
        if (str_starts_with($upperMemo, "DEBITO ARRECADACAO")) {
            if (preg_match("/([0-9]{14})/", $memo, $matches)) {
                $result["document"] = $matches[1];
            }
            $result["name"] = "Receita Federal / Impostos";
            $result["clean_description"] = "Imposto (DARF/DAS/GPS)";
            $result["movement_type"] = BankMovementType::TAX_BILL->value;
            return $result;
        }

        // 9. Saque em Dinheiro
        if (str_starts_with($upperMemo, "SAQUE DINHEIRO ATM")) {
            $result["clean_description"] = "Saque em Dinheiro (ATM)";
            $result["movement_type"] = BankMovementType::ATM_OUT->value;
            return $result;
        }

        // 10. Folha de Pagamento em Lote
        if (str_starts_with($upperMemo, "DEB. FOLHA PAGTO")) {
            $result["clean_description"] = "Folha de Pagamento (Lote)";
            $result["movement_type"] = BankMovementType::PAYROLL->value;
            return $result;
        }

        // 11. Tarifas Bancárias, Encargos e Juros de Limite
        if (
            str_starts_with($upperMemo, "CESTA DE RELACIONAMENTO") ||
            str_starts_with($upperMemo, "PLANO INT CAPITAL") ||
            str_starts_with($upperMemo, "IOF") ||
            str_starts_with($upperMemo, "JUROS UTILIZ") ||
            str_starts_with($upperMemo, "TARIFA")
        ) {
            $result["name"] = "Banco Sicredi";
            $result["clean_description"] = (str_contains($upperMemo, "CESTA") || str_contains($upperMemo, "TARIFA"))
                ? "Tarifa Bancária"
                : "Juros e Encargos";
            $result["movement_type"] = BankMovementType::BANK_FEE->value;
            return $result;
        }

        $result["clean_description"] = preg_replace("/\s+/", " ", $result["clean_description"]);
        return $result;
    }
}
