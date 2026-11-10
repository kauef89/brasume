<?php

namespace App\Filament\Resources\Employees;

use App\Filament\Traits\HasGlobalSearch;
use App\Filament\Resources\Employees\Pages;
use App\Filament\Resources\Employees\Schemas\EmployeeForm;
use App\Filament\Resources\Employees\Tables\EmployeesTable;
use App\Filament\Resources\Employees\RelationManagers\LedgersRelationManager;
use App\Filament\Resources\Employees\RelationManagers\ConsumptionsRelationManager;
use App\Filament\Resources\Employees\RelationManagers\EmployeePayablesRelationManager;
use App\Filament\Resources\Employees\RelationManagers\PayslipsRelationManager;
use App\Filament\Resources\Employees\RelationManagers\SchedulesRelationManager;
use App\Filament\Resources\Employees\RelationManagers\VacationsRelationManager;
use App\Filament\Resources\Employees\RelationManagers\DeliveriesRelationManager;
use App\Models\Employee;
use Filament\Schemas\Schema;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class EmployeeResource extends Resource
{
    use HasGlobalSearch;

    protected static ?string $model = Employee::class;
    protected static string|\UnitEnum|null $navigationGroup = 'Gestão & RH';
    protected static ?string $modelLabel = "Colaborador";
    protected static ?string $pluralModelLabel = "Colaboradores";
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return EmployeeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeesTable::table($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make("Dados Pessoais e Contato")
                ->columns(3)
                ->schema([
                    TextEntry::make("name")->label("Nome Completo")->weight('bold'),
                    TextEntry::make("cpf")->label("CPF")->placeholder('-'),
                    TextEntry::make("phone")->label("Telefone/WhatsApp")->placeholder('-'),
                    TextEntry::make("email")->label("E-mail")->placeholder('-'),
                    TextEntry::make("role")->label("Função Principal")->badge(),
                    TextEntry::make("is_active")->label("Status")->badge()->formatStateUsing(fn ($s) => $s ? 'Ativo' : 'Inativo')->color(fn ($s) => $s ? 'success' : 'danger'),
                ]),
            Section::make("Contrato & Remuneração")
                ->columns(3)
                ->schema([
                    TextEntry::make("employment_type")->label("Tipo de Vínculo")->badge(),
                    TextEntry::make("base_remuneration")->label("Remuneração Base")->money("BRL")->weight('bold'),
                    TextEntry::make("payment_cycle")->label("Ciclo de Pagamento")->badge(),
                    TextEntry::make("food_allowance_amount")->label("Teto Vale-Alimentação")->money("BRL")->placeholder('Padrão Global'),
                    TextEntry::make("payment_method")->label("Forma de Pagamento")->badge(),
                    TextEntry::make("pix_key")->label("Chave PIX")->placeholder('-'),
                ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            LedgersRelationManager::class,
            ConsumptionsRelationManager::class,
            EmployeePayablesRelationManager::class,
            PayslipsRelationManager::class,
            SchedulesRelationManager::class,
            VacationsRelationManager::class,
            DeliveriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            "index"  => Pages\ListEmployees::route("/"),
            "create" => Pages\CreateEmployee::route("/create"),
            "view"   => Pages\ViewEmployee::route("/{record}"),
            "edit"   => Pages\EditEmployee::route("/{record}/edit"),
        ];
    }
}