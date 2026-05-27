<?php

namespace App\Filament\Traits;

use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cookie;
use Filament\Support\Enums\Alignment;

trait HasWelcomeModal
{
    public function checkWelcomeModal(): void
    {
        if (! request()->cookie($this->getWelcomeCookieKey())) {
            $this->mountAction('bemVindo');
        }
    }

    protected function getWelcomeCookieKey(): string
    {
        $identifier = isset(static::$resource)
            ? class_basename(static::$resource)
            : class_basename(static::class);

        return "hide_welcome_{$identifier}_" . auth()->id();
    }

    protected function getWelcomeAction(): Action
    {
        if (isset(static::$resource)) {
            $resource = static::$resource;
            $title = Str::title($resource::getPluralModelLabel());
            $icon = $resource::getNavigationIcon() ?? 'heroicon-o-sparkles';
        } else {
            $title = method_exists($this, 'getTitle') ? $this->getTitle() : (static::$title ?? Str::title(class_basename(static::class)));
            $icon = method_exists($this, 'getNavigationIcon') ? $this->getNavigationIcon() : (static::$navigationIcon ?? 'heroicon-o-sparkles');
        }

        return Action::make('bemVindo')
            ->label('Como funciona?')
            ->icon('heroicon-o-question-mark-circle')
            ->color('gray')
            ->outlined()

            // 🚀 Identificador para o CSS
            ->extraModalWindowAttributes(['class' => 'wm-modal'])

            ->modalHeading("Boas-vindas ao módulo de {$title}!")
            ->modalIcon($icon)
            ->modalIconColor('primary') // Deixa o ícone com a cor da sua marca
            ->modalAlignment(Alignment::Center)
            ->modalWidth('2xl')

            ->modalDescription($this->getWelcomeMessage())
            ->modalSubmitActionLabel('Entendi, vamos lá!')
            ->modalCancelAction(false)
            ->form([
                Toggle::make('nao_mostrar_novamente')
                    ->label('Não mostrar esta mensagem automaticamente da próxima vez')
                    ->default(false)
            ])
            ->action(function (array $data) {
                if ($data['nao_mostrar_novamente']) {
                    Cookie::queue($this->getWelcomeCookieKey(), 'true', 60 * 24 * 365);
                }
            });
    }

    protected function getWelcomeMessage(): HtmlString
    {
        return new HtmlString('
            <div class="wm-container">
                Configure a mensagem sobrescrevendo o método <code>getWelcomeMessage()</code>.
            </div>
        ');
    }
}
