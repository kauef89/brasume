<?php
$dir = new RecursiveDirectoryIterator(__DIR__ . '/app/Filament');
$iterator = new RecursiveIteratorIterator($dir);
$count = 0;

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        
        // Padrão Regex: Captura exatamente ->helperText('texto') ou ->helperText("texto")
        $pattern = '/(->helperText\(\s*)([\'"])(.*?)(?<!\\\\)\2(\s*\))/s';
        
        // Substitui pelo hintIcon nativo com a cor primary e Tooltip
        $replacement = '->hintIcon(\'heroicon-m-question-mark-circle\', tooltip: $2$3$2)->hintColor(\'primary\')';
        
        $newContent = preg_replace($pattern, $replacement, $content, -1, $replaced);
        
        if ($replaced > 0 && $newContent !== $content) {
            file_put_contents($file->getPathname(), $newContent);
            $count += $replaced;
            echo "✅ Ajustado: " . $file->getFilename() . " ({$replaced} ocorrencias)\n";
        }
    }
}
echo "\n🚀 Total de strings estaticas convertidas para Tooltips: {$count}\n";
