<?php

namespace App\Filament\Resources\ReceivedInvoices\Actions;

use Filament\Actions\Action;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\Facades\Http;
use App\Filament\Resources\ReceivedInvoices\ReceivedInvoiceResource;
use App\Filament\Pages\ImportInvoice;
use Filament\Notifications\Notification;

class InvoiceSummaryAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'resumo_nota';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Ver Resumo')
            ->extraAttributes(['class' => 'hidden']) // Oculto na coluna, ativo no clique da linha
            ->modalHeading('Resumo da Nota Fiscal')
            ->modalDescription(
                fn($record) => $record && $record->status === 'pending'
                    ? 'A SEFAZ exige a "Ciência da Operação" para liberar a lista de produtos (XML completo). Abaixo estão os dados de cabeçalho disponíveis neste momento.'
                    : 'Detalhes da Nota Fiscal recebida.'
            )
            ->modalSubmitAction(false) // Mantemos desativado o "Salvar" padrão
            ->modalCancelActionLabel('Fechar e Voltar')

            ->extraModalFooterActions([

                // BOTÃO 1: MANIFESTAR (Ciência da Operação)
                Action::make('manifestar')
                    ->label('Manifestar Ciência')
                    ->icon('heroicon-m-check-badge')
                    ->color('info') // Padronizado com a cor da tabela
                    ->visible(fn($record) => $record && $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Manifestar Ciência da Operação')
                    ->modalDescription('Confirma a ciência desta nota? O sistema solicitará o XML completo à SEFAZ.')
                    ->action(function ($record) {
                        try {
                            // Lógica pescada da Tabela
                            ReceivedInvoiceResource::transmitirEventoSefaz($record->nfe_key, 210210, 'Ciencia da operacao');
                            $record->update(['status' => 'manifested']);

                            Notification::make()->title('Ciência Confirmada!')->success()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('Erro Técnico')->body($e->getMessage())->danger()->send();
                        }
                    }),

                // BOTÃO 2: REJEITAR (Desconhecimento da Operação)
                Action::make('rejeitar')
                    ->label('Desconhecer Operação')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->visible(fn($record) => $record && $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Desconhecer Operação')
                    ->modalDescription('Tem certeza que deseja informar à SEFAZ o desconhecimento desta nota? Esta ação é irreversível.')
                    ->action(function ($record) {
                        try {
                            // Lógica pescada da Tabela
                            ReceivedInvoiceResource::transmitirEventoSefaz($record->nfe_key, 210220, 'Desconhecimento da operacao');
                            $record->update(['status' => 'rejected']);

                            Notification::make()->title('Operação Rejeitada!')->success()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('Erro ao Rejeitar')->body($e->getMessage())->danger()->send();
                        }
                    }),

                // BOTÃO 3: PROCESSAR OU VISUALIZAR (Importar / Auditar)
                Action::make('processar')
                    ->label(fn($record) => $record->status === 'processed' ? 'Visualizar / Auditar' : 'Processar Entrada')
                    ->icon(fn($record) => $record->status === 'processed' ? 'heroicon-m-eye' : 'heroicon-m-arrow-path')
                    ->color(fn($record) => $record->status === 'processed' ? 'info' : 'success')
                    ->visible(fn($record) => $record && in_array($record->status, ['ready', 'manifested', 'processed']) && !empty($record->xml_content) && !str_contains($record->xml_content, 'resNFe'))
                    ->action(function ($record) {
                        return redirect()->to(ImportInvoice::getUrl(['invoice' => $record->id]));
                    }),
            ])

            ->infolist([
                Section::make('Dados Informados pela SEFAZ')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('nfe_key')
                            ->label('Chave de Acesso (44 Dígitos)')
                            ->columnSpan(1)
                            ->copyable()
                            ->extraAttributes(['class' => 'break-all']),

                        TextEntry::make('emitter_cnpj')->label('CNPJ do Emissor'),

                        TextEntry::make('emitter_name')->label('Razão Social (SEFAZ)'),

                        TextEntry::make('fantasy_name')
                            ->label('Nome Fantasia (BrasilAPI)')
                            ->state(function ($record) {
                                $cnpjLimpo = preg_replace('/[^0-9]/', '', (string) $record->emitter_cnpj);
                                if (empty($cnpjLimpo) || strlen($cnpjLimpo) !== 14) return 'Dados incompletos';

                                try {
                                    $response = Http::withoutVerifying()->timeout(5)->get("https://brasilapi.com.br/api/cnpj/v1/{$cnpjLimpo}");
                                    if ($response->successful()) {
                                        $data = $response->json();
                                        return !empty($data['nome_fantasia']) ? $data['nome_fantasia'] : ($data['razao_social'] ?? 'Sem Fantasia');
                                    }
                                    return "Não encontrado (Status: {$response->status()})";
                                } catch (\Exception $e) {
                                    return 'Falha na consulta externa';
                                }
                            }),

                        TextEntry::make('issue_date')->label('Data de Emissão')->dateTime('d/m/Y H:i'),

                        TextEntry::make('total_value')->label('Valor Total da Nota')->money('BRL'),
                    ])
            ]);
    }
}
