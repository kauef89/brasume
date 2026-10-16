<?php

namespace App\Services\Printing;

class BlueprintBuilder
{
    // 🚀 Público para permitir manipulação direta em documentos complexos como FiscalReceipt
    public array $blueprint = [];

    public function alignCenter(): self
    {
        $this->blueprint[] = ['cmd' => 'align', 'val' => 'center'];
        return $this;
    }

    public function alignLeft(): self
    {
        $this->blueprint[] = ['cmd' => 'align', 'val' => 'left'];
        return $this;
    }

    public function alignRight(): self
    {
        $this->blueprint[] = ['cmd' => 'align', 'val' => 'right'];
        return $this;
    }

    public function text(string $text, bool $bold = false, string $size = '1x', bool $invert = false): self
    {
        $command = ['cmd' => 'text', 'val' => $text];
        if ($bold) $command['bold'] = true;
        if ($size !== 'normal' && $size !== '1x') $command['size'] = $size;
        if ($invert) $command['invert'] = true;

        $this->blueprint[] = $command;
        return $this;
    }

    /**
     * 🎯 Atalho de alta visibilidade: texto em negativo (fundo preto com letra branca).
     * Aplica padding lateral automático para preenchimento térmico consistente.
     */
    public function inverted(string $text, bool $bold = true, string $size = '2x'): self
    {
        $padded = ' ' . trim($text) . ' ';
        return $this->text($padded, $bold, $size, true);
    }

    public function divider(): self
    {
        // 48 caracteres preenchem perfeitamente a bobina de 80mm
        $this->blueprint[] = ['cmd' => 'text', 'val' => '------------------------------------------------'];
        return $this;
    }

    public function space(): self
    {
        $this->blueprint[] = ['cmd' => 'text', 'val' => ' '];
        return $this;
    }

    public function image(string $url): self
    {
        $this->blueprint[] = ['cmd' => 'image', 'url' => $url];
        return $this;
    }

    public function qrCode(string $data, int $size = 6): self
    {
        $this->blueprint[] = ['cmd' => 'qrcode', 'data' => $data, 'size' => $size];
        return $this;
    }

    public function cut(): self
    {
        $this->blueprint[] = ['cmd' => 'cut'];
        return $this;
    }

    public function beep(): self
    {
        $this->blueprint[] = ['cmd' => 'beep'];
        return $this;
    }

    public function get(): array
    {
        return $this->blueprint;
    }
}
