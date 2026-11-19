<?php

namespace App\Services\PayableDictionaries;

use App\Enums\AccountingCategory;
use App\Enums\AccountingSubcategory;
use App\Enums\PayableDocumentType;
use App\Models\AccountPayable;
use App\Models\Supplier;
use Exception;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Smalot\PdfParser\Parser;
use Symfony\Component\Process\Process;

class PayableDocumentScannerService
{
    protected array $parsers = [
        DareScDictionary::class,
        DasSimplesNacionalDictionary::class,
        DarfInssDictionary::class,
        FgtsDigitalDictionary::class,
        AccountingFeeDictionary::class,
        MunicipalFireFeeDictionary::class,
        GenericBoletoDictionary::class,
    ];

    public function scanPdf(string $pdfPath, ?string &$rawTextOut = null): array
    {
        $text = $this->extractText($pdfPath);
        $rawTextOut = $text;

        if (!$this->hasMeaningfulText($text)) {
            throw new Exception("Não foi possível extrair texto deste PDF (arquivo vazio ou imagem ilegível).");
        }

        if (preg_match('/RECIBO\s+DE\s+(?:PAGAMENTO\s+DE\s+)?(?:SAL[ÁA]RIO|F[ÉE]RIAS)|AVISO\s+E\s+RECIBO\s+DE\s+F[ÉE]RIAS/ui', $text)) {
            throw new Exception("Recibo de Folha / 13º / Férias do SCI. Importe em Gestão & RH → Folhas de Pagamento.");
        }

        foreach ($this->parsers as $parserClass) {
            $parser = new $parserClass();
            if ($parser->matches($text)) {
                $data = $parser->extract($text);
                $type = $parser->type();

                $data['document_type'] = $type;
                $data['category'] ??= $type->defaultCategory()->value;
                $data['subcategory'] ??= $type->defaultSubcategory()->value;

                if (empty($data['due_date'])) {
                    throw new Exception("Layout reconhecido ({$type->getLabel()}), mas a data de vencimento não foi localizada.");
                }

                if ((float) ($data['amount'] ?? 0) <= 0) {
                    throw new Exception("Layout reconhecido ({$type->getLabel()}), mas o valor do documento veio zerado ou ilegível.");
                }

                return $data;
            }
        }

        throw new Exception("Layout não reconhecido pelos dicionários cadastrados.");
    }

    public function dryRunBatch(array $fileEntries, string $disk = 'public'): array
    {
        $ready = [];
        $duplicates = [];
        $errors = [];

        foreach ($fileEntries as $entry) {
            $resolved = $this->resolveFileEntry($entry, $disk);
            if (!$resolved) {
                continue;
            }

            $rawText = '';
            try {
                $parsed = $this->scanPdf($resolved['path'], $rawText);
                /** @var PayableDocumentType $docType */
                $docType = $parsed['document_type'] ?? PayableDocumentType::GENERIC_BOLETO;

                $supplier = null;
                $cleanCnpj = null;
                if (!empty($parsed['supplier_cnpj'])) {
                    $cleanCnpj = preg_replace('/[^0-9]/', '', $parsed['supplier_cnpj']);
                    $supplier = Supplier::where('document', $cleanCnpj)->first();
                }

                $existingDuplicate = AccountPayable::where('due_date', $parsed['due_date'])
                    ->where('amount', $parsed['amount'])
                    ->where(function ($q) use ($supplier, $parsed) {
                        if ($supplier) {
                            $q->where('supplier_id', $supplier->id);
                        } else {
                            $q->where('description', $parsed['description']);
                        }
                    })
                    ->first();

                $catEnum = AccountingCategory::tryFrom((string) ($parsed['category'] ?? ''));
                $subEnum = AccountingSubcategory::tryFrom((string) ($parsed['subcategory'] ?? ''));

                $itemReport = [
                    'filename'               => $resolved['filename'],
                    'stored_path'            => $resolved['stored_path'],
                    'document_type'          => $docType,
                    'type_label'             => $docType->getLabel(),
                    'description'            => $parsed['description'],
                    'amount'                 => (float) $parsed['amount'],
                    'due_date'               => $parsed['due_date'],
                    'supplier_id'            => $supplier?->id,
                    'supplier_cnpj'          => $parsed['supplier_cnpj'] ?? null,
                    'supplier_resolved_name' => $supplier ? $supplier->name : ($parsed['supplier_name'] ?? 'Sem Fornecedor'),
                    'supplier_is_new'        => !empty($cleanCnpj) && !$supplier,
                    'category'               => $parsed['category'],
                    'subcategory'            => $parsed['subcategory'],
                    'category_label'         => $catEnum?->getLabel() ?? $parsed['category'],
                    'subcategory_label'      => $subEnum?->getLabel() ?? $parsed['subcategory'],
                    'observations'           => $parsed['observations'] ?? null,
                ];

                if ($existingDuplicate) {
                    $itemReport['existing_id'] = $existingDuplicate->id;
                    $duplicates[] = $itemReport;
                } else {
                    $ready[] = $itemReport;
                }
            } catch (\Throwable $e) {
                $cleanPreview = (string) preg_replace('/[\p{Z}\p{C}]+/u', ' ', $rawText);
                $snippet = trim($cleanPreview);
                if (mb_strlen($snippet) > 260) {
                    $snippet = mb_substr($snippet, 0, 260) . '...';
                }

                $errors[] = [
                    'filename'     => $resolved['filename'],
                    'stored_path'  => $resolved['stored_path'],
                    'reason'       => $e->getMessage(),
                    'text_preview' => $snippet,
                ];
            }
        }

        return [
            'total_files'  => count($ready) + count($duplicates) + count($errors),
            'ready'        => $ready,
            'duplicates'   => $duplicates,
            'errors'       => $errors,
            'total_amount' => round(array_sum(array_column($ready, 'amount')), 2),
        ];
    }

    private function hasMeaningfulText(string $text): bool
    {
        $alnumOnly = preg_replace('/[^\p{L}\p{N}]/u', '', $text);
        return mb_strlen((string) $alnumOnly) >= 25;
    }

    private function resolveFileEntry(mixed $entry, string $disk): ?array
    {
        if ($entry instanceof TemporaryUploadedFile) {
            return [
                'filename'    => $entry->getClientOriginalName(),
                'path'        => $entry->getRealPath(),
                'stored_path' => null,
            ];
        }

        if (is_string($entry) && $entry !== '') {
            $fullPath = Storage::disk($disk)->exists($entry)
                ? Storage::disk($disk)->path($entry)
                : (file_exists($entry) ? $entry : null);

            if ($fullPath) {
                return [
                    'filename'    => basename($entry),
                    'path'        => $fullPath,
                    'stored_path' => $entry,
                ];
            }
        }

        return null;
    }

    private function extractText(string $pdfPath): string
    {
        // 1. Extração nativa via Smalot\PdfParser
        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($pdfPath);
            $text = $pdf->getText();
            if ($this->hasMeaningfulText($text)) {
                return $text;
            }
        } catch (\Throwable $e) {}

        // 2. Fallback via pdftotext (Poppler)
        $pdftotextBin = file_exists('/usr/bin/pdftotext') ? '/usr/bin/pdftotext' : 'pdftotext';
        try {
            $process = new Process([$pdftotextBin, '-layout', $pdfPath, '-']);
            $process->setTimeout(20);
            $process->run();
            if ($process->isSuccessful()) {
                $text = $process->getOutput();
                if ($this->hasMeaningfulText($text)) {
                    return $text;
                }
            }
        } catch (\Throwable $e) {}

        // 3. Fallback OCR via pdftoppm + tesseract (PDFs imagem como FGTS Digital / DARF)
        $pdftoppmBin = file_exists('/usr/bin/pdftoppm') ? '/usr/bin/pdftoppm' : 'pdftoppm';
        $tesseractBin = file_exists('/usr/bin/tesseract') ? '/usr/bin/tesseract' : 'tesseract';

        try {
            $tmpPrefix = sys_get_temp_dir() . '/ocr_payable_' . uniqid();
            $render = new Process([$pdftoppmBin, '-png', '-r', '250', '-f', '1', '-l', '2', $pdfPath, $tmpPrefix]);
            $render->setTimeout(30);
            $render->run();

            $images = glob($tmpPrefix . '*.png') ?: [];
            $ocrText = '';

            foreach ($images as $img) {
                $ocr = new Process([$tesseractBin, $img, 'stdout', '-l', 'por', '--psm', '6']);
                $ocr->setTimeout(30);
                $ocr->run();
                if ($ocr->isSuccessful()) {
                    $ocrText .= "\n" . $ocr->getOutput();
                }
                @unlink($img);
            }

            if ($this->hasMeaningfulText($ocrText)) {
                return $ocrText;
            }
        } catch (\Throwable $e) {}

        return '';
    }
}