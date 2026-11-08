<?php

namespace App\Filament\Resources\TableAreas\Tables;

use App\Models\TableArea;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use ZipArchive;

class TableAreasTable
{
    public static function build(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Área')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('dining_tables_count')
                    ->label('Qtd. de Mesas')
                    ->counts('diningTables')
                    ->badge()
                    ->color('info'),
            ])
            ->filters([
                //
            ])
            ->actions([
                // ==========================================
                // DOWNLOAD DOS QR CODES PARA GRÁFICA (ZIP)
                // ==========================================
                Action::make('download_qrcodes')
                    ->label('Baixar QR Codes')
                    ->icon('heroicon-m-qr-code')
                    ->color('success')
                    ->tooltip('Baixa um arquivo .zip com os arquivos SVG em alta resolução para enviar à gráfica.')
                    ->action(function (TableArea $record) {
                        $tenant = tenant();
                        $domain = $tenant?->domains?->first()?->domain 
                            ?? ($tenant ? $tenant->id . '.brasume.com.br' : request()->getHost());
                        
                        $zipFileName = 'qrcodes_' . Str::slug($record->name) . '_' . date('YmdHis') . '.zip';
                        $zipPath = storage_path('app/public/' . $zipFileName);

                        $zip = new ZipArchive();
                        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                            
                            foreach ($record->diningTables as $table) {
                                // Garante que a mesa tenha um hash
                                if (!$table->hash) {
                                    $table->update(['hash' => substr(md5(uniqid()), 0, 8)]);
                                }
                                
                                // Constrói a URL exata do tenant (HTTPS)
                                $url = "https://{$domain}/mesa/{$table->hash}";
                                
                                // Gera o SVG em alta qualidade (500x500px, margem de segurança)
                                $svg = QrCode::size(500)->margin(1)->generate($url);
                                
                                // Nomeia o arquivo claramente para a gráfica (ex: Mesa_01.svg)
                                $filename = 'Mesa_' . Str::slug($table->name) . '.svg';
                                $zip->addFromString($filename, $svg);
                            }
                            
                            $zip->close();
                        }

                        // O Filament processa perfeitamente o retorno de um download
                        return response()->download($zipPath)->deleteFileAfterSend(true);
                    }),

                EditAction::make()->slideOver(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}