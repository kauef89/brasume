namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum AttendanceStatus: string implements HasLabel, HasColor, HasIcon
{
    case EXPECTED  = 'expected';   // Escalado / Aguardado no turno (não chegou ainda)
    case PRESENT   = 'present';    // Presente / Em atividade confirmada
    case LATE      = 'late';       // Chegou com atraso registrado
    case ABSENT    = 'absent';     // Falta não justificada
    case JUSTIFIED = 'justified';  // Falta justificada (atestado / dispensa)

    public function getLabel(): ?string
    {
        return match ($this) {
            self::EXPECTED  => 'Aguardado (Escalado)',
            self::PRESENT   => 'Presente',
            self::LATE      => 'Atrasado',
            self::ABSENT    => 'Falta',
            self::JUSTIFIED => 'Justificada',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::EXPECTED  => 'warning',
            self::PRESENT   => 'success',
            self::LATE      => 'amber',
            self::ABSENT    => 'danger',
            self::JUSTIFIED => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::EXPECTED  => 'heroicon-o-clock',
            self::PRESENT   => 'heroicon-o-check-circle',
            self::LATE      => 'heroicon-o-exclamation-circle',
            self::ABSENT    => 'heroicon-o-x-circle',
            self::JUSTIFIED => 'heroicon-o-document-text',
        };
    }
}
