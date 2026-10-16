<?php

namespace App\Filament\Pages\Settings\Schemas\Partials;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

// 🚀 IMPORTAÇÕES DA V3
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class VisualIdentitySchema
{
    public static function make(): array
    {
        return [
            // ==========================================
            // 🌐 DOMÍNIO PRÓPRIO DO TENANT
            // ==========================================
            Section::make("Domínio Próprio e Presença Web")
                ->description("Se a sua loja possui um domínio próprio registrado, os links enviados aos clientes (avaliações, cardápio, comprovantes) usarão este endereço.")
                ->icon("heroicon-o-globe-alt")
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('custom_domain')
                            ->label('Domínio Personalizado')
                            ->placeholder('ex: meurestaurante.com.br ou delivery.minhaloja.com.br')
                            ->prefix('https://')
                            ->helperText('Informe o domínio sem "https://" ou barras no final. Deixe em branco para usar o subdomínio padrão.')
                            ->dehydrateStateUsing(fn (?string $state) => $state ? strtolower(trim(preg_replace('#^https?://#i', '', rtrim($state, '/')))) : null)
                            ->maxLength(255)
                            ->columnSpan(1),

                        Placeholder::make('domain_hint')
                            ->label('Como funciona o empacotamento?')
                            ->content(function (Get $get) {
                                $custom = $get('custom_domain');
                                $clean = $custom ? strtolower(trim(preg_replace('#^https?://#i', '', rtrim($custom, '/')))) : null;
                                $displayUrl = $clean ? "https://{$clean}/avaliacao" : url('/avaliacao');

                                return new HtmlString("
                                    <div class='text-xs text-gray-500 dark:text-gray-400 pt-2 leading-relaxed'>
                                        Link de avaliação gerado:<br>
                                        <code class='text-primary-600 dark:text-primary-400 font-bold'>{$displayUrl}</code>
                                    </div>
                                ");
                            })
                            ->columnSpan(1),
                    ]),
                ]),

            Section::make("Cor de Destaque")
                ->description("Defina a cor principal que será usada nos botões e detalhes do aplicativo.")
                ->icon("heroicon-o-swatch")
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('theme_color')
                            ->label('Selecione a Cor')
                            ->options([
                                'blue' => 'Azul', 'sky' => 'Azul Claro', 'indigo' => 'Azul Índigo', 'violet' => 'Violeta',
                                'purple' => 'Roxo', 'fuchsia' => 'Fúcsia', 'pink' => 'Rosa', 'teal' => 'Verde Petróleo',
                                'cyan' => 'Ciano', 'lime' => 'Verde Limão', 'orange' => 'Laranja', 'slate' => 'Cinza',
                                'amber' => 'Amarelo', 'rose' => 'Vermelho', 'emerald' => 'Esmeralda',
                            ])
                            ->default('amber')
                            ->required()
                            ->native(false)
                            ->live()
                            ->columnSpan(1),

                        Placeholder::make('preview')
                            ->label('Visualização')
                            ->content(function (Get $get) {
                                $color = $get('theme_color') ?? 'amber';
                                $bgHexCodes = ['amber' => '#ffba00', 'orange' => '#ff8904', 'rose' => '#ff2056', 'pink' => '#f6339a', 'purple' => '#a855f7', 'violet' => '#8e51ff', 'indigo' => '#4f39f6', 'blue' => '#155dfc', 'sky' => '#00bcff', 'cyan' => '#00d3f2', 'teal' => '#00d5be', 'emerald' => '#00d492', 'slate' => '#45556c', 'fuchsia' => '#e12afb', 'lime' => '#9ae600'];
                                $textHexCodes = ['amber' => '#78350f', 'orange' => '#7c2d12', 'rose' => '#ffffff', 'pink' => '#ffffff', 'purple' => '#ffffff', 'violet' => '#ffffff', 'indigo' => '#ffffff', 'blue' => '#ffffff', 'sky' => '#052f4a', 'cyan' => '#104e64', 'teal' => '#0b4f4a', 'emerald' => '#004f3b', 'slate' => '#ffffff', 'fuchsia' => '#ffffff', 'lime' => '#3c6300'];
                                $bgHex = $bgHexCodes[$color] ?? '#f59e0b';
                                $textHex = $textHexCodes[$color] ?? '#78350f';

                                return new HtmlString('<div style="display: flex; align-items: center; padding-top: 0.5rem;"><button type="button" style="background-color: ' . $bgHex . '; color: ' . $textHex . '; padding: 0.5rem 1rem; border-radius: 0.5rem; font-weight: 600; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); display: inline-flex; align-items: center; gap: 0.5rem;"><svg style="width: 1.25rem; height: 1.25rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg> Exemplo </button></div>');
                            })
                            ->columnSpan(1),
                    ]),
                ]),

            Section::make("Logotipos e Ícones")
                ->description("Imagens da sua marca utilizadas no cabeçalho e na instalação do App (PWA).")
                ->icon("heroicon-o-photo")
                ->schema([
                    Grid::make(3)->schema([
                        FileUpload::make("logo_path")
                            ->label("Logo Otimizada (WebP)")
                            ->directory("company_logos")
                            ->image()
                            ->disk('public')
                            ->visibility('public')
                            ->saveUploadedFileUsing(function (TemporaryUploadedFile $file): string {
                                $manager = new ImageManager(new Driver());
                                $path = 'company_logos/' . Str::uuid() . '.webp';

                                $image = $manager->read($file->getRealPath())
                                    ->scaleDown(width: 800)
                                    ->toWebp(quality: 100);

                                Storage::disk('public')->put($path, $image->toString(), 'public');
                                return $path;
                            })
                            ->columnSpan(1),

                        FileUpload::make("logo_svg_path")
                            ->label("Logo Vetorial (SVG)")
                            ->acceptedFileTypes(['image/svg+xml'])
                            ->directory("company_logos")
                            ->columnSpan(1)
                            ->disk('public')
                            ->visibility('public'),

                        FileUpload::make('push_badge_path')
                            ->label('Ícone de Notificação (Badge)')
                            ->helperText('Obrigatório: Arquivo PNG com fundo transparente e a silhueta da logo em BRANCO PURO.')
                            ->directory('settings/badges')
                            ->acceptedFileTypes(['image/png'])
                            ->disk('public')
                            ->visibility('public')
                            ->columnSpan(1),

                        FileUpload::make("icon_path")
                            ->label("Ícone PWA (512x512)")
                            ->helperText("Gerado automaticamente em PNG 512x512 para a tela de abertura do App.")
                            ->directory("company_icons")
                            ->image()
                            ->imageEditor()
                            ->disk('public')
                            ->visibility('public')
                            ->saveUploadedFileUsing(function (TemporaryUploadedFile $file): string {
                                $manager = new ImageManager(new Driver());
                                $path = 'company_icons/' . Str::uuid() . '.png';

                                $image = $manager->read($file->getRealPath())
                                    ->cover(512, 512)
                                    ->toPng();

                                Storage::disk('public')->put($path, $image->toString(), 'public');
                                return $path;
                            })
                            ->columnSpan(1),

                        FileUpload::make("icon_192_path")
                            ->label("Ícone PWA (192x192)")
                            ->helperText("Gerado automaticamente em PNG 192x192 para a barra de tarefas e início.")
                            ->directory("company_icons")
                            ->image()
                            ->imageEditor()
                            ->disk('public')
                            ->visibility('public')
                            ->saveUploadedFileUsing(function (TemporaryUploadedFile $file): string {
                                $manager = new ImageManager(new Driver());
                                $path = 'company_icons/' . Str::uuid() . '.png';

                                $image = $manager->read($file->getRealPath())
                                    ->cover(192, 192)
                                    ->toPng();

                                Storage::disk('public')->put($path, $image->toString(), 'public');
                                return $path;
                            })
                            ->columnSpan(1),
                    ]),
                ]),

            Section::make("Imagens de Fundo de Tela")
                ->description("Planos de fundo exibidos na tela inicial do aplicativo. Arquivos grandes são convertidos e otimizados automaticamente.")
                ->icon("heroicon-o-device-phone-mobile")
                ->schema([
                    Grid::make(2)->schema([
                        FileUpload::make('home_bg_open')
                            ->label('Fundo: Loja Aberta')
                            ->helperText('Sorteado aleatoriamente a cada visita. Otimizado para WebP 1920px.')
                            ->image()
                            ->multiple()
                            ->maxFiles(30)
                            ->disk('public')
                            ->visibility('public')
                            ->saveUploadedFileUsing(function (TemporaryUploadedFile $file): string {
                                $manager = new ImageManager(new Driver());
                                $path = 'company_backgrounds/' . Str::uuid() . '.webp';

                                $image = $manager->read($file->getRealPath())
                                    ->scaleDown(width: 1920)
                                    ->toWebp(quality: 80);

                                Storage::disk('public')->put($path, $image->toString(), 'public');
                                return $path;
                            })
                            ->columnSpan(1),

                        FileUpload::make('home_bg_closed')
                            ->label('Fundo: Loja Fechada')
                            ->helperText('Usado quando a loja está fechada. Otimizado para WebP 1920px.')
                            ->image()
                            ->multiple()
                            ->maxFiles(30)
                            ->disk('public')
                            ->visibility('public')
                            ->saveUploadedFileUsing(function (TemporaryUploadedFile $file): string {
                                $manager = new ImageManager(new Driver());
                                $path = 'company_backgrounds/' . Str::uuid() . '.webp';

                                $image = $manager->read($file->getRealPath())
                                    ->scaleDown(width: 1920)
                                    ->toWebp(quality: 80);

                                Storage::disk('public')->put($path, $image->toString(), 'public');
                                return $path;
                            })
                            ->columnSpan(1),
                    ]),
                ]),
        ];
    }
}
