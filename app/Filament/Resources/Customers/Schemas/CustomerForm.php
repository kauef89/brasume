<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Enums\AcquirerMatchStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentEngineType;
use App\Models\AcquirerTransaction;
use App\Models\BankStatement;
use App\Models\Customer;
use Filament\Forms\Components as FormComponents;
use Filament\Schemas\Components as SchemaComponents;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\Unique;

class CustomerForm
{
    public static function renderExecutiveSummary(?Customer $record): HtmlString
    {
        if (!$record || !$record->exists) {
            return new HtmlString('');
        }

        $orders = $record->orders()
            ->with(['payments.paymentMethod', 'payments.acquirerTransaction'])
            ->whereNotIn('status', OrderStatus::cancelledValues())
            ->orderByDesc('created_at')
            ->get();

        $totalOrders = $orders->count();
        $totalSpent  = (float) $orders->sum('total');
        $avgTicket   = $totalOrders > 0 ? ($totalSpent / $totalOrders) : 0.0;
        $lastOrder   = $orders->first();
        $lastOrderText = $lastOrder?->created_at
            ? $lastOrder->created_at->format('d/m/Y H:i') . " (#{$lastOrder->id})"
            : 'Nenhum pedido';

        // Auditoria documental dos pagamentos eletrônicos e Pix do cliente
        $allPayments = $orders->flatMap->payments->where('is_paid', true)->where('status', '!=', 'cancelled');
        $paymentIds  = $allPayments->pluck('id')->all();

        $matchedAcqByPay = empty($paymentIds)
            ? collect()
            : AcquirerTransaction::whereIn('payment_id', $paymentIds)
                ->where('status', AcquirerMatchStatus::MATCHED->value)
                ->pluck('id', 'payment_id');

        $matchedStmtByPay = empty($paymentIds)
            ? collect()
            : BankStatement::where('conciliable_type', \App\Models\Payment::class)
                ->whereIn('conciliable_id', $paymentIds)
                ->where('status', 'reconciled')
                ->pluck('id', 'conciliable_id');

        $unreconciledCount = 0;
        $unreconciledTotal = 0.0;

        foreach ($allPayments as $p) {
            $engine = $p->paymentMethod?->engine_type;
            $engineVal = $engine instanceof \BackedEnum ? $engine->value : (string) $engine;

            $needsCardProof = in_array($engineVal, [
                PaymentEngineType::POS_MANUAL->value,
                PaymentEngineType::SMART_POS->value,
                PaymentEngineType::TEF->value,
                PaymentEngineType::PIX_MACHINE->value,
            ], true);

            $needsBankProof = in_array($engineVal, [
                PaymentEngineType::PIX_STATIC->value,
                PaymentEngineType::BANK_API->value,
            ], true);

            if ($needsCardProof) {
                $hasProof = !empty($p->acquirer_transaction_id) || $matchedAcqByPay->has($p->id);
                if (!$hasProof) {
                    $unreconciledCount++;
                    $unreconciledTotal += (float) $p->amount;
                }
            } elseif ($needsBankProof) {
                $hasProof = $matchedStmtByPay->has($p->id);
                if (!$hasProof) {
                    $unreconciledCount++;
                    $unreconciledTotal += (float) $p->amount;
                }
            }
        }

        // Pedidos sem baixa financeira
        $unpaidOrdersCount = $orders->filter(fn ($o) => !in_array($o->payment_status, ['paid', 'settled_internal'], true))->count();

        $spentFmt  = 'R$ ' . number_format($totalSpent, 2, ',', '.');
        $ticketFmt = 'R$ ' . number_format($avgTicket, 2, ',', '.');
        $points    = number_format((float) ($record->loyalty_points ?? 0), 0, ',', '.');

        $badges = [];
        if ($record->is_employee) {
            $badges[] = "<span style='background:#dbeafe;color:#1e40af;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;'>👷 Colaborador RH</span>";
        }
        if ($record->is_convenio) {
            $badges[] = "<span style='background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;'>🏢 Convênio</span>";
        }
        if (!empty($record->allow_credit)) {
            $lim = number_format((float) ($record->credit_limit ?? 0), 2, ',', '.');
            $badges[] = "<span style='background:#f3e8ff;color:#6b21a8;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;'>💳 Fiado Ativo (Lim: R$ {$lim})</span>";
        }
        $badgesHtml = implode(' ', $badges);

        // Pagadores usuais (Pix de terceiros)
        $usualPayersHtml = '';
        if (is_array($record->usual_payers) && !empty($record->usual_payers)) {
            $names = collect($record->usual_payers)
                ->map(fn ($up) => e($up['name'] ?? 'Terceiro') . ' (' . ($up['count'] ?? 1) . 'x)')
                ->implode(' • ');
            $usualPayersHtml = "<div style='margin-top:8px;font-size:11px;color:#6b7280;'><strong>👥 Pagadores Pix Usuais:</strong> {$names}</div>";
        }

        if ($unreconciledCount > 0 || $unpaidOrdersCount > 0) {
            $unrecFmt = number_format($unreconciledTotal, 2, ',', '.');
            $auditBox = "
                <div style='padding:12px;border-radius:12px;background:rgba(244,63,94,0.08);border:1px solid rgba(244,63,94,0.3);'>
                    <div style='font-size:10px;font-weight:800;text-transform:uppercase;color:#e11d48;'>⚠️ Auditoria Documental</div>
                    <div style='font-size:18px;font-weight:900;color:#e11d48;margin-top:4px;'>{$unreconciledCount} sem comprovante</div>
                    <div style='font-size:11px;color:#9f1239;margin-top:2px;'>Valor pendente: <strong>R$ {$unrecFmt}</strong> • Sem baixa: <strong>{$unpaidOrdersCount}</strong></div>
                </div>
            ";
        } else {
            $auditBox = "
                <div style='padding:12px;border-radius:12px;background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.3);'>
                    <div style='font-size:10px;font-weight:800;text-transform:uppercase;color:#059669;'>✅ Auditoria Documental</div>
                    <div style='font-size:18px;font-weight:900;color:#059669;margin-top:4px;'>100% Conciliado</div>
                    <div style='font-size:11px;color:#065f46;margin-top:2px;'>Todos os pagamentos eletrônicos validados</div>
                </div>
            ";
        }

        return new HtmlString("
            <div style='display:flex;flex-direction:column;gap:10px;'>
                " . ($badgesHtml ? "<div style='display:flex;flex-wrap:wrap;gap:6px;'>{$badgesHtml}</div>" : '') . "
                <div style='display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;'>
                    <div style='padding:12px;border-radius:12px;background:rgba(99,102,241,0.06);border:1px solid rgba(99,102,241,0.2);'>
                        <div style='font-size:10px;font-weight:800;text-transform:uppercase;color:#6366f1;'>💰 Total Consumido (LTV)</div>
                        <div style='font-size:18px;font-weight:900;margin-top:4px;'>{$spentFmt}</div>
                        <div style='font-size:11px;color:#6b7280;margin-top:2px;'>{$totalOrders} pedido(s) válido(s)</div>
                    </div>

                    <div style='padding:12px;border-radius:12px;background:rgba(14,165,233,0.06);border:1px solid rgba(14,165,233,0.2);'>
                        <div style='font-size:10px;font-weight:800;text-transform:uppercase;color:#0284c7;'>🧾 Ticket Médio & Última Compra</div>
                        <div style='font-size:18px;font-weight:900;margin-top:4px;'>{$ticketFmt}</div>
                        <div style='font-size:11px;color:#6b7280;margin-top:2px;'>Último: {$lastOrderText}</div>
                    </div>

                    <div style='padding:12px;border-radius:12px;background:rgba(245,158,11,0.06);border:1px solid rgba(245,158,11,0.2);'>
                        <div style='font-size:10px;font-weight:800;text-transform:uppercase;color:#d97706;'>⭐ Fidelidade & Pontos</div>
                        <div style='font-size:18px;font-weight:900;margin-top:4px;'>{$points} pts</div>
                        <div style='font-size:11px;color:#6b7280;margin-top:2px;'>Saldo disponível para resgate</div>
                    </div>

                    {$auditBox}
                </div>
                {$usualPayersHtml}
            </div>
        ");
    }

    public static function getFormSchema(): array
    {
        return [
            SchemaComponents\Section::make("Visão 360° de Consumo & Saúde Documental")
                ->visible(fn (?Customer $record) => $record !== null && $record->exists)
                ->schema([
                    FormComponents\Placeholder::make("executive_summary")
                        ->hiddenLabel()
                        ->content(fn (?Customer $record) => static::renderExecutiveSummary($record))
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),

            Tabs::make("Abas do Cliente")
                ->tabs([
                    // ==========================================
                    // ABA 1: PESSOAL E CONTATOS
                    // ==========================================
                    Tabs\Tab::make("Dados e Contatos")
                        ->icon("heroicon-o-user")
                        ->schema([
                            SchemaComponents\Section::make("Dados Pessoais")
                                ->schema([
                                    FormComponents\TextInput::make("name")
                                        ->label("Nome Completo")
                                        ->required()
                                        ->columnSpan(2),
                                    FormComponents\TextInput::make("cpf")
                                        ->label("CPF")
                                        ->mask("999.999.999-99")
                                        ->stripCharacters([".", "-"])
                                        ->unique(
                                            ignoreRecord: true,
                                            modifyRuleUsing: fn (Unique $rule) => $rule->withoutTrashed(),
                                        )
                                        ->columnSpan(1),
                                    FormComponents\DatePicker::make("birth_date")
                                        ->label("Data de Nascimento")
                                        ->displayFormat("d/m/Y")
                                        ->columnSpan(1),
                                ])
                                ->columns(4),

                            SchemaComponents\Section::make("Contatos")
                                ->schema([
                                    FormComponents\TextInput::make("email")
                                        ->label("E-mail")
                                        ->email()
                                        ->columnSpan(2),
                                    FormComponents\TextInput::make("phone")
                                        ->label("WhatsApp / Telefone")
                                        ->mask("(99) 99999-9999")
                                        ->stripCharacters(["(", ")", "-", " "])
                                        ->columnSpan(2),
                                ])
                                ->columns(4),
                        ]),

                    // ==========================================
                    // ABA 2: ENDEREÇOS
                    // ==========================================
                    Tabs\Tab::make("Endereços")
                        ->icon("heroicon-o-map-pin")
                        ->schema([
                            FormComponents\Repeater::make("addresses")
                                ->relationship("addresses")
                                ->label("")
                                ->schema([
                                    FormComponents\Select::make("nickname")
                                        ->label("Apelido")
                                        ->options([
                                            "Casa"     => "Casa",
                                            "Trabalho" => "Trabalho",
                                            "Amor"     => "Amor",
                                            "Pai/Mãe"  => "Pai/Mãe",
                                            "Outro"    => "Outro",
                                        ])
                                        ->default("Casa")
                                        ->required()
                                        ->columnSpan(2),

                                    FormComponents\Select::make("street_id")
                                        ->relationship("street", "name")
                                        ->label("Rua")
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->live()
                                        ->createOptionForm(
                                            \App\Filament\Resources\Streets\Schemas\StreetForm::getFormSchema(),
                                        )
                                        ->afterStateUpdated(function (Set $set, $state) {
                                            if (!$state) {
                                                return;
                                            }
                                            $lastAddress = \App\Models\Address::where("street_id", $state)
                                                ->whereNotNull("neighborhood_id")
                                                ->first();
                                            if ($lastAddress) {
                                                $set("neighborhood_id", $lastAddress->neighborhood_id);
                                            }
                                        })
                                        ->columnSpan(4),

                                    FormComponents\Select::make("neighborhood_id")
                                        ->relationship("neighborhood", "name")
                                        ->label("Bairro")
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->createOptionForm(
                                            \App\Filament\Resources\Neighborhoods\Schemas\NeighborhoodForm::getFormSchema(),
                                        )
                                        ->columnSpan(3),

                                    FormComponents\Toggle::make("no_number")
                                        ->label("Sem número?")
                                        ->live()
                                        ->afterStateUpdated(function (Set $set, $state) {
                                            $set("number", $state ? "S/N" : null);
                                        })
                                        ->columnSpan(1),

                                    FormComponents\TextInput::make("number")
                                        ->label("Número")
                                        ->required(fn (Get $get) => !$get("no_number"))
                                        ->readOnly(fn (Get $get) => $get("no_number"))
                                        ->columnSpan(2),

                                    FormComponents\TextInput::make("complement")
                                        ->label("Complemento")
                                        ->required(fn (Get $get) => $get("no_number"))
                                        ->columnSpan(3),

                                    FormComponents\TextInput::make("reference")
                                        ->label("Ponto de Referência")
                                        ->columnSpan(2),

                                    FormComponents\Toggle::make("is_active")
                                        ->label("Ativo?")
                                        ->default(true)
                                        ->columnSpan(1),

                                    FormComponents\TextInput::make("location_link")
                                        ->label("Link de Localização (Maps/WhatsApp)")
                                        ->url()
                                        ->required(fn (Get $get) => $get("no_number"))
                                        ->columnSpan(6),
                                ])
                                ->columns(6)
                                ->addActionLabel("Adicionar Novo Endereço")
                                ->addAction(fn ($action) => $action->color("primary"))
                                ->collapsible()
                                ->itemLabel(fn (array $state): ?string => $state["nickname"] ?? "Novo Endereço"),
                        ]),
                ])
                ->columnSpanFull(),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }
}
