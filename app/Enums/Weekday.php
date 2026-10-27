<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Weekday: string implements HasLabel
{
    case SUN = 'sun';
    case MON = 'mon';
    case TUE = 'tue';
    case WED = 'wed';
    case THU = 'thu';
    case FRI = 'fri';
    case SAT = 'sat';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::SUN => 'Domingo',
            self::MON => 'Segunda-feira',
            self::TUE => 'Terça-feira',
            self::WED => 'Quarta-feira',
            self::THU => 'Quinta-feira',
            self::FRI => 'Sexta-feira',
            self::SAT => 'Sábado',
        };
    }

    public function getShortLabel(): string
    {
        return match ($this) {
            self::SUN => 'Dom',
            self::MON => 'Seg',
            self::TUE => 'Ter',
            self::WED => 'Qua',
            self::THU => 'Qui',
            self::FRI => 'Sex',
            self::SAT => 'Sáb',
        };
    }

    /**
     * Retorna o índice numérico padrão Carbon / PHP date('w') (0 = Domingo, ..., 6 = Sábado).
     */
    public function getDayOfWeek(): int
    {
        return match ($this) {
            self::SUN => 0,
            self::MON => 1,
            self::TUE => 2,
            self::WED => 3,
            self::THU => 4,
            self::FRI => 5,
            self::SAT => 6,
        };
    }

    /**
     * Instancia o Enum a partir do dia numérico do Carbon (0 = Domingo, 6 = Sábado).
     */
    public static function fromDayOfWeek(int $day): self
    {
        return match ($day % 7) {
            0 => self::SUN,
            1 => self::MON,
            2 => self::TUE,
            3 => self::WED,
            4 => self::THU,
            5 => self::FRI,
            6 => self::SAT,
            default => self::SUN,
        };
    }

    /**
     * Retorna o Enum correspondente ao dia de hoje no servidor.
     */
    public static function today(): self
    {
        return self::fromDayOfWeek((int) now()->dayOfWeek);
    }

    /**
     * Opções formatadas indexadas por inteiro (0 a 6) para Filament e banco numérico.
     */
    public static function numericOptions(): array
    {
        return [
            0 => self::SUN->getLabel(),
            1 => self::MON->getLabel(),
            2 => self::TUE->getLabel(),
            3 => self::WED->getLabel(),
            4 => self::THU->getLabel(),
            5 => self::FRI->getLabel(),
            6 => self::SAT->getLabel(),
        ];
    }
}