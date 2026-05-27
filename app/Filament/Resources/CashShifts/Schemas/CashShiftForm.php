<?php

namespace App\Filament\Resources\CashShifts\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;

class CashShiftForm
{
    /**
     * @return array<int,Section>
     */
    public static function getFormSchema(): array
    {
        return [
            Section::make("Auditoria do Turno")
                ->schema([
                    TextInput::make("bankAccount.name")
                        ->label("Gaveta / Cofre")
                        ->readOnly(),
                    TextInput::make("opener.name")
                        ->label("Aberto por")
                        ->readOnly(),
                    TextInput::make("closer.name")
                        ->label("Fechado por")
                        ->readOnly(),
                    DateTimePicker::make("opened_at")
                        ->label("Data de Abertura")
                        ->readOnly(),
                    DateTimePicker::make("closed_at")
                        ->label("Data de Fechamento")
                        ->readOnly(),
                ])
                ->columns(3)
                ->columnSpanFull(), // <-- Força a pular de linha!

            Section::make("Valores Financeiros")
                ->schema([
                    TextInput::make("opening_balance")
                        ->label("Fundo de Troco")
                        ->prefix('R$')
                        ->readOnly(),
                    TextInput::make("closing_balance_expected")
                        ->label("Esperado (Sistema)")
                        ->prefix('R$')
                        ->readOnly(),
                    TextInput::make("closing_balance_actual")
                        ->label("Contado (Gaveta)")
                        ->prefix('R$')
                        ->readOnly(),
                    Textarea::make("notes")
                        ->label("Justificativa de Quebra")
                        ->readOnly()
                        ->columnSpanFull(),
                ])
                ->columns(3)
                ->columnSpanFull(), // <-- Força a pular de linha!
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }
}
