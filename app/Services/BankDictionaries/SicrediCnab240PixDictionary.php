<?php

namespace App\Services\BankDictionaries;

use App\Enums\PayableSettlementMode;
use App\Models\AccountPayable;

/**
 * Dicionário de regras do Manual Sicredi CNAB 240 FEBRABAN (v3.4)
 * Produto: Pagamento Fornecedor (Serviço 20) / Forma de Lançamento 45 (PIX Transferência)
 * Segmentos A (Câmara 009 - SPI) e B (Identificação Chave PIX 01..04).
 */
class SicrediCnab240PixDictionary
{
    public const SERVICE_TYPE_SUPPLIER = '20';
    public const LAUNCH_FORM_PIX       = '45';
    public const CLEARING_HOUSE_SPI    = '009';
    public const LOT_LAYOUT_VERSION    = '042';

    public const KEY_TYPE_PHONE  = '01';
    public const KEY_TYPE_EMAIL  = '02';
    public const KEY_TYPE_DOC    = '03';
    public const KEY_TYPE_RANDOM = '04';
    public const KEY_TYPE_BANK   = '05';

    /**
     * Classifica e normaliza uma chave PIX segundo as regras do Manual Sicredi (págs. 16 e 44).
     */
    public static function classifyPixKey(?string $rawKey, ?string $fallbackDocument = null): ?array
    {
        $key = trim((string) $rawKey);
        $cleanDoc = preg_replace('/\D/', '', (string) $fallbackDocument);

        if ($key === '') {
            return null;
        }

        // 1. E-mail (Tipo '02', máx 77 caracteres)
        if (filter_var($key, FILTER_VALIDATE_EMAIL)) {
            $email = mb_strtolower($key);
            if (strlen($email) > 77) {
                return null;
            }
            return [
                'type'       => self::KEY_TYPE_EMAIL,
                'type_label' => '02 - E-mail',
                'key_value'  => $email,
                'doc_type'   => strlen($cleanDoc) === 14 ? '2' : (strlen($cleanDoc) === 11 ? '1' : '0'),
                'doc_number' => $cleanDoc ?: '0',
            ];
        }

        // 2. Chave Aleatória EVP / UUID (Tipo '04', 36 posições com traços)
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $key)) {
            return [
                'type'       => self::KEY_TYPE_RANDOM,
                'type_label' => '04 - Chave Aleatória',
                'key_value'  => strtolower($key),
                'doc_type'   => strlen($cleanDoc) === 14 ? '2' : (strlen($cleanDoc) === 11 ? '1' : '0'),
                'doc_number' => $cleanDoc ?: '0',
            ];
        }

        $digits = preg_replace('/\D/', '', $key);

        // 3. Telefone explícito com +55 ou máscara de celular/telefone
        $hasPhoneHint = str_starts_with($key, '+')
            || preg_match('/^\(?\d{2}\)?\s*9?\d{4}[-\s]\d{4}$/', $key);

        if ($hasPhoneHint) {
            if (str_starts_with($digits, '55') && in_array(strlen($digits), [12, 13], true)) {
                $phone = '+' . $digits;
            } elseif (in_array(strlen($digits), [10, 11], true)) {
                $phone = '+55' . $digits;
            } else {
                return null;
            }

            return [
                'type'       => self::KEY_TYPE_PHONE,
                'type_label' => '01 - Telefone',
                'key_value'  => $phone,
                'doc_type'   => strlen($cleanDoc) === 14 ? '2' : (strlen($cleanDoc) === 11 ? '1' : '0'),
                'doc_number' => $cleanDoc ?: '0',
            ];
        }

        // 4. CPF (11 dígitos) ou CNPJ (14 dígitos) — Tipo '03'
        // Regra Manual Sicredi Pág. 16: O banco valida Posições 18 e 19-32 do Segmento B.
        if (strlen($digits) === 14) {
            return [
                'type'       => self::KEY_TYPE_DOC,
                'type_label' => '03 - CNPJ',
                'key_value'  => $digits,
                'doc_type'   => '2',
                'doc_number' => $digits,
            ];
        }

        if (strlen($digits) === 11) {
            // Se o cadastro tem CPF conhecido e difere dos 11 dígitos informados, trata como celular (DDD + 9 dígitos)
            if (strlen($cleanDoc) === 11 && $cleanDoc !== $digits && substr($digits, 2, 1) === '9') {
                return [
                    'type'       => self::KEY_TYPE_PHONE,
                    'type_label' => '01 - Telefone',
                    'key_value'  => '+55' . $digits,
                    'doc_type'   => '1',
                    'doc_number' => $cleanDoc,
                ];
            }

            return [
                'type'       => self::KEY_TYPE_DOC,
                'type_label' => '03 - CPF',
                'key_value'  => $digits,
                'doc_type'   => '1',
                'doc_number' => $digits,
            ];
        }

        return null;
    }

    /**
     * Resolve dados completos do favorecido (Fornecedor ou Colaborador) a partir de um título do Contas a Pagar.
     */
    public static function resolvePayee(AccountPayable $payable): array
    {
        $payable->loadMissing(['supplier', 'employee']);

        $mode = $payable->settlement_mode instanceof PayableSettlementMode
            ? $payable->settlement_mode
            : (PayableSettlementMode::tryFrom((string) $payable->settlement_mode) ?? PayableSettlementMode::MANUAL);

        $beneficiaryType = 'Avulso';
        $beneficiaryName = $payable->supplier_name ?: $payable->description;
        $rawDocument = null;
        $rawPixKey = null;

        if ($payable->employee) {
            $emp = $payable->employee;
            $empTypeLabel = is_object($emp->employment_type) && method_exists($emp->employment_type, 'getLabel')
                ? $emp->employment_type->getLabel()
                : 'Colaborador';
            $beneficiaryType = "Colaborador ({$empTypeLabel})";
            $beneficiaryName = $emp->name;
            $rawDocument = $emp->cpf;
            $rawPixKey = $emp->pix_key;
        } elseif ($payable->supplier) {
            $sup = $payable->supplier;
            $beneficiaryType = 'Fornecedor';
            $beneficiaryName = $sup->document_name ?: $sup->name;
            $rawDocument = $sup->document;
            $rawPixKey = $sup->pix_key;
        }

        if ($mode->isBankAutomated()) {
            return [
                'valid'            => false,
                'blocked_by_mode'  => true,
                'beneficiary_type' => $beneficiaryType,
                'beneficiary_name' => $beneficiaryName,
                'settlement_mode'  => $mode,
                'pix'              => null,
                'error_reason'     => "Título configurado como {$mode->getLabel()} (gira automaticamente no banco).",
            ];
        }

        $classified = self::classifyPixKey($rawPixKey, $rawDocument);

        if (!$classified) {
            $reason = empty(trim((string) $rawPixKey))
                ? "Favorecido sem Chave PIX cadastrada."
                : "Chave PIX cadastrada ('{$rawPixKey}') em formato inválido para CNAB 240.";

            return [
                'valid'            => false,
                'blocked_by_mode'  => false,
                'beneficiary_type' => $beneficiaryType,
                'beneficiary_name' => $beneficiaryName,
                'settlement_mode'  => $mode,
                'pix'              => null,
                'error_reason'     => $reason,
            ];
        }

        return [
            'valid'            => true,
            'blocked_by_mode'  => false,
            'beneficiary_type' => $beneficiaryType,
            'beneficiary_name' => $beneficiaryName,
            'settlement_mode'  => $mode,
            'pix'              => $classified,
            'error_reason'     => null,
        ];
    }
}