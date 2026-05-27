<?php

namespace App\Filament\Resources\Employees;

use App\Filament\Traits\HasGlobalSearch;
use App\Filament\Resources\Employees\Pages;
use App\Filament\Resources\Employees\Schemas\EmployeeForm;
use App\Filament\Resources\Employees\Tables\EmployeeTable;
use App\Models\Employee;
use App\Livewire\EmployeeConsumptions;
use Filament\Schemas\Schema;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Livewire;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class EmployeeResource extends Resource
{
    use HasGlobalSearch;

    protected static ?string $model = Employee::class;
    protected static string|\UnitEnum|null $navigationGroup = "Recursos Humanos";
    protected static ?string $modelLabel = "Colaborador";
    protected static ?string $pluralModelLabel = "Colaboradores";
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return EmployeeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeeTable::table($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make("Tabs")
                ->columnSpanFull()
                ->tabs([
                    // Aba 1: Informações Cadastrais
                    Tabs\Tab::make("Informações Gerais")
                        ->icon("heroicon-o-information-circle")
                        ->schema([
                            Section::make("Dados Pessoais e Contato")
                                ->columns(3)
                                ->schema([
                                    TextEntry::make("name")->label(
                                        "Nome Completo",
                                    ),
                                    TextEntry::make("email")->label("E-mail"),
                                    TextEntry::make("phone")->label("Telefone"),
                                    TextEntry::make("cpf")->label("CPF"),
                                    TextEntry::make("pix_key_type")->label(
                                        "Tipo de Chave PIX",
                                    ),
                                    TextEntry::make("pix_key")->label(
                                        "Chave PIX",
                                    ),
                                ]),
                            Section::make("Contrato e Remuneração")
                                ->columns(3)
                                ->schema([
                                    TextEntry::make("employment_type")
                                        ->label("Tipo de Contrato")
                                        ->formatStateUsing(
                                            fn($state) => match ($state) {
                                                "clt_44" => "CLT 44h",
                                                "clt_40" => "CLT 40h",
                                                "freelance_fixed"
                                                    => "Freelancer Fixo",
                                                "freelance_eventual"
                                                    => "Freelancer Eventual",
                                                default => $state,
                                            },
                                        )
                                        ->badge(),

                                    TextEntry::make("base_remuneration")
                                        ->label("Remuneração Base")
                                        ->money("BRL"),

                                    TextEntry::make("payment_cycle")->label(
                                        "Ciclo de Pagamento",
                                    ),
                                ]),
                        ]),

                    // 🚀 ABA 2 (ANTIGA EXTRATO & SALDO) FOI REMOVIDA DAQUI!

                    // Aba 3 (Agora 2): Histórico de Consumo
                    Tabs\Tab::make("Histórico de Consumo")
                        ->icon("heroicon-o-shopping-bag")
                        ->schema([
                            Livewire::make(EmployeeConsumptions::class)->key(
                                "employee-consumptions-widget",
                            ),
                        ]),
                ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            // A nossa "Mesa de Operações Financeiras" do colaborador fica aqui embaixo
            \App\Filament\Resources\Employees\RelationManagers\LedgersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            "index" => Pages\ListEmployees::route("/"),
            "create" => Pages\CreateEmployee::route("/create"),
            "view" => Pages\ViewEmployee::route("/{record}"),
            "edit" => Pages\EditEmployee::route("/{record}/edit"),
        ];
    }
}
