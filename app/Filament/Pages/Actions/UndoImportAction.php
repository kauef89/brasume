<?php

namespace App\Filament\Pages\Actions;

use Filament\Actions\Action;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;

// Models
use App\Models\LegacyImportLog;
use App\Models\LegacyDailyRevenue;
use App\Models\LegacyOrder;
use App\Models\FiscalDocument;

class UndoImportAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'undo_import';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label("Desfazer")
            ->icon("heroicon-m-arrow-uturn-left")
            ->color("danger")
            ->requiresConfirmation()
            ->action(function (LegacyImportLog $record) {
                DB::beginTransaction();
                try {
                    if ($record->type === "v0_consolidated") {
                        LegacyDailyRevenue::where("import_tag", $record->import_tag)->delete();

                    } elseif ($record->type === "v2_fiscal") {
                        // 1. Desfaz as notas em staging
                        FiscalDocument::where('import_tag', $record->import_tag)->delete();

                        // 2. Desfaz os matches na V1
                        LegacyOrder::where('xml_content', 'like', "%{$record->import_tag}%")
                            ->orWhereNotNull('nfe_key')
                            ->whereDoesntHave('items') // fallback heurístico
                            ->update(['nfe_key' => null, 'xml_content' => null, 'series' => null, 'number' => null]);

                        // 3. Apaga os pedidos criados NESTA importação
                        $orderIds = LegacyOrder::where("import_tag", $record->import_tag)->pluck("id");
                        DB::table("legacy_order_items")->whereIn("legacy_order_id", $orderIds)->delete();
                        DB::table("legacy_order_payments")->whereIn("legacy_order_id", $orderIds)->delete();
                        LegacyOrder::whereIn("id", $orderIds)->delete();

                    } elseif ($record->type === "v1_database") {
                        // 🚀 1. Lógica Híbrida: Limpa as Notas Fiscais Enriquecidas (Tira a etiqueta colada)
                        $enrichedOrders = LegacyOrder::where('import_tag', 'like', "% | {$record->import_tag}")->get();
                        foreach ($enrichedOrders as $order) {
                            $cleanTag = trim(str_replace(" | {$record->import_tag}", "", $order->import_tag));

                            $order->update([
                                'number' => null,
                                'order_type' => null,
                                'import_tag' => $cleanTag
                            ]);

                            // Apaga os itens e pagamentos, deixando a capa do XML limpa para a próxima vez
                            DB::table("legacy_order_items")->where("legacy_order_id", $order->id)->delete();
                            DB::table("legacy_order_payments")->where("legacy_order_id", $order->id)->delete();
                        }

                        // 🚀 2. Lógica Híbrida: Apaga os pedidos puramente criados do zero pelo WP
                        $orderIds = LegacyOrder::where("import_tag", $record->import_tag)->pluck("id");
                        DB::table("legacy_order_items")->whereIn("legacy_order_id", $orderIds)->delete();
                        DB::table("legacy_order_payments")->whereIn("legacy_order_id", $orderIds)->delete();
                        LegacyOrder::whereIn("id", $orderIds)->delete();

                    } else {
                        // V1 Padrão antigo
                        $orderIds = LegacyOrder::where("import_tag", $record->import_tag)->pluck("id");
                        DB::table("legacy_order_items")->whereIn("legacy_order_id", $orderIds)->delete();
                        DB::table("legacy_order_payments")->whereIn("legacy_order_id", $orderIds)->delete();
                        LegacyOrder::whereIn("id", $orderIds)->delete();
                    }

                    $record->delete();
                    DB::commit();
                    Notification::make()->title("Importação revertida com sucesso!")->success()->send();
                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            });
    }
}
