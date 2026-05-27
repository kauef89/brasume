<?php

namespace App\Filament\Resources\ReceivedInvoices\Actions;

use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\HtmlString;

class ViewDanfeAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'view_danfe';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Ver DANFE')
            ->icon('heroicon-o-document-text')
            ->iconButton()
            ->tooltip('Visualizar DANFE (PDF)')
            ->color('gray')
            ->visible(fn ($record) => !empty($record->xml_content) && !str_contains($record->xml_content, 'resNFe'))
            ->modalHeading('Documento Auxiliar da Nota Fiscal (DANFE)')
            ->modalWidth('7xl')
            ->modalSubmitAction(false)
            ->infolist([
                TextEntry::make('xml_content')
                    ->label('')
                    ->formatStateUsing(function ($state) {
                        try {
                            $danfe = new \NFePHP\DA\NFe\Danfe($state);
                            $pdf = $danfe->render();
                            $base64 = base64_encode($pdf);
                            return new HtmlString(
                                "<iframe src='data:application/pdf;base64,{$base64}#toolbar=0' width='100%' height='700px' style='border: none; border-radius: 8px;'></iframe>"
                            );
                        } catch (\Exception $e) {
                            return "Erro ao gerar PDF.";
                        }
                    })
            ]);
    }
}
