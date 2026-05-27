<?php

namespace App\Filament\Central\Resources\Tenants\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;

class TenantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // ... (as suas columns e filters ficam iguais) ...
            ->actions([
                EditAction::make(),
                
                // 🚨 Ação de Eliminação com Desafio de Segurança
                DeleteAction::make()
                    ->modalHeading(fn ($record) => "Excluir Lanchonete: {$record->name}")
                    ->modalDescription('ATENÇÃO: Esta ação é irreversível e destruirá todo o banco de dados desta lanchonete. Para continuar, confirme a sua intenção.')
                    ->form([
                        TextInput::make('challenge')
                            ->label(fn ($record) => "Digite exatamente '{$record->id}' para confirmar")
                            ->required()
                            ->rules([
                                fn ($record) => function (string $attribute, $value, \Closure $fail) use ($record) {
                                    if ($value !== $record->id) {
                                        $fail('O identificador digitado não confere. Ação abortada.');
                                    }
                                },
                            ]),
                    ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    // Para a exclusão em massa, um desafio genérico
                    DeleteBulkAction::make()
                        ->modalHeading('Excluir Múltiplas Lanchonetes')
                        ->form([
                            TextInput::make('challenge')
                                ->label("Digite 'EXCLUIR TUDO' para confirmar")
                                ->required()
                                ->in(['EXCLUIR TUDO'])
                                ->validationMessages([
                                    'in' => 'Você deve digitar EXCLUIR TUDO exatamente como escrito.',
                                ]),
                        ]),
                ]),
            ]);
    }
}