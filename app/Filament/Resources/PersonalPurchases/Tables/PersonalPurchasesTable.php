<?php

namespace App\Filament\Resources\PersonalPurchases\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

// 🚀 IMPORTAÇÕES PARA OS FILTROS
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;

use Filament\Notifications\Notification;
use App\Models\ReceivedInvoice;
use Carbon\Carbon;

class PersonalPurchasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make("nfe_key")
                    ->label("Data")
                    ->formatStateUsing(function (string $state) {
                        $invoice = ReceivedInvoice::where(
                            "nfe_key",
                            $state,
                        )->first();
                        $dataCompra = $invoice ? $invoice->issue_date : null;

                        return $dataCompra
                            ? Carbon::parse($dataCompra)->format("d/m/Y")
                            : "Desconhecida";
                    })
                    ->searchable(),

                TextColumn::make("supplier.name")
                    ->label("Fornecedor")
                    ->formatStateUsing(function (string $state, $record) {
                        return $record->supplier
                            ? $record->supplier->name
                            : "Desconhecido";
                    })
                    ->searchable(["name", "document_name"]), // 🚀 Atualizado!

                TextColumn::make("xml_name")->label("Item (XML)")->searchable(),

                TextColumn::make("quantity")->label("Qtd")->numeric(),

                TextColumn::make("unit_cost")
                    ->label("Custo Unit.")
                    ->money("BRL"),

                TextColumn::make("total_cost")
                    ->label("Total do Item")
                    ->money("BRL")
                    ->sortable(),
            ])
            ->defaultSort("created_at", "desc")

            // 🚀 OS NOSSOS NOVOS FILTROS
            ->filters([
                SelectFilter::make("supplier_id")
                    ->label("Fornecedor")
                    ->relationship("supplier", "name")
                    ->searchable()
                    ->preload(),

                Filter::make("data_compra")
                    ->form([
                        DatePicker::make("created_from")->label("A partir de"),
                        DatePicker::make("created_until")->label("Até"),
                    ])
                    // Motor inteligente que filtra pela data da Nota Fiscal original
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data["created_from"],
                                fn(
                                    Builder $query,
                                    $date,
                                ): Builder => $query->whereIn(
                                    "nfe_key",
                                    ReceivedInvoice::whereDate(
                                        "issue_date",
                                        ">=",
                                        $date,
                                    )->pluck("nfe_key"),
                                ),
                            )
                            ->when(
                                $data["created_until"],
                                fn(
                                    Builder $query,
                                    $date,
                                ): Builder => $query->whereIn(
                                    "nfe_key",
                                    ReceivedInvoice::whereDate(
                                        "issue_date",
                                        "<=",
                                        $date,
                                    )->pluck("nfe_key"),
                                ),
                            );
                    })
                    // Mostra as tags bonitas de filtro ativo em cima da tabela
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data["created_from"] ?? null) {
                            $indicators[] = Indicator::make(
                                "A partir de " .
                                    Carbon::parse(
                                        $data["created_from"],
                                    )->format("d/m/Y"),
                            )->removeField("created_from");
                        }
                        if ($data["created_until"] ?? null) {
                            $indicators[] = Indicator::make(
                                "Até " .
                                    Carbon::parse(
                                        $data["created_until"],
                                    )->format("d/m/Y"),
                            )->removeField("created_until");
                        }
                        return $indicators;
                    }),
            ])

            ->actions([
                Action::make("desvincular")
                    ->label("Desfazer")
                    ->icon("heroicon-o-arrow-uturn-left")
                    ->color("danger")
                    ->tooltip("Desfazer marcação de uso pessoal")
                    ->requiresConfirmation()
                    ->modalHeading("Desfazer uso pessoal?")
                    ->modalDescription(
                        "Esta ação devolverá o item para a tela de importação da Nota Fiscal. (Nota: Nenhum estoque será alterado, pois o item não havia entrado no estoque).",
                    )
                    ->action(function ($record) {
                        // 1. Mudar status da NFe para 'ready' (A nota volta a ficar pendente de finalização)
                        $invoice = ReceivedInvoice::where(
                            "nfe_key",
                            $record->nfe_key,
                        )->first();
                        if ($invoice && $invoice->status === "processed") {
                            $invoice->update(["status" => "ready"]);
                        }

                        // 2. Deletar o registro de uso pessoal
                        $record->delete();

                        Notification::make()
                            ->title("Ação desfeita com sucesso!")
                            ->body(
                                "O item voltou para a Nota Fiscal original para ser reprocessado.",
                            )
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
