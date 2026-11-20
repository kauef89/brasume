<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Filament\Resources\Orders\Schemas\OrderInfolist;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Pedido')
                    ->formatStateUsing(fn ($state) => "#{$state}")
                    ->weight('bold')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data / Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('source')
                    ->label('Origem')
                    ->badge()
                    ->color(fn ($state) => str_starts_with((string) $state, 'customer_app') ? 'info' : 'gray')
                    ->formatStateUsing(fn ($state) => match ((string) $state) {
                        'customer_app'       => 'CustomerApp',
                        'customer_app_table' => 'Mesa QR',
                        'pos_app'            => 'PDV',
                        'cashier'            => 'Caixa',
                        'team_app'           => 'TeamApp',
                        'kiosk'              => 'Totem',
                        'backoffice'         => 'Retaguarda',
                        default              => (string) ($state ?: 'Sistema'),
                    }),

                Tables\Columns\TextColumn::make('type')
                    ->label('Modalidade')
                    ->badge(),

                Tables\Columns\TextColumn::make('customer_info')
                    ->label('Cliente & Perfil')
                    ->state(function (Order $record) {
                        if ($record->customer) {
                            return $record->customer->name;
                        }
                        if ($record->customer_name) {
                            return $record->customer_name;
                        }
                        if ($record->table_number || $record->diningTable) {
                            return $record->diningTable?->name ?? "Mesa {$record->table_number}";
                        }
                        return 'Não Identificado';
                    })
                    ->description(function (Order $record) {
                        $profile = OrderInfolist::resolveCustomerProfile($record);
                        $phone = $record->customer_phone ?: $record->customer?->phone;
                        $phoneText = $phone ? " • {$phone}" : '';
                        return "{$profile['label']}{$phoneText}";
                    })
                    ->searchable(['customer_name', 'customer_phone']),

                Tables\Columns\TextColumn::make('payment_summary')
                    ->label('Pagamento / Promessa')
                    ->state(function (Order $record) {
                        if ($record->payments && $record->payments->where('is_paid', true)->isNotEmpty()) {
                            return $record->payments
                                ->where('is_paid', true)
                                ->pluck('method')
                                ->unique()
                                ->implode(' + ');
                        }

                        if (is_array($record->payment_intent_methods) && !empty($record->payment_intent_methods)) {
                            $methods = collect($record->payment_intent_methods)
                                ->pluck('method_name')
                                ->filter()
                                ->unique()
                                ->implode(' + ');
                            return $methods ? "Promessa: {$methods}" : 'Promessa não detalhada';
                        }

                        return OrderInfolist::formatIntentStatus($record->payment_intent_status);
                    })
                    ->description(fn (Order $record) => OrderInfolist::formatPaymentStatus($record->payment_status)),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('BRL')
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => OrderInfolist::statusColor((string) $state))
                    ->formatStateUsing(fn ($state) => OrderInfolist::formatStatus((string) $state)),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status do Pedido')
                    ->options(\App\Enums\OrderStatus::class)
                    ->multiple(),

                Tables\Filters\SelectFilter::make('type')
                    ->label('Modalidade')
                    ->options(\App\Enums\OrderType::class),

                Tables\Filters\SelectFilter::make('source')
                    ->label('Canal / Origem')
                    ->options([
                        'customer_app'       => 'CustomerApp (Delivery/Retirada)',
                        'customer_app_table' => 'CustomerApp (Mesa QR)',
                        'pos_app'            => 'PDV / Balcão',
                        'cashier'            => 'Frente de Caixa',
                        'team_app'           => 'App Equipe / Garçom',
                        'kiosk'              => 'Totem',
                        'backoffice'         => 'Retaguarda / Importação',
                    ]),

                Tables\Filters\SelectFilter::make('customer_profile')
                    ->label('Perfil do Cliente')
                    ->options([
                        'registered' => 'Cliente Cadastrado (Com Conta)',
                        'guest'      => 'Visitante (Guest / Sem Conta)',
                        'anonymous'  => 'Avulso (Sem Vínculo)',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'registered' => $query->whereHas('customer', fn (Builder $q) => $q->whereNotNull('user_id')),
                            'guest'      => $query->whereHas('customer', fn (Builder $q) => $q->whereNull('user_id')),
                            'anonymous'  => $query->whereNull('customer_id'),
                            default      => $query,
                        };
                    }),

                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('Status Financeiro')
                    ->options(\App\Enums\PaymentStatus::class),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')->label('De'),
                        DatePicker::make('created_until')->label('Até'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                ViewAction::make()
                    ->label('Detalhes')
                    ->iconButton(),

                ActionGroup::make([
                    Action::make('download_xml')
                        ->label('Baixar XML')
                        ->icon('heroicon-o-code-bracket')
                        ->color('info')
                        ->action(function (Order $record) {
                            $doc = $record->fiscalDocument;
                            return response()->streamDownload(function () use ($doc) {
                                echo $doc->xml_content;
                            }, "{$doc->access_key}.xml");
                        }),

                    Action::make('view_danfe')
                        ->label('Ver Cupom (DANFE)')
                        ->icon('heroicon-o-document-text')
                        ->color('gray')
                        ->modalHeading(fn (Order $record) => "NFC-e Autorizada - Pedido #{$record->id}")
                        ->modalWidth('2xl')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Fechar')
                        ->modalContent(function (Order $record) {
                            try {
                                $danfce = new \NFePHP\DA\NFe\Danfce($record->fiscalDocument->xml_content);
                                $danfce->creditsIntegratorFooter('Gerado por Brasume ERP');
                                $pdf = $danfce->render();
                                $base64 = base64_encode($pdf);
                                return new HtmlString("<iframe src='data:application/pdf;base64,{$base64}#toolbar=0' width='100%' height='650px' style='border: none; border-radius: 8px;'></iframe>");
                            } catch (\Throwable $e) {
                                return new HtmlString("<div class='p-4 text-red-500'>Erro ao gerar PDF do Cupom: {$e->getMessage()}</div>");
                            }
                        }),
                ])
                    ->label('Fiscal')
                    ->icon('heroicon-m-document-check')
                    ->color('gray')
                    ->tooltip('Ações Fiscais (NFC-e)')
                    ->visible(fn (Order $record) => $record->fiscalDocument && filled($record->fiscalDocument->xml_content)),
            ])
            ->bulkActions([]);
    }
}