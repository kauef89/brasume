<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function formatSource(?string $state): string
    {
        return match ($state) {
            'customer_app'       => 'CustomerApp (Delivery/Retirada)',
            'customer_app_table' => 'CustomerApp (QR Code Mesa)',
            'pos_app'            => 'PDV / Balcão',
            'cashier'            => 'Frente de Caixa',
            'team_app'           => 'App Equipe / Garçom',
            'kiosk'              => 'Totem Autoatendimento',
            'backoffice'         => 'Retaguarda / Importação',
            default              => $state ?: 'Não informado',
        };
    }

    public static function formatType(\App\Enums\OrderType|string|null $state): string
    {
        if ($state instanceof \App\Enums\OrderType) {
            return $state->getLabel() ?? $state->value;
        }

        return match ($state) {
            'delivery' => 'Entrega (Delivery)',
            'pickup'   => 'Retirada no Balcão',
            'counter'  => 'Consumo / Balcão',
            'table'    => 'Mesa',
            default    => $state ?: 'Não informado',
        };
    }

    public static function formatStatus(?string $state): string
    {
        return match ($state) {
            'pending_payment'  => 'Aguardando Pagamento',
            'pending'          => 'Pendente / Aguardando Preparo',
            'confirmed'        => 'Confirmado',
            'preparing'        => 'Em Preparo',
            'ready'            => 'Pronto',
            'dispatched', 'out_for_delivery' => 'Saiu para Entrega',
            'delivered'        => 'Entregue',
            'closed', 'completed', 'finished' => 'Finalizado',
            'cancelled', 'canceled' => 'Cancelado',
            'merged'           => 'Mesclado',
            default            => $state ?: 'Desconhecido',
        };
    }

    public static function statusColor(?string $state): string
    {
        return match ($state) {
            'pending_payment' => 'warning',
            'pending', 'confirmed' => 'info',
            'preparing', 'ready', 'dispatched', 'out_for_delivery' => 'primary',
            'closed', 'completed', 'finished', 'delivered' => 'success',
            'cancelled', 'canceled' => 'danger',
            default => 'gray',
        };
    }

    public static function formatPaymentStatus(?string $state): string
    {
        return match ($state) {
            'paid'             => 'Pago / Liquidado',
            'settled_internal' => 'Baixa Interna / Sem Receita',
            'partial'          => 'Parcialmente Pago',
            'pending'          => 'Pendente',
            'cancelled'        => 'Cancelado',
            default            => $state ?: 'Pendente',
        };
    }

    public static function formatIntentStatus(?string $state): string
    {
        return match ($state) {
            'paid'            => 'Liquidado',
            'pay_now'         => 'Pagamento Imediato (Checkout)',
            'pix_waiting', 'pending_pix' => 'Aguardando PIX (API)',
            'pay_on_delivery' => 'Promessa: Pagar na Entrega',
            'pay_on_pickup'   => 'Promessa: Pagar na Retirada',
            'pending_choice'  => 'Aguardando Definição (Mesa/Balcão)',
            default           => $state ?: 'Não definida',
        };
    }

    public static function resolveCustomerProfile(Order $record): array
    {
        if (!$record->customer_id || !$record->customer) {
            return [
                'label' => 'Avulso / Sem Vínculo',
                'color' => 'gray',
                'is_guest' => false,
                'is_registered' => false,
            ];
        }

        $customer = $record->customer;

        if (!empty($customer->user_id)) {
            $badges = ['Cliente Cadastrado'];
            if ($customer->is_employee) {
                $badges[] = 'Colaborador';
            }
            if ($customer->is_convenio) {
                $badges[] = 'Convênio';
            }
            return [
                'label' => implode(' • ', $badges),
                'color' => 'success',
                'is_guest' => false,
                'is_registered' => true,
            ];
        }

        return [
            'label' => 'Visitante (Guest / Sem Conta)',
            'color' => 'warning',
            'is_guest' => true,
            'is_registered' => false,
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // =============================================================
                // 1. RESUMO OPERACIONAL & RASTREABILIDADE
                // =============================================================
                Section::make('Resumo Operacional')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('id')
                            ->label('Pedido #')
                            ->formatStateUsing(fn ($state) => "#{$state}")
                            ->weight('bold'),

                        TextEntry::make('created_at')
                            ->label('Criado em')
                            ->dateTime('d/m/Y H:i:s'),

                        TextEntry::make('source')
                            ->label('Canal / Origem')
                            ->badge()
                            ->color(fn ($state) => str_starts_with((string) $state, 'customer_app') ? 'info' : 'gray')
                            ->formatStateUsing(fn ($state) => self::formatSource((string) $state)),

                        TextEntry::make('type')
                            ->label('Modalidade')
                            ->badge(),

                        TextEntry::make('status')
                            ->label('Status do Pedido')
                            ->badge()
                            ->color(fn ($state) => self::statusColor((string) $state))
                            ->formatStateUsing(fn ($state) => self::formatStatus((string) $state)),

                        TextEntry::make('payment_status')
                            ->label('Status Financeiro')
                            ->badge()
                            ->color(fn ($state) => match ((string) $state) {
                                'paid' => 'success',
                                'settled_internal' => 'purple',
                                'partial' => 'warning',
                                'cancelled' => 'danger',
                                default => 'warning',
                            })
                            ->formatStateUsing(fn ($state) => self::formatPaymentStatus((string) $state)),

                        TextEntry::make('cashShift.id')
                            ->label('Turno de Caixa')
                            ->formatStateUsing(fn ($state, Order $record) => $state
                                ? "Turno #{$state}" . ($record->cashShift?->opened_at ? ' (' . $record->cashShift->opened_at->format('d/m H:i') . ')' : '')
                                : 'Sem turno vinculado')
                            ->placeholder('Sem turno vinculado'),

                        TextEntry::make('attendant.name')
                            ->label('Operador / Atendente')
                            ->state(fn (Order $record) => $record->attendant?->name ?? $record->cashier?->name ?? 'Autoatendimento (Cliente)'),

                        TextEntry::make('notes')
                            ->label('Observações / Histórico de Ocorrências')
                            ->placeholder('Nenhuma observação registrada.')
                            ->columnSpanFull(),
                    ]),

                // =============================================================
                // 2. IDENTIFICAÇÃO DO CLIENTE (CADASTRADO VS. VISITANTE)
                // =============================================================
                Section::make('Identificação do Cliente & Histórico de Comportamento')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('customer_profile_badge')
                            ->label('Tipo de Perfil')
                            ->badge()
                            ->state(fn (Order $record) => self::resolveCustomerProfile($record)['label'])
                            ->color(fn (Order $record) => self::resolveCustomerProfile($record)['color']),

                        TextEntry::make('customer_display_name')
                            ->label('Nome Informado no Pedido')
                            ->weight('bold')
                            ->state(fn (Order $record) => $record->customer_name ?: ($record->customer?->name ?? 'Não informado')),

                        TextEntry::make('customer.name')
                            ->label('Nome na Ficha (Base)')
                            ->placeholder('Sem ficha vinculada'),

                        TextEntry::make('customer_contact_phone')
                            ->label('Telefone / WhatsApp')
                            ->state(function (Order $record) {
                                $raw = $record->customer_phone ?: $record->customer?->phone;
                                if (!$raw) {
                                    return 'Não informado';
                                }
                                $digits = preg_replace('/\D/', '', $raw);
                                if (strlen($digits) === 11) {
                                    return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7));
                                }
                                if (strlen($digits) === 10) {
                                    return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6));
                                }
                                return $raw;
                            }),

                        TextEntry::make('customer.cpf')
                            ->label('CPF Cadastrado')
                            ->placeholder('Não informado'),

                        TextEntry::make('customer_order_history')
                            ->label('Histórico deste Cliente / Visitante')
                            ->badge()
                            ->state(function (Order $record) {
                                $cleanPhone = preg_replace('/\D/', '', (string) ($record->customer_phone ?: $record->customer?->phone));

                                if (!$record->customer_id && $cleanPhone === '') {
                                    return 'Pedido avulso sem histórico';
                                }

                                $query = Order::query()->where('id', '!=', $record->id);
                                if ($record->customer_id) {
                                    $query->where('customer_id', $record->customer_id);
                                } else {
                                    $query->where('customer_phone', 'like', "%{$cleanPhone}%");
                                }

                                $totalOutros = (clone $query)->count();
                                $cancelados = (clone $query)->whereIn('status', ['cancelled', 'canceled'])->count();
                                $concluidos = (clone $query)->whereIn('status', ['closed', 'completed', 'delivered', 'finished'])->count();

                                if ($totalOutros === 0) {
                                    return '1º pedido na loja (Sem histórico anterior)';
                                }

                                return "{$totalOutros} pedido(s) anterior(es): {$concluidos} concluído(s), {$cancelados} cancelado(s)";
                            })
                            ->color(function (Order $record) {
                                if (!$record->customer_id) {
                                    return 'gray';
                                }
                                $cancelados = Order::where('customer_id', $record->customer_id)
                                    ->where('id', '!=', $record->id)
                                    ->whereIn('status', ['cancelled', 'canceled'])
                                    ->count();
                                return $cancelados >= 2 ? 'danger' : 'info';
                            }),

                        TextEntry::make('ip_address')
                            ->label('Endereço IP (App)')
                            ->placeholder('N/A (Operação Interna)'),

                        TextEntry::make('user_agent')
                            ->label('Dispositivo / Navegador')
                            ->limit(60)
                            ->tooltip(fn ($state) => $state)
                            ->placeholder('N/A'),
                    ]),

                // =============================================================
                // 3. LOGÍSTICA, ENTREGA OU MESA
                // =============================================================
                Section::make('Logística & Local de Atendimento')
                    ->columns(3)
                    ->columnSpanFull()
                    ->visible(fn (Order $record) => in_array($record->type, [\App\Enums\OrderType::DELIVERY, \App\Enums\OrderType::TABLE], true) || filled($record->table_number) || filled($record->dining_table_id))
                    ->schema([
                        TextEntry::make('formatted_address')
                            ->label('Endereço de Entrega (Snapshot)')
                            ->columnSpan(2)
                            ->visible(fn (Order $record) => $record->type === \App\Enums\OrderType::DELIVERY)
                            ->state(function (Order $record) {
                                $addr = $record->address ?? $record->address_snapshot;
                                if (is_array($addr) && !empty($addr)) {
                                    if (!empty($addr['full_address'])) {
                                        return $addr['full_address'];
                                    }
                                    $street = $addr['street'] ?? '';
                                    $number = $addr['number'] ?? 'S/N';
                                    $neighborhood = $addr['neighborhood'] ?? '';
                                    $complement = !empty($addr['complement']) ? " - {$addr['complement']}" : '';
                                    $ref = !empty($addr['reference']) ? " (Ref: {$addr['reference']})" : '';
                                    return trim("{$street}, Nº {$number} - {$neighborhood}{$complement}{$ref}");
                                }
                                return $record->deliveryAddress?->full_address ?? 'Endereço não registrado';
                            }),

                        TextEntry::make('delivery_distance_km')
                            ->label('Distância Estimada')
                            ->visible(fn (Order $record) => $record->type === \App\Enums\OrderType::DELIVERY)
                            ->formatStateUsing(fn ($state) => $state ? number_format((float) $state, 2, ',', '.') . ' km' : 'Não calculada')
                            ->placeholder('Não calculada'),

                        TextEntry::make('courier.name')
                            ->label('Entregador Atribuído')
                            ->visible(fn (Order $record) => $record->type === \App\Enums\OrderType::DELIVERY)
                            ->placeholder('Nenhum entregador atribuído'),

                        TextEntry::make('courier_fee')
                            ->label('Taxa do Motoboy')
                            ->visible(fn (Order $record) => $record->type === \App\Enums\OrderType::DELIVERY)
                            ->money('BRL'),

                        TextEntry::make('courier_settled_at')
                            ->label('Acerto com Motoboy')
                            ->visible(fn (Order $record) => $record->type === \App\Enums\OrderType::DELIVERY)
                            ->state(fn (Order $record) => $record->courier_settled_at
                                ? 'Liquidado em ' . $record->courier_settled_at->format('d/m/Y H:i')
                                : ($record->courier_id ? 'Pendente de acerto' : 'N/A')),

                        TextEntry::make('table_info')
                            ->label('Mesa')
                            ->visible(fn (Order $record) => $record->type === \App\Enums\OrderType::TABLE || filled($record->table_number) || filled($record->dining_table_id))
                            ->state(fn (Order $record) => $record->diningTable?->name ?? ($record->table_number ? "Mesa {$record->table_number}" : 'N/A')),
                    ]),

                // =============================================================
                // 4. ITENS DO PEDIDO (COM MODIFICADORES E STATUS DE PRODUÇÃO)
                // =============================================================
                Section::make('Itens do Pedido')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->contained(true)
                            ->columns(6)
                            ->schema([
                                TextEntry::make('quantity')
                                    ->label('Qtd')
                                    ->formatStateUsing(fn ($state) => "{$state}x")
                                    ->weight('bold')
                                    ->columnSpan(1),

                                TextEntry::make('product_details')
                                    ->label('Produto / Adicionais / Combo')
                                    ->columnSpan(2)
                                    ->state(function ($record) {
                                        $lines = [$record->product_name];

                                        if ($record->modifiers && $record->modifiers->isNotEmpty()) {
                                            foreach ($record->modifiers as $mod) {
                                                $modPrice = (float) $mod->price > 0
                                                    ? ' (+R$ ' . number_format((float) $mod->price, 2, ',', '.') . ')'
                                                    : '';
                                                $lines[] = "• {$mod->quantity}x {$mod->name}{$modPrice}";
                                            }
                                        }

                                        if ($record->subItems && $record->subItems->isNotEmpty()) {
                                            foreach ($record->subItems as $sub) {
                                                $lines[] = "↳ Combo: {$sub->quantity}x {$sub->product_name}";
                                            }
                                        }

                                        if (!empty($record->notes)) {
                                            $lines[] = "Obs: {$record->notes}";
                                        }

                                        if ($record->status === 'cancelled' && !empty($record->cancel_reason)) {
                                            $lines[] = "Motivo cancelamento: {$record->cancel_reason}";
                                        }

                                        return implode("\n", $lines);
                                    })
                                    ->html()
                                    ->formatStateUsing(fn ($state) => nl2br(e($state))),

                                TextEntry::make('status')
                                    ->label('Status Item')
                                    ->badge()
                                    ->color(fn ($state) => $state === 'cancelled' ? 'danger' : 'success')
                                    ->formatStateUsing(fn ($state) => $state === 'cancelled' ? 'Cancelado' : 'Ativo')
                                    ->columnSpan(1),

                                TextEntry::make('production_status')
                                    ->label('KDS / Cozinha')
                                    ->badge()
                                    ->color(fn ($state) => match ((string) $state) {
                                        'ready', 'delivered' => 'success',
                                        'preparing' => 'warning',
                                        'cancelled' => 'danger',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn ($state) => match ((string) $state) {
                                        'pending'   => 'Na Fila',
                                        'preparing' => 'Preparando',
                                        'ready'     => 'Pronto',
                                        'delivered' => 'Entregue',
                                        'cancelled' => 'Cancelado',
                                        default     => (string) ($state ?: 'N/A'),
                                    })
                                    ->columnSpan(1),

                                TextEntry::make('total_price')
                                    ->label('Subtotal Item')
                                    ->money('BRL')
                                    ->weight('bold')
                                    ->columnSpan(1),
                            ]),
                    ]),

                // =============================================================
                // 5. PROMESSAS DE PAGAMENTO VS. PAGAMENTOS EFETIVADOS & TOTAIS
                // =============================================================
                Grid::make(3)
                    ->columnSpanFull()
                    ->schema([
                        // Coluna Esquerda (2/3): Promessas + Pagamentos Efetivos
                        Section::make('Promessas de Pagamento (Checkout) & Liquidações')
                            ->columnSpan(2)
                            ->schema([
                                Grid::make(3)->schema([
                                    TextEntry::make('payment_intent_status')
                                        ->label('Intenção / Promessa no Checkout')
                                        ->badge()
                                        ->color(fn ($state) => match ((string) $state) {
                                            'paid' => 'success',
                                            'pix_waiting', 'pending_pix' => 'warning',
                                            'pay_on_delivery', 'pay_on_pickup' => 'info',
                                            default => 'gray',
                                        })
                                        ->formatStateUsing(fn ($state) => self::formatIntentStatus((string) $state)),

                                    TextEntry::make('requested_change')
                                        ->label('Troco Solicitado pelo Cliente')
                                        ->state(function (Order $record) {
                                            $target = (float) ($record->delivery_change_target ?: $record->change_target ?: 0);
                                            if ($target <= 0) {
                                                return 'Não solicitado';
                                            }
                                            $troco = max(0, $target - (float) $record->total);
                                            return 'Para R$ ' . number_format($target, 2, ',', '.') . ' (Levar R$ ' . number_format($troco, 2, ',', '.') . ')';
                                        }),

                                    TextEntry::make('txid')
                                        ->label('TXID PIX do Pedido')
                                        ->copyable()
                                        ->placeholder('Sem cobrança PIX'),
                                ]),

                                RepeatableEntry::make('payment_intent_methods')
                                    ->label('Formas Prometidas pelo Cliente (Intenção)')
                                    ->placeholder('Nenhuma intenção detalhada registrada.')
                                    ->columns(4)
                                    ->schema([
                                        TextEntry::make('method_name')
                                            ->label('Forma Escolhida')
                                            ->weight('bold'),

                                        TextEntry::make('engine_type')
                                            ->label('Modalidade Gateway')
                                            ->badge()
                                            ->formatStateUsing(fn ($state) => match ((string) $state) {
                                                'bank_api' => 'PIX Online (API)',
                                                'money' => 'Dinheiro',
                                                'internal_loyalty' => 'Cashback Fidelidade',
                                                'manual' => 'Maquininha / Presencial',
                                                default => (string) ($state ?: 'Presencial'),
                                            }),

                                        TextEntry::make('amount')
                                            ->label('Valor Prometido')
                                            ->money('BRL'),

                                        TextEntry::make('change_target')
                                            ->label('Troco Para')
                                            ->formatStateUsing(fn ($state) => (float) $state > 0 ? 'R$ ' . number_format((float) $state, 2, ',', '.') : '—')
                                            ->placeholder('—'),
                                    ]),

                                RepeatableEntry::make('payments')
                                    ->label('Pagamentos Efetivamente Registrados no Sistema')
                                    ->placeholder('Nenhum pagamento liquidado ou lançado neste pedido.')
                                    ->columns(6)
                                    ->schema([
                                        TextEntry::make('method')
                                            ->label('Método')
                                            ->weight('bold'),

                                        TextEntry::make('amount')
                                            ->label('Valor')
                                            ->money('BRL'),

                                        TextEntry::make('is_paid')
                                            ->label('Situação')
                                            ->badge()
                                            ->state(fn ($record) => $record->is_paid ? 'Liquidado' : ($record->status === 'cancelled' ? 'Cancelado' : 'Pendente'))
                                            ->color(fn ($record) => $record->is_paid ? 'success' : ($record->status === 'cancelled' ? 'danger' : 'warning')),

                                        TextEntry::make('documentary_proof')
                                            ->label('Comprovante Documental (360°)')
                                            ->html()
                                            ->state(function ($record): string {
                                                $acq = $record->acquirerTransaction
                                                    ?? \App\Models\AcquirerTransaction::where('payment_id', $record->id)
                                                        ->where('status', \App\Enums\AcquirerMatchStatus::MATCHED->value)
                                                        ->first();

                                                if ($acq) {
                                                    $url = \App\Filament\Resources\AcquirerTransactions\AcquirerTransactionResource::getUrl('index', [
                                                        'tableSearch' => $acq->nsu,
                                                    ]);
                                                    $nsu = e($acq->nsu);
                                                    $tid = e($acq->terminal_code);
                                                    return "<a href='{$url}' target='_blank' rel='noopener noreferrer' class='inline-flex items-center gap-1 font-semibold text-emerald-700 dark:text-emerald-400 hover:underline'>💳 Maquininha NSU {$nsu} (TID {$tid}) ↗</a>";
                                                }

                                                $stmt = \App\Models\BankStatement::where('conciliable_type', \App\Models\Payment::class)
                                                    ->where('conciliable_id', $record->id)
                                                    ->where('status', 'reconciled')
                                                    ->first();

                                                if ($stmt) {
                                                    $searchKey = $stmt->document ?: ($stmt->counterparty_name ?: (string) $stmt->id);
                                                    $url = \App\Filament\Resources\BankStatements\BankStatementResource::getUrl('index', [
                                                        'tableSearch' => $searchKey,
                                                    ]);
                                                    $payer = e($stmt->counterparty_name ?: $stmt->description);
                                                    $date = $stmt->bank_date?->format('d/m/Y') ?? '';
                                                    return "<a href='{$url}' target='_blank' rel='noopener noreferrer' class='inline-flex items-center gap-1 font-semibold text-sky-700 dark:text-sky-400 hover:underline'>💠 Extrato #{$stmt->id} ({$date} • {$payer}) ↗</a>";
                                                }

                                                $engine = $record->paymentMethod?->engine_type;
                                                $engineVal = $engine instanceof \BackedEnum ? $engine->value : (string) $engine;
                                                if (in_array($engineVal, ['money', 'cash', 'internal_credit', 'convenio'], true)) {
                                                    return "<span class='text-xs text-gray-500'>💵 Conferência Interna / Gaveta</span>";
                                                }

                                                return "<span class='text-xs font-semibold text-amber-600 dark:text-amber-400'>⚠️ Sem vínculo documental</span>";
                                            }),

                                        TextEntry::make('ledger_transaction')
                                            ->label('Mov. Financeira / E2E')
                                            ->html()
                                            ->state(function ($record): string {
                                                $tx = $record->transaction ?? ($record->transaction_id ? \App\Models\Transaction::find($record->transaction_id) : null);
                                                if ($tx) {
                                                    $url = \App\Filament\Resources\Transactions\TransactionResource::getUrl('edit', ['record' => $tx->id]);
                                                    return "<a href='{$url}' target='_blank' rel='noopener noreferrer' class='font-semibold text-indigo-600 dark:text-indigo-400 hover:underline'>📒 Transação #{$tx->id} ↗</a>";
                                                }
                                                $ref = $record->end_to_end_id ?: ($record->txid ?: '—');
                                                return "<span class='font-mono text-xs'>" . e($ref) . "</span>";
                                            }),

                                        TextEntry::make('created_at')
                                            ->label('Data/Hora')
                                            ->dateTime('d/m H:i'),
                                    ]),
                            ]),

                        // Coluna Direita (1/3): Resumo Numérico
                        Section::make('Totais & Conciliação')
                            ->columnSpan(1)
                            ->schema([
                                TextEntry::make('subtotal')
                                    ->label('Subtotal dos Itens')
                                    ->money('BRL'),

                                TextEntry::make('delivery_fee')
                                    ->label('Taxa de Entrega')
                                    ->money('BRL'),

                                TextEntry::make('service_fee')
                                    ->label('Taxa de Serviço')
                                    ->money('BRL'),

                                TextEntry::make('coupon.code')
                                    ->label('Cupom Aplicado')
                                    ->badge()
                                    ->color('info')
                                    ->placeholder('Nenhum'),

                                TextEntry::make('discount')
                                    ->label('Desconto')
                                    ->money('BRL'),

                                TextEntry::make('cashback_used')
                                    ->label('Cashback Utilizado')
                                    ->money('BRL'),

                                TextEntry::make('total')
                                    ->label('TOTAL DO PEDIDO')
                                    ->money('BRL')
                                    ->weight('bold')
                                    ->size('lg'),

                                TextEntry::make('total_paid')
                                    ->label('Total Efetivamente Pago')
                                    ->money('BRL')
                                    ->color('success')
                                    ->weight('bold'),

                                TextEntry::make('balance_due')
                                    ->label('Saldo em Aberto')
                                    ->money('BRL')
                                    ->color(fn ($state) => (float) $state > 0.01 ? 'danger' : 'gray'),

                                TextEntry::make('change_returned')
                                    ->label('Troco Devolvido no Caixa')
                                    ->money('BRL')
                                    ->visible(fn (Order $record) => (float) ($record->change_returned ?? 0) > 0),
                            ]),
                    ]),

                // =============================================================
                // 6. DOCUMENTO FISCAL (NFC-e) & AVALIAÇÃO DO CLIENTE
                // =============================================================
                Section::make('Fiscal (NFC-e) & Avaliação do Cliente')
                    ->columns(4)
                    ->columnSpanFull()
                    ->visible(fn (Order $record) => (bool) $record->fiscalDocument || (bool) $record->rating)
                    ->schema([
                        TextEntry::make('fiscalDocument.status')
                            ->label('Status NFC-e')
                            ->badge()
                            ->color(fn ($state) => $state === 'authorized' ? 'success' : ($state === 'cancelled' ? 'danger' : 'warning'))
                            ->formatStateUsing(fn ($state) => match ((string) $state) {
                                'authorized' => 'Autorizada SEFAZ',
                                'cancelled'  => 'Cancelada',
                                'rejected'   => 'Rejeitada',
                                default      => (string) ($state ?: 'Não emitida'),
                            }),

                        TextEntry::make('fiscalDocument.number')
                            ->label('Número / Série')
                            ->state(fn (Order $record) => $record->fiscalDocument
                                ? "Nº {$record->fiscalDocument->number} / Série {$record->fiscalDocument->series}"
                                : '—'),

                        TextEntry::make('fiscalDocument.access_key')
                            ->label('Chave de Acesso NFC-e')
                            ->copyable()
                            ->columnSpan(2)
                            ->placeholder('—'),

                        TextEntry::make('rating.rating')
                            ->label('Nota do Cliente')
                            ->visible(fn (Order $record) => (bool) $record->rating)
                            ->formatStateUsing(fn ($state) => str_repeat('★', (int) $state) . " ({$state}/5)"),

                        TextEntry::make('rating.comment')
                            ->label('Comentário da Avaliação')
                            ->visible(fn (Order $record) => (bool) $record->rating)
                            ->columnSpan(3)
                            ->placeholder('Sem comentário escrito.'),
                    ]),
            ]);
    }
}