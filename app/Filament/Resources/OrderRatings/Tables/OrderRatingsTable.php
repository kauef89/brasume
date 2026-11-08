<?php

namespace App\Filament\Resources\OrderRatings\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Actions\Action;
use App\Models\OrderRating;
use App\Filament\Resources\Orders\OrderResource;
use Illuminate\Database\Eloquent\Builder;

class OrderRatingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Data / Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('rating')
                    ->label('Avaliação')
                    ->badge()
                    ->color(fn (int $state): string => match ($state) {
                        5       => 'success',
                        4       => 'info',
                        3       => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn (int $state): string => match ($state) {
                        5 => '★★★★★ (5)',
                        4 => '★★★★☆ (4)',
                        3 => '★★★☆☆ (3)',
                        2 => '★★☆☆☆ (2)',
                        1 => '★☆☆☆☆ (1)',
                        default => "{$state} estrelas",
                    })
                    ->sortable(),

                TextColumn::make('order.id')
                    ->label('Pedido')
                    ->formatStateUsing(fn ($state) => "#{$state}")
                    ->url(fn (OrderRating $record): ?string => $record->order_id ? OrderResource::getUrl('edit', ['record' => $record->order_id]) : null)
                    ->color('primary')
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->searchable()
                    ->placeholder('Consumidor Final')
                    ->description(fn (OrderRating $record) => $record->customer?->phone),

                TextColumn::make('tags')
                    ->label('Destaques / Tags')
                    ->badge()
                    ->separator(',')
                    ->placeholder('-'),

                TextColumn::make('comment')
                    ->label('Comentário')
                    ->searchable()
                    ->wrap()
                    ->placeholder('Sem comentário por escrito')
                    ->limit(60),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('rating')
                    ->label('Classificação por Estrelas')
                    ->options([
                        5 => '★★★★★ (5 Estrelas - Excelente)',
                        4 => '★★★★☆ (4 Estrelas - Bom)',
                        3 => '★★★☆☆ (3 Estrelas - Regular)',
                        2 => '★★☆☆☆ (2 Estrelas - Ruim)',
                        1 => '★☆☆☆☆ (1 Estrela - Péssimo)',
                    ]),

                TernaryFilter::make('has_comment')
                    ->label('Comentário Escrito')
                    ->placeholder('Todas as avaliações')
                    ->trueLabel('Apenas com comentário')
                    ->falseLabel('Sem comentário')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('comment')->where('comment', '!=', ''),
                        false: fn (Builder $q) => $q->whereNull('comment')->orWhere('comment', ''),
                    ),

                Filter::make('created_at')
                    ->form([
                        DatePicker::make('from')->label('Avaliado de'),
                        DatePicker::make('until')->label('Até'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
                    ),
            ])
            ->actions([
                Action::make('whatsapp_sac')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->visible(fn (OrderRating $record) => !empty($record->customer?->phone))
                    ->url(function (OrderRating $record): string {
                        $phone = preg_replace('/\D/', '', (string) ($record->customer?->phone ?? ''));
                        if (strlen($phone) <= 11 && !str_starts_with($phone, '55')) {
                            $phone = '55' . $phone;
                        }
                        $name = $record->customer?->name ? explode(' ', trim($record->customer->name))[0] : 'Cliente';
                        $orderId = $record->order_id;
                        $rating = $record->rating;
                        $text = urlencode("Olá {$name}! Aqui é da equipe do Brasume. Vimos sua avaliação de {$rating} estrela(s) sobre o pedido #{$orderId}. Gostaríamos de saber mais sobre a sua experiência!");
                        return "https://wa.me/{$phone}?text={$text}";
                    }, shouldOpenInNewTab: true),
            ]);
    }
}