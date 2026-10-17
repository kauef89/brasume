<?php

namespace App\Http\Requests\Pos;

use App\Models\CompanySetting;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PosOrderSubmitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('team')->check() || auth('web')->check() || auth()->check();
    }

    public function rules(): array
    {
        $orderId = $this->input('order_id');
        $isAppend = !empty($orderId);
        $type = data_get($this->all(), 'routing.type', 'table');

        return [
            'source'   => ['required', 'string', 'in:pos_app,cashier,team_app,kiosk,customer_app,customer_app_table'],
            'order_id' => [
                'nullable',
                'integer',
                Rule::exists('orders', 'id')->where(function ($query) {
                    $query->whereNotIn('status', ['cancelled', 'completed']);
                }),
            ],

            // 1. ITENS DO CARRINHO
            'items'                                                 => ['required', 'array', 'min:1'],
            'items.*.cartId'                                        => ['nullable', 'numeric'],
            'items.*.id'                                            => ['required'],
            'items.*.name'                                          => ['required', 'string'],
            'items.*.price'                                         => ['required', 'numeric'],
            'items.*.qty'                                           => ['required', 'numeric', 'min:0.001'],
            'items.*.notes'                                         => ['nullable', 'string'],
            'items.*.isTakeaway'                                    => ['required', 'boolean'],
            'items.*.comboHash'                                     => ['nullable', 'string'],
            'items.*.modHash'                                       => ['nullable', 'string'],
            'items.*.replaces_item_id'                              => ['nullable', 'integer'],
            'items.*.auth_reason'                                   => ['nullable', 'string'],
            'items.*.is_loss'                                       => ['nullable', 'boolean'],
            'items.*.isCombo'                                       => ['nullable', 'boolean'],
            'items.*.is_reward'                                     => ['nullable', 'boolean'],

            // Modificadores
            'items.*.structuredModifiers'                           => ['nullable', 'array'],
            'items.*.structuredModifiers.*.id'                      => ['nullable'],
            'items.*.structuredModifiers.*.name'                    => ['required', 'string'],
            'items.*.structuredModifiers.*.price'                   => ['required', 'numeric'],
            'items.*.structuredModifiers.*.qty'                     => ['required', 'numeric'],
            'items.*.structuredModifiers.*.group_id'                => ['nullable', 'integer'],
            'items.*.structuredModifiers.*.is_joker'                => ['nullable', 'boolean'],

            // Combos
            'items.*.comboItems'                                    => ['nullable', 'array'],
            'items.*.comboItems.*.id'                               => ['nullable'],
            'items.*.comboItems.*.product_id'                       => ['nullable'],
            'items.*.comboItems.*.name'                             => ['nullable', 'string'],
            'items.*.comboItems.*.price'                            => ['nullable', 'numeric'],
            'items.*.comboItems.*.qty'                              => ['nullable', 'numeric'],
            'items.*.comboItems.*.group_id'                         => ['nullable', 'integer'],
            'items.*.comboItems.*.notes'                            => ['nullable', 'string'],
            'items.*.comboItems.*.structuredModifiers'              => ['nullable', 'array'],
            'items.*.comboItems.*.structuredModifiers.*.id'         => ['nullable'],
            'items.*.comboItems.*.structuredModifiers.*.name'       => ['required', 'string'],
            'items.*.comboItems.*.structuredModifiers.*.price'      => ['required', 'numeric'],
            'items.*.comboItems.*.structuredModifiers.*.qty'        => ['required', 'numeric'],
            'items.*.comboItems.*.structuredModifiers.*.group_id'   => ['nullable', 'integer'],

            // 2. ROTEAMENTO
            'routing'                                               => ['required', 'array'],
            'routing.type'                                          => ['required', 'string', 'in:table,counter,delivery'],
            'routing.notes'                                         => ['nullable', 'string'],
            'routing.identifier'                                    => [
                Rule::requiredIf(!$isAppend && $type === 'counter' && empty(data_get($this->all(), 'routing.customer.id'))),
                'nullable',
                'string',
                'max:100'
            ],

            // Mesa (obrigatória apenas se não for append ou se for troca explícita)
            'routing.table'                                         => ['nullable', 'array'],
            'routing.table.id'                                      => [Rule::requiredIf(!$isAppend && $type === 'table'), 'nullable', 'integer', 'exists:dining_tables,id'],
            'routing.dining_table_id'                               => [Rule::requiredIf(!$isAppend && $type === 'table'), 'nullable', 'integer', 'exists:dining_tables,id'],
            'routing.table_id'                                      => ['nullable', 'integer', 'exists:dining_tables,id'],
            'routing.table_number'                                  => ['nullable', 'string'],

            // Cliente
            'routing.customer'                                      => ['nullable', 'array'],
            'routing.customer.id'                                   => ['nullable'],
            'routing.customer.name'                                 => ['nullable', 'string', 'max:255'],
            'routing.customer.phone'                                => ['nullable', 'string', 'max:20'],
            'routing.customer.cpf'                                  => ['nullable', 'string'],

            // Endereço (Delivery)
            'routing.address_id'                                    => ['nullable'],
            'routing.address'                                       => ['nullable', 'array'],
            'routing.address.id'                                    => [Rule::requiredIf(!$isAppend && $type === 'delivery'), 'nullable', 'integer', 'exists:addresses,id'],
            'routing.address.delivery_fee'                          => [Rule::requiredIf(!$isAppend && $type === 'delivery'), 'nullable', 'numeric', 'min:0'],

            // Pagamento
            'routing.payment_method_id'                             => ['nullable', 'integer', 'exists:payment_methods,id'],
            'routing.payment_method_name'                           => ['nullable', 'string'],
            'routing.payment_engine'                                => ['nullable', 'string'],
            'routing.change_target'                                 => ['nullable', 'numeric'],
            'routing.cash_change_target'                            => ['nullable', 'numeric'],
            'routing.payment_intent_status'                         => ['nullable', 'string'],
            'routing.payment_intent_methods'                        => ['nullable', 'array'],

            // 3. FINANCEIRO
            'financials'                                            => ['required', 'array'],
            'financials.subtotal'                                   => ['required', 'numeric'],
            'financials.deliveryFee'                                => ['required', 'numeric'],
            'financials.serviceFee'                                 => ['required', 'numeric'],
            'financials.discount'                                   => ['required', 'numeric'],
            'financials.finalTotal'                                 => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_id.exists'             => 'A comanda informada não existe, já foi concluída ou foi cancelada.',
            'routing.identifier.required' => 'Para pedidos de Balcão, informe o nome do cliente ou selecione um cadastro.',
            'routing.table.id.required'   => 'Selecione uma mesa válida para o lançamento.',
            'routing.address.id.required' => 'Selecione um endereço cadastrado para entrega.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $setting = CompanySetting::getCached() ?? CompanySetting::first();
            $isDelivery = data_get($this->all(), 'routing.type') === 'delivery';
            $isCash = data_get($this->all(), 'routing.payment_engine') === 'cash'
                   || data_get($this->all(), 'routing.payment_engine') === 'money';

            $finalTotal = (float) data_get($this->all(), 'financials.finalTotal', 0);
            $changeTarget = (float) (data_get($this->all(), 'routing.change_target') ?? data_get($this->all(), 'routing.cash_change_target', 0));

            // Valida teto de troco no Delivery conforme PosSchema
            if ($isDelivery && $isCash && $changeTarget > $finalTotal) {
                $hasLimit = (bool) ($setting?->has_max_change_limit ?? false);
                $maxChange = (float) ($setting?->max_change_amount ?? 0);

                if ($hasLimit && $maxChange > 0) {
                    $calculatedChange = $changeTarget - $finalTotal;
                    if ($calculatedChange > $maxChange) {
                        $maxFormatted = number_format($maxChange, 2, ',', '.');
                        $validator->errors()->add(
                            'routing.change_target',
                            "O troco solicitado (R$ " . number_format($calculatedChange, 2, ',', '.') . ") excede o limite operacional de R$ {$maxFormatted} permitido pelo caixa."
                        );
                    }
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $routing = $this->input('routing', []);
        $items = $this->input('items', []);
        $financials = $this->input('financials', []);

        $rootOrderId = $this->input('order_id')
            ?? $routing['order_id']
            ?? $routing['existing_order_id']
            ?? $routing['id']
            ?? null;

        if ($rootOrderId) {
            $this->merge(['order_id' => (int) $rootOrderId]);
        }

        if (is_array($items)) {
            foreach ($items as &$item) {
                if (!empty($item['comboItems']) && is_array($item['comboItems'])) {
                    foreach ($item['comboItems'] as &$sub) {
                        if (isset($sub['id']) && is_string($sub['id']) && preg_match('/_prod_(\d+)/', $sub['id'], $matches)) {
                            $sub['product_id'] = (int) $matches[1];
                        } elseif (!empty($sub['product_id'])) {
                            $sub['product_id'] = (int) $sub['product_id'];
                        } elseif (!empty($sub['id']) && is_numeric($sub['id'])) {
                            $sub['product_id'] = (int) $sub['id'];
                        }
                    }
                }
            }
            $this->merge(['items' => $items]);
        }

        if (isset($routing['type']) && $routing['type'] === 'table') {
            $tableId = $routing['dining_table_id'] ?? $routing['table_id'] ?? $routing['table']['id'] ?? null;
            if ($tableId) {
                $table = DiningTable::find((int) $tableId);
                $routing['dining_table_id'] = (int) $tableId;
                $routing['table_id'] = (int) $tableId;
                $routing['table'] = ['id' => (int) $tableId, 'name' => $table?->name ?? ''];
            }
        }

        if (isset($routing['type']) && $routing['type'] !== 'delivery') {
            $routing['address'] = null;
            $routing['address_id'] = null;
            if (is_array($financials)) {
                $financials['deliveryFee'] = 0.00;
            }
        }

        if (!empty($routing['payment_method_id'])) {
            $paymentMethod = PaymentMethod::find((int) $routing['payment_method_id']);
            if ($paymentMethod) {
                $routing['payment_engine'] = $paymentMethod->engine_type;
                $routing['payment_method_name'] = $paymentMethod->name;
            }
        }

        $this->merge([
            'routing'    => $routing,
            'financials' => $financials,
            'source'     => $this->input('source') ?: 'pos_app',
        ]);
    }
}
