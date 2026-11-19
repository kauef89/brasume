<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\HtmlString;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function getTitle(): string
    {
        return "Pedido #{$this->record->id}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open_customer')
                ->label('Ficha do Cliente')
                ->icon('heroicon-o-user')
                ->color('gray')
                ->visible(fn () => filled($this->record->customer_id) && class_exists(CustomerResource::class))
                ->url(fn () => CustomerResource::getUrl('edit', ['record' => $this->record->customer_id]))
                ->openUrlInNewTab(),

            Action::make('download_xml')
                ->label('Baixar XML')
                ->icon('heroicon-o-code-bracket')
                ->color('info')
                ->visible(fn () => $this->record->fiscalDocument && filled($this->record->fiscalDocument->xml_content))
                ->action(function () {
                    $doc = $this->record->fiscalDocument;
                    return response()->streamDownload(function () use ($doc) {
                        echo $doc->xml_content;
                    }, "{$doc->access_key}.xml");
                }),

            Action::make('view_danfe')
                ->label('Ver Cupom (DANFE)')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->visible(fn () => $this->record->fiscalDocument && filled($this->record->fiscalDocument->xml_content))
                ->modalHeading(fn () => "NFC-e Autorizada - Pedido #{$this->record->id}")
                ->modalWidth('2xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->modalContent(function () {
                    try {
                        $danfce = new \NFePHP\DA\NFe\Danfce($this->record->fiscalDocument->xml_content);
                        $danfce->creditsIntegratorFooter('Gerado por Brasume ERP');
                        $pdf = $danfce->render();
                        $base64 = base64_encode($pdf);
                        return new HtmlString("<iframe src='data:application/pdf;base64,{$base64}#toolbar=0' width='100%' height='650px' style='border: none; border-radius: 8px;'></iframe>");
                    } catch (\Throwable $e) {
                        return new HtmlString("<div class='p-4 text-red-500'>Erro ao gerar PDF do Cupom: {$e->getMessage()}</div>");
                    }
                }),
        ];
    }
}