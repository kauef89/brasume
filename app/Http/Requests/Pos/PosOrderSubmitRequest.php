<?php

namespace App\Http\Requests\Pos;

use App\Models\DiningTable;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;

class PosOrderSubmitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('team')->check() || auth('web')->check() || auth()->check();
    }

    public function rules(): array
    {
        return [
            'source'   => ['required', 'string', 'in:pos_app,cashier,team_app,kiosk,customer_app,customer_app_table'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],

            // 1. ITENS PRINCIPAIS
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

            // Modificadores do Item Principal
            'items.*.structuredModifiers'                           => ['nullable', 'array'],
            'items.*.structuredModifiers.*.id'                      => ['nullable'],
            'items.*.structuredModifiers.*.name'                    => ['required', 'string'],
            'items.*.structuredModifiers.*.price'                   => ['required', 'numeric'],
            'items.*.structuredModifiers.*.qty'                     => ['required', 'numeric'],
            'items.*.structuredModifiers.*.group_id'                => ['nullable', 'integer'],
            'items.*.structuredModifiers.*.is_joker'                => ['nullable', 'boolean'],

            // Subitens de Combo
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

            // 2. ROTEAMENTO E LOGÍSTICA
            'routing'                                               => ['required', 'array'],
            'routing.type'                                          => ['required', 'string', 'in:table,counter,delivery'],
            'routing.notes'                                         => ['nullable', 'string'],
            'routing.identifier'                                    => ['nullable', 'string'],

            'routing.id'                                            => ['nullable', 'integer'],
            'routing.order_id'                                      => ['nullable', 'integer'],
            'routing.existing_order_id'                             => ['nullable', 'integer'],

            // MESA
            'routing.table'                                         => ['nullable', 'array'],
            'routing.table.id'                                      => ['required_if:routing.type,table', 'nullable', 'integer', 'exists:dining_tables,id'],
            'routing.table.name'                                    => ['required_if:routing.type,table', 'nullable', 'string'],
            'routing.dining_table_id'                               => ['required_if:routing.type,table', 'nullable', 'integer', 'exists:dining_tables,id'],
            'routing.table_id'                                      => ['nullable', 'integer', 'exists:dining_tables,id'],
            'routing.table_number'                                  => ['nullable', 'string'],

            'routing.customer'                                      => ['nullable', 'array'],
            'routing.customer.id'                                   => ['nullable'],
            'routing.customer.name'                                 => ['nullable', 'string', 'max:255'],
            'routing.customer.phone'                                => ['nullable', 'string', 'max:20'],
            'routing.customer.cpf'                                  => ['nullable', 'string'],
            'routing.customer_id'                                   => ['nullable'],
            'routing.customer_name'                                 => ['nullable', 'string'],
            'routing.customer_phone'                                => ['nullable', 'string', 'max:20'],

            'routing.address_id'                                    => ['nullable'],
            'routing.address'                                       => ['nullable', 'array'],
            // 🚀 CORREÇÃO CRÍTICA: Não permite endereço nulo se for delivery! Deve existir no DB.
            'routing.address.id'                                    => ['required_if:routing.type,delivery', 'integer', 'exists:addresses,id'],
            'routing.address.street_id'                             => ['nullable'],
            'routing.address.street'                                => ['nullable'],
            'routing.address.number'                                => ['nullable'],
            'routing.address.no_number'                             => ['nullable', 'boolean'],
            'routing.address.neighborhood_id'                       => ['nullable'],
            'routing.address.neighborhood'                          => ['nullable'],
            'routing.address.complement'                            => ['nullable', 'string'],
            'routing.address.reference'                             => ['nullable', 'string'],
            'routing.address.nickname'                              => ['nullable', 'string'],
            'routing.address.full_address'                          => ['nullable', 'string'],
            // 🚀 BLINDAGEM: Garante que a taxa foi processada
            'routing.address.delivery_fee'                          => ['required_if:routing.type,delivery', 'numeric', 'min:0'],

            'routing.payment_method_id'                             => ['nullable', 'integer', 'exists:payment_methods,id'],
            'routing.payment_method_name'                           => ['nullable', 'string'],
            'routing.payment_engine'                                => ['nullable', 'string'],
            'routing.change_target'                                 => ['nullable', 'numeric'],
            'routing.cash_change_target'                            => ['nullable', 'numeric'],

            'routing.payment_intent_status'                         => ['nullable', 'string'],
            'routing.payment_intent_methods'                        => ['nullable', 'array'],
            'routing.payment_intent_methods.*.method_id'            => ['nullable', 'integer'],
            'routing.payment_intent_methods.*.method_name'          => ['nullable', 'string'],
            'routing.payment_intent_methods.*.amount'               => ['nullable', 'numeric'],
            'routing.payment_intent_methods.*.change_target'        => ['nullable', 'numeric'],

            // 3. FINANCEIRO
            'financials'                                            => ['required', 'array'],
            'financials.subtotal'                                   => ['required', 'numeric'],
            'financials.deliveryFee'                                => ['required', 'numeric'],
            'financials.serviceFee'                                 => ['required', 'numeric'],
            'financials.discount'                                   => ['required', 'numeric'],
            'financials.finalTotal'                                 => ['required', 'numeric', 'min:0'],
            'financials.coupon_code'                                => ['nullable', 'string'],
            'financials.coupon_id'                                  => ['nullable', 'integer'],
            'financials.cashback_discount'                          => ['nullable', 'numeric'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $routing = $this->input('routing', []);
        $items = $this->input('items', []);
        $financials = $this->input('financials', []);

        $rootOrderId = $this->input('order_id');
        if (empty($rootOrderId)) {
            $rootOrderId = $routing['order_id'] ?? $routing['existing_order_id'] ?? $routing['id'] ?? null;
            if ($rootOrderId) {
                $this->merge(['order_id' => (int) $rootOrderId]);
            }
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
            $tableId = $routing['dining_table_id']
                ?? $routing['table_id']
                ?? $routing['table']['id']
                ?? null;

            if ($tableId) {
                $table = DiningTable::find((int) $tableId);
                $tableIdInt = (int) $tableId;
                $tableName = $table?->name ?? ($routing['table_number'] ?? $routing['table']['name'] ?? '');

                $routing['dining_table_id'] = $tableIdInt;
                $routing['table_id'] = $tableIdInt;
                $routing['table_number'] = $tableName;
                $routing['table'] = [
                    'id'   => $tableIdInt,
                    'name' => $tableName,
                ];
            }
        }

        if (isset($routing['type']) && $routing['type'] !== 'delivery') {
            $routing['address'] = null;
            $routing['address_id'] = null;
            if (is_array($financials)) {
                $financials['deliveryFee'] = 0.00;
            }
        }

        if (isset($routing['type']) && $routing['type'] === 'delivery') {
            // Se o frontend mandar o address solto, nós o estruturamos.
            if (!empty($routing['address_id']) && empty($routing['address']['id'])) {
                if (!isset($routing['address']) || !is_array($routing['address'])) {
                    $routing['address'] = [];
                }
                $routing['address']['id'] = $routing['address_id'];
            }
        }

        if (isset($routing['type']) && $routing['type'] === 'counter') {
            if (empty($routing['customer']['id']) && empty($routing['identifier'])) {
                $routing['identifier'] = null;
            }
        }

        if (isset($routing['cash_change_target']) && !isset($routing['change_target'])) {
            $routing['change_target'] = $routing['cash_change_target'];
        } elseif (isset($routing['change_target']) && !isset($routing['cash_change_target'])) {
            $routing['cash_change_target'] = $routing['change_target'];
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
