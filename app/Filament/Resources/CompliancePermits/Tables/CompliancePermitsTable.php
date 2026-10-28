<?php

namespace App\Filament\Resources\CompliancePermits\Tables;

use App\Enums\CompliancePermitType;
use App\Models\CompliancePermit;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class CompliancePermitsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('expires_at', 'asc')
            ->columns([
                TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->state(fn (CompliancePermit $record) => match ($record->status) {
                        'expired'       => 'VENCIDO',
                        'expiring_soon' => 'A VENCER',
                        default         => 'VIGENTE',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'VENCIDO'  => 'danger',
                        'A VENCER' => 'warning',
                        default    => 'success',
                    }),

                TextColumn::make('name')
                    ->label('Obrigação / Licença')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (CompliancePermit $record) => $record->issuer_name ?: $record->document_number),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->sortable(),

                TextColumn::make('expires_at')
                    ->label('Validade')
                    ->date('d/m/Y')
                    ->sortable()
                    ->description(function (CompliancePermit $record) {
                        $days = $record->days_remaining;
                        if ($days < 0) return abs($days) . ' dia(s) atrás';
                        if ($days === 0) return 'Vence hoje!';
                        return "Restam {$days} dia(s)";
                    }),

                TextColumn::make('estimated_cost')
                    ->label('Taxa Estimada')
                    ->money('BRL')
                    ->sortable()
                    ->placeholder('-'),

                IconColumn::make('document_path')
                    ->label('Laudo/PDF')
                    ->boolean()
                    ->trueIcon('heroicon-o-paper-clip')
                    ->falseIcon('heroicon-o-minus')
                    ->color(fn ($state) => $state ? 'primary' : 'gray'),

                IconColumn::make('account_payable_id')
                    ->label('No Caixa?')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->color(fn ($state) => $state ? 'success' : 'gray')
                    ->tooltip(fn (CompliancePermit $record) => $record->account_payable_id ? 'Conta a pagar provisionada' : 'Ainda não provisionado'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo de Licença')
                    ->options(CompliancePermitType::class),
            ])
            ->actions([
                Action::make('download_document')
                    ->label('Ver Laudo')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (CompliancePermit $record) => !empty($record->document_path))
                    ->url(fn (CompliancePermit $record) => Storage::url($record->document_path), shouldOpenInNewTab: true),

                Action::make('generate_payable')
                    ->label('Lançar Conta a Pagar')
                    ->icon('heroicon-m-banknotes')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Provisionar Taxa de Renovação')
                    ->modalDescription('Deseja criar um Contas a Pagar na categoria de Taxas e Alvarás para a data de vencimento desta licença?')
                    ->visible(fn (CompliancePermit $record) => empty($record->account_payable_id))
                    ->action(function (CompliancePermit $record) {
                        $payable = $record->generatePayable();
                        if ($payable) {
                            Notification::make()
                                ->title('Conta a pagar provisionada no valor de R$ ' . number_format($payable->amount, 2, ',', '.'))
                                ->success()
                                ->send();
                        }
                    }),
            ]);
    }
}