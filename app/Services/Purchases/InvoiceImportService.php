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
        if ($supplierId) {
            $exactStock = ProductCostHistory::where('supplier_id', $supplierId)->where('xml_name', $xmlName)->latest('id')->first();
            $exactPersonal = PersonalPurchase::where('supplier_id', $supplierId)->where('xml_name', $xmlName)->latest('id')->first();

            if ($exactPersonal && (!$exactStock || $exactPersonal->id >= $exactStock->id)) {
                return [
                    'destination_type'  => 'personal',
                    'is_personal'       => true,
                    'product_id'        => null,
                    'conversion_factor' => 1.0,
                    'auto_approve'      => (bool) ($exactPersonal->auto_approve ?? false),
                ];
            }

            if ($exactStock) {
                return [
                    'destination_type'  => 'stock',
                    'is_personal'       => false,
                    'product_id'        => $exactStock->product_id,
                    'conversion_factor' => $exactStock->conversion_factor,
                    'auto_approve'      => (bool) ($exactStock->auto_approve ?? false),
                ];
            }
        }

        $broadStock = ProductCostHistory::where('xml_name', $xmlName)->latest('id')->first();
        $broadPersonal = PersonalPurchase::where('xml_name', $xmlName)->latest('id')->first();

        if ($broadPersonal && (!$broadStock || $broadPersonal->id >= $broadStock->id)) {
            return [
                'destination_type'  => 'personal',
                'is_personal'       => true,
                'product_id'        => null,
                'conversion_factor' => 1.0,
                'auto_approve'      => false,
            ];
        }

        if ($broadStock) {
            return [
                'destination_type'  => 'stock',
                'is_personal'       => false,
                'product_id'        => $broadStock->product_id,
                'conversion_factor' => $broadStock->conversion_factor,
                'auto_approve'      => false,
            ];
        }

        return null;
    }

    private function _deprecatedFindInternalMatch(?int $supplierId, string $xmlName): ?array
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

                    $isPersonalMatch = (($matchData['destination_type'] ?? null) === 'personal');
                    $finalDest = $isPersonalMatch ? 'personal' : $defaultDestination;
                    $items[] = [
                        'xml_name' => $xmlName,
                        'supplier_product_code' => $xmlCode,
                        'xml_qty'  => $xmlQty,
                        'available_qty' => $xmlQty,
                        'xml_cost' => $xmlCost,
                        'xml_uom'  => $xmlUom,
                        'xml_cfop' => $xmlCfop,
                        'destination_type' => $finalDest,
                        'is_personal' => $isPersonalMatch,
                        'conversion_factor' => $matchFactor,
                        'product_id' => $matchProductId,
                        'is_matched' => ($matchProductId || $isPersonalMatch) ? 1 : 0,
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
                ReceivedInvoice::where('id', $formData['received_invoice_id'])->update([
                    'status' => 'processed',
                    'supplier_id' => $formData['supplier_id'] ?? null
                ]);
            }
        });
    }

        protected function generateFinancial(array $data): void
    {
        $supplier = !empty($data['supplier_id']) ? \App\Models\Supplier::find($data['supplier_id']) : null;
        $supplierName = $supplier ? ($supplier->trade_name ?: $supplier->name) : 'Fornecedor';
        $category = $supplier?->accounting_category ?? (class_exists(\App\Enums\AccountingCategory::class) ? \App\Enums\AccountingCategory::INGREDIENTS->value : 'ingredients');
        $subcategory = $supplier?->accounting_subcategory;

        // 🚀 A MÁGICA DO FATIAMENTO: Busca o total pessoal já salvo no banco
        $personalTotal = \App\Models\PersonalPurchase::where('nfe_key', $data['invoice_key'])->sum('total_cost');
        $xmlTotalAmount = (float) $data['xml_total_amount'];

        if ($xmlTotalAmount <= 0) $xmlTotalAmount = 1; // Previne divisão por zero
        $personalRatio = min(1.0, $personalTotal / $xmlTotalAmount);

        $installments = $data['xml_installments'] ?? [];
        if (empty($installments)) {
            $installments[] = ['number' => '1/1', 'due_date' => now()->toDateString(), 'amount' => $xmlTotalAmount];
        }

        foreach ($installments as $dup) {
            $installmentAmount = (float) $dup['amount'];
            $personalAmount = round($installmentAmount * $personalRatio, 2);
            $operationalAmount = $installmentAmount - $personalAmount;

            if ($operationalAmount > 0) {
                \App\Models\AccountPayable::create([
                    'nfe_key'                => $data['invoice_key'],
                    'supplier_id'            => $data['supplier_id'],
                    'supplier_name'          => $supplierName,
                    'description'            => "Nota #{$data['invoice_key']} - Parc {$dup['number']}" . ($personalAmount > 0 ? " (Operacional)" : ""),
                    'amount'                 => $operationalAmount,
                    'installment'            => $dup['number'],
                    'due_date'               => $dup['due_date'],
                    'status'                 => 'pending',
                    'accounting_category'    => $category,
                    'accounting_subcategory' => $subcategory,
                    'is_personal'            => false,
                ]);
            }

            if ($personalAmount > 0) {
                \App\Models\AccountPayable::create([
                    'nfe_key'                => $data['invoice_key'],
                    'supplier_id'            => $data['supplier_id'],
                    'supplier_name'          => $supplierName,
                    'description'            => "Nota #{$data['invoice_key']} - Parc {$dup['number']} (Uso Pessoal)",
                    'amount'                 => $personalAmount,
                    'installment'            => $dup['number'],
                    'due_date'               => $dup['due_date'],
                    'status'                 => 'pending',
                    'accounting_category'    => 'partners',
                    'accounting_subcategory' => 'personal',
                    'is_personal'            => true,
                ]);
            }
        }
    }
public function attemptAutoApproval(ReceivedInvoice $invoice): bool
    {
        $data = $this->extractDataFromXml($invoice->xml_content, $invoice->id);
        if (empty($data['xml_items']) || empty($data['supplier_id'])) return false;

        $supplier = Supplier::find($data['supplier_id']);
        if (!$supplier || !$supplier->is_trusted) return false;

        $familyIds = !empty($supplier->family_ids) ? $supplier->family_ids : [$supplier->id];
        $itemsToProcess = [];

        foreach ($data['xml_items'] as $item) {
            $xmlUom = strtoupper(preg_replace('/[^A-Za-z]/', '', $item['xml_uom']));

            $stockRule = ProductCostHistory::whereIn('supplier_id', $familyIds)
                ->where('xml_name', $item['xml_name'])
                ->where('auto_approve', true)
                ->latest('id')
                ->first();

            if ($stockRule && $this->areUnitsCompatible($xmlUom, $stockRule->original_unit_of_measure)) {
                $itemsToProcess[] = [
                    'product_id'        => $stockRule->product_id,
                    'conversion_factor' => (float) $stockRule->conversion_factor,
                    'destination_type'  => 'stock',
                    'is_personal'       => false,
                    'item'              => $item,
                ];
                continue;
            }

            $personalRule = PersonalPurchase::whereIn('supplier_id', $familyIds)
                ->where('xml_name', $item['xml_name'])
                ->where('auto_approve', true)
                ->latest('id')
                ->first();

            if ($personalRule) {
                $itemsToProcess[] = [
                    'product_id'        => null,
                    'conversion_factor' => 1.0,
                    'destination_type'  => 'personal',
                    'is_personal'       => true,
                    'item'              => $item,
                ];
                continue;
            }

            \Illuminate\Support\Facades\Log::info("⏸️ [Auto-Aprovação Pendente] NF-e {$invoice->nfe_key}: O item '{$item['xml_name']}' requer conferência.");
            return false;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($itemsToProcess, $data, $invoice, $supplier) {
            $processor = app(InvoiceItemProcessor::class);
            foreach ($itemsToProcess as $p) {
                $payload = array_merge($p['item'], [
                    'product_id'        => $p['product_id'],
                    'conversion_factor' => $p['conversion_factor'],
                    'destination_type'  => $p['destination_type'],
                    'is_personal'       => $p['is_personal'],
                ]);
                $processor->processDirectItem($payload, $data['invoice_key'], $data['supplier_id'], true);
            }
            unset($data['xml_items']);
            $this->finalizeInvoice($data);
            \Illuminate\Support\Facades\Log::info("🚀 [Auto-Importação] NF-e {$invoice->nfe_key} finalizada automaticamente!");
        });

        return true;
    }

    private function areUnitsCompatible(?string $uomA, ?string $uomB): bool
    {
        $cleanA = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $uomA));
        $cleanB = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $uomB));
        if ($cleanA === $cleanB) return true;
        $synonyms = [
            'UN'  => ['UN', 'UND', 'UNID', 'PC', 'PCA'],
            'KG'  => ['KG', 'KIL', 'KILO', 'QUILO'],
            'LT'  => ['L', 'LT', 'LIT', 'LITRO'],
            'CX'  => ['CX', 'CXA', 'CAIXA'],
            'PCT' => ['PC', 'PCT', 'PACOTE'],
        ];
        foreach ($synonyms as $group) {
            if (in_array($cleanA, $group) && in_array($cleanB, $group)) return true;
        }
        return false;
    }
    public function isReadyToFinalize(ReceivedInvoice $invoice): bool
    {
        if ($invoice->status !== 'ready' || !$invoice->has_full_xml || empty($invoice->xml_content)) return false;
        $hasHistory = \App\Models\ProductCostHistory::where('nfe_key', $invoice->nfe_key)->exists()
            || \App\Models\PersonalPurchase::where('nfe_key', $invoice->nfe_key)->exists()
            || \App\Models\CompanyAsset::where('nfe_key', $invoice->nfe_key)->exists();
        if (!$hasHistory) return false;
        $data = $this->extractDataFromXml($invoice->xml_content, $invoice->id);
        return !empty($data) && empty($data['xml_items']) && !empty($data['supplier_id']);
    }

}