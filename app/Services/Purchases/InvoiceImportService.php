<?php

namespace App\Services\Purchases;

use App\Models\ProductCostHistory;
use App\Models\Supplier;
use App\Models\AccountPayable;
use App\Models\ReceivedInvoice;
use App\Models\PersonalPurchase;
use App\Models\CompanyAsset;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvoiceImportService
{
    /**
     * Tenta encontrar o ID do Fornecedor pelo CNPJ do XML
     */
    public function findSupplierIdByCnpj(string $cnpj): ?int
    {
        $cleanCnpj = preg_replace('/[^0-9]/', '', $cnpj);
        return Supplier::where('document', $cleanCnpj)->value('id');
    }

    /**
     * 🚀 SMART MATCH (Busca em Cascata):
     * Retorna um array com o 'product_id' e o 'conversion_factor'.
     */
    public function findInternalMatch(?int $supplierId, string $xmlName): ?array
    {
        // 1. Tenta a Correspondência Exata (Mesmo fornecedor + Mesmo Nome XML)
        if ($supplierId) {
            $exactMatch = ProductCostHistory::where('supplier_id', $supplierId)
                ->where('xml_name', $xmlName)
                ->latest('id')
                ->first();

            if ($exactMatch) {
                return [
                    'product_id' => $exactMatch->product_id,
                    'conversion_factor' => $exactMatch->conversion_factor
                ];
            }
        }

        // 2. Tenta a Correspondência Ampla (Qualquer fornecedor + Mesmo Nome XML)
        $broadMatch = ProductCostHistory::where('xml_name', $xmlName)
            ->latest('id')
            ->first();

        if ($broadMatch) {
            return [
                'product_id' => $broadMatch->product_id,
                'conversion_factor' => $broadMatch->conversion_factor
            ];
        }

        return null;
    }

    public function extractDataFromXml(string $xmlContent, int|string|null $receivedInvoiceId = null): array
    {
        try {
            if (str_contains($xmlContent, 'base64,')) {
                $xmlContent = base64_decode(explode('base64,', $xmlContent)[1]);
            }

            $xmlContent = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $xmlContent);
            $xml = @simplexml_load_string($xmlContent, 'SimpleXMLElement', LIBXML_NOCDATA);

            $nfeNode = isset($xml->NFe) ? $xml->NFe : ($xml->getName() === 'NFe' ? $xml : null);
            $inf = $nfeNode->infNFe;

            $state = [
                'received_invoice_id' => $receivedInvoiceId,
                'invoice_key' => str_replace('NFe', '', (string) $inf->attributes()->Id),
                'xml_total_amount' => (float) $inf->total->ICMSTot->vNF,
                'xml_supplier_cnpj' => (string) $inf->emit->CNPJ,
                'xml_supplier_name' => (string) $inf->emit->xNome,
            ];

            $state['financial_already_launched'] = AccountPayable::where('nfe_key', $state['invoice_key'])->exists();
            $state['supplier_id'] = $this->findSupplierIdByCnpj($state['xml_supplier_cnpj']);

            $installments = [];
            if (isset($inf->cobr->dup)) {
                foreach ($inf->cobr->dup as $dup) {
                    $installments[] = [
                        'number' => (string) $dup->nDup,
                        'due_date' => (string) $dup->dVenc,
                        'amount' => (float) $dup->vDup
                    ];
                }
            }
            $state['xml_installments'] = $installments;

            $groupedItems = [];
            if (isset($inf->det)) {
                foreach ($inf->det as $det) {
                    $xmlName = (string) $det->prod->xProd;
                    $xmlQty = (float) $det->prod->qCom;
                    $xmlCost = (float) $det->prod->vUnCom;
                    $xmlTotal = (float) $det->prod->vProd;
                    $xmlUom = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $det->prod->uCom));
                    $xmlCode = (string) $det->prod->cProd;
                    $xmlCfop = (string) ($det->prod->CFOP ?? '');

                    if ($xmlCost <= 0.001 && $xmlQty > 0) {
                        $xmlCost = $xmlTotal / $xmlQty;
                    }

                    $groupKey = md5($xmlName . '_' . $xmlCost . '_' . $xmlUom . '_' . $xmlCfop);

                    if (!isset($groupedItems[$groupKey])) {
                        $groupedItems[$groupKey] = [
                            'name' => $xmlName,
                            'code' => $xmlCode,
                            'cost' => $xmlCost,
                            'uom'  => $xmlUom,
                            'cfop' => $xmlCfop,
                            'qty'  => 0
                        ];
                    }
                    $groupedItems[$groupKey]['qty'] += $xmlQty;
                }
            }

            $items = [];
            foreach ($groupedItems as $group) {
                $xmlName = $group['name'];
                $xmlCost = $group['cost'];
                $xmlQty = $group['qty'];
                $xmlUom = $group['uom'];
                $xmlCode = $group['code'];
                $xmlCfop = $group['cfop'];

                // 🚀 Verifica se já foi processado em Estoque, Despesa Pessoal ou Patrimônio
                $processed = ProductCostHistory::where('nfe_key', $state['invoice_key'])->where('xml_name', $xmlName)->exists()
                    || PersonalPurchase::where('nfe_key', $state['invoice_key'])->where('xml_name', $xmlName)->exists()
                    || CompanyAsset::where('nfe_key', $state['invoice_key'])->where(function ($q) use ($xmlName) {
                        $q->where('name', $xmlName)->orWhere('description', 'like', "%{$xmlName}%");
                    })->exists();

                if (!$processed) {
                    $matchData = $this->findInternalMatch($state['supplier_id'], $xmlName);
                    $matchProductId = $matchData['product_id'] ?? null;
                    $matchFactor = $matchData['conversion_factor'] ?? 1;

                    // 🚀 Se o CFOP indicar Ativo Imobilizado (1551, 2551, 3551), sugere 'asset'
                    $isAssetCfop = in_array($xmlCfop, ['1551', '2551', '3551']);
                    $defaultDestination = $isAssetCfop ? 'asset' : 'stock';

                    $items[] = [
                        'xml_name' => $xmlName,
                        'supplier_product_code' => $xmlCode,
                        'xml_qty'  => $xmlQty,
                        'available_qty' => $xmlQty,
                        'xml_cost' => $xmlCost,
                        'xml_uom'  => $xmlUom,
                        'xml_cfop' => $xmlCfop,
                        'destination_type' => $defaultDestination,
                        'conversion_factor' => $matchFactor,
                        'product_id' => $matchProductId,
                        'is_matched' => $matchProductId ? 1 : 0,
                        'item_uuid'  => (string) Str::uuid(),
                        'confirmation_step' => 0,
                        'factor_source' => 'auto',
                        'asset_name' => $xmlName,
                        'asset_location' => null,
                        'asset_tag' => null,
                        'warranty_until' => null,
                    ];
                }
            }

            $state['xml_items'] = collect($items)->sortByDesc('is_matched')->values()->toArray();
            return $state;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Erro no XML Import: " . $e->getMessage());
            return [];
        }
    }

    public function finalizeInvoice(array $formData): void
    {
        DB::transaction(function () use ($formData) {
            if (!empty($formData['supplier_id'])) {
                $supplier = Supplier::find($formData['supplier_id']);
                if ($supplier) {
                    $needsSave = false;
                    if (empty($supplier->document) && !empty($formData['xml_supplier_cnpj'])) {
                        $supplier->document = $formData['xml_supplier_cnpj'];
                        $needsSave = true;
                    }
                    if (empty($supplier->name) && !empty($formData['xml_supplier_name'])) {
                        $supplier->name = $formData['xml_supplier_name'];
                        $needsSave = true;
                    }
                    if ($needsSave) $supplier->save();
                }
            }

            if (!$formData['financial_already_launched']) {
                $this->generateFinancial($formData);
            }

            if (!empty($formData['received_invoice_id'])) {
                ReceivedInvoice::where('id', $formData['received_invoice_id'])->update(['status' => 'processed']);
            }
        });
    }

    protected function generateFinancial(array $data): void
    {
        $supplier = Supplier::find($data['supplier_id']);
        $supplierName = $supplier ? ($supplier->trade_name ?: $supplier->name) : 'Fornecedor';
        $installments = $data['xml_installments'] ?? [];

        if (count($installments) > 0) {
            foreach ($installments as $dup) {
                AccountPayable::create([
                    'nfe_key' => $data['invoice_key'],
                    'supplier_id' => $data['supplier_id'],
                    'supplier_name' => $supplierName,
                    'description' => "Invoice #{$data['invoice_key']} - Parc {$dup['number']}",
                    'amount' => $dup['amount'],
                    'installment' => $dup['number'],
                    'due_date' => $dup['due_date'],
                    'status' => 'pending',
                ]);
            }
        } else {
            AccountPayable::create([
                'nfe_key' => $data['invoice_key'],
                'supplier_id' => $data['supplier_id'],
                'supplier_name' => $supplierName,
                'description' => "Pagamento Único - Nota #{$data['invoice_key']}",
                'amount' => $data['xml_total_amount'],
                'installment' => '1/1',
                'due_date' => now()->toDateString(),
                'status' => 'pending',
            ]);
        }
    }

    public function attemptAutoApproval(ReceivedInvoice $invoice): void
    {
        $data = $this->extractDataFromXml($invoice->xml_content, $invoice->id);

        if (empty($data['xml_items']) || empty($data['supplier_id'])) {
            return;
        }

        $supplier = Supplier::find($data['supplier_id']);
        if (!$supplier || !$supplier->is_trusted) {
            return;
        }

        $processor = app(InvoiceItemProcessor::class);
        $allProcessed = true;

        foreach ($data['xml_items'] as $index => $item) {
            $rule = ProductCostHistory::where('supplier_id', $data['supplier_id'])
                ->where('xml_name', $item['xml_name'])
                ->where('auto_approve', true)
                ->latest('id')
                ->first();

            if ($rule) {
                $xmlUom = strtoupper(preg_replace('/[^A-Za-z]/', '', $item['xml_uom']));
                $ruleUom = strtoupper(preg_replace('/[^A-Za-z]/', '', $rule->original_unit_of_measure ?? ''));

                if ($xmlUom === $ruleUom) {
                    $item['product_id'] = $rule->product_id;
                    $item['conversion_factor'] = $rule->conversion_factor;
                    $item['destination_type'] = 'stock';
                    $item['is_personal'] = false;

                    $processor->processDirectItem($item, $data['invoice_key'], $data['supplier_id'], true);
                    continue;
                }
            }

            $allProcessed = false;
        }

        if ($allProcessed) {
            unset($data['xml_items']);
            $this->finalizeInvoice($data);
            \Illuminate\Support\Facades\Log::info("🚀 [Auto-Importação] NF-e {$invoice->nfe_key} do Fornecedor Confiável '{$supplier->name}' finalizada e estocada automaticamente sem intervenção humana!");
        }
    }
}
