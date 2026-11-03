<?php

namespace App\Services\Accounting;

use App\Models\EmployeeLedger;
use App\Models\FiscalDocument;
use App\Models\CompanySetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class AccountingExportService
{
    public function sendMonthlyClosing(?Carbon $date = null): void
    {
        $date = $date ?? Carbon::now()->subMonth();
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();
        $competence = $date->format('m/Y');

        $settings = CompanySetting::first();
        $fiscalEmail = $settings->fiscal_accountant_email ?? 'fiscal@contabilidade.com.br';
        $dpEmail = $settings->hr_accountant_email ?? 'dp@contabilidade.com.br';

        // 1. Gera o arquivo de RH (Vales, Faltas e Consumos)
        $hrCsvPath = $this->generateHrReport($startOfMonth, $endOfMonth);

        // 2. Gera o arquivo Fiscal (ZIP com XMLs)
        $fiscalZipPath = $this->generateFiscalZip($startOfMonth, $endOfMonth);

        // 3. Dispara o E-mail para o DP
        if ($hrCsvPath) {
            Mail::raw("Olá equipe do DP,\n\nSegue em anexo o relatório de vales, descontos e consumos internos dos colaboradores referente à competência {$competence}.\n\nAtenciosamente,\n" . tenant('id'), function ($message) use ($dpEmail, $hrCsvPath, $competence) {
                $message->to($dpEmail)
                        ->subject('[' . strtoupper(tenant('id')) . '] Fechamento de RH - ' . $competence)
                        ->attach($hrCsvPath);
            });
        }

        // 4. Dispara o E-mail para o Fiscal
        if ($fiscalZipPath) {
            Mail::raw("Olá equipe Fiscal,\n\nSegue em anexo o arquivo ZIP contendo todos os XMLs de notas fiscais emitidas (NFC-e/NF-e) e o relatório de produtos monofásicos referente à competência {$competence}.\n\nAtenciosamente,\n" . tenant('id'), function ($message) use ($fiscalEmail, $fiscalZipPath, $competence) {
                $message->to($fiscalEmail)
                        ->subject('[' . strtoupper(tenant('id')) . '] Fechamento Fiscal (XMLs) - ' . $competence)
                        ->attach($fiscalZipPath);
            });
        }

        // Limpeza dos arquivos temporários
        if ($hrCsvPath) @unlink($hrCsvPath);
        if ($fiscalZipPath) @unlink($fiscalZipPath);
    }

    private function generateHrReport(Carbon $start, Carbon $end): ?string
    {
        $ledgers = EmployeeLedger::with('employee')
            ->whereBetween('created_at', [$start, $end])
            ->where('type', 'debit') // Apenas consumos/vales que geram desconto
            ->get();

        if ($ledgers->isEmpty()) return null;

        $csvFileName = 'storage/temp/rh_descontos_' . $start->format('Y_m') . '.csv';
        if (!is_dir('storage/temp')) mkdir('storage/temp', 0755, true);

        $file = fopen($csvFileName, 'w');
        // BOM para o Excel ler acentuação corretamente
        fputs($file, $bom =(chr(0xEF) . chr(0xBB) . chr(0xBF)));
        fputcsv($file, ['CPF', 'Colaborador', 'Data', 'Descrição', 'Valor (R$)'], ';');

        foreach ($ledgers as $ledger) {
            fputcsv($file, [
                $ledger->employee->cpf,
                $ledger->employee->name,
                $ledger->created_at->format('d/m/Y'),
                $ledger->description,
                number_format($ledger->amount, 2, ',', '')
            ], ';');
        }
        fclose($file);

        return $csvFileName;
    }

    private function generateFiscalZip(Carbon $start, Carbon $end): ?string
    {
        $documents = FiscalDocument::whereBetween('emission_date', [$start, $end])
            ->where('status', 'authorized')
            ->whereNotNull('xml_content')
            ->get();

        if ($documents->isEmpty()) return null;

        $zipFileName = 'storage/temp/xmls_fiscais_' . $start->format('Y_m') . '.zip';
        if (!is_dir('storage/temp')) mkdir('storage/temp', 0755, true);

        $zip = new ZipArchive();
        if ($zip->open($zipFileName, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            foreach ($documents as $doc) {
                // Adiciona o conteúdo do XML direto no ZIP sem precisar salvar no disco antes
                $zip->addFromString("{$doc->access_key}-nfe.xml", $doc->xml_content);
            }
            $zip->close();
            return $zipFileName;
        }

        return null;
    }
}
