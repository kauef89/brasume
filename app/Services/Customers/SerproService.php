<?php

namespace App\Services\Customers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\CompanySetting;
use Exception;

class SerproService
{
    private $baseUrl = 'https://gateway.apiserpro.serpro.gov.br';

    public function consultCpf(string $cpf): array
    {
        $settings = CompanySetting::getCached();

        if (empty($settings->serpro_client_id) || empty($settings->serpro_client_secret)) {
            Log::error("🛸 [SERPRO] Falha: Credenciais ausentes no banco de dados.");
            throw new Exception("As credenciais da Serpro não estão configuradas no painel Administrativo.", 500);
        }

        $cleanCpf = preg_replace('/[^0-9]/', '', $cpf);
        Log::info("🛸 [SERPRO] Iniciando consulta (v2) para o CPF: {$cleanCpf}");

        // 🚀 Bypass de testes ajustado para o formato com barras
        if ($cleanCpf === '00000000000') {
            return [
                'nome' => 'Usuário Teste Nativa',
                'nascimento' => '01/01/2000',
            ];
        }

        $token = $this->getAccessToken($settings->serpro_client_id, $settings->serpro_client_secret);

        $response = Http::withToken($token)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("{$this->baseUrl}/consulta-cpf-df/v2/cpf/{$cleanCpf}");

        $status = $response->status();

        if ($status !== 200 && $status !== 206) {
            Log::error("🛸 [SERPRO] Falha na consulta do CPF. Status HTTP: {$status} | Corpo: {$response->body()}");
            throw new Exception("CPF protegido por LGPD ou dados não encontrados.", 403);
        }

        $data = $response->json();
        Log::info("🛸 [SERPRO] Consulta (v2) do CPF realizada com sucesso.");

        // 🚀 O SEGREDO: A v2 retorna DDMMYYYY contínuo (Ex: 15051990).
        // Vamos formatar para DD/MM/YYYY para agradar a sua API Controller.
        $dob = $data['nascimento'] ?? '';
        $dobFmt = $dob;

        if (strlen($dob) === 8 && is_numeric($dob)) {
            $dobFmt = substr($dob, 0, 2) . '/' . substr($dob, 2, 2) . '/' . substr($dob, 4, 4);
        }

        $name = !empty($data['nomeSocial']) ? $data['nomeSocial'] : ($data['nome'] ?? '');

        return [
            'nome' => ucwords(strtolower($name)),
            'nascimento' => $dobFmt
        ];
    }

    private function getAccessToken($clientId, $clientSecret): string
    {
        Log::info("🛸 [SERPRO] Solicitando novo token de acesso...");

        $response = Http::withBasicAuth($clientId, $clientSecret)
            ->asForm()
            ->post("{$this->baseUrl}/token", [
                'grant_type' => 'client_credentials',
            ]);

        if ($response->failed()) {
            Log::error("🛸 [SERPRO] Falha na geração do token. Status HTTP: {$response->status()} | Corpo: {$response->body()}");
            throw new Exception("Falha ao autenticar na Serpro. Verifique as chaves.");
        }

        Log::info("🛸 [SERPRO] Token gerado com sucesso.");
        return $response->json('access_token');
    }
}
