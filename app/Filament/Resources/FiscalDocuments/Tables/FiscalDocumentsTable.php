<?php

namespace App\Filament\Resources\FiscalDocuments\Tables;

use App\Models\FiscalDocument;
use App\Services\Fiscal\SpedDocumentService;
use App\Filament\Resources\FiscalDocuments\FiscalDocumentResource;
use App\Filament\Resources\Orders\OrderResource; // <-- Importação do Resource de Pedidos
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Illuminate\Database\Eloquent\Builder;

class FiscalDocumentsTable
{
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('issue_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Número')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('series')
                    ->label('Série')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('order.id')
                    ->label('Pedido')
                    ->searchable()
                    ->sortable()
                    // 🚀 Forma correta e dinâmica para Tenants: pedir a URL direto para o Resource alvo
                    ->url(fn (FiscalDocument $record): string => OrderResource::getUrl('edit', ['record' => $record->order_id]))
                    ->color('primary'),

                Tables\Columns\TextColumn::make('issue_date')
                    ->label('Emissão')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_value')
                    ->label('Valor Total')
                    ->money('BRL')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'authorized' => 'success',
                        'canceled', 'canceled_by_substitution' => 'danger',
                        'rejected' => 'warning',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'authorized' => 'Autorizada',
                        'canceled' => 'Cancelada',
                        'rejected' => 'Rejeitada',
                        default => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('access_key')
                    ->label('Chave de Acesso')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->copyable()
                    ->tooltip('Clique para copiar'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'authorized' => 'Autorizada',
                        'canceled' => 'Cancelada',
                    ]),
                Filter::make('issue_date')
                    ->form([
                        Forms\Components\DatePicker::make('emitted_from')->label('Emitido a partir de'),
                        Forms\Components\DatePicker::make('emitted_until')->label('Emitido até'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['emitted_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('issue_date', '>=', $date),
                            )
                            ->when(
                                $data['emitted_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('issue_date', '<=', $date),
                            );
                    })
            ])
            ->actions([
                Action::make('view')
                    ->label('Visualizar')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (FiscalDocument $record): string => FiscalDocumentResource::getUrl('view', ['record' => $record])),

                Action::make('download_xml')
                    ->label('XML')
                    ->icon('heroicon-o-code-bracket')
                    ->color('info')
                    ->action(function (FiscalDocument $record) {
                        $filename = "{$record->access_key}-nfce.xml";
                        return response()->streamDownload(function () use ($record) {
                            echo $record->xml_content;
                        }, $filename, ['Content-Type' => 'application/xml']);
                    }),

                Action::make('download_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->action(function (FiscalDocument $record, SpedDocumentService $spedService) {
                        try {
                            $pdfContent = $spedService->renderDanfePdf($record->xml_content);
                            $filename = "{$record->access_key}-danfe.pdf";
                            return response()->streamDownload(function () use ($pdfContent) {
                                echo $pdfContent;
                            }, $filename, ['Content-Type' => 'application/pdf']);
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()->title('Erro')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->bulkActions([
                BulkAction::make('export_selected_xmls')
                    ->label('Baixar XMLs (ZIP)')
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->form([
                        Forms\Components\Toggle::make('distribute_freight_in_products')
                            ->label('Distribuir frete nos produtos (Compatibilidade Contábil)')
                            ->helperText('Incorpora o frete proporcionalmente aos itens e zera a tag de frete nos XMLs.')
                            ->default(false),
                    ])
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data, FiscalExportService $exportService) {
                        $distributeFreight = (bool) ($data['distribute_freight_in_products'] ?? false);
                        $zip = new \ZipArchive();
                        $zipFileName = 'NFCe_Selecionadas_' . now()->format('YmdHis') . '.zip';
                        $zipFilePath = storage_path('app/temp/' . $zipFileName);

                        if (!file_exists(storage_path('app/temp'))) {
                            mkdir(storage_path('app/temp'), 0755, true);
                        }

                        if ($zip->open($zipFilePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
                            foreach ($records as $record) {
                                if ($record->xml_content) {
                                    $xml = $record->xml_content;
                                    if ($distributeFreight) {
                                        $xml = $exportService->distributeFreightInXml($xml);
                                    }
                                    $zip->addFromString("{$record->access_key}-nfce.xml", $xml);
                                }
                            }
                            $zip->close();
                        }

                        return response()->download($zipFilePath)->deleteFileAfterSend(true);
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }
}
