<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Enums\CategoryType;
use App\Enums\ProductStatus;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class CategoryForm
{
    public static function getFormSchema(): array
    {
        return [
            Section::make("Detalhes")
                ->schema([
                    TextInput::make("name")
                        ->label("Nome")
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state)))
                        ->unique(
                            ignoreRecord: true,
                            modifyRuleUsing: fn(Unique $rule) => $rule->withoutTrashed(),
                        )
                        ->columnSpan(2),

                    TextInput::make("slug")
                        ->label("Slug (URL)")
                        ->required()
                        ->unique(
                            ignoreRecord: true,
                            modifyRuleUsing: fn(Unique $rule) => $rule->withoutTrashed(),
                        )
                        ->columnSpan(1),

                    Select::make("type")
                        ->label("Macro Área (Tipo)")
                        ->options(CategoryType::class)
                        ->required()
                        ->default(CategoryType::EXTERNAL->value)
                        ->columnSpan(1),

                    Select::make("parent_id")
                        ->relationship("parent", "name")
                        ->label("Categoria Pai (Opcional)")
                        ->searchable()
                        ->preload()
                        ->columnSpan(1)
                        ->createOptionForm([
                            TextInput::make("name")
                                ->label("Nome")
                                ->required(),
                            Select::make("type")
                                ->label("Macro Área")
                                ->options(CategoryType::class)
                                ->required()
                                ->default(CategoryType::EXTERNAL->value),
                        ]),

                    Select::make("status")
                        ->label("Status de Disponibilidade")
                        ->options(ProductStatus::class)
                        ->required()
                        ->default(ProductStatus::AVAILABLE->value)
                        ->columnSpan(1),

                    Select::make("visibility")
                        ->label("Visibilidade")
                        ->options([
                            "external" => "Visível (Público/Cardápio)",
                            "internal" => "Oculto (Uso Interno/PDV)",
                        ])
                        ->required()
                        ->default("external")
                        ->columnSpan(1),
                ])
                ->columns(3)
                ->columnSpanFull(),

            Section::make("Mídia & Descrição")
                ->schema([
                    FileUpload::make("image_path")
                        ->label("Imagem da Categoria")
                        ->image()
                        ->disk('public')
                        ->visibility('public')
                        ->directory("categories")
                        ->saveUploadedFileUsing(function (TemporaryUploadedFile $file): string {
                            $manager = new ImageManager(new Driver());
                            $path = 'categories/' . Str::uuid() . '.webp';

                            $image = $manager->read($file->getRealPath())
                                ->scaleDown(width: 500)
                                ->toWebp(quality: 85);

                            Storage::disk('public')->put($path, $image->toString(), 'public');
                            return $path;
                        })
                        ->columnSpan(1),

                    Textarea::make("description")
                        ->label("Descrição")
                        ->rows(4)
                        ->columnSpan(2),
                ])
                ->columns(3)
                ->columnSpanFull(),

            Section::make("Configurações")
                ->schema([
                    TextInput::make("sort_order")
                        ->label("Ordem de Exibição")
                        ->numeric()
                        ->default(0)
                        ->columnSpan(1),

                    Toggle::make("is_active")
                        ->label("Ativa?")
                        ->default(true)
                        ->inline(false)
                        ->columnSpan(1),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }
}
