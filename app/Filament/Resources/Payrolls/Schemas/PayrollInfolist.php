<?php

namespace App\Filament\Resources\Payrolls\Schemas;

use Filament\Schemas\Schema;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;

class PayrollInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Resumo da Competência')
                ->schema([
                    Grid::make(4)->schema([
                        TextEntry::make('reference_period')
                            ->label('Competência')
                            ->date('m/Y')
                            ->weight('bold'),

                        TextEntry::make('payment_date')
                            ->label('Previsão de Pagamento')
                            ->date('d/m/Y'),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'draft'    => 'warning',
                                'approved' => 'primary',
                                'paid'     => 'success',
                                default    => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'draft'    => 'Rascunho / Aberta',
                                'approved' => 'Aprovada',
                                'paid'     => 'Paga / Finalizada',
                                default    => $state,
                            }),

                        TextEntry::make('payslips_count')
                            ->label('Total de Colaboradores')
                            ->state(fn ($record) => $record->payslips()->count() . ' colaboradores')
                            ->weight('bold'),
                    ]),
                ]),

            Section::make('Consolidação Financeira & Encargos')
                ->schema([
                    Grid::make(5)->schema([
                        TextEntry::make('total_earnings')
                            ->label('Total Bruto (Proventos)')
                            ->money('BRL'),

                        TextEntry::make('total_deductions')
                            ->label('Total Descontos (Retenções)')
                            ->money('BRL')
                            ->color('danger'),

                        TextEntry::make('total_net')
                            ->label('Líquido a Desembolsar')
                            ->money('BRL')
                            ->weight('black')
                            ->color('success'),

                        TextEntry::make('total_fgts')
                            ->label('FGTS a Recolher')
                            ->money('BRL'),

                        TextEntry::make('total_inss')
                            ->label('INSS Total')
                            ->money('BRL'),
                    ]),
                ]),
        ]);
    }
}
