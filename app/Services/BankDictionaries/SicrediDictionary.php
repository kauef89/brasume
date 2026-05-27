<?php

namespace App\Services\BankDictionaries;

class SicrediDictionary implements BankDictionaryInterface
{
    public function parseMemo(string $memo): array
    {
        $result = [
            "document" => null,
            "name" => null,
            "clean_description" => trim($memo),
            "movement_type" => "other", // 🚀 NOVA PROPRIEDADE PADRÃO
        ];

        $upperMemo = strtoupper($memo);

        // 1. Pix, Boletos, TEDs, Transferências
        if (
            preg_match(
                "/(?:PAGAMENTO PIX.*?|RECEBIMENTO PIX.*?|DEVOLUCAO PIX|LIQUIDACAO BOLETO.*?|DEBITO TED\/IB|TED|TRANSF ENTRE CONTAS|DEP DINHEIRO ATM).*?\s+([0-9]{11,14}|\*\*\*[0-9]{6}\*\*)\s+(.+)/i",
                $memo,
                $matches,
            )
        ) {
            $doc = trim($matches[1]);
            $result["document"] = str_contains($doc, "*") ? null : $doc;
            $result["name"] = trim($matches[2]);

            if (str_contains($upperMemo, "RECEBIMENTO PIX")) {
                $prefix = "Pix Recebido";
                $result["movement_type"] = "pix_in";
            } elseif (str_contains($upperMemo, "DEVOLUCAO PIX")) {
                $prefix = "Estorno de Pix";
                $result["movement_type"] = "pix_out";
            } elseif (str_contains($upperMemo, "LIQUIDACAO BOLETO")) {
                $prefix = "Boleto Pago";
                $result["movement_type"] = "boleto_out";
            } elseif (
                str_contains($upperMemo, "TED") ||
                str_contains($upperMemo, "TRANSF")
            ) {
                $prefix = "Transferência";
                $result["movement_type"] = "transfer";
            } elseif (str_contains($upperMemo, "DEP DINHEIRO")) {
                $prefix = "Depósito ATM";
                $result["movement_type"] = "atm_in";
            } else {
                $prefix = "Pix Pago";
                $result["movement_type"] = "pix_out";
            }
            $result["clean_description"] = $result["name"]; // 🚀 Limpamos o prefixo do nome para a tabela ficar bonita!
        }
        // 2. Maquininha de Cartão
        elseif (
            preg_match(
                "/SICREDI (DEBITO|CREDITO|ANTECIPACAO|ANTEC) (VISA|MASTER|ELO|AMEX)/i",
                $memo,
                $matches,
            )
        ) {
            $result["name"] =
                "Repasse " .
                ucfirst(strtolower($matches[1])) .
                " " .
                $matches[2];
            $result["clean_description"] = $result["name"];
            $result["movement_type"] = "card_in";
        }
        // 3. Maquininha Física
        elseif (str_starts_with($upperMemo, "REEMBOLSO POS SICREDI")) {
            $result["name"] = "Banco Sicredi";
            $result["clean_description"] = "Reembolso Maquininha POS";
            $result["movement_type"] = "card_in";
        }
        // 4. Contas Fixas
        elseif (
            preg_match(
                "/DEBITO CONVENIOS-(.*?)\s+(?:ID.*?)?(?:([0-9]{11,14}))/i",
                $memo,
                $matches,
            )
        ) {
            $result["name"] = trim($matches[1]);
            $result["document"] = isset($matches[2]) ? $matches[2] : null;
            $result["clean_description"] =
                "Convênio / Fatura - " . $result["name"];
            $result["movement_type"] = "fixed_bill";
        }
        // 5. Arrecadação de Impostos
        elseif (str_starts_with($upperMemo, "DEBITO ARRECADACAO")) {
            if (preg_match("/([0-9]{14})/", $memo, $matches)) {
                $result["document"] = $matches[1];
            }
            $result["name"] = "Receita Federal / Impostos";
            $result["clean_description"] = "Imposto (DARF/DAS/GPS)";
            $result["movement_type"] = "tax_bill";
        }
        // 6. Saque Físico
        elseif (str_starts_with($upperMemo, "SAQUE DINHEIRO ATM")) {
            $result["clean_description"] = "Saque em Dinheiro (ATM)";
            $result["movement_type"] = "atm_out";
        }
        // 7. Folha de Pagamento em Lote
        elseif (str_starts_with($upperMemo, "DEB. FOLHA PAGTO")) {
            $result["clean_description"] = "Folha de Pagamento (Lote)";
            $result["movement_type"] = "payroll";
        }
        // 8. Taxas e Juros do Banco
        elseif (
            str_starts_with($upperMemo, "CESTA DE RELACIONAMENTO") ||
            str_starts_with($upperMemo, "PLANO INT CAPITAL") ||
            str_starts_with($upperMemo, "IOF") ||
            str_starts_with($upperMemo, "JUROS UTILIZ")
        ) {
            $result["name"] = "Banco Sicredi";
            $result["clean_description"] = str_contains($upperMemo, "CESTA")
                ? "Tarifa Bancária (Cesta)"
                : "Juros e Encargos";
            $result["movement_type"] = "bank_fee";
        }

        $result["clean_description"] = preg_replace(
            "/\s+/",
            " ",
            $result["clean_description"],
        );
        return $result;
    }
}
