<?php

namespace App\Filament\Resources\CompliancePermits\Schemas;

use App\Enums\CompliancePermitType;
use App\Models\Supplier;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

class CompliancePermitForm
{
    public static function getFormSchema(): array
    {
        return [
            Section::make('Identificação da Licença / Laudo')
                ->description('Defina o tipo de obrigação sanitária ou legal e o órgão responsável.')
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('name')
                            ->label('Nome da Obrigação')
                            ->placeholder('Ex: Alvará Sanitário 2026')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),

                        Select::make('type')
                            ->label('Categoria')
                            ->options(CompliancePermitType::class)
                            ->required()
                            ->columnSpan(1),
                    ]),

                    Grid::make(3)->schema([
                        TextInput::make('issuer_name')
                            ->label('Órgão Emissor / Empresa')
                            ->placeholder('Ex: Vigilância Sanitária Municipal')
                            ->maxLength(255),

                        Select::make('supplier_id')
                            ->label('Favorecido (Cadastro de Fornecedor)')
                            ->options(Supplier::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->helperText('Usado para emitir o contas a pagar da taxa de renovação.'),

                        TextInput::make('document_number')
                            ->label('Nº do Documento / Processo')
                            ->placeholder('Ex: 2026/04918-VISA')
                            ->maxLength(100),
                    ]),
                ]),

            Section::make('Vigência & Alertas de Renovação')
                ->description('Prazos legais para evitar interdição ou multas sanitárias.')
                ->schema([
                    Grid::make(3)->schema([
                        DatePicker::make('issued_at')
                            ->label('Data de Emissão')
                            ->displayFormat('d/m/Y'),

                        DatePicker::make('expires_at')
                            ->label('Data de Vencimento Legal')
                            ->displayFormat('d/m/Y')
                            ->required(),

                        TextInput::make('alert_days_before')
                            ->label('Avisar com Antecedência (Dias)')
                            ->numeric()
                            ->default(30)
                            ->suffix('dias')
                            ->required()
                            ->helperText('Prazo necessário para agendar vistoria ou coletar laudos.'),
                    ]),

                    Grid::make(2)->schema([
                        TextInput::make('estimated_cost')
                            ->label('Custo Estimado da Renovação (Taxa)')
                            ->numeric()
                            ->prefix('R$')
                            ->placeholder('0,00')
                            ->helperText('Valor provisionado no fluxo de caixa futuro.'),

                        Toggle::make('is_active')
                            ->label('Licença Ativa / Monitorada')
                            ->default(true)
                            ->inline(false),
                    ]),
                ]),

            Section::make('Arquivo Digital & Vistoria')
                ->description('Mantenha o PDF ou foto do alvará acessível para fiscalizações surpresa.')
                ->schema([
                    FileUpload::make('document_path')
                        ->label('Anexo do Alvará / Laudo Técnico')
                        ->directory('compliance-permits')
                        ->acceptedFileTypes(['application/pdf', 'image/*'])
                        ->openable()
                        ->downloadable()
                        ->maxSize(10240),

                    Textarea::make('notes')
                        ->label('Exigências da Fiscalização / Observações')
                        ->placeholder('Ex: Exigida troca do termômetro da geladeira na última vistoria.')
                        ->rows(3),
                ]),
        ];
    }
}