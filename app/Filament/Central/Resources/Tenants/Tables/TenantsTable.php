<?php

namespace App\Filament\Central\Resources\Tenants\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
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
            ->columns([
                TextColumn::make('name')
                    ->label('Lanchonete')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('domains.domain')
                    ->label('Domínio')
                    ->badge()
                    ->color('success')
                    ->searchable(),

                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('phone')
                    ->label('Telefone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // Exibe o status da conta como um ícone
                IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->actions([
                EditAction::make(),

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
