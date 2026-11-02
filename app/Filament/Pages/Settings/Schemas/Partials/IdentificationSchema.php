<?php

namespace App\Filament\Pages\Settings\Schemas\Partials;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Actions\Action;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Filament\Notifications\Notification;
use App\Models\CompanySetting;

class IdentificationSchema
{
    public static function make(): array
    {
        return [
            Section::make("Instruções e Boas Práticas")
                ->icon("heroicon-o-information-circle")
                ->schema([
                    Placeholder::make('instrucoes')
                        ->hiddenLabel()
                        ->content(new HtmlString('
                            <div class="flex flex-col gap-2 p-4 bg-info-50 text-info-700 dark:bg-info-900/30 dark:text-info-400 rounded-xl border border-info-200 dark:border-info-800 text-sm">
                                <div class="flex items-center gap-2 font-bold mb-1">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Antes de alterar os dados, preste atenção:
                                </div>
                                <ul class="list-disc list-inside space-y-1.5 ml-1">
                                    <li><b>Certificado Digital:</b> Envie apenas arquivos <code>.pfx</code>. Informe a senha <b>antes</b> ou <b>durante</b> o envio do arquivo.</li>
                                    <li><b>Travamento Fiscal:</b> Se o certificado for validado, o <b>CNPJ e Razão Social serão bloqueados</b>. Para corrigi-los, remova o certificado primeiro.</li>
                                    <li><b>Autopreenchimento de Endereço:</b> Digite o CEP e aguarde 1 segundo. O sistema preencherá a Rua, Bairro e os <b>códigos do IBGE</b> obrigatórios para a Sefaz.</li>
                                </ul>
                            </div>
                        ')),
                ]),

            Section::make("Identidade e Certificado")
                ->schema([
                    Grid::make(6)->schema([
                        TextInput::make("trade_name")->label("Nome Fantasia")->required()->columnSpan(2),
                        TextInput::make("app_name")->label("Nome do App")->helperText("Pode ser diferente do Fantasia")->required()->columnSpan(2),
                        TextInput::make("support_whatsapp")->label("WhatsApp de Suporte")->helperText("Apenas números com DDD")->mask("(99) 99999-9999")->stripCharacters(['(', ')', '-', ' '])->columnSpan(1),
                        TextInput::make("whatsapp_handle")->label("Usuário do WhatsApp (@)")->placeholder("Ex: pastelarianativa")->helperText("Se preenchido, os botões usarão este @ em vez do número.")->prefix('@')->columnSpan(1),
                        Hidden::make("certificate_path"),

                        Grid::make(2)->schema([
                            FileUpload::make("new_certificate")
                                ->label("Atualizar Certificado (.pfx)")
                                ->storeFiles(false)
                                ->acceptedFileTypes(['application/x-pkcs12', 'application/octet-stream'])
                                ->helperText('Ao salvar, os dados serão extraídos automaticamente.')
                                ->required(fn (Get $get) => $get('is_fiscal_emitter') && blank($get('certificate_expiration')))
                                ->columnSpan(1),

                            TextInput::make("certificate_password")
                                ->label("Senha do Certificado")
                                ->password()
                                ->revealable()
                                ->dehydrated(fn($state) => filled($state))
                                ->helperText('Obrigatório ao enviar um novo arquivo.')
                                ->required(fn (Get $get) => filled($get('new_certificate')) || ($get('is_fiscal_emitter') && blank($get('certificate_expiration'))))
                                ->columnSpan(1),

                            TextInput::make("certificate_expiration")
                                ->label("Certificado Válido Até")
                                ->readOnly()
                                ->dehydrated(false)
                                ->formatStateUsing(function ($state) {
                                    if (empty($state) || $state === 'Não identificado') return null;
                                    try { return \Carbon\Carbon::parse($state)->format('d/m/Y'); } catch (\Exception $e) { return null; }
                                })
                                ->visible(fn(Get $get) => filled($get("certificate_expiration")))
                                ->helperText(function (?CompanySetting $record, Get $get) {
                                    $expState = $get("certificate_expiration");
                                    if (empty($expState) || $expState === 'Não identificado') return null;
                                    try {
                                        $expiryDate = str_contains($expState, '/') ? \Carbon\Carbon::createFromFormat('d/m/Y', $expState)->startOfDay() : \Carbon\Carbon::parse($expState)->startOfDay();
                                        $today = now()->startOfDay();
                                        if ($today->greaterThan($expiryDate)) return new HtmlString('<span class="text-red-500 font-medium">⚠️ Certificado expirado!</span>');
                                        $days = (int) $today->diffInDays($expiryDate);
                                        if ($days <= 60) return new HtmlString('<span class="text-red-500 font-medium">⚠️ Atenção: Faltam apenas ' . $days . ' dias para expirar!</span>');
                                        return new HtmlString('<span class="text-success-600 font-medium">Faltam ' . $days . ' dias para expirar.</span>');
                                    } catch (\Exception $e) { return null; }
                                })
                                ->columnSpan(1),

                            Actions::make([
                                Action::make("download_certificate")
                                    ->label("Baixar Cópia")
                                    ->icon("heroicon-o-arrow-down-tray")
                                    ->color("success")
                                    ->action(function () {
                                        $setting = CompanySetting::getCached();
                                        if ($setting && $setting->certificate_path && Storage::disk('local')->exists($setting->certificate_path)) {
                                            return Storage::disk('local')->download($setting->certificate_path, "certificado_atual.pfx");
                                        }
                                    }),

                                Action::make("clear_certificate")
                                    ->label("Remover do Cofre")
                                    ->icon("heroicon-o-trash")
                                    ->color("danger")
                                    ->requiresConfirmation()
                                    ->action(function (Set $set) {
                                        $setting = CompanySetting::first();
                                        if ($setting) {
                                            if ($setting->certificate_path && Storage::disk('local')->exists($setting->certificate_path)) Storage::disk('local')->delete($setting->certificate_path);
                                            $setting->update(['certificate_path' => null, 'certificate_expiration' => null, 'certificate_password' => null]);
                                        }
                                        $set("new_certificate", null); $set("certificate_path", null); $set("certificate_expiration", null); $set("certificate_password", null);
                                        Notification::make()->title("Certificado Removido")->success()->send();
                                    }),
                            ])->visible(fn(Get $get) => filled($get("certificate_expiration")))->columnSpan(1),
                        ])->columnSpanFull(),
                    ]),
                ]),

            Section::make("Identificação Fiscal e Endereço")
                ->description("Se houver um Certificado Digital ativo, Razão Social e CNPJ serão bloqueados para evitar rejeição da Sefaz. O endereço utiliza a base oficial do IBGE.")
                ->schema([
                    Grid::make(4)->schema([
                        TextInput::make("company_name")->label("Razão Social")->readOnly(fn(Get $get) => filled($get('certificate_expiration')))->columnSpan(2),
                        TextInput::make("cnpj")->label("CNPJ")->mask("99.999.999/9999-99")->readOnly(fn(Get $get) => filled($get('certificate_expiration')))->columnSpan(1),
                        TextInput::make("ie")->label("Inscrição Estadual (IE)")->numeric()->required(fn(Get $get) => $get('is_fiscal_emitter'))->helperText("Obrigatório para emissão de notas.")->columnSpan(1),

                        TextInput::make("zip_code")
                            ->label("CEP")
                            ->columnSpan(1)
                            ->mask("99999-999")
                            ->live(debounce: 600)
                            ->afterStateUpdated(function (?string $state, Set $set) {
                                if (!$state) return;
                                $cleanCep = preg_replace('/[^0-9]/', '', $state);
                                if (strlen($cleanCep) !== 8) return;

                                // 🚀 1. ViaCEP (fonte primária com código IBGE garantido)
                                try {
                                    $viaCep = Http::timeout(4)->get("https://viacep.com.br/ws/{$cleanCep}/json/");
                                    if ($viaCep->successful() && empty($viaCep->json('erro'))) {
                                        $data = $viaCep->json();
                                        if (!empty($data['logradouro'])) $set('street', $data['logradouro']);
                                        if (!empty($data['bairro'])) $set('neighborhood', $data['bairro']);
                                        if (!empty($data['uf'])) $set('state', strtoupper($data['uf']));
                                        if (!empty($data['ibge'])) $set('ibge_code', (string) $data['ibge']);
                                        if (!empty($data['localidade'])) $set('city', $data['localidade']);
                                        return;
                                    }
                                } catch (\Throwable $e) {}

                                // 🚀 2. Fallback BrasilAPI
                                try {
                                    $brasilApi = Http::timeout(4)->get("https://brasilapi.com.br/api/cep/v2/{$cleanCep}");
                                    if ($brasilApi->successful()) {
                                        $bData = $brasilApi->json();
                                        if (!empty($bData['street'])) $set('street', $bData['street']);
                                        if (!empty($bData['neighborhood'])) $set('neighborhood', $bData['neighborhood']);
                                        if (!empty($bData['state'])) $set('state', strtoupper($bData['state']));
                                        if (!empty($bData['city'])) $set('city', $bData['city']);
                                    }
                                } catch (\Throwable $e) {}
                            }),

                        TextInput::make("street")->label("Rua / Logradouro")->columnSpan(2),
                        TextInput::make("number")->label("Número")->columnSpan(1),
                        TextInput::make("neighborhood")->label("Bairro")->columnSpan(2),

                        Select::make("state")
                            ->label("Estado (UF)")
                            ->columnSpan(1)
                            ->searchable()
                            ->options(function () {
                                return Cache::remember('ufs_lista_oficial', 604800, function () {
                                    return [
                                        'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas',
                                        'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo',
                                        'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul',
                                        'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
                                        'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
                                        'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina',
                                        'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins'
                                    ];
                                });
                            })
                            ->live()
                            ->afterStateUpdated(fn(Set $set) => $set('ibge_code', null)),

                        Select::make("ibge_code")
                            ->label("Cidade")
                            ->columnSpan(1)
                            ->searchable()
                            ->required(fn(Get $get) => (bool) $get('is_fiscal_emitter'))
                            ->options(function (Get $get) {
                                $uf = $get('state');
                                if (!$uf) return [];

                                $cacheKey = "ibge_cities_v3_{$uf}";
                                $cached = Cache::get($cacheKey);
                                if (!empty($cached) && is_array($cached)) {
                                    return $cached;
                                }

                                // 🏛️ 1. API Oficial do IBGE (Direta, sem rate-limit e oficial do governo)
                                try {
                                    $res = Http::timeout(4)->get("https://servicodados.ibge.gov.br/api/v1/localidades/estados/{$uf}/municipios");
                                    if ($res->successful() && is_array($res->json())) {
                                        $cities = collect($res->json())
                                            ->mapWithKeys(fn($c) => [(string) $c['id'] => (string) $c['nome']])
                                            ->sort()
                                            ->toArray();

                                        if (!empty($cities)) {
                                            Cache::put($cacheKey, $cities, 604800); // 7 dias
                                            return $cities;
                                        }
                                    }
                                } catch (\Throwable $e) {}

                                // 🇧🇷 2. Fallback BrasilAPI
                                try {
                                    $res = Http::timeout(4)->get("https://brasilapi.com.br/api/ibge/municipios/v1/{$uf}");
                                    if ($res->successful() && is_array($res->json())) {
                                        $cities = collect($res->json())
                                            ->mapWithKeys(fn($c) => [(string) $c['codigo_ibge'] => (string) $c['nome']])
                                            ->sort()
                                            ->toArray();

                                        if (!empty($cities)) {
                                            Cache::put($cacheKey, $cities, 604800);
                                            return $cities;
                                        }
                                    }
                                } catch (\Throwable $e) {}

                                return [];
                            })
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                if (!$state) return;
                                $uf = $get('state');
                                $cidades = Cache::get("ibge_cities_v3_{$uf}", []);
                                if (isset($cidades[(string)$state])) {
                                    $set('city', $cidades[(string)$state]);
                                }
                            }),

                        Hidden::make("city")->dehydrated(true),
                    ])
                ]),
        ];
    }
}
