<?php

namespace App\Filament\Pages\Actions;

use Filament\Actions\Action;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\HtmlString;

// Models
use App\Models\LegacyImportLog;
use App\Models\LegacyOrder;
use App\Models\LegacyOrderItem;
use App\Models\LegacyOrderPayment;
use App\Models\FiscalDocument;
use App\Models\LegacyProduct;
use App\Models\LegacyPaymentMethod;

class MapFiscalDocumentsAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'map_fiscal_documents';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label("Mapear Notas (Match)")
            ->icon("heroicon-o-cpu-chip")
            ->color("success")
            ->visible(fn(LegacyImportLog $record) => $record->type === 'v2_fiscal')
            ->form(function(LegacyImportLog $record) {
                // A Trava de Segurança: Checa se os dicionários estão resolvidos
                $unmappedProducts = LegacyProduct::whereNull('current_product_id')->where('is_ignored', false)->count();
                $unmappedPayments = LegacyPaymentMethod::whereNull('current_payment_method_id')->count();

                if ($unmappedProducts > 0 || $unmappedPayments > 0) {
                    return [
                        Placeholder::make('warning')
                            ->hiddenLabel()
                            ->content(new HtmlString("<div class='p-4 bg-danger-50 text-danger-700 border border-danger-200 rounded-lg'><b>⚠️ Acesso Bloqueado:</b><br>Existem <b>{$unmappedProducts} produtos</b> e <b>{$unmappedPayments} formas de pagamento</b> legadas aguardando mapeamento no dicionário.<br><br>Você precisa resolvê-los primeiro nas suas respectivas telas para que o sistema consiga traduzir os itens dos XMLs de forma segura.</div>"))
                    ];
                }

                $docsCount = FiscalDocument::where('import_tag', $record->import_tag)->where('status', 'processing')->count();

                if ($docsCount === 0) {
                    return [
                        Placeholder::make('empty')->hiddenLabel()->content(new HtmlString("<div class='p-4 bg-success-50 text-success-700 rounded-lg'>Todas as notas deste lote já foram mapeadas e processadas!</div>"))
                    ];
                }

                return [
                    Placeholder::make('info')
                        ->hiddenLabel()
                        ->content(new HtmlString("<div class='p-4 bg-primary-50 text-primary-700 border border-primary-200 rounded-lg'><b>Tudo pronto para a Transformação!</b><br>Existem <b>{$docsCount} notas fiscais</b> em área de Staging neste lote.<br><br><b>Motor de Resolução:</b><br>1. O sistema buscará pedidos antigos sem nota com o mesmo valor e data (Match 100%).<br>2. Caso não exista correspondência, criará automaticamente os novos pedidos históricos extraindo os itens e pagamentos do XML.</div>"))
                ];
            })
            ->action(function (LegacyImportLog $record, Action $action) {
                // Bloqueia a execução se os dicionários estiverem sujos
                $unmappedProducts = LegacyProduct::whereNull('current_product_id')->where('is_ignored', false)->count();
                $unmappedPayments = LegacyPaymentMethod::whereNull('current_payment_method_id')->count();

                if ($unmappedProducts > 0 || $unmappedPayments > 0) {
                    Notification::make()->title("Ação cancelada. Resolva os dicionários primeiro.")->danger()->send();
                    $action->halt();
                }

                $docs = FiscalDocument::where('import_tag', $record->import_tag)
                    ->where('status', 'processing')
                    ->get();

                if($docs->isEmpty()) return;

                $matchedCount = 0;
                $createdCount = 0;

                DB::beginTransaction();
                try {
                    foreach($docs as $doc) {
                        // 1. O MATCH: Procura um pedido do mesmo dia, mesmo valor e sem nota
                        $match = LegacyOrder::whereDate('issue_date', $doc->issue_date->toDateString())
                            ->where('total_value', $doc->total_value)
                            ->whereNull('nfe_key')
                            ->first();

                        if ($match) {
                            $match->update([
                                'nfe_key' => $doc->access_key,
                                'series' => $doc->series,
                                'number' => $doc->number,
                                'xml_content' => $doc->xml_content
                            ]);
                            $doc->update(['status' => 'authorized']);
                            $matchedCount++;
                        } else {
                            // 2. CRIAÇÃO: Nota órfã, cria pedido novo extraindo os itens
                            $xml = @simplexml_load_string(str_replace("xmlns=", "ns=", $doc->xml_content));
                            if(!$xml) continue;

                            $infNFe = $xml->NFe->infNFe ?? $xml->infNFe;

                            $newOrder = LegacyOrder::create([
                                'import_tag' => $doc->import_tag,
                                'nfe_key' => $doc->access_key,
                                'series' => $doc->series,
                                'number' => $doc->number,
                                'issue_date' => $doc->issue_date,
                                'total_value' => $doc->total_value,
                                'discount' => (float) ($infNFe->total->ICMSTot->vDesc ?? 0),
                                'status' => 'completed',
                                'xml_content' => $doc->xml_content,
                            ]);

                            // Traduz Pagamentos
                            if (isset($infNFe->pag->detPag)) {
                                foreach ($infNFe->pag->detPag as $detPag) {
                                    $tPag = (string) $detPag->tPag;
                                    $vPag = (float) $detPag->vPag;
                                    $legacyPayment = LegacyPaymentMethod::where('sefaz_code', $tPag)->first();

                                    if ($legacyPayment) {
                                        LegacyOrderPayment::create([
                                            'legacy_order_id' => $newOrder->id,
                                            'legacy_payment_method_id' => $legacyPayment->id,
                                            'amount' => $vPag,
                                        ]);
                                    }
                                }
                            }

                            // Traduz Itens
                            if (isset($infNFe->det)) {
                                foreach ($infNFe->det as $det) {
                                    $prod = $det->prod;
                                    $xProd = trim((string) $prod->xProd);
                                    $legacyProd = LegacyProduct::where('name', $xProd)->first();

                                    if ($legacyProd && !$legacyProd->is_ignored) {
                                        LegacyOrderItem::create([
                                            'legacy_order_id' => $newOrder->id,
                                            'legacy_product_id' => $legacyProd->id,
                                            'quantity' => (float) $prod->qCom,
                                            'unit_price' => (float) $prod->vUnCom,
                                            'total_price' => (float) $prod->vProd,
                                        ]);
                                    }
                                }
                            }

                            $doc->update(['status' => 'authorized']);
                            $createdCount++;
                        }
                    }
                    DB::commit();
                    Notification::make()->title("Mapeamento concluído!")->body("<b>{$matchedCount}</b> notas vinculadas a pedidos antigos.<br><b>{$createdCount}</b> novos pedidos criados e conciliados.")->success()->send();
                } catch (\Exception $e) {
                    DB::rollBack();
                    Notification::make()->title("Erro no mapeamento")->body($e->getMessage())->danger()->send();
                }
            });
    }
}
