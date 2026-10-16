<?php

namespace App\Filament\Resources\Coupons\Tables;


use App\Enums\CouponType;use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CouponTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Campanha / Promoção')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->placeholder('Sem nome definido')
                    ->description(fn ($record) => $record->description ? Str::limit($record->description, 45) : null),

                TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->copyable()
                    ->copyMessage('Código copiado!')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn ($record) => $record->is_pos_only ? '⚡ Exclusivo PDV' : null),

                TextColumn::make('type_and_value')
                    ->label('Desconto')
                    ->state(function ($record) {
                        if ($record->type === 'free_delivery') return 'Entrega Grátis';
                        if ($record->type === 'fixed') return 'R$ ' . number_format((float) $record->value, 2, ',', '.');
                        if ($record->type === 'fixed_total') return 'Total R$ ' . number_format((float) $record->value, 2, ',', '.');
                        return round((float) $record->value, 0) . '%';
                    })
                    ->badge()
                    ->color(fn ($record) => match ($record->type) {
                        'free_delivery' => 'info',
                        'fixed_total' => 'warning',
                        default => 'success',
                    }),

                TextColumn::make('usage_count')
                    ->label('Utilizações')
                    ->formatStateUsing(fn ($record) => $record->global_usage_limit > 0
                        ? "{$record->usage_count} / {$record->global_usage_limit}"
                        : "{$record->usage_count} (Ilimitado)")
                    ->sortable(),

                TextColumn::make('customer.name')
                    ->label('Exclusividade')
                    ->placeholder('Público (Todos)')
                    ->searchable()
                    ->description(fn ($record) => $record->first_order_only ? 'Apenas 1ª Compra' : null),

                TextColumn::make('status_badge')
                    ->label('Situação')
                    ->badge()
                    ->state(function ($record) {
                        if (!$record->is_active) return 'Desativado';
                        if ($record->expires_at && now()->greaterThan($record->expires_at)) return 'Expirado';
                        if ($record->starts_at && now()->lessThan($record->starts_at)) return 'Programado';
                        if ($record->global_usage_limit > 0 && $record->usage_count >= $record->global_usage_limit) return 'Esgotado';
                        return 'Ativo';
                    })
                    ->colors([
                        'success' => 'Ativo',
                        'info' => 'Programado',
                        'danger' => fn ($state) => in_array($state, ['Desativado', 'Expirado', 'Esgotado']),
                    ]),

                ToggleColumn::make('is_active')
                    ->label('Ativo?')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status de Ativação'),

                TernaryFilter::make('is_pos_only')
                    ->label('Canal')
                    ->trueLabel('Apenas PDV (Caixa)')
                    ->falseLabel('Disponível no App / Delivery'),

                SelectFilter::make('type')
                    ->label('Tipo de Desconto')
                    ->options(CouponType::class),
            ])
            ->actions([
                ViewAction::make()->iconButton(),
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
