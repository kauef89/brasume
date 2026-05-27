<?php

namespace App\Filament\Pages\Settings\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Actions\Action;
use Filament\Schemas\Components\Actions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use App\Models\CompanySetting;
use Filament\Notifications\Notification;

class CompanySettingsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->statePath("data")->components([
            Section::make("Certificado Digital (A1)")
                ->description("O upload processa os dados da sua empresa automaticamente.")
                ->schema([
                    ViewField::make("certificate_path")
                        ->label("Arquivo do Certificado (.pfx)")
                        ->view("filament.forms.components.pfx-upload")
                        ->live()
                        ->visible(fn(Get $get) => empty($get("certificate_expiration")))
                        ->columnSpan(1),

                    TextInput::make("certificate_password")
                        ->label("Senha do Certificado")
                        ->password()
                        ->revealable()
                        ->visible(fn(Get $get) => empty($get("certificate_expiration")))
                        ->columnSpan(1),

                    Actions::make([
                        Action::make("processar_certificado")
                            ->label("Processar e salvar")
                            ->icon("heroicon-m-sparkles")
                            ->color("primary")
                            ->action("submit"), // Chama o método do Maestro
                    ])
                        ->visible(fn(Get $get) => empty($get("certificate_expiration")))
                        ->extraAttributes(["class" => "mt-7"])
                        ->columnSpan(1),

                    TextInput::make("certificate_expiration")
                        ->label("Válido até")
                        ->readOnly()
                        ->dehydrated(false) // 🚀 A MÁGICA QUE RESOLVE O ERRO AQUI!
                        ->formatStateUsing(fn($state) => $state ? \Carbon\Carbon::parse($state)->format("d/m/Y") : "Não identificado")
                        ->visible(fn(Get $get) => !empty($get("certificate_expiration")))
                        ->helperText(function (?\App\Models\CompanySetting $record) {
                            if (!$record || !$record->certificate_expiration) return null;
                            $expiryDate = \Carbon\Carbon::parse($record->certificate_expiration);
                            $now = now();

                            if ($now->greaterThan($expiryDate)) {
                                return new HtmlString('<span class="text-red-500 font-medium">⚠️ Certificado expirado!</span>');
                            }

                            $diff = $now->diff($expiryDate);
                            $parts = [];
                            if ($diff->y > 0) $parts[] = $diff->y . ($diff->y == 1 ? " ano" : " anos");
                            if ($diff->m > 0) $parts[] = $diff->m . ($diff->m == 1 ? " mês" : " meses");
                            if ($diff->d > 0) $parts[] = $diff->d . ($diff->d == 1 ? " dia" : " dias");

                            $countdown = match(count($parts)) {
                                3 => "{$parts[0]}, {$parts[1]} e {$parts[2]}",
                                2 => "{$parts[0]} e {$parts[1]}",
                                1 => $parts[0],
                                default => "Expira hoje!",
                            };

                            return "Faltam {$countdown} para expirar.";
                        })
                        ->columnSpan(3),

                    Actions::make([
                        Action::make("download_certificate")
                            ->label("Baixar")
                            ->icon("heroicon-o-arrow-down-tray")
                            ->color("success")
                            ->action(function () {
                                $setting = CompanySetting::first();
                                if ($setting && $setting->certificate_path && Storage::exists($setting->certificate_path)) {
                                    return Storage::download($setting->certificate_path, "certificado_atual.pfx");
                                }
                                Notification::make()->title("Erro")->body("Arquivo original não encontrado.")->danger()->send();
                            }),

                        Action::make("clear_certificate")
                            ->label("Trocar")
                            ->icon("heroicon-o-arrow-path")
                            ->color("warning")
                            ->requiresConfirmation()
                            ->action(function (Set $set) {
                                $set("certificate_path", null);
                                $set("certificate_expiration", null);
                                $set("certificate_password", null);
                            }),

                        Action::make("delete_certificate_permanently")
                            ->label("Remover")
                            ->icon("heroicon-o-trash")
                            ->color("danger")
                            ->requiresConfirmation()
                            ->action(function (Set $set) {
                                $setting = CompanySetting::first();
                                if ($setting) {
                                    if ($setting->certificate_path && Storage::exists($setting->certificate_path)) {
                                        Storage::delete($setting->certificate_path);
                                    }
                                    $setting->update(["certificate_path" => null, "certificate_password" => null, "certificate_expiration" => null]);
                                }
                                $set("certificate_path", null);
                                $set("certificate_expiration", null);
                                $set("certificate_password", null);
                                Notification::make()->title("Certificado Removido!")->success()->send();
                            }),
                    ])
                        ->visible(fn(Get $get) => !empty($get("certificate_expiration")))
                        ->columnSpan(3),
                ])->columns(3),

            Section::make("Identificação e Endereço")->schema([
                TextInput::make("cnpj")->label("CNPJ")->mask("99.999.999/9999-99")->readOnly(),
                TextInput::make("ie")->label("Inscrição Estadual (IE)")->numeric(),
                TextInput::make("company_name")->label("Razão Social")->readOnly(),
                TextInput::make("zip_code")->label("CEP"),
                TextInput::make("street")->label("Rua / Logradouro"),
                TextInput::make("number")->label("Número"),
                TextInput::make("neighborhood")->label("Bairro"),
                TextInput::make("city")->label("Cidade"),
                TextInput::make("state")->label("UF")->maxLength(2),
            ])->columns(5),

            Section::make("Enquadramento Fiscal e Impostos")->schema([
                Select::make("tax_regime")->label("Regime Tributário")->options([
                    "mei" => "MEI", "simples" => "Simples Nacional", "presumido" => "Lucro Presumido", "real" => "Lucro Real"
                ])->default("mei")->live()->required()->columnSpan(1),
                TextInput::make("simples_rate")->label("Alíquota do Simples (%)")->numeric()->default(0.0)->suffix("%")->visible(fn(Get $get) => $get("tax_regime") === "simples")->columnSpan(1),
                TextInput::make("icms_rate")->label("Alíquota ICMS (%)")->numeric()->default(0.0)->suffix("%")->hidden()->columnSpan(1),
            ])->columns(3),

            Section::make("Configurações de Emissão (NFC-e)")->schema([
                Select::make("environment")->label("Ambiente da Sefaz")->options(["2" => "Homologação", "1" => "Produção"])->default("2")->required(),
                TextInput::make("csc_id")->label("ID do CSC (Token)"),
                TextInput::make("csc_code")->label("Código CSC")->password()->revealable(),
            ])->columns(4),

            Section::make("Identidade Visual")->schema([
                FileUpload::make("logo_path")->label("Logotipo Horizontal")->directory("company_logos")->columnSpan(1),
                FileUpload::make("icon_path")->label("Ícone Quadrado")->directory("company_icons")->columnSpan(1),
            ])->columns(2),

            Section::make("Regras de Atendimento e Taxas")->schema([
                Toggle::make("has_service_tax")->label("Cobrar Taxa de Serviço")->inline(false)->live()->columnSpan(1),
                TextInput::make("service_tax_percentage")->label("Porcentagem (%)")->numeric()->default(10.0)->suffix("%")->visible(fn(Get $get) => $get("has_service_tax"))->required(fn(Get $get) => $get("has_service_tax"))->columnSpan(1),
            ])->columns(2),

            Section::make("Controle de Caixa (Turnos / PDV)")->schema([
                Select::make('cash_opening_mode')->label('Modo de Abertura / Fechamento')->options([
                    'total' => 'Valor Fechado',
                    'detailed' => 'Contagem Cega (Contar notas e moedas)'
                ])->default('total')->required()->columnSpan(1),
                Select::make('carry_over_mode')->label('Fundo de Troco (Saldo do turno anterior)')->options([
                    'none' => 'Zerar o caixa',
                    'coins_only' => 'Importar apenas Moedas',
                    'total' => 'Importar todo o dinheiro'
                ])->default('none')->required()->columnSpan(1),
            ])->columns(2),
        ]);
    }
}
