<?php

namespace App\Filament\Resources\ReceivedInvoices;

use App\Filament\Resources\ReceivedInvoices\Pages;
use App\Models\ReceivedInvoice;
use App\Models\CompanySetting;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use NFePHP\NFe\Tools;
use NFePHP\Common\Certificate;

// Importa nossa Tabela Isolada
use App\Filament\Resources\ReceivedInvoices\Tables\ReceivedInvoicesTable;

class ReceivedInvoiceResource extends Resource
{
    protected static ?string $model = ReceivedInvoice::class;
    protected static string | \UnitEnum | null $navigationGroup = 'Estoque & Compras';
    protected static ?string $title = 'Notas de Compra';
    protected static ?string $modelLabel = 'Nota de Compra';
    protected static ?string $pluralModelLabel = 'Notas de Compra';

    // =========================================================
    // BADGES NO MENU DO RECURSO (Círculos de Aviso)
    // =========================================================
    public static function getNavigationBadge(): ?string
    {
        try {
            $pending = static::getModel()::where('status', 'pending')->count();
            if ($pending > 0) return (string) $pending;

            $processing = static::getModel()::whereIn('status', ['manifested', 'ready'])->count();
            if ($processing > 0) return (string) $processing;

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function getNavigationBadgeColor(): string | array | null
    {
        try {
            $pending = static::getModel()::where('status', 'pending')->count();
            if ($pending > 0) return 'danger'; // Vermelho para ação urgente

            $processing = static::getModel()::whereIn('status', ['manifested', 'ready'])->count();
            if ($processing > 0) return 'warning'; // Âmbar para notas aguardando processamento

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    // =========================================================
    // MOTOR CENTRAL DE EVENTOS DA SEFAZ
    // =========================================================
    public static function transmitirEventoSefaz($chave, $tpEvento, $justificativa = '')
    {
        $setting = CompanySetting::getCached();
        if (!$setting || !$setting->certificate_path || !$setting->certificate_password) {
            throw new \Exception('O certificado digital não foi configurado.');
        }

        $config = json_encode([
            "atualizacao" => date('Y-m-d H:i:s'),
            "tpAmb" => 1,
            "razaosocial" => $setting->company_name,
            "cnpj" => preg_replace('/[^0-9]/', '', $setting->cnpj),
            "siglaUF" => $setting->state,
            "schemes" => "PL_009_V4",
            "versao" => '4.00',
        ]);

        $pfxContent = Storage::get($setting->certificate_path);
        $certificate = Certificate::readPfx($pfxContent, $setting->certificate_password);
        $tools = new Tools($config, $certificate);

        $response = $tools->sefazManifesta($chave, $tpEvento, $justificativa, 1);

        $std = new \NFePHP\NFe\Common\Standardize();
        $arr = $std->toArray($response);

        if (isset($arr['cStat']) && $arr['cStat'] == 128) {
            $ret = $arr['retEvento'][0] ?? $arr['retEvento'] ?? [];
            $inf = $ret['infRet'] ?? $ret['infEvento'] ?? null;
            $eventoStat = $inf['cStat'] ?? null;
            $motivo = $inf['xMotivo'] ?? 'Sem motivo informado pela Sefaz';

            // 135 = Evento Registrado | 573 = Duplicidade
            if (in_array($eventoStat, [135, 136, 573])) {
                return true;
            }
            throw new \Exception("Sefaz negou o evento ({$eventoStat}): {$motivo}");
        }

        throw new \Exception("Falha de Lote SEFAZ: " . ($arr['xMotivo'] ?? 'Comunicação falhou.'));
    }

    // =========================================================
    // DELEGAÇÃO DE TABELA
    // =========================================================
    public static function table(Table $table): Table
    {
        return ReceivedInvoicesTable::build($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageReceivedInvoices::route('/'),
        ];
    }
}
