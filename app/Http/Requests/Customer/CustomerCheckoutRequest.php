<?php

namespace App\Http\Requests\Customer;

use App\Models\Address;
use App\Models\CompanySetting;
use App\Models\DiningTable;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Log;

class CustomerCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        $setting = CompanySetting::getCached() ?? CompanySetting::first();
        $allowGuest = (bool) ($setting->customer_allow_guest ?? true);

        return auth()->check()
            || session()->has('guest_customer_id')
            || $allowGuest;
    }

    public function rules(): array
    {
        $requiresPayment = (float) data_get($this->all(), 'financials.finalTotal', 0) > 0;
        $isDelivery      = data_get($this->all(), 'routing.type') === 'delivery';
        $hasAddressId    = !empty(data_get($this->all(), 'routing.address_id'))
                        || !empty(data_get($this->all(), 'routing.address.id'));

        return [
            'append_to_order' => ['nullable', 'integer', 'exists:orders,id'],

            // 1. ITENS DO CARRINHO
            'items' => ['required', 'array', 'min:1'],
            'items.*.cartId' => ['nullable', 'numeric'],
            'items.*.id' => ['required'],
            'items.*.name' => ['required', 'string'],
            'items.*.price' => ['required', 'numeric'],
            'items.*.qty' => ['required', 'numeric', 'min:0.001'],
            'items.*.notes' => ['nullable', 'string'],
            'items.*.isTakeaway' => ['required', 'boolean'],
            'items.*.isCombo' => ['nullable', 'boolean'],
            'items.*.comboHash' => ['nullable', 'string'],
            'items.*.modHash' => ['nullable', 'string'],
            'items.*.replaces_item_id' => ['nullable', 'integer'],
            'items.*.auth_reason' => ['nullable', 'string'],
            'items.*.is_loss' => ['nullable', 'boolean'],
            'items.*.productOriginal' => ['nullable', 'array'],

            'items.*.structuredModifiers' => ['nullable', 'array'],
            'items.*.structuredModifiers.*.id' => ['nullable'],
            'items.*.structuredModifiers.*.name' => ['required', 'string'],
            'items.*.structuredModifiers.*.price' => ['required', 'numeric'],
            'items.*.structuredModifiers.*.qty' => ['required', 'numeric'],
            'items.*.structuredModifiers.*.is_joker' => ['nullable', 'boolean'],
            'items.*.structuredModifiers.*.group_id' => ['nullable', 'integer'],

            'items.*.comboItems' => ['nullable', 'array'],
            'items.*.comboItems.*.id' => ['nullable'],
            'items.*.comboItems.*.product_id' => ['nullable'],
            'items.*.comboItems.*.name' => ['nullable', 'string'],
            'items.*.comboItems.*.price' => ['nullable', 'numeric'],
            'items.*.comboItems.*.qty' => ['nullable', 'numeric'],
            'items.*.comboItems.*.group_id' => ['nullable', 'integer'],
            'items.*.comboItems.*.notes' => ['nullable', 'string'],
            'items.*.comboItems.*.structuredModifiers' => ['nullable', 'array'],
            'items.*.comboItems.*.structuredModifiers.*.id' => ['nullable'],
            'items.*.comboItems.*.structuredModifiers.*.name' => ['required', 'string'],
            'items.*.comboItems.*.structuredModifiers.*.price' => ['required', 'numeric'],
            'items.*.comboItems.*.structuredModifiers.*.qty' => ['required', 'numeric'],
            'items.*.comboItems.*.structuredModifiers.*.group_id' => ['nullable', 'integer'],

            // 2. ROTEAMENTO E LOGÍSTICA
            'routing' => ['required', 'array'],
            'routing.type' => ['required', 'string', 'in:delivery,counter,table'],
            'routing.identifier' => ['nullable', 'string'],
            'routing.notes' => ['nullable', 'string'],

            // 🚀 MESA (Normalizada estritamente com base no Model DiningTable)
            'routing.table' => ['nullable', 'array'],
            'routing.table.id' => ['required_if:routing.type,table', 'nullable', 'integer', 'exists:dining_tables,id'],
            'routing.table.name' => ['nullable', 'string'],
            'routing.dining_table_id' => ['required_if:routing.type,table', 'nullable', 'integer', 'exists:dining_tables,id'],
            'routing.table_id' => ['nullable', 'integer', 'exists:dining_tables,id'],
            'routing.table_number' => ['nullable', 'string'],

            'routing.customer' => ['nullable', 'array'],
            'routing.customer.id' => ['nullable'],
            'routing.customer.name' => ['nullable', 'string'],
            'routing.customer.phone' => ['nullable', 'string'],
            'routing.customer.cpf' => ['nullable', 'string'],
            'routing.customer.email' => ['nullable', 'string'],
            'routing.customer.birth_date' => ['nullable', 'date'],
            'routing.customer.loyalty_points' => ['nullable', 'numeric'],
            'routing.customer.addresses' => ['nullable', 'array'],
            'routing.customer_name' => ['nullable', 'string', 'max:255'],
            'routing.customer_phone' => ['nullable', 'string', 'max:20'],

            'routing.address' => ['required_if:routing.type,delivery', 'nullable', 'array'],
            'routing.address.id' => ['nullable'],
            'routing.address_id' => ['nullable'],
            'routing.address.street' => [
                Rule::requiredIf($isDelivery && !$hasAddressId),
                'nullable',
                'string',
                'max:255'
            ],
            'routing.address.street_id' => ['nullable', 'integer'],
            'routing.address.number' => ['nullable', 'string', 'max:50'],
            'routing.address.neighborhood' => [
                Rule::requiredIf($isDelivery && !$hasAddressId),
                'nullable',
                'string',
                'max:255'
            ],
            'routing.address.neighborhood_id' => ['nullable', 'integer'],
            'routing.address.complement' => ['nullable', 'string', 'max:255'],
            'routing.address.reference' => ['nullable', 'string', 'max:255'],
            'routing.address.is_active' => ['nullable', 'boolean'],
            'routing.address.nickname' => ['nullable', 'string', 'max:100'],
            'routing.address.full_address' => ['nullable', 'string', 'max:500'],
            'routing.address.delivery_fee' => ['nullable', 'numeric'],

            'routing.payment_method_id' => [
                Rule::requiredIf($requiresPayment),
                'nullable',
                'integer',
                'exists:payment_methods,id'
            ],
            'routing.payment_method_name' => [
                Rule::requiredIf($requiresPayment),
                'nullable',
                'string'
            ],
            'routing.payment_engine' => [
                Rule::requiredIf($requiresPayment),
                'nullable',
            ],
            'routing.change_target' => ['nullable', 'numeric'],
            'routing.cash_change_target' => ['nullable', 'numeric'],

            'routing.payment_intent_status' => [
                Rule::requiredIf($requiresPayment),
                'string',
                Rule::notIn(['pending_choice', 'not_applicable', 'pending'])
            ],
            'routing.payment_intent_methods' => ['nullable', 'array'],
            'routing.payment_intent_methods.*.method_id' => ['required_with:routing.payment_intent_methods', 'integer'],
            'routing.payment_intent_methods.*.method_name' => ['required_with:routing.payment_intent_methods', 'string'],
            'routing.payment_intent_methods.*.amount' => ['required_with:routing.payment_intent_methods', 'numeric'],
            'routing.payment_intent_methods.*.change_target' => ['nullable', 'numeric'],

            // 3. FINANCEIRO E TOTAIS
            'financials' => ['required', 'array'],
            'financials.subtotal' => ['required', 'numeric'],
            'financials.deliveryFee' => ['required', 'numeric'],
            'financials.serviceFee' => ['required', 'numeric'],
            'financials.discount' => ['required', 'numeric'],
            'financials.finalTotal' => ['required', 'numeric', 'min:0'],
            'financials.coupon_code' => ['nullable', 'string', 'prohibits:routing.cashback_discount'],
            'routing.cashback_discount' => ['nullable', 'numeric', 'prohibits:financials.coupon_code'],
            'items.*.is_reward' => ['nullable', 'boolean', 'prohibited_if:financials.coupon_code,!=,null'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $routing = $this->input('routing', []);
        if (isset($routing['payment_engine'])) {
            if ($routing['payment_engine'] instanceof \BackedEnum) {
                $routing['payment_engine'] = $routing['payment_engine']->value;
            } elseif (is_array($routing['payment_engine'])) {
                $routing['payment_engine'] = reset($routing['payment_engine']);
            }
            $this->merge(['routing' => $routing]);
        }
        $routing = $this->input('routing', []);
        $financials = $this->input('financials', []);
        $items = $this->input('items', []);

        if (is_array($items)) {
            foreach ($items as &$item) {
                if (!empty($item['structuredModifiers']) && is_array($item['structuredModifiers'])) {
                    foreach ($item['structuredModifiers'] as &$mod) {
                        if (!isset($mod['qty']) && isset($mod['quantity'])) {
                            $mod['qty'] = $mod['quantity'];
                        }
                        if (!isset($mod['qty']) || $mod['qty'] === '') {
                            $mod['qty'] = 1;
                        }
                        if (!isset($mod['price']) || $mod['price'] === '') {
                            $mod['price'] = 0;
                        }
                    }
                }

                if (!empty($item['comboItems']) && is_array($item['comboItems'])) {
                    foreach ($item['comboItems'] as &$sub) {
                        if (isset($sub['id']) && is_string($sub['id']) && preg_match('/_prod_(\d+)/', $sub['id'], $matches)) {
                            $sub['product_id'] = (int) $matches[1];
                        } elseif (!empty($sub['product_id'])) {
                            $sub['product_id'] = (int) $sub['product_id'];
                        } elseif (!empty($sub['id']) && is_numeric($sub['id'])) {
                            $sub['product_id'] = (int) $sub['id'];
                        }

                        if (!empty($sub['structuredModifiers']) && is_array($sub['structuredModifiers'])) {
                            foreach ($sub['structuredModifiers'] as &$subMod) {
                                if (!isset($subMod['qty']) && isset($subMod['quantity'])) {
                                    $subMod['qty'] = $subMod['quantity'];
                                }
                                if (!isset($subMod['qty']) || $subMod['qty'] === '') {
                                    $subMod['qty'] = 1;
                                }
                                if (!isset($subMod['price']) || $subMod['price'] === '') {
                                    $subMod['price'] = 0;
                                }
                            }
                        }
                    }
                }
            }
            $this->merge(['items' => $items]);
        }

        // 🚀 Resolução de Mesa estritamente por ID via Model DiningTable
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

        // 🚀 Normalização e desaninhamento de objetos de Rua e Bairro
        if (isset($routing['address']) && is_array($routing['address'])) {
            if (isset($routing['address']['street']) && is_array($routing['address']['street'])) {
                $routing['address']['street_id'] = $routing['address']['street']['id'] ?? ($routing['address']['street_id'] ?? null);
                $routing['address']['street'] = $routing['address']['street']['name'] ?? '';
            }
            if (isset($routing['address']['neighborhood']) && is_array($routing['address']['neighborhood'])) {
                $routing['address']['neighborhood_id'] = $routing['address']['neighborhood']['id'] ?? ($routing['address']['neighborhood_id'] ?? null);
                $routing['address']['neighborhood'] = $routing['address']['neighborhood']['name'] ?? '';
            }
            if (empty($routing['address_id']) && !empty($routing['address']['id'])) {
                $routing['address_id'] = $routing['address']['id'];
            }
        }

        if (!empty($routing['address_id']) && empty($routing['address']['id'])) {
            $routing['address']['id'] = $routing['address_id'];
        }

        // 🚀 Sincronização e Auto-Hidratação de Endereço Existente do Banco
        $addressId = $routing['address_id'] ?? ($routing['address']['id'] ?? null);
        if (!empty($addressId) && is_numeric($addressId)) {
            $routing['address_id'] = (int) $addressId;
            if (!isset($routing['address']) || !is_array($routing['address'])) {
                $routing['address'] = [];
            }
            $routing['address']['id'] = (int) $addressId;

            if (empty($routing['address']['street']) || empty($routing['address']['neighborhood'])) {
                $existingAddress = Address::with(['street', 'neighborhood'])->find((int) $addressId);
                if ($existingAddress) {
                    $routing['address']['street'] = $existingAddress->street?->name ?? ($existingAddress->street_name ?? 'Logradouro');
                    $routing['address']['street_id'] = $existingAddress->street_id;
                    $routing['address']['neighborhood'] = $existingAddress->neighborhood?->name ?? ($existingAddress->neighborhood_name ?? 'Bairro');
                    $routing['address']['neighborhood_id'] = $existingAddress->neighborhood_id;
                    $routing['address']['number'] = $existingAddress->number ?? ($routing['address']['number'] ?? 'S/N');
                    $routing['address']['full_address'] = $existingAddress->full_address ?? ($routing['address']['full_address'] ?? null);
                    if (!isset($routing['address']['delivery_fee'])) {
                        $routing['address']['delivery_fee'] = $existingAddress->delivery_fee;
                    }
                }
            }
        }

        if (isset($routing['cash_change_target']) && !isset($routing['change_target'])) {
            $routing['change_target'] = $routing['cash_change_target'];
        } elseif (isset($routing['change_target']) && !isset($routing['cash_change_target'])) {
            $routing['cash_change_target'] = $routing['change_target'];
        }

        if (empty($financials['coupon_code'])) {
            unset($financials['coupon_code']);
        }
        if (empty($routing['cashback_discount']) || (float) $routing['cashback_discount'] <= 0) {
            unset($routing['cashback_discount']);
        }

        if (isset($financials['delivery_fee'])) {
            $financials['deliveryFee'] = $financials['delivery_fee'];
            unset($financials['delivery_fee']);
        }
        if (isset($financials['final_total'])) {
            $financials['finalTotal'] = $financials['final_total'];
            unset($financials['final_total']);
        }
        if (!isset($financials['serviceFee'])) {
            $financials['serviceFee'] = 0;
        }

        // 🚀 Resolução de Forma de Pagamento baseada no Model PaymentMethod
        if (!empty($routing['payment_method_id'])) {
            $paymentMethod = PaymentMethod::find((int) $routing['payment_method_id']);
            if ($paymentMethod) {
                $routing['payment_engine'] = $paymentMethod->engine_type instanceof \BackedEnum ? $paymentMethod->engine_type->value : (string) ($paymentMethod->engine_type ?? 'local');
                $routing['payment_method_name'] = $paymentMethod->name;
            }
        }

        $finalTotal = (float) ($financials['finalTotal'] ?? 0);
        $engine = $routing['payment_engine'] ?? null;
        if ($finalTotal > 0 && empty($routing['payment_intent_status'])) {
            if ($engine === 'bank_api') {
                $routing['payment_intent_status'] = 'paid';
            } elseif (!empty($routing['payment_method_id'])) {
                $routing['payment_intent_status'] = 'pay_on_delivery';
            }
        } elseif ($finalTotal <= 0) {
            $routing['payment_intent_status'] = 'free_order';
        }

        $this->merge([
            'routing'    => $routing,
            'financials' => $financials
        ]);
    }

    public function attributes(): array
    {
        return [
            'financials.deliveryFee'         => 'Taxa de Entrega',
            'financials.serviceFee'          => 'Taxa de Serviço',
            'financials.subtotal'            => 'Subtotal',
            'financials.finalTotal'          => 'Total Final',
            'routing.payment_method_id'      => 'Forma de Pagamento',
            'routing.address.street'         => 'Rua/Logradouro',
            'routing.address.number'         => 'Número',
            'routing.address.neighborhood'   => 'Bairro',
            'routing.payment_intent_status'  => 'Status da Intenção de Pagamento',
            'routing.payment_intent_methods' => 'Formas de Pagamento na Entrega',
            'routing.dining_table_id'        => 'Mesa',
            'routing.table.id'               => 'Mesa',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'                     => 'O seu carrinho está vazio. Adicione itens antes de finalizar.',
            'routing.type.required'              => 'O formato do pedido (Delivery, Balcão ou Mesa) é obrigatório.',
            'routing.table.id.required_if'       => 'Por favor, informe a mesa para atendimento.',
            'routing.dining_table_id.required_if'=> 'Por favor, informe a mesa para atendimento.',
            'routing.address.street.required'    => 'Para delivery, selecione ou informe o logradouro de entrega.',
            'routing.address.neighborhood.required' => 'Para delivery, selecione ou informe o bairro de entrega.',
            'routing.payment_method_id.required' => 'Por favor, selecione uma forma de pagamento para concluir o pedido.',
            'routing.payment_method_id.exists'   => 'A forma de pagamento selecionada não está mais disponível.',
            'routing.payment_intent_status.required' => 'A forma de pagamento é obrigatória.',
            'routing.payment_intent_status.not_in'   => 'Não é permitido finalizar pedidos com pagamento pendente no cardápio online.',
            'financials.coupon_code.prohibits'   => 'Não é possível utilizar um Cupom de Desconto junto com saldo de Cashback.',
            'routing.cashback_discount.prohibits'=> 'Não é possível utilizar saldo de Cashback junto com um Cupom de Desconto.',
            'items.*.is_reward.prohibited_if'    => 'Remova os itens de cortesia (Fidelidade) para poder utilizar um Cupom de Desconto.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $setting = CompanySetting::getCached() ?? CompanySetting::first();
            $subtotal = (float) $this->input('financials.subtotal', 0);
            $finalTotal = (float) $this->input('financials.finalTotal', 0);

            // 1. RESTRAÇÃO DE LOJA FECHADA INDETERMINADA
            if ((bool) ($setting?->is_closed_indefinitely ?? false)) {
                $validator->errors()->add('general', 'A loja está temporariamente fechada para novos pedidos no momento.');
                return;
            }

            // 2. RESTRAÇÃO DE APPEND NO APP DO CLIENTE
            $appendToOrderId = $this->input('append_to_order');
            if ($appendToOrderId) {
                if (!($setting?->customer_can_append_orders ?? true)) {
                    $validator->errors()->add('append_to_order', 'Adições de itens a pedidos em andamento não estão liberadas nesta loja.');
                }

                $targetOrder = \App\Models\Order::find($appendToOrderId);
                if (!$targetOrder || in_array($targetOrder->status, ['cancelled', 'completed', 'delivered'])) {
                    $validator->errors()->add('append_to_order', 'O pedido original já foi concluído ou não aceita mais novos itens.');
                }
            }

            // 3. REGRAS DE RESGATE DE CASHBACK (MARKETING SCHEMA)
            $cashbackUsed = (float) $this->input('routing.cashback_discount', 0);
            if ($cashbackUsed > 0 && ($setting?->loyalty_is_active ?? false)) {
                $minRedeem = (float) ($setting->loyalty_min_redeem_value ?? 0);
                if ($minRedeem > 0 && $subtotal < $minRedeem) {
                    $minFormatted = number_format($minRedeem, 2, ',', '.');
                    $validator->errors()->add(
                        'routing.cashback_discount',
                        "O consumo mínimo para utilizar saldo de cashback é de R$ {$minFormatted}."
                    );
                }

                $maxRedeemValue = (float) ($setting->loyalty_max_redeem_value ?? 0);
                $maxRedeemType = $setting->loyalty_max_redeem_type ?? 'fixed';

                if ($maxRedeemValue > 0) {
                    $allowedMax = $maxRedeemType === 'percentage'
                        ? round(($subtotal * ($maxRedeemValue / 100)), 2)
                        : $maxRedeemValue;

                    if ($cashbackUsed > ($allowedMax + 0.05)) {
                        $allowedFormatted = number_format($allowedMax, 2, ',', '.');
                        $validator->errors()->add(
                            'routing.cashback_discount',
                            "O valor máximo de cashback permitido para este pedido é de R$ {$allowedFormatted}."
                        );
                    }
                }
            }

            // 4. RESTRIÇÃO PARA VISITANTE (GUEST MODE)
            $isGuest = !auth()->check();
            if ($isGuest && $finalTotal > 0) {
                $restricted = $setting?->guest_restricted_payments ?? true;
                $engine = $this->input('routing.payment_engine');
                if ($restricted && $engine !== 'bank_api') {
                    $validator->errors()->add(
                        'routing.payment_method_id',
                        'Por medida de segurança, visitantes só podem pagar online (PIX). Faça login para liberar outras formas de pagamento.'
                    );
                }
            }
        });
    }

    protected function failedValidation(Validator $validator)
    {
        Log::error('🚨 [SONDA CHECKOUT] Falha de validação no pedido:', [
            'erros'            => $validator->errors()->toArray(),
            'payload_recebido' => $this->all()
        ]);
        parent::failedValidation($validator);
    }
}
