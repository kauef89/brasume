<?php

namespace App\Http\Controllers\Tenant\Terminals;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\Order;
use App\Http\Requests\Customer\CustomerCheckoutRequest;
use App\Services\Orders\CustomerCheckoutService;
use App\Services\Fiscal\SpedDocumentService;
use Exception;

class CustomerCheckoutController extends Controller
{
    /**
     * 🚀 Processa o checkout do cliente (B2C)
     */
    public function store(CustomerCheckoutRequest $request, CustomerCheckoutService $checkoutService)
    {
        $payload = $request->validated();

        $user = auth()->user();
        $isGuest = !auth()->check();
        $guestCustomerId = session('guest_customer_id');

        $customer = $user ? $user->customer : Customer::find($guestCustomerId);

        if (!$customer) {
            abort(403, 'Sessão inválida. Por favor, identifique-se novamente.');
        }

        try {
            $result = $checkoutService->processB2CCheckout($payload, $customer, $isGuest);

            if ($result['isBankApi']) {
                return back()->with([
                    'pix_data' => $result['pixData'],
                    'order_id' => $result['order']->id,
                    'success'  => 'Código PIX gerado com sucesso!'
                ]);
            }

            return back()->with([
                'order_id' => $result['order']->id,
                'success'  => 'Pedido recebido com sucesso!'
            ]);

        } catch (\Exception $e) {
            $code = $e->getCode() === 403 ? 403 : 500;
            if ($code === 403) {
                abort(403, $e->getMessage());
            }

            return redirect()->back()->with('error', 'Erro ao processar checkout: ' . $e->getMessage());
        }
    }

    /**
     * 🛑 Cancelamento ativo pelo cliente (B2C)
     */
    public function cancel(Order $order, CustomerCheckoutService $checkoutService)
    {
        try {
            $customerId = auth()->user()?->customer?->id ?? session('guest_customer_id');

            $checkoutService->cancelOrderByCustomer($order, $customerId ? (int) $customerId : null);

            return redirect()->back()->with('success', 'Pedido cancelado com sucesso.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * 🌟 Recebe a avaliação do cliente (1 a 5 estrelas)
     */
    public function rate(Order $order, Request $request, CustomerCheckoutService $checkoutService)
    {
        $validated = $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'tags'    => 'nullable|array',
            'comment' => 'nullable|string|max:500',
        ]);

        $customerId = auth()->user()?->customer?->id ?? session('guest_customer_id');

        try {
            $checkoutService->rateOrder($order, $validated, $customerId ? (int) $customerId : null);

            return redirect()->back()->with('success', 'Avaliação enviada com sucesso!');
        } catch (\Exception $e) {
            if ($e->getCode() === 403) {
                abort(403, $e->getMessage());
            }
            return redirect()->back()->with('error', 'Erro ao enviar avaliação: ' . $e->getMessage());
        }
    }

    /**
     * 📄 Gera e exibe o recibo do pedido em PDF
     */
    public function receipt(Order $order, SpedDocumentService $spedService)
    {
        $customerId = auth()->user()?->customer?->id ?? session('guest_customer_id');
        if ($order->customer_id && (int) $order->customer_id !== (int) $customerId) {
            abort(403, 'Acesso negado. Você não tem permissão para visualizar este recibo.');
        }

        $order->load('fiscalDocument');
        if (!$order->fiscalDocument || empty($order->fiscalDocument->xml_content)) {
            abort(404, 'A Nota Fiscal (NFC-e) ainda não está disponível para este pedido.');
        }

        try {
            $pdfContent = $spedService->renderDanfePdf($order->fiscalDocument->xml_content);

            return response()->streamDownload(
                function () use ($pdfContent) {
                    echo $pdfContent;
                },
                "Recibo_Pedido_{$order->id}.pdf",
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => "inline; filename=\"Recibo_Pedido_{$order->id}.pdf\""
                ]
            );

        } catch (\Exception $e) {
            abort(500, 'Erro ao gerar o PDF da Nota Fiscal: ' . $e->getMessage());
        }
    }
}
