<?php

namespace App\Filament\Resources\BankStatements\Tables;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Illuminate\Support\HtmlString;
use App\Enums\BankMovementType;
use App\Models\BankStatement;
use App\Models\Supplier;
use App\Models\Employee;
use App\Filament\Resources\BankStatements\Schemas\ConciliationTabsSchema;
use App\Filament\Resources\BankStatements\Actions\BulkPersonalAction;
use App\Filament\Resources\BankStatements\Actions\BulkExpenseAction;
use App\Filament\Resources\BankStatements\Actions\BulkEmployeeAction;
use App\Filament\Resources\BankStatements\Actions\BulkRecurringAction;
use App\Filament\Resources\BankStatements\Actions\BulkTransferAction;
use Filament\Notifications\Notification;

class BankStatementsTable
{
    public static function build(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make("bank_date")
                    ->label("Data")
                    ->date("d/m/Y")
                    ->sortable(),

                Tables\Columns\TextColumn::make("movement_type")
                    ->label("Operação")
                    ->badge(),

                Tables\Columns\TextColumn::make("counterparty_name")
                    ->label("Contraparte")
                    ->searchable()
                    ->weight("bold")
                    ->description(function (BankStatement $record) {
                        $badge = "";
                        if ($record->document) {
                            if (Supplier::where("document", $record->document)->exists()) {
                                $badge = '<span class="mt-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-100 text-blue-800">🏢 Fornecedor</span>';
                            } elseif (Employee::where("cpf", $record->document)->exists()) {
                                $badge = '<span class="mt-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-fuchsia-100 text-fuchsia-800">👤 Colaborador</span>';
                            }
                        }
                        return $badge ? new HtmlString($badge) : null;
                    }),

                Tables\Columns\TextColumn::make("document")
                    ->label("Documento / ID")
                    ->searchable()
                    ->getStateUsing(fn (BankStatement $record) => $record->document ?: '-')
                    ->description(fn (BankStatement $record) => $record->statement_identifier ? "ID: " . $record->statement_identifier : null),

                Tables\Columns\TextColumn::make("amount")
                    ->label("Valor")
                    ->money("BRL")
                    ->sortable()
                    ->color(fn($record) => $record->type === "credit" ? "success" : "danger")
                    ->weight("bold"),

                Tables\Columns\TextColumn::make("related_name")
                    ->label("Vínculo / Pedido")
                    ->getStateUsing(function (BankStatement $record): string {
                        if ($record->status === 'pending') return '-';
                        return $record->related_name;
                    })
                    ->url(function (BankStatement $record): ?string {
                        if ($record->conciliable_type === \App\Models\Payment::class && $record->conciliable?->order_id) {
                            return \App\Filament\Resources\Orders\OrderResource::getUrl('edit', ['record' => $record->conciliable->order_id]);
                        }
                        if ($record->conciliable_type === \App\Models\Order::class && $record->conciliable_id) {
                            return \App\Filament\Resources\Orders\OrderResource::getUrl('edit', ['record' => $record->conciliable_id]);
                        }
                        return null;
                    })
                    ->openUrlInNewTab()
                    ->color('info')
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make("status")
                    ->label("Status / Origem")
                    ->badge()
                    ->formatStateUsing(
                        fn(string $state): string => match ($state) {
                            "pending" => "Pendente", "reconciled" => "Conciliado", "ignored" => "Ignorado", default => $state,
                        }
                    )
                    ->colors(["warning" => "pending", "success" => "reconciled", "gray" => "ignored"])
                    ->description(function (BankStatement $record): ?string {
                        if ($record->status === 'pending') return null;
                        $src = $record->reconciliation_source === 'manual'
                            ? '👤 Manual' . ($record->reconciled_by_name ? " • {$record->reconciled_by_name}" : '')
                            : '🤖 Automática';
                        $dt = $record->reconciled_at ? ' (' . $record->reconciled_at->format('d/m H:i') . ')' : '';
                        return $src . $dt;
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('bank_account_id')
                    ->label('Conta Bancária')
                    ->relationship('bankAccount', 'name'),
                Tables\Filters\SelectFilter::make('movement_type')
                    ->label('Operação')
                    ->options(BankMovementType::class)
                    ->multiple(),
                Tables\Filters\Filter::make('bank_date')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('De'),
                        \Filament\Forms\Components\DatePicker::make('until')->label('Até'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('bank_date', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('bank_date', '<=', $date));
                    }),
            ])
            ->actions([
            Action::make("conciliar")
                ->label("Conciliar")
                ->icon("heroicon-m-arrows-right-left")
                ->button()
                ->outlined()
                ->color("gray")
                ->visible(fn(BankStatement $record): bool => $record->status === "pending")
                ->modalHeading("Conciliar Transação")
                // 🚀 Abre elegantemente na lateral
                ->slideOver()
                ->fillForm(fn (BankStatement $record): array => ConciliationTabsSchema::detectSmartDefaults($record))
                ->form(fn(BankStatement $record) => [ConciliationTabsSchema::make($record)])
                ->action(function (array $data, BankStatement $record): void {
                    ConciliationTabsSchema::processConciliation($data, $record);

                    \Filament\Notifications\Notification::make()
                        ->title("Conciliado com sucesso!")
                        ->success()
                        ->send();
                }),

           //     Action::make("ignorar")
           //         ->label("Ignorar")
           //         ->icon("heroicon-m-x-mark")
           //         ->button()
           //         ->color("gray")
           //         ->requiresConfirmation()
           //         ->visible(fn(BankStatement $record): bool => $record->status === "pending")
           //         ->action(fn(BankStatement $record) => $record->update(["status" => "ignored"])),
            
Action::make("desfazer_conciliacao")
                ->label("Desfazer")
                ->icon("heroicon-m-arrow-uturn-left")
                ->button()
                ->outlined()
                ->color("danger")
                ->visible(fn(BankStatement $record): bool => in_array($record->status, ["reconciled", "ignored"], true))
                ->requiresConfirmation()
                ->modalHeading("Desfazer Conciliação Bancária")
                ->modalDescription("O lançamento voltará para 'Pendente' e qualquer vínculo com pedido ou conta será desfeito. Deseja continuar?")
                ->modalSubmitActionLabel("Sim, Desfazer")
                ->action(function (BankStatement $record): void {
                    \App\Services\Accounting\BankReconciliationService::undo($record);

                    Notification::make()
                        ->title("Conciliação desfeita!")
                        ->body("O lançamento voltou para a fila de pendentes.")
                        ->warning()
                        ->send();
                }),
                        ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkExpenseAction::make(),
                    BulkEmployeeAction::make(), // 🚀 A Nova
                    BulkTransferAction::make(),
                    BulkRecurringAction::make(), // 🚀 A Nova
                    BulkPersonalAction::make(),
                ]),
            ])
            ->defaultSort("bank_date", "desc");
    }
}
