<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions; // <-- A MÁGICA ESTÁ AQUI: Importamos a pasta correta de Ações
use App\Filament\Resources\Orders\OrderResource;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';
    protected static ?string $title = 'Histórico de Pedidos';
    protected static string | \BackedEnum | null $icon = 'heroicon-o-shopping-bag';

    public function form(Schema $schema): Schema 
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Pedido #')->sortable()->searchable(),
                
                Tables\Columns\TextColumn::make('type')->label('Modalidade')->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) { 
                        'delivery' => 'Delivery', 'counter' => 'Balcão', 'pickup' => 'Retirada', 'table' => 'Mesa', default => $state 
                    })
                    ->colors(['info' => 'delivery', 'warning' => 'counter', 'success' => 'pickup', 'gray' => 'table']),
                    
                Tables\Columns\TextColumn::make('total')->label('Total')->money('BRL')->sortable(),
                
                Tables\Columns\TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pendente', 'preparing' => 'Preparando', 'ready' => 'Pronto', 'out_for_delivery' => 'Em Entrega', 'closed' => 'Concluído', 'cancelled' => 'Cancelado', default => $state
                    })
                    ->colors(['warning' => 'pending', 'info' => 'preparing', 'success' => fn ($state) => in_array($state, ['ready', 'closed']), 'danger' => 'cancelled', 'primary' => 'out_for_delivery']),
                    
                Tables\Columns\TextColumn::make('payment_status')->label('Pagamento')->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) { 
                        'paid' => 'Pago', 'partial' => 'Parcial', 'pending' => 'Pendente', 'cancelled' => 'Cancelado', default => $state 
                    })
                    ->colors(['success' => 'paid', 'warning' => fn ($state) => in_array($state, ['pending', 'partial']), 'danger' => 'cancelled']),
                    
                Tables\Columns\TextColumn::make('created_at')->label('Data')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            // <-- USAMOS A ESTRUTURA CORRETA DO SEU SISTEMA (recordActions e toolbarActions)
            ->recordActions([
                Actions\Action::make('Ver Pedido Completo')
                    ->icon('heroicon-m-eye')
                    ->url(fn ($record): string => OrderResource::getUrl('edit', ['record' => $record]))
            ])
            ->toolbarActions([]);
    }
}