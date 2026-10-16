<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

class InspectFilamentMenus extends Command
{
    protected $signature = 'filament:inspect-menus';
    protected $description = 'Lista todos os Resources do Filament agrupados por Navigation Group';

    public function handle()
    {
        $finder = new Finder();
        // Varre a pasta de Resources em busca de arquivos que terminem com Resource.php
        $finder->files()->in(app_path('Filament/Resources'))->name('*Resource.php');

        $groups = [];
        $ungrouped = [];

        foreach ($finder as $file) {
            // Monta o namespace da classe dinamicamente baseado no caminho do arquivo
            $class = 'App\\Filament\\Resources\\' . str_replace('/', '\\', $file->getRelativePathname());
            $class = str_replace('.php', '', $class);

            if (class_exists($class) && is_subclass_of($class, \Filament\Resources\Resource::class)) {
                $group = $class::getNavigationGroup();
                $name = $class::getModelLabel() ?? class_basename($class);

                if ($group) {
                    $groups[$group][] = $name;
                } else {
                    $ungrouped[] = $name;
                }
            }
        }

        $this->info("=== RAIO-X DOS MENUS DO FILAMENT ===\n");

        foreach ($groups as $groupName => $resources) {
            $this->warn("📁 Grupo: {$groupName}");
            foreach ($resources as $resource) {
                $this->line("   - {$resource}");
            }
            $this->line("");
        }

        if (count($ungrouped) > 0) {
            $this->error("⚠️ SEM GRUPO DEFINIDO (Órfãos):");
            foreach ($ungrouped as $resource) {
                $this->line("   - {$resource}");
            }
        } else {
            $this->info("✅ Nenhum Resource órfão encontrado!");
        }
    }
}
