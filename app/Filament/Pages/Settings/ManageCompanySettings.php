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

// Importamos a classe que isola o design!
use App\Filament\Pages\Settings\Schemas\CompanySettingsForm;

class ManageCompanySettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = "heroicon-o-cog-6-tooth";
    protected static string|\UnitEnum|null $navigationGroup = "Configurações";
    protected static ?string $title = "Dados da Empresa e Fiscal";
    protected string $view = "filament.pages.manage-company-settings";

    public ?array $data = [];

    public function mount(): void
    {
        $setting = CompanySetting::first();
        $this->form->fill($setting ? $setting->toArray() : []);
    }

    public function form(Schema $schema): Schema
    {
        return CompanySettingsForm::configure($schema);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make("save")->label("Salvar")->color("primary")->action("submit"),
        ];
    }

    public function submit()
    {
        $data = $this->form->getState();
        $setting = CompanySetting::first() ?? new CompanySetting();

        $hasNewUpload = !empty($data["certificate_path"]) && str_contains($data["certificate_path"], "base64,");

        if ($hasNewUpload && empty($data["certificate_password"])) {
            Notification::make()->title("Senha Obrigatória")->body("Por favor, informe a senha do certificado recém-anexado.")->warning()->send();
            return;
        }

        if ($hasNewUpload) {
            $pfxContent = base64_decode(explode("base64,", $data["certificate_path"])[1]);
            $certs = [];

            if (openssl_pkcs12_read($pfxContent, $certs, $data["certificate_password"])) {
                $fileName = "certificates/cert_" . time() . ".pfx";
                Storage::put($fileName, $pfxContent);

                $setting->fill($data);
                $setting->certificate_path = $fileName;
                $setting->certificate_password = $data["certificate_password"];

                $certData = openssl_x509_parse($certs["cert"]);
                if (isset($certData["validTo_time_t"])) {
                    $setting->certificate_expiration = date("Y-m-d", $certData["validTo_time_t"]);
                }

                $commonName = $certData["subject"]["CN"] ?? "";
                preg_match("/[0-9]{14}/", $commonName, $matches);
                $cnpjExtraido = $matches[0] ?? null;

                if ($cnpjExtraido && empty($setting->company_name)) {
                    $response = Http::get("https://brasilapi.com.br/api/cnpj/v1/{$cnpjExtraido}");
                    if ($response->successful()) {
                        $apiData = $response->json();
                        $setting->cnpj = $cnpjExtraido;
                        $setting->company_name = $apiData["razao_social"] ?? null;
                        $setting->zip_code = $apiData["cep"] ?? null;
                        $setting->street = $apiData["logradouro"] ?? null;
                        $setting->number = $apiData["numero"] ?? null;
                        $setting->neighborhood = $apiData["bairro"] ?? null;
                        $setting->city = $apiData["municipio"] ?? null;
                        $setting->state = $apiData["uf"] ?? null;
                    }
                }

                $setting->save();
                $this->form->fill($setting->toArray());

                Notification::make()->title("Certificado Processado!")->body("Dados extraídos e salvos com sucesso.")->success()->send();
                return;
            } else {
                Notification::make()->title("Senha Incorreta")->body("Não foi possível ler o arquivo. Verifique a senha e tente novamente.")->danger()->send();
                return;
            }
        }

        $setting->fill($data);
        $setting->save();
        Notification::make()->title("Salvo!")->body("Suas configurações foram atualizadas.")->success()->send();
    }
}
