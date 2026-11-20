<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum AuditDiagnosticStatus: string implements HasLabel, HasColor, HasIcon
{
    case OPEN                  = 'open';
    case PENDING               = 'pending';
    case RECONCILED            = 'reconciled';
    case RECONCILED_WITH_FIX   = 'reconciled_with_fix';
    case SURPLUS               = 'surplus';
    case SHORTAGE              = 'shortage';
    case METHOD_SWAP           = 'method_swap';
    case MULTIPLE_DIVERGENCE   = 'multiple_divergence';
    case HOMOLOGATED           = 'homologated';

    public function getLabel(): string
    {
        return match ($this) {
            self::OPEN                => 'Em Operação',
            self::PENDING             => 'Sem Comprovante',
            self::RECONCILED          => 'Conciliado',
            self::RECONCILED_WITH_FIX => 'Conciliado (c/ Correção)',
            self::SURPLUS             => 'Sobra',
            self::SHORTAGE            => 'Falta',
            self::METHOD_SWAP         => 'Troca de Meio',
            self::MULTIPLE_DIVERGENCE => 'Divergência Múltipla',
            self::HOMOLOGATED         => 'Homologado c/ Ressalva',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::RECONCILED          => 'success',
            self::RECONCILED_WITH_FIX,
            self::SURPLUS             => 'info',
            self::METHOD_SWAP,
            self::HOMOLOGATED         => 'warning',
            self::SHORTAGE,
            self::MULTIPLE_DIVERGENCE => 'danger',
            self::OPEN,
            self::PENDING             => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::RECONCILED,
            self::RECONCILED_WITH_FIX => 'heroicon-m-check-circle',
            self::SURPLUS             => 'heroicon-m-arrow-trending-up',
            self::SHORTAGE            => 'heroicon-m-arrow-trending-down',
            self::METHOD_SWAP         => 'heroicon-m-arrows-right-left',
            self::MULTIPLE_DIVERGENCE,
            self::HOMOLOGATED         => 'heroicon-m-exclamation-triangle',
            self::OPEN,
            self::PENDING             => 'heroicon-m-clock',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this->getColor()) {
            'success' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
            'info'    => 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
            'danger'  => 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300',
            'warning' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
            default   => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
        };
    }

    public function valueTextClasses(): string
    {
        return match ($this->getColor()) {
            'success' => 'text-emerald-600 dark:text-emerald-400',
            'info'    => 'text-blue-600 dark:text-blue-400',
            'danger'  => 'text-rose-600 dark:text-rose-400',
            'warning' => 'text-amber-600 dark:text-amber-400',
            default   => 'text-gray-500 dark:text-gray-400',
        };
    }

    /**
     * Resolve o status padronizado de um canal individual (Gaveta, POS ou Pix).
     */
    public static function fromChannelDiff(float $diff, bool $hasProof = true, bool $allItemsMatched = true): self
    {
        if (!$hasProof) {
            return self::PENDING;
        }

        if (abs($diff) >= 0.05) {
            return $diff > 0 ? self::SURPLUS : self::SHORTAGE;
        }

        return $allItemsMatched ? self::RECONCILED : self::SHORTAGE;
    }

    /**
     * Resolve o diagnóstico consolidado do turno (Card Geral e Tabela de Turnos).
     */
    public static function fromShiftSummary(
        string $shiftStatus,
        float $cashDiff,
        float $electronicDiff,
        bool $hasElectronicProof = true,
        int $correctedCount = 0,
        float $pixDiff = 0.0,
        bool $hasPixProof = false
    ): self {
        if ($shiftStatus === 'open') {
            return self::OPEN;
        }

        if ($shiftStatus === 'homologated') {
            return self::HOMOLOGATED;
        }

        if ($shiftStatus === 'reconciled') {
            return $correctedCount > 0 ? self::RECONCILED_WITH_FIX : self::RECONCILED;
        }

        $hasCash = abs($cashDiff) >= 0.05;
        $hasPos  = $hasElectronicProof && abs($electronicDiff) >= 0.05;
        $hasPix  = $hasPixProof && abs($pixDiff) >= 0.05;

        if (!$hasCash && !$hasPos && !$hasPix) {
            return $correctedCount > 0 ? self::RECONCILED_WITH_FIX : self::RECONCILED;
        }

        // Sobra de um lado compensando falta equivalente do outro
        if ($hasCash && $hasPos && !$hasPix && abs($cashDiff + $electronicDiff) <= 0.05) {
            return self::METHOD_SWAP;
        }

        $activeDivergences = ($hasCash ? 1 : 0) + ($hasPos ? 1 : 0) + ($hasPix ? 1 : 0);
        if ($activeDivergences > 1) {
            return self::MULTIPLE_DIVERGENCE;
        }

        $singleDiff = $hasCash ? $cashDiff : ($hasPos ? $electronicDiff : $pixDiff);

        return $singleDiff > 0 ? self::SURPLUS : self::SHORTAGE;
    }

    public static function formatSignedDiff(float $diff): string
    {
        if (abs($diff) < 0.01) {
            return 'R$ 0,00';
        }

        $sign = $diff > 0 ? '+' : '-';

        return $sign . 'R$ ' . number_format(abs($diff), 2, ',', '.');
    }
}
