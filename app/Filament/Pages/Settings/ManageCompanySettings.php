<?php

namespace App\Filament\Pages\Settings;

use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use App\Models\CompanySetting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Filament\Actions\Action;
use App\Filament\Pages\Settings\Schemas\CompanySettingsForm;

class ManageCompanySettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = "heroicon-o-cog-6-tooth";
    protected static string|\UnitEnum|null $navigationGroup = "Configurações";
    protected static ?string $title = "Geral";
    protected string $view = "filament.pages.manage-company-settings";

    public ?array $data = [];

    public function mount(): void
    {
        // 🚀 CORREÇÃO: O Admin lê SEMPRE direto do banco de dados!
        $setting = CompanySetting::first();
        $this->form->fill($setting ? $setting->toArray() : []);
    }

    public function form(Schema $schema): Schema
    {
        return CompanySettingsForm::configure($schema)
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make("save")
                ->label("Salvar Tudo")
                ->color("primary")
                ->action("submit"),
        ];
    }

    public function submit()
    {
        $data = $this->form->getState();
        // 🚀 CORREÇÃO: Pega a instância fresca do banco para atualizar
        $setting = CompanySetting::first() ?? new CompanySetting();

        // 1. PROCESSA NOVO CERTIFICADO NO ATO DE SALVAR
        if (!empty($data["new_certificate"])) {
            if (empty($data["certificate_password"])) {
                Notification::make()->title("Senha Obrigatória")->body("Por favor, informe a senha do certificado recém-anexado.")->warning()->send();
                return;
            }

            $fileData = $data["new_certificate"];
            $uploadedFile = is_array($fileData) ? array_values($fileData)[0] : $fileData;

            if ($uploadedFile instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                $pfxContent = $uploadedFile->get();
                $certs = [];

                if (openssl_pkcs12_read($pfxContent, $certs, $data["certificate_password"])) {

                    if ($setting->certificate_path && Storage::disk('local')->exists($setting->certificate_path)) {
                        Storage::disk('local')->delete($setting->certificate_path);
                    }

                    $fileName = "certificates/cert_" . time() . ".pfx";
                    Storage::disk('local')->put($fileName, $pfxContent);
                    $data["certificate_path"] = $fileName;

                    $certPem = $certs["cert"] ?? null;
                    if (empty($certPem) && !empty($certs["extracerts"])) {
                        $certPem = $certs["extracerts"][0];
                    }

                    if ($certPem) {
                        $certData = openssl_x509_parse($certPem);

                        if (isset($certData["validTo_time_t"])) {
                            $data["certificate_expiration"] = date("Y-m-d", $certData["validTo_time_t"]);
                        }

                        $commonName = $certData["subject"]["CN"] ?? "";
                        preg_match("/[0-9]{14}/", $commonName, $matches);
                        $cnpjExtraido = $matches[0] ?? null;

                        if ($cnpjExtraido) {
                            $data["cnpj"] = $cnpjExtraido;

                            if (empty($data["company_name"]) || empty($data["zip_code"])) {
                                $response = Http::get("https://brasilapi.com.br/api/cnpj/v1/{$cnpjExtraido}");

                                if ($response->successful()) {
                                    $apiData = $response->json();

                                    if (empty($data["company_name"])) $data["company_name"] = $apiData["razao_social"] ?? null;
                                    if (empty($data["trade_name"])) $data["trade_name"] = $apiData["nome_fantasia"] ?? $apiData["razao_social"] ?? null;

                                    if (empty($data["zip_code"]) && !empty($apiData["cep"])) {
                                        $data["zip_code"] = $apiData["cep"];
                                        $cepClean = preg_replace('/[^0-9]/', '', $apiData["cep"]);
                                        $cepResp = Http::get("https://brasilapi.com.br/api/cep/v2/{$cepClean}");

                                        if ($cepResp->successful()) {
                                            $cepData = $cepResp->json();
                                            if (empty($data["street"])) $data["street"] = $cepData["street"] ?? null;
                                            if (empty($data["neighborhood"])) $data["neighborhood"] = $cepData["neighborhood"] ?? null;
                                            if (empty($data["state"])) $data["state"] = $cepData["state"] ?? null;
                                            if (empty($data["ibge_code"])) $data["ibge_code"] = $cepData["ibge"] ?? null;
                                            if (empty($data["city"])) $data["city"] = $cepData["city"] ?? null;
                                        }
                                    }
                                }
                            }
                        }
                    }
                    Notification::make()->title("Certificado Lido!")->body("Os dados foram extraídos e validados.")->success()->send();
                } else {
                    Notification::make()->title("Senha Incorreta")->body("Não foi possível ler o arquivo. Verifique a senha informada.")->danger()->send();
                    return;
                }
            }
        }

        unset($data["new_certificate"]);

        if (empty($data["certificate_password"])) {
            unset($data["certificate_password"]);
        }

        $setting->fill($data);
        $setting->save(); // 🚀 Isso vai acionar o boot() do model e limpar o cache para o Vue!

        Notification::make()->title("Salvo!")->body("As configurações foram gravadas com sucesso.")->success()->send();

        return $this->redirect(static::getUrl());
    }
}
