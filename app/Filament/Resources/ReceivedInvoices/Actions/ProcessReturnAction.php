<?php

namespace App\Filament\Resources\ReceivedInvoices\Actions;

use Filament\Actions\Action;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use App\Models\ReceivedInvoice;
use App\Models\ProductCostHistory;
use App\Models\Product;
use App\Models\AccountPayable;
use Filament\Notifications\Notification;

class ProcessReturnAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'process_return';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Processar Devolução')
            ->icon('heroicon-o-arrow-path-rounded-square')
            ->color('danger')
            ->iconButton() // 🚀 Transforma num botão de ícone limpo
            ->tooltip('Processar Devolução') // 🚀 Tooltip nativo
            // Só aparece na nota de compra que foi marcada como pendente de devolução
            ->visible(fn ($record) => $record->status === 'return_pending')
            ->modalHeading('Processar Evento de Devolução')
            ->modalDescription(new HtmlString(
                'Esta ação aplicará a devolução desta nota na sua base de dados:<br><br>' .
                '&bull; Será dada <strong>saída no estoque</strong> dos itens devolvidos.<br>' .
                '&bull; O <strong>Contas a Pagar</strong> gerado por ela será ajustado.<br><br>' .
                'Após processar, esta nota será arquivada no histórico.'
            ))
            ->requiresConfirmation()
            ->modalSubmitActionLabel('Confirmar e Estornar')
            ->action(function ($record) {
                try {
                    DB::transaction(function () use ($record) {
                        // 1. Procura o Recibo de Devolução que referenciou esta nota
                        $recibo = ReceivedInvoice::where('status', 'return_receipt')
                            ->where('xml_content', 'like', '%' . $record->nfe_key . '%')
                            ->first();

                        if (!$recibo) {
                            // Fallback de segurança: se o XML do recibo sumiu, apenas arquiva a nota
                            $record->update(['status' => 'returned']);
                            Notification::make()->title('Nota arquivada!')->warning()->send();
                            return;
                        }

                        $cleanXml = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $recibo->xml_content);
                        $xml = simplexml_load_string($cleanXml);
                        $hasProcessedHistory = ProductCostHistory::where('nfe_key', $record->nfe_key)->exists();

                        if ($hasProcessedHistory) {
                            // A NOTA ORIGINAL JÁ ESTAVA NO ESTOQUE: APLICA O ESTORNO
                            if (isset($xml->NFe->infNFe->det)) {
                                foreach ($xml->NFe->infNFe->det as $det) {
                                    $xmlName = (string) $det->prod->xProd;
                                    $xmlQty = (float) $det->prod->qCom;
                                    $xmlCost = (float) $det->prod->vUnCom;
                                    $xmlUom = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $det->prod->uCom));

                                    $historyOriginal = ProductCostHistory::where('nfe_key', $record->nfe_key)
                                        ->where('xml_name', $xmlName)
                                        ->first();

                                    if ($historyOriginal && $historyOriginal->product_id) {
                                        $factor = clone $historyOriginal->conversion_factor ?: 1;
                                        $internalQty = $xmlQty * $factor;

                                        ProductCostHistory::create([
                                            'product_id' => $historyOriginal->product_id,
                                            'supplier_id' => $historyOriginal->supplier_id,
                                            'nfe_key' => $recibo->nfe_key, // Salva com a chave do recibo para auditoria
                                            'xml_name' => "[DEVOLUÇÃO] " . $xmlName,
                                            'quantity' => -$internalQty,
                                            'conversion_factor' => $factor,
                                            'unit_cost' => $historyOriginal->unit_cost,
                                            'original_quantity' => -$xmlQty,
                                            'original_unit_of_measure' => $xmlUom,
                                            'original_unit_cost' => $xmlCost,
                                            'auto_approve' => 0,
                                        ]);

                                        $product = Product::find($historyOriginal->product_id);
                                        if ($product) {
                                            $product->current_stock -= $internalQty;
                                            $product->save();
                                        }
                                    }
                                }
                            }

                            // Cancela o contas a pagar
                            $payables = AccountPayable::where('nfe_key', $record->nfe_key)->where('status', 'pending')->get();
                            foreach ($payables as $payable) {
                                $payable->update([
                                    'status' => 'cancelled',
                                    'description' => $payable->description . ' (Cancelado ref. Devolução ' . $recibo->nfe_key . ')',
                                ]);
                            }

                            Notification::make()->title('Devolução Processada!')->body('Estoque e financeiro atualizados.')->success()->send();
                        } else {
                            // A NOTA ORIGINAL NUNCA ENTROU: APENAS ARQUIVA
                            Notification::make()->title('Nota Anulada!')->body('A compra não havia entrado no estoque, foi apenas arquivada.')->info()->send();
                        }

                        // 2. A MÁGICA: Muda o status da nota protagonista para ir pro histórico!
                        $record->update(['status' => 'returned']);
                    });
                } catch (\Exception $e) {
                    Notification::make()->title('Erro ao processar')->body($e->getMessage())->danger()->send();
                }
            });
    }
}
