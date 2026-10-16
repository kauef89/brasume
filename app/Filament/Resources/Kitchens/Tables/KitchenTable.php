<?php

namespace App\Filament\Resources\Kitchens\Tables;

use Filament\Tables\Table;
use Filament\Tables;

// 🚀 IMPORTAÇÕES UNIFICADAS DO FILAMENT V4
use Filament\Actions;
use App\Models\Category;
use App\Models\Product;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;

class KitchenTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Cozinha')->searchable(),
                Tables\Columns\TextColumn::make('printer.name')->label('Impressora vinculada'),
                Tables\Columns\ToggleColumn::make('is_active')->label('Ativa'),
            ])
            ->recordActions([
                // 🚀 Usando o namespace raiz unificado
                Actions\EditAction::make()->iconButton()->tooltip('Editar'),

                // 🚀 O BOTÃO MÁGICO DE ATRIBUIÇÃO EM LOTE
                Actions\Action::make('bulk_assign')
                    ->label('Vincular Categorias')
                    ->icon('heroicon-o-sparkles')
                    ->color('success')
                    ->iconButton()
                    ->tooltip('Atribuir a categorias inteiras')
                    ->modalHeading(fn ($record) => "Vincular Categorias à Cozinha: {$record->name}")
                    ->modalDescription('Todos os produtos das categorias selecionadas passarão a ser preparados nesta cozinha automaticamente. (As configurações individuais anteriores serão sobrescritas).')
                    ->form([
                        Select::make('category_ids')
                            ->label('Escolha as Categorias')
                            ->options(Category::where('type', 'external')->pluck('name', 'id'))
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->required()
                    ])
                    ->action(function ($record, array $data) {
                        // Faz a injeção em massa hiper-rápida direto no banco
                        $count = Product::whereIn('category_id', $data['category_ids'])
                            ->update(['kitchen_id' => $record->id]);

                        Notification::make()
                            ->success()
                            ->title('Associação em Massa Concluída!')
                            ->body("A cozinha foi vinculada a {$count} produtos com sucesso.")
                            ->send();
                    })
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()
                ])
            ]);
    }
}
