<?php

namespace App\Http\Controllers\Tenant\RestaurantOps;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\CloseShiftRequest;
use App\Http\Requests\Pos\OpenShiftRequest;
use App\Models\CashShift;
use App\Models\Order;
use App\Models\Printer;
use App\Services\Printing\Documents\ShiftClosureReport;
use App\Services\Printing\PrintDispatcher;
use App\Services\RestaurantOps\ShiftAttendanceService;
use App\Services\RestaurantOps\ShiftCashierService;
use App\Services\RestaurantOps\OperationalShiftClosureService;
use Illuminate\Http\Request;

class CashierController extends Controller
{
    public function __construct(protected ShiftCashierService $cashierService) {}

    protected function getOperator()
    {
        return auth('team')->user() ?? auth('web')->user() ?? auth()->user();
    }

    public function open(OpenShiftRequest $request, ShiftAttendanceService $attendanceService)
    {
        $data = $request->validated();
        $operator = $this->getOperator();

        try {
            $shift = $this->cashierService->openShift($data, $operator?->id ?? 1, $operator?->name ?? 'Sistema');

            if (!empty($data['attendees'])) {
                $attendanceService->syncShiftAttendees($data['attendees'], $shift);
            }

            return back()->with('success', 'Caixa aberto e equipe registrada!');
        } catch (\Exception $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }
    }

    public function close(Request $request, ShiftCashierService $cashierService, ShiftAttendanceService $attendanceService)
    {
        $shift = CashShift::where('status', 'open')->latest('opened_at')->first();

        if (!$shift) {
            return back()->withErrors(['error' => 'Nenhum turno aberto encontrado.', 'shift' => 'Nenhum turno aberto.']);
        }

        // 1. AUTO-LIMPEZA: Cancela pedidos vazios ou zerados
        $abandonedOrders = Order::whereNotIn('status', ['closed', 'cancelled'])
            ->where(function ($q) {
                $q->whereDoesntHave('items', function ($iq) {
                    $iq->where('status', '!=', 'cancelled');
                })
                ->orWhere(function ($sub) {
                    $sub->where('total', '<=', 0.01)
                        ->whereDoesntHave('payments');
                });
            })
            ->get();

        foreach ($abandonedOrders as $abandoned) {
            $abandoned->update([
                'status' => 'cancelled',
                'payment_status' => 'cancelled'
            ]);

            $tableId = $abandoned->dining_table_id;
            if ($tableId) {
                $hasOtherActive = Order::where('dining_table_id', $tableId)
                    ->where('id', '!=', $abandoned->id)
                    ->whereNotIn('status', ['closed', 'cancelled'])
                    ->exists();

                if (!$hasOtherActive) {
                    \App\Models\DiningTable::where('id', $tableId)->update(['status' => 'free']);
                }
            }
        }

        // 2. VERIFICAÇÃO DE COMANDAS: Bloqueia apenas comandas pendentes de pagamento
        $blockingOrders = Order::whereNotIn('status', ['closed', 'cancelled'])
            ->whereHas('items', function ($iq) {
                $iq->where('status', '!=', 'cancelled');
            })
            ->where(function ($q) {
                $q->where('payment_status', '!=', 'paid')
                  ->orWhere('total', '>', 0.01);
            })
            ->get();

        if ($blockingOrders->isNotEmpty()) {
            $tableNumbers = $blockingOrders->where('type', 'table')->pluck('table_number')->filter()->unique()->join(', ');
            $otherCounts = $blockingOrders->where('type', '!=', 'table')->count();

            $details = [];
            if ($tableNumbers) $details[] = "Mesa(s): {$tableNumbers}";
            if ($otherCounts > 0) $details[] = "{$otherCounts} pedido(s) balcão/delivery";

            $detailMsg = !empty($details) ? ' (' . implode(' | ', $details) . ')' : '';
            $msg = "Não é possível fechar o caixa com pedidos em aberto{$detailMsg}. Finalize ou cancele as contas pendentes.";

            return back()->withErrors(['error' => $msg, 'shift' => $msg]);
        }

        // 3. Sincronização da Equipa no Fecho
        if ($request->filled('attendees')) {
            $attendanceService->syncShiftAttendees($request->input('attendees'), $shift);
        }

        // 4. Execução do fechamento
        try {
            $operator = $this->getOperator();
            $operatorId = $operator?->id ?? 1;
            $operatorName = $operator?->name ?? 'Operador';

            $cashierService->closeShift($shift, $request->all(), $operatorId, $operatorName);

            return back()->with('success', 'Caixa fechado com sucesso.');
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage(), 'shift' => $e->getMessage()]);
        }
    }

    public function closeOperationalShift(Request $request)
    {
        $activeShift = CashShift::where('status', 'open')->latest('opened_at')->first();
        if (!$activeShift) {
            return back()->withErrors(['error' => 'Nenhum turno aberto no momento.']);
        }

        $closeStore = $request->boolean('close_store', true);
        $acceptingOrders = $request->has('accepting_orders')
            ? $request->boolean('accepting_orders')
            : !$closeStore;

        try {
            $activeShift->update([
                'accepting_orders' => $acceptingOrders,
            ]);

            \Illuminate\Support\Facades\Cache::forget('store_availability_status');
            \Illuminate\Support\Facades\Cache::forget('customer_catalog_data');

            if (method_exists(\App\Models\CompanySetting::class, 'purgeCache')) {
                \App\Models\CompanySetting::purgeCache();
            }

            $tenantId = function_exists('tenant') && tenant() ? tenant('id') : 'central';
            if (class_exists(\App\Events\StoreStatusUpdated::class)) {
                broadcast(new \App\Events\StoreStatusUpdated($tenantId));
            }

            $msg = $acceptingOrders
                ? 'Loja online aberta! O cardápio digital voltou a aceitar pedidos.'
                : 'Loja online pausada! Novos pedidos no app do cliente foram suspensos.';

            return back()->with('success', $msg);
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'Falha ao atualizar status da loja: ' . $e->getMessage()]);
        }
    }

    /**
     * 🚀 SANGRIA E SUPRIMENTO (operations.cashier.transaction)
     */
    public function transaction(Request $request)
    {
        $data = $request->validate([
            'type'        => 'required|in:in,out',
            'amount'      => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
            'pin'         => 'nullable|string',
        ]);

        $operator = $this->getOperator();

        try {
            $this->cashierService->recordTransaction($data, $operator?->name ?? 'Operador');
            return back()->with('success', 'Movimentação registrada com sucesso!');
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * 🚀 REIMPRESSÃO DO RELATÓRIO DO TURNO (operations.cashier.print-shift-report)
     */
    public function printShiftReport(CashShift $shift, PrintDispatcher $printDispatcher)
    {
        try {
            $cashierPrinter = Printer::where('is_cashier', true)->where('is_active', true)->first()
                ?? Printer::where('is_active', true)->first();

            if (!$cashierPrinter) {
                return back()->withErrors(['error' => 'Nenhuma impressora ativa encontrada para imprimir o relatório.']);
            }

            $report = new ShiftClosureReport($shift);
            $printDispatcher->dispatch($cashierPrinter, $report);

            return back()->with('success', 'Relatório enviado para a impressora!');
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'Falha na impressão: ' . $e->getMessage()]);
        }
    }

    /**
     * 🚀 ESTORNO E CLONAGEM (operations.orders.refund-and-clone)
     */
    public function refundAndClone(Order $order, Request $request)
    {
        try {
            foreach ($order->payments as $payment) {
                $this->cashierService->rollbackPayment($order, $payment->id);
            }

            $order->update([
                'status' => 'cancelled',
                'payment_status' => 'cancelled'
            ]);

            return back()->with('success', "Pedido #{$order->id} estornado com sucesso.");
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'Falha ao estornar pedido: ' . $e->getMessage()]);
        }
    }

    public function rollbackPayment(Order $order, $payment, Request $request)
    {
        $paymentId = $payment instanceof \App\Models\Payment ? $payment->id : (int) $payment;

        if ($request->filled('pin')) {
            $pin = (string) $request->input('pin');
            $authorized = false;

            $checkHashOrPlain = function (string $input, ?string $hashCandidate): bool {
                if (empty($hashCandidate)) return false;
                if (str_starts_with($hashCandidate, '$2y$') || str_starts_with($hashCandidate, '$2a$') || str_starts_with($hashCandidate, '$2b$')) {
                    try {
                        return \Illuminate\Support\Facades\Hash::check($input, $hashCandidate);
                    } catch (\Throwable $e) {
                        return false;
                    }
                }
                return hash_equals((string) $hashCandidate, (string) $input);
            };

            if (auth()->user()) {
                $user = auth()->user();
                if ($checkHashOrPlain($pin, $user->pin) || $checkHashOrPlain($pin, $user->password)) {
                    $authorized = true;
                }
            }

            if (!$authorized) {
                $managers = \App\Models\User::whereHas('roles', function ($q) {
                    $q->whereIn('name', ['admin', 'gerente', 'manager']);
                })->get();

                foreach ($managers as $manager) {
                    if ($checkHashOrPlain($pin, $manager->pin) || $checkHashOrPlain($pin, $manager->password)) {
                        $authorized = true;
                        break;
                    }
                }
            }

            if (!$authorized) {
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'PIN de autorização incorreto.'], 403);
                }
                return back()->withErrors(['pin' => 'PIN de autorização incorreto.']);
            }
        }

        try {
            $this->cashierService->rollbackPayment($order, $paymentId);

            $order->refresh()->load([
                'items.modifiers',
                'payments.items.modifiers',
                'payments.transaction',
                'customer',
                'diningTable'
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Pagamento estornado com sucesso!',
                    'order'   => $order,
                ]);
            }

            return back()->with('success', 'Pagamento estornado com sucesso!');
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 422);
            }

            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
