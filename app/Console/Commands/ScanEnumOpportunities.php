<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;
use Illuminate\Support\Facades\File;

class ScanEnumOpportunities extends Command
{
    protected $signature = 'dev:scan-enums';
    protected $description = 'Rastreia arrays hardcoded em Selects/Radios e constantes em Models que poderiam virar Enums.';

    public function handle()
    {
        $this->info("🔍 INICIANDO VARREDURA DE DÍVIDA TÉCNICA (ENUMS)...\n");

        $this->scanFilamentOptions();
        $this->scanModelConstants();

        $this->info("\n✅ Varredura concluída! Analise os itens acima para extrair novos Enums.");
    }

    private function scanFilamentOptions()
    {
        $this->warn("📁 ANALISANDO FILAMENT (Arrays hardcoded em options)");

        $finder = new Finder();
        $finder->files()->in(app_path('Filament'))->name('*.php');

        $found = 0;

        foreach ($finder as $file) {
            $content = File::get($file->getRealPath());

            // Busca padrões como ->options(['chave' => 'Valor', ...])
            // Ignora chamadas de métodos (ex: ->options(User::pluck(...)))
            if (preg_match_all('/->options\(\s*\[(.*?)\]\s*\)/s', $content, $matches)) {
                $fileName = $file->getRelativePathname();

                foreach ($matches[1] as $match) {
                    // Se o array contém setas '=>' e aspas, é um forte candidato a Enum
                    if (str_contains($match, '=>') && preg_match('/[\'"]/', $match)) {
                        // Tenta extrair o nome do campo anterior ao options (ex: Select::make('status'))
                        $fieldMatch = '';
                        $offset = strpos($content, $match);
                        $context = substr($content, max(0, $offset - 150), 150);
                        if (preg_match('/make\([\'"]([^\'"]+)[\'"]\)/', $context, $nameMatch)) {
                            $fieldMatch = " (Campo: <options=bold>{$nameMatch[1]}</>)";
                        }

                        // Limpa quebras de linha para exibição amigável
                        $preview = substr(trim(preg_replace('/\s+/', ' ', $match)), 0, 60);

                        $this->line("   - <fg=cyan>{$fileName}</>{$fieldMatch}");
                        $this->line("     <fg=gray>Array: [ {$preview}... ]</>");
                        $found++;
                    }
                }
            }
        }

        if ($found === 0) {
            $this->line("   Nenhum array hardcoded encontrado nos formulários!");
        }
    }

    private function scanModelConstants()
    {
        $this->warn("\n📁 ANALISANDO MODELS (Constantes agrupadas)");

        $finder = new Finder();
        $finder->files()->in(app_path('Models'))->name('*.php');

        $found = 0;

        foreach ($finder as $file) {
            $content = File::get($file->getRealPath());

            // Busca por const NOME = 'valor';
            if (preg_match_all('/const\s+([A-Z0-9_]+)\s*=\s*[\'"][^\'"]+[\'"]\s*;/i', $content, $matches)) {
                $constants = $matches[1];

                // Se o Model tiver 3 ou mais constantes, ou se elas compartilharem prefixos (ex: STATUS_PENDING, STATUS_PAID)
                if (count($constants) >= 2) {
                    $fileName = $file->getFilename();
                    $list = implode(', ', $constants);

                    $this->line("   - <fg=cyan>{$fileName}</>");
                    $this->line("     <fg=gray>Constantes: {$list}</>");
                    $found++;
                }
            }
        }

        if ($found === 0) {
            $this->line("   Nenhuma constante suspeita encontrada nos Models!");
        }
    }
}
