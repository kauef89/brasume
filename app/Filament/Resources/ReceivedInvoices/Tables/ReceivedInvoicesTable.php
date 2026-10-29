<?php

namespace App\Filament\Resources\ReceivedInvoices\Tables;

use App\Filament\Resources\ReceivedInvoices\ReceivedInvoiceResource;
use App\Filament\Resources\ReceivedInvoices\Actions\InvoiceSummaryAction;
use App\Filament\Resources\ReceivedInvoices\Actions\ViewDanfeAction;
use App\Filament\Resources\ReceivedInvoices\Actions\ProcessReturnAction;
use App\Filament\Pages\ImportInvoice;
use App\Models\Supplier;
use App\Models\CompanySetting; // 🚀 Importado para checar o certificado
use App\Filament\Pages\ManageCompanySettings; // 🚀 Importado para gerar a URL correta
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Filters\SelectFilter;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Database\Eloquent\Builder;

class ReceivedInvoicesTable
{
    public static function build(Table $table): Table
    {
        // 🚀 CHECAGEM DO CERTIFICADO:
        // Busca as configurações e avalia se o certificado digital está preenchido
        $setting = CompanySetting::getCached();

        // Ajuste 'certificate_path' abaixo para o nome real da coluna no seu banco (ex: certificate, certificado_file...)
        $hasCertificate = $setting && !empty($setting->certificate_path);

        return $table
            // 🚀 SE NÃO HOUVER CERTIFICADO, FORÇA A TABELA A FICAR VAZIA PARA EXIBIR A TELA DE BLOQUEIO
            ->modifyQueryUsing(function ($query) use ($hasCertificate) {
                if (! $hasCertificate) {
                    return $query->whereRaw('1 = 0'); // Query impossível para esvaziar a tabela de forma limpa
                }
                return $query;
            })
            ->columns([
                Tables\Columns\TextColumn::make("issue_date")
                    ->label("Emissão")
                    ->dateTime("d/m/Y H:i")
                    ->sortable(),

                Tables\Columns\TextColumn::make("emitter_name")
                    ->label("Fornecedor")
                    ->state(function ($record) {
                        $supplier = Supplier::with("parent")
                            ->where("document", $record->emitter_cnpj)
                            ->first();

                        if ($supplier) {
                            $childName = $supplier->name;

                            if ($supplier->parent) {
                                $parentName = $supplier->parent->name;
                                return "{$parentName} ({$childName})";
                            }

                            return $childName;
                        }

                        return $record->emitter_name;
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                            return $query->where('emitter_name', 'like', "%{$search}%")
                                ->orWhereHas('costHistories', function ($q) use ($search) {
                                    $q->where('xml_name', 'like', "%{$search}%");
                                })
                                ->orWhereHas('personalPurchases', function ($q) use ($search) {
                                    $q->where('xml_name', 'like', "%{$search}%");
                                });
                        })
                        ->limit(45),

                Tables\Columns\TextColumn::make("emitter_cnpj")
                    ->label("CNPJ")
                    ->copyable(),

                Tables\Columns\TextColumn::make("total_value")
                    ->label("Valor Total")
                    ->money("BRL"),

                Tables\Columns\TextColumn::make("xml_content")
                    ->searchable()
                    ->hidden(),

                    Tables\Columns\TextColumn::make("status")
                        ->label("Status")
                        ->badge()
                        ->formatStateUsing(
                                                    fn(string $state): string => match ($state) {
                                                        "pending" => "Pendente",
                                                        "manifested" => "Aguard. XML",
                                                        "ready" => "Confirmada",
                                                        "processed" => "Processada",
                                                        "rejected" => "Rejeitada",
                                                        "canceled_by_supplier" => "Cancelada",
                                                        "returned" => "Devolvida (Anulada)", // 🚀 A nota de Venda original
                                                        "return_receipt" => "Recibo de Devolução",
                                                        "expired_manifestation" => "Expirada",// 🚀 A nota que o fornecedor emitiu anulando
                                                        default => $state,
                                                    },
                                                )
                                                ->colors([
                                                    "gray" => ["pending", "return_receipt"],
                                                    "warning" => "manifested",
                                                    "success" => "processed",
                                                    "primary" => "ready",
                                                    "danger" => ["rejected", "canceled_by_supplier", "returned", "expired_manifestation"],
                                                ]),
            ])
            ->defaultSort("issue_date", "desc")
            ->recordAction("resumo_nota")
            ->filters([
            SelectFilter::make("status")
                ->label("Filtrar Status")
                ->options([
                    "pending" => "Pendente",
                    "ready" => "Manifestada",
                    "processed" => "Processada",
                    "rejected" => "Rejeitada",
                    "canceled_by_supplier" => "Cancelada", // 🚀 Novo Filtro
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make("finalizar_lote")
                        ->label("Finalizar Selecionadas")
                        ->icon("heroicon-m-check-badge")
                        ->color("success")
                        ->requiresConfirmation()
                        ->modalHeading("Finalizar Notas Selecionadas")
                        ->modalDescription("Isso finalizará todas as notas selecionadas que já estiverem com 100% dos itens estocados, gerando o Contas a Pagar.")
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $service = app(\App\Services\Purchases\InvoiceImportService::class);
                            $count = 0;
                            foreach ($records as $record) {
                                if ($service->isReadyToFinalize($record)) {
                                    $data = $service->extractDataFromXml($record->xml_content, $record->id);
                                    $service->finalizeInvoice($data);
                                    $count++;
                                }
                            }
                            \Filament\Notifications\Notification::make()->title("{$count} nota(s) finalizada(s)!")->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make("manifestar_lote")
                        ->label("Dar Ciência nas Selecionadas")
                        ->icon("heroicon-m-check-badge")
                        ->color("info")
                        ->requiresConfirmation()
                        ->visible(
                            fn(\Livewire\Component $livewire) => property_exists($livewire, "activeTab")
                                ? $livewire->activeTab !== "historico"
                                : true,
                        )
                        ->action(function (Collection $records) {
                            $sucesso = 0;
                            $falha = 0;
                            foreach ($records as $record) {
                                if ($record->status !== "pending") {
                                    continue;
                                }
                                try {
                                    ReceivedInvoiceResource::transmitirEventoSefaz(
                                        $record->nfe_key,
                                        210210,
                                        "Ciencia da operacao"
                                    );
                                    $record->update(["status" => "manifested"]);
                                    $sucesso++;
                                } catch (\Exception $e) {
                                    // 🚀 SEFAZ GRITOU 596? MARCA COMO EXPIRADO SILENCIOSAMENTE!
                                    if (str_contains($e->getMessage(), '596')) {
                                        $record->update(["status" => "expired_manifestation"]);
                                    }
                                    $falha++;
                                }
                            }
                            if ($sucesso > 0) {
                                Notification::make()->title("{$sucesso} notas prontas para processar!")->success()->send();
                            }
                            if ($falha > 0) {
                                Notification::make()->title("Falha em {$falha} notas.") ->danger()->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make("estornar_lote")
                        ->label("Estornar Lote Selecionado")
                        ->icon("heroicon-m-arrow-uturn-left")
                        ->color("danger")
                        ->requiresConfirmation()
                        ->modalHeading("Estornar Notas Fiscais")
                        ->modalDescription("Isso vai reverter o estoque adicionado, apagar as despesas, excluir os lançamentos do contas a pagar e devolver as notas para a fila de processamento. Deseja continuar?")
                        ->visible(
                            fn(\Livewire\Component $livewire) => property_exists($livewire, "activeTab")
                                ? $livewire->activeTab === "historico"
                                : false,
                        )
                        ->action(function (Collection $records) {
                            foreach ($records as $invoice) {
                                if ($invoice->status !== "processed") {
                                    continue;
                                }

                                \Illuminate\Support\Facades\DB::transaction(function () use ($invoice) {
                                    $histories = \App\Models\ProductCostHistory::where("nfe_key", $invoice->nfe_key)->with("product")->get();
                                    foreach ($histories as $history) {
                                        if ($history->product) {
                                            $history->product->decrement("current_stock", $history->quantity);
                                        }
                                        $history->delete();
                                    }

                                \App\Models\PersonalPurchase::where("nfe_key", $invoice->nfe_key)->delete();

                                $payables = \App\Models\AccountPayable::where("nfe_key", $invoice->nfe_key)->get();
                                foreach ($payables as $payable) {
                                    if ($payable->status === 'paid') {
                                        throw new \Exception("A nota {$invoice->nfe_key} possui faturas já pagas. Desfaça a conciliação no Extrato Bancário primeiro.");
                                    }
                                    $payable->delete();
                                }

                                $invoice->update(["status" => "ready"]);
                                });
                            }
                            Notification::make()->title("Lote estornado com sucesso!")->body("As notas voltaram para a fila de pendências.") ->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->actions([
                InvoiceSummaryAction::make(),
                Action::make('upload_xml_row')
                    ->label('Anexar XML')
                    ->icon('heroicon-m-document-arrow-up')
                    ->iconButton() // Botão limpo na tabela
                    ->tooltip('Prazo expirado. Anexe o XML manualmente.')
                    ->color('warning')
                    ->visible(fn($record) => $record->status === 'expired_manifestation') // Só aparece quando der o erro 596
                    ->form([
                        \Filament\Forms\Components\FileUpload::make('xml_file')
                            ->label('Ficheiro XML da Nota Completo')
                            ->acceptedFileTypes(['text/xml', 'application/xml'])
                            ->directory('temp_xmls')
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            // 1. Lê o ficheiro temporário que o Filament subiu
                            $xmlContent = \Illuminate\Support\Facades\Storage::get($data['xml_file']);

                            // 2. Apaga o ficheiro do disco, já o temos na memória!
                            \Illuminate\Support\Facades\Storage::delete($data['xml_file']);

                            $xml = simplexml_load_string($xmlContent);
                            if (!$xml || $xml->getName() !== 'nfeProc') {
                                throw new \Exception("Ficheiro inválido. Tem de subir o XML completo (nfeProc).");
                            }

                            // 3. O Escudo de Segurança: Confirma se o XML é o dono desta linha
                            $chaveRecebida = str_replace('NFe', '', (string) $xml->NFe->infNFe['Id']);
                            if ($chaveRecebida !== $record->nfe_key) {
                                throw new \Exception("Atenção: O XML enviado pertence a outra nota fiscal (Chave diferente)!");
                            }

                            // 4. Injeta na linha existente e acorda o processo
                            $record->update([
                                'xml_content' => $xmlContent,
                                'status' => 'ready'
                            ]);

                            // 5. Tenta aprovar automaticamente os itens no histórico
                            app(\App\Services\Purchases\InvoiceImportService::class)->attemptAutoApproval($record);

                            Notification::make()->title('XML anexado com sucesso!')->body('A nota está pronta para ser importada.')->success()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('Erro na Leitura')->body($e->getMessage())->danger()->send();
                        }
                    }),
                ProcessReturnAction::make(),
                Action::make("manifestar")
                    ->label("Dar Ciência")
                    ->icon("heroicon-m-check-badge")
                    ->iconButton()
                    ->tooltip('Manifestar conhecimento')
                    ->color("info")
                    ->visible(fn($record) => $record->status === "pending" && $record->status !== "canceled_by_supplier")
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        try {
                            ReceivedInvoiceResource::transmitirEventoSefaz($record->nfe_key, 210210, "Ciencia da operacao");
                            $record->update(["status" => "manifested"]);
                            Notification::make()->title("Ciência enviada! O XML será baixado em breve.")->success()->send();
                        } catch (\Exception $e) {
                            // 🚀 O INTERCEPTADOR DO ERRO 596
                            if (str_contains($e->getMessage(), '596')) {
                                $record->update(['status' => 'expired_manifestation']);
                                Notification::make()
                                    ->title('Prazo Expirado!')
                                    ->body('A Sefaz bloqueou a ciência por limite de tempo. Use o botão de anexo na linha para subir o XML.')
                                    ->warning()
                                    ->send();
                            } else {
                                Notification::make()->title("Erro Técnico")->body($e->getMessage())->danger()->send();
                            }
                        }
                    }),

                Action::make("rejeitar")
                    ->label("Desconhecer")
                    ->icon("heroicon-m-x-circle")
                    ->iconButton()
                    ->tooltip('Rejeitar nota')
                    ->color("danger")
                    ->visible(fn($record) => $record->status === "pending")
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        try {
                            ReceivedInvoiceResource::transmitirEventoSefaz($record->nfe_key, 210220, "Desconhecimento da operacao");
                            $record->update(["status" => "rejected"]);
                            Notification::make()->title("Operação Rejeitada!")->success()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title("Erro ao Rejeitar")->body($e->getMessage())->danger()->send();
                        }
                    }),

                    Action::make("finalizar_direto")
                    ->label("Finalizar")
                    ->icon("heroicon-m-check-badge")
                    ->iconButton()
                    ->tooltip("Finalizar Entrada (Itens 100% estocados)")
                    ->color("success")
                    ->visible(fn($record) => $record->status === "ready" && $record->has_full_xml && app(\App\Services\Purchases\InvoiceImportService::class)->isReadyToFinalize($record))
                    ->requiresConfirmation()
                    ->modalHeading("Finalizar Entrada da Nota Fiscal")
                    ->modalDescription(fn($record) => "Todos os itens da nota (" . $record->emitter_name . ") já estão integrados ao estoque. Deseja gerar o Contas a Pagar e finalizar?")
                    ->modalSubmitActionLabel("Finalizar Nota")
                    ->action(function ($record) {
                        try {
                            $service = app(\App\Services\Purchases\InvoiceImportService::class);
                            $data = $service->extractDataFromXml($record->xml_content, $record->id);
                            $service->finalizeInvoice($data);
                            \Filament\Notifications\Notification::make()->title("Nota Finalizada com Sucesso!")->body("Estoque atualizado e fatura gerada no Contas a Pagar.")->success()->send();
                        } catch (\Throwable $e) {
                            \Filament\Notifications\Notification::make()->title("Erro ao Finalizar")->body($e->getMessage())->danger()->send();
                        }
                    }),

                Action::make("processar")
                    ->label("Importar")
                    ->icon("heroicon-o-arrow-down-on-square-stack")
                    ->iconButton()
                    ->tooltip("Processar nota (Itens pendentes)")
                    ->color("gray")
                    ->visible(fn($record) => $record->status === "ready" && $record->has_full_xml && ! app(\App\Services\Purchases\InvoiceImportService::class)->isReadyToFinalize($record))
                    ->action(fn($record) => redirect()->to(ImportInvoice::getUrl(["invoice" => $record->id]))),

                ViewDanfeAction::make(),
                Action::make("download_xml")
                    ->label("Baixar")
                    ->icon("heroicon-o-arrow-down-tray")
                    ->iconButton()
                    ->tooltip('Baixar XML')
                    ->color("gray")
                    ->visible(fn($record) => !empty($record->xml_content))
                    ->action(fn($record) => response()->streamDownload(fn() => print $record->xml_content, "NFe_{$record->nfe_key}.xml")),
            ])
            // 🚀 O SEGREDO VISUAL: Constrói uma tela vazia totalmente customizada com Tailwind!
            ->emptyStateHeading(fn() => $hasCertificate ? 'Nenhuma nota fiscal encontrada' : 'Configuração Pendente: Certificado Digital A1')
            ->emptyStateDescription(fn() => $hasCertificate
                ? 'Use o botão de sincronização no topo para buscar novos documentos na SEFAZ.'
                : new HtmlString('
                    <div class="max-w-md mx-auto text-center space-y-3 mt-2">
                        <p class="text-gray-500 text-sm leading-relaxed">
                            Para consultar e importar notas fiscais direto da SEFAZ, sua empresa precisa de um <b>Certificado Digital A1 ativo</b>. O certificado atual está ausente ou inválido.
                        </p>
                    </div>
                ')
            )
            ->emptyStateIcon(fn() => $hasCertificate ? 'heroicon-o-document-text' : 'heroicon-o-shield-exclamation')
            ->emptyStateActions([
                // Só renderiza o botão se o certificado estiver faltando
                Action::make('ir_configuracoes')
                    ->label('Ir para as Configurações')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->color('primary')
                    ->button()
                    ->url(fn() => ManageCompanySettings::getUrl())
                    ->visible(! $hasCertificate)
            ]);
    }
}
