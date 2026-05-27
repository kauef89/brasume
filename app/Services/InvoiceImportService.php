<?php

namespace App\Services;

use App\Models\ProductCostHistory;
use App\Models\Supplier;
use App\Models\AccountPayable;
use App\Models\ReceivedInvoice;
use App\Models\PersonalPurchase;
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
     * CORRIGIDO: Agora busca pelo NOME DO XML (xml_name), que é o que está no banco!
     */
    public function findInternalMatch(?int $supplierId, string $xmlName): ?int
    {
        if (!$supplierId) return null;

        return ProductCostHistory::where('supplier_id', $supplierId)
            ->where('xml_name', $xmlName) // Mudamos aqui!
            ->latest('id')
            ->value('product_id');
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
                    $xmlTotal = (float) $det->prod->vProd; // 🚀 Lemos o valor total da linha!
                    $xmlUom = (string) $det->prod->uCom;
                    $xmlCode = (string) $det->prod->cProd;

                    // 🔥 CIRURGIA AUTOMÁTICA: Se o custo unitário vier zerado, nós calculamos na marra
                    if ($xmlCost <= 0.001 && $xmlQty > 0) {
                        $xmlCost = $xmlTotal / $xmlQty;
                    }

                    $groupKey = md5($xmlName . '_' . $xmlCost . '_' . $xmlUom);

                    if (!isset($groupedItems[$groupKey])) {
                        $groupedItems[$groupKey] = [
                            'name' => $xmlName,
                            'code' => $xmlCode,
                            'cost' => $xmlCost,
                            'uom'  => $xmlUom,
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

                $processed = ProductCostHistory::where('nfe_key', $state['invoice_key'])->where('xml_name', $xmlName)->exists()
                    || PersonalPurchase::where('nfe_key', $state['invoice_key'])->where('xml_name', $xmlName)->exists();

                if (!$processed) {

                    // CORRIGIDO: Passando o NOME em vez do CÓDIGO
                    $matchProductId = $this->findInternalMatch($state['supplier_id'], $xmlName);
                    $matchFactor = 1;

                    if ($matchProductId && $state['supplier_id']) {
                        // 🔥 BUSCA O ÚLTIMO FATOR APENAS SE O NOME DO XML FOR IDENTICO
                        $lastPurchase = ProductCostHistory::where('supplier_id', $state['supplier_id'])
                            ->where('product_id', $matchProductId)
                            ->where('xml_name', $xmlName) // 👈 Garante a integridade na carga inicial
                            ->latest('id')
                            ->first();

                        $matchFactor = $lastPurchase?->conversion_factor ?? 1;
                    }

                    $items[] = [
                        'xml_name' => $xmlName,
                        'supplier_product_code' => $xmlCode, // Mantemos no array, mas não quebramos o banco!
                        'xml_qty'  => $xmlQty,
                        'available_qty' => $xmlQty,
                        'xml_cost' => $xmlCost,
                        'xml_uom'  => $xmlUom,
                        'conversion_factor' => $matchFactor,
                        'product_id' => $matchProductId,
                        'is_matched' => $matchProductId ? 1 : 0,
                        'item_uuid'  => (string) Str::uuid(),
                        'confirmation_step' => 0,
                        'factor_source' => 'auto',
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
}
