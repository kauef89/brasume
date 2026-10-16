<?php

namespace App\Filament\Resources\Products\Schemas\Sections;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\UnitOfMeasure;
use Filament\Forms;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use App\Models\Category;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class BasicSection
{
    public static function make(): Section
    {
        return Section::make("Básico")
            ->icon("heroicon-o-information-circle")
            ->schema([
                Grid::make(3)->schema([
                    Forms\Components\TextInput::make("name")
                        ->label("Nome do Produto")
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(2),

                    Forms\Components\Select::make("status")
                        ->label("Status Operacional")
                        ->options(ProductStatus::class)
                        ->default("available")
                        ->helperText("Use 'Pausado' para suspender a venda imediatamente, mesmo que haja saldo em estoque.")
                        ->required()
                        ->columnSpan(1),

                    Forms\Components\Select::make("category_id")
                        ->label("Categoria")
                        ->required()
                        ->relationship(
                            name: "category",
                            titleAttribute: "name",
                            modifyQueryUsing: fn($query) => $query->where("type", "external"),
                        )
                        ->searchable()
                        ->preload()
                        ->createOptionForm([
                            Forms\Components\TextInput::make("name")
                                ->label("Nome da Nova Categoria")
                                ->required()
                                ->maxLength(255),
                        ])
                        ->createOptionUsing(function (array $data) {
                            $data["type"] = "external";
                            return Category::create($data)->id;
                        })
                        ->columnSpan(1),

                    Forms\Components\Select::make("type")
                        ->label("Tipo")
                        ->options(ProductType::class)
                        ->required()
                        ->live()
                        ->columnSpan(1),

                    Forms\Components\TextInput::make("serves_people")
                        ->label("Serve quantas pessoas?")
                        ->numeric()
                        ->columnSpan(1),
                ]),

                Grid::make(2)->schema([
                    Forms\Components\Textarea::make("description")
                        ->label("Descrição (Cardápio)")
                        ->rows(4)
                        ->columnSpan(1),

                    Forms\Components\FileUpload::make("image_path")
                        ->label("Foto do Produto")
                        ->image()
                        ->disk('public')
                        ->visibility('public')
                        ->directory("products")
                        ->saveUploadedFileUsing(function (TemporaryUploadedFile $file): string {
                            $manager = new ImageManager(new Driver());
                            $path = 'products/' . Str::uuid() . '.webp';

                            $image = $manager->read($file->getRealPath())
                                ->scaleDown(width: 500)
                                ->toWebp(quality: 85);

                            Storage::disk('public')->put($path, $image->toString(), 'public');
                            return $path;
                        })
                        ->columnSpan(1),
                ]),

                Grid::make(3)->schema([
                    Forms\Components\Toggle::make("show_on_menu")
                        ->label("Mostrar no PDV/Balcão?")
                        ->default(true),
                    Forms\Components\Toggle::make("show_on_delivery")
                        ->label("Mostrar no Delivery?")
                        ->default(true),
                    Forms\Components\Toggle::make("is_age_restricted")
                        ->label("Restrito para +18?")
                        ->default(false),
                ]),
            ])
            ->columnSpanFull();
    }
}
