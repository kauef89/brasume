<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\CashShift;
use App\Models\Order;
use App\Models\CompanySetting;
use App\Models\Transaction;
use App\Services\Pos\CashierService;
use Carbon\Carbon;

class CashierController extends Controller
{
    protected $cashierService;

    public function __construct(CashierService $cashierService)
    {
        $this->cashierService = $cashierService;
    }

    public function index()
    {
        $settings = CompanySetting::first();
        $currentShift = CashShift::where('status', 'open')->latest()->first();

        $ordersQuery = Order::with(['payments', 'employee'])->whereIn('status', ['completed', 'cancelled'])->orderBy('updated_at', 'desc')->limit(50);
        $transactionsQuery = Transaction::orderBy('created_at', 'desc')->limit(50);

        if ($currentShift) {
            $ordersQuery->where('updated_at', '>=', $currentShift->opened_at);
            $transactionsQuery->where('created_at', '>=', $currentShift->opened_at);
        } else {
            $ordersQuery->whereDate('updated_at', Carbon::today());
            $transactionsQuery->whereDate('created_at', Carbon::today());
        }

        $ordersHistory = $ordersQuery->get()->map(fn($o) => [
            'id' => $o->id,
            'customer_name' => $o->customer_name,
            'status' => $o->status,
            'total' => (float) $o->total,
            'time' => $o->updated_at->format('H:i'),
            'operator' => $o->employee->name ?? 'Operador',
            'payments' => $o->payments->map(fn($p) => ['method' => $p->method, 'amount' => (float) $p->amount])
        ]);

        $transactionsHistory = $transactionsQuery->get()->map(fn($t) => [
            'id' => $t->id,
            'type' => $t->type,
            'amount' => (float) $t->amount,
            'description' => $t->description,
            'time' => $t->created_at->format('H:i'),
            'operator' => $t->operator_name,
        ]);

        return Inertia::render('Pos/Cashier', [
            'companySettings' => [
                'cash_opening_mode' => $settings->cash_opening_mode ?? 'total',
                'carry_over_mode' => $settings->carry_over_mode ?? 'none',
            ],
            'currentShift' => $currentShift,
            'ordersHistory' => $ordersHistory,
            'transactionsHistory' => $transactionsHistory,
        ]);
    }

    public function open(Request $request)
    {
        $request->validate(['amount' => 'required|numeric']);

        $this->cashierService->openShift((float) $request->amount, auth()->id());
        return back()->with('success', 'Caixa aberto!');
    }

    public function close(Request $request)
    {
        $request->validate(['amount' => 'required|numeric']);

        $this->cashierService->closeShift((float) $request->amount, auth()->id());
        return back()->with('success', 'Caixa fechado!');
    }

    public function transaction(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:in,out',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string',
        ]);

        $this->cashierService->recordTransaction($data, auth()->user()->name);
        return back()->with('success', 'Movimentação registrada!');
    }

    public function refundAndClone(Order $order, Request $request)
    {
        $data = $request->validate([
            'pin' => 'required|string',
            'reason' => 'required|string',
            'is_loss' => 'nullable|boolean',
        ]);

        try {
            $this->cashierService->refundAndClone($order, $data);
            return back()->with('success', 'Pedido estornado e clonado!');
        } catch (\Exception $e) {
            return back()->withErrors(['pin' => $e->getMessage()]);
        }
    }
}
