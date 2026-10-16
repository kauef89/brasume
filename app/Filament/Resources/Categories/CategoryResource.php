<?php
namespace App\Filament\Resources\Categories;

use App\Filament\Traits\HasGlobalSearch;
use App\Filament\Resources\Categories\Pages;
use App\Filament\Resources\Categories\Schemas\CategoryForm;
use App\Filament\Resources\Categories\Tables\CategoriesTable;
use App\Models\Category;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class CategoryResource extends Resource {
    use HasGlobalSearch;
    protected static ?string $model = Category::class;
    // protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-tag';
    protected static string | \UnitEnum | null $navigationGroup = 'Cardápio';
    protected static ?string $modelLabel = 'Categoria';
    protected static ?string $pluralModelLabel = 'Categorias';

    public static function form(Schema $schema): Schema { return CategoryForm::configure($schema); }
    public static function table(Table $table): Table { return CategoriesTable::configure($table); }
    public static function getPages(): array {
        return [
            'index' => Pages\ListCategories::route('/'),
            'tree' => Pages\TreeCategory::route('/tree'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
