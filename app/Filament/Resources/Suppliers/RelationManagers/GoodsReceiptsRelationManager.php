<?php

namespace App\Filament\Resources\Suppliers\RelationManagers;

use App\Filament\Resources\GoodsReceipts\GoodsReceiptResource;
use App\Models\GoodsReceipt;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GoodsReceiptsRelationManager extends RelationManager
{
    protected static string $relationship = 'goodsReceipts';
    protected static ?string $title = 'Notas Fiscais de Entrada (Recebimento)';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('nfe_key')
                ->label('Chave de Acesso NFe')
                ->maxLength(44),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('receipt_number')
            ->columns([
                TextColumn::make('received_at')
                    ->label('Data Recebimento')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('receipt_number')
                    ->label('Nº Nota / Recibo')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Sem número'),

                TextColumn::make('nfe_key')
                    ->label('Chave NFe')
                    ->limit(22)
                    ->tooltip(fn (GoodsReceipt $record) => $record->nfe_key)
                    ->placeholder('-'),

                TextColumn::make('total_amount')
                    ->label('Valor Total')
                    ->money('BRL')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft'     => 'gray',
                        'processed', 'completed' => 'success',
                        'cancelled' => 'danger',
                        default     => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft'     => 'Rascunho',
                        'processed', 'completed' => 'Processado',
                        'cancelled' => 'Cancelado',
                        default     => $state,
                    }),
            ])
            ->defaultSort('received_at', 'desc')
            ->actions([
                Action::make('view_receipt')
                    ->label('Ver Entrada')
                    ->icon('heroicon-m-eye')
                    ->url(fn (GoodsReceipt $record) => GoodsReceiptResource::getUrl('edit', ['record' => $record->id])),
            ]);
    }
}