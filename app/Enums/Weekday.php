<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Weekday: string implements HasLabel
{
    case MON = 'mon';
    case TUE = 'tue';
    case WED = 'wed';
    case THU = 'thu';
    case FRI = 'fri';
    case SAT = 'sat';
    case SUN = 'sun';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::MON => 'Segunda-feira',
            self::TUE => 'Terça-feira',
            self::WED => 'Quarta-feira',
            self::THU => 'Quinta-feira',
            self::FRI => 'Sexta-feira',
            self::SAT => 'Sábado',
            self::SUN => 'Domingo',
        };
    }

    public function getShortLabel(): string
    {
        return match ($this) {
            self::MON => 'Seg',
            self::TUE => 'Ter',
            self::WED => 'Qua',
            self::THU => 'Qui',
            self::FRI => 'Sex',
            self::SAT => 'Sáb',
            self::SUN => 'Dom',
        };
    }
}
