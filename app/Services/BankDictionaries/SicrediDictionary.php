<?php

namespace App\Services\BankDictionaries;

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
            $result["movement_type"] = "atm_in";
            return $result;
        }

        // 2. Fatura de Cartão de Crédito Sicredi PJ
        if (str_contains($upperMemo, "DEB.CTA.FATURA") || str_contains($upperMemo, "DEB.FATURA")) {
            $result["name"] = "Cartão Sicredi PJ";
            $result["clean_description"] = "Pagamento de Fatura de Cartão";
            $result["movement_type"] = "credit_card_payment";
            return $result;
        }

        // 3. Empréstimos, Capital de Giro e Tarifas de Crédito
        if (str_contains($upperMemo, "LIBERACAO CREDITO") || str_contains($upperMemo, "PRONAMPE") || str_contains($upperMemo, "GIRO")) {
            $result["name"] = "Banco Sicredi (Giro / Crédito)";
            $result["clean_description"] = "Liberação de Capital de Giro / Empréstimo";
            $result["movement_type"] = "loan_in";
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
                $result["movement_type"] = "pix_in";
            } elseif (str_contains($upperMemo, "DEVOLUCAO PIX")) {
                $result["movement_type"] = "pix_out";
            } elseif (str_contains($upperMemo, "LIQUIDACAO BOLETO")) {
                $result["movement_type"] = "boleto_out";
            } elseif (
                str_contains($upperMemo, "TED") ||
                str_contains($upperMemo, "TRANSF")
            ) {
                $result["movement_type"] = "transfer";
            } else {
                $result["movement_type"] = "pix_out";
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
            $result["movement_type"] = "card_in";
            return $result;
        }

        // 6. Maquininha Física (Reembolso de Aluguel POS)
        if (str_starts_with($upperMemo, "REEMBOLSO POS SICREDI")) {
            $result["name"] = "Banco Sicredi";
            $result["clean_description"] = "Reembolso Maquininha POS";
            $result["movement_type"] = "card_in";
            return $result;
        }

        // 7. Contas de Consumo / Faturas Recorrentes (CASAN, TIM, CELESC)
        if (
            preg_match(
                "/DEBITO CONVENIOS-(.*?)\s+(?:ID.*?)?(?:([0-9]{11,14}))/i",
                $memo,
                $matches,
            )
        ) {
            $result["name"] = trim($matches[1]);
            $result["document"] = isset($matches[2]) ? $matches[2] : null;
            $result["clean_description"] = "Convênio / Fatura - " . $result["name"];
            $result["movement_type"] = "fixed_bill";
            return $result;
        }

        // 8. Tributos e Arrecadação Fiscal (DAS, DARF, GPS)
        if (str_starts_with($upperMemo, "DEBITO ARRECADACAO")) {
            if (preg_match("/([0-9]{14})/", $memo, $matches)) {
                $result["document"] = $matches[1];
            }
            $result["name"] = "Receita Federal / Impostos";
            $result["clean_description"] = "Imposto (DARF/DAS/GPS)";
            $result["movement_type"] = "tax_bill";
            return $result;
        }

        // 9. Saque em Dinheiro
        if (str_starts_with($upperMemo, "SAQUE DINHEIRO ATM")) {
            $result["clean_description"] = "Saque em Dinheiro (ATM)";
            $result["movement_type"] = "atm_out";
            return $result;
        }

        // 10. Folha de Pagamento em Lote
        if (str_starts_with($upperMemo, "DEB. FOLHA PAGTO")) {
            $result["clean_description"] = "Folha de Pagamento (Lote)";
            $result["movement_type"] = "payroll";
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
            $result["movement_type"] = "bank_fee";
            return $result;
        }

        $result["clean_description"] = preg_replace("/\s+/", " ", $result["clean_description"]);
        return $result;
    }
}
