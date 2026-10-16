<?php

namespace App\Filament\Resources\Checklists\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Utilities\Get;
use App\Models\User;

class ChecklistForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(self::getFormSchema());
    }

    public static function getFormSchema(): array
    {
        return [
            // =========================================================
            // 1. A ROTINA (AGRUPADOR)
            // =========================================================
            Section::make('Identificação do Grupo de Tarefas')
                ->description('Esta é a "Pasta" que organizará as tarefas. As regras de quem faz e quando faz são definidas em cada tarefa abaixo.')
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('title')
                            ->label('Nome do Grupo (Rotina)')
                            ->placeholder('Ex: Limpeza da Cozinha, Fechamento de Caixa')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),

                        Toggle::make('is_active')
                            ->label('Grupo Ativo')
                            ->default(true)
                            ->inline(false)
                            ->columnSpan(1),
                    ]),
                ]),

            // =========================================================
            // 2. AS TAREFAS INTELIGENTES
            // =========================================================
            Section::make('Tarefas')
                ->description('Cadastre cada atividade especificando as suas próprias regras de atribuição e recorrência.')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('tasks')
                        ->relationship()
                        ->hiddenLabel()
                        ->addActionLabel('Adicionar Nova Tarefa')
                        ->orderColumn('sort_order')
                        ->reorderableWithButtons()
                        ->collapsible()
                        ->cloneable()
                        ->itemLabel(fn (array $state): ?string => $state['description'] ?? 'Nova Tarefa')
                        ->schema([

                        // Ação Principal
                        TextInput::make('description')
                            ->label('O que deve ser feito?')
                            ->placeholder('Ex: Limpar a chapa, Contar o fundo de caixa')
                            ->required()
                            ->columnSpanFull(),

                        \Filament\Forms\Components\Textarea::make('instructions')
                            ->label('Instruções / Detalhes (Opcional)')
                            ->placeholder('Detalhes de como executar a tarefa...')
                            ->rows(2)
                            ->columnSpanFull(),

                        // =========================================================
                        // 🚀 NOVO: VÍNCULO COM INVENTÁRIO (Auditoria Rápida)
                        // =========================================================
                        Toggle::make('is_inventory_task')
                            ->label('Vincular a um Item de Estoque (Auditoria Rápida)')
                            ->live()
                            ->columnSpanFull(),

                        Grid::make(12)
                            ->visible(fn (Get $get) => $get('is_inventory_task') === true)
                            ->schema([
                                Select::make('inventory_item_type')
                                    ->label('Tipo de Item')
                                    ->options([
                                        'App\\Models\\Product' => 'Produto ou Insumo (Revenda, Prato, Ingrediente)',
                                        'App\\Models\\Supply'  => 'Material de Uso / Suprimento',
                                    ])
                                    ->live()
                                    ->required(fn (Get $get) => $get('is_inventory_task'))
                                    ->columnSpan(4),

                                // 🎯 Select Dinâmico: Se escolheu "Product" (Pratos/Insumos)
                                Select::make('inventory_item_id')
                                    ->label('Buscar Produto/Insumo')
                                    ->options(function () {
                                        return \App\Models\Product::orderBy('name')
                                            ->get()
                                            ->mapWithKeys(fn ($p) => [$p->id => "{$p->name} ({$p->type})"])
                                            ->toArray();
                                    })
                                    ->searchable()
                                    ->visible(fn (Get $get) => $get('inventory_item_type') === 'App\\Models\\Product')
                                    ->required(fn (Get $get) => $get('inventory_item_type') === 'App\\Models\\Product')
                                    ->columnSpan(8),

                                // 🎯 Select Dinâmico: Se escolheu "Supply" (Suprimentos)
                                Select::make('inventory_item_id')
                                    ->label('Buscar Suprimento')
                                    ->options(function () {
                                        return \App\Models\Supply::orderBy('name')
                                            ->pluck('name', 'id')
                                            ->toArray();
                                    })
                                    ->searchable()
                                    ->visible(fn (Get $get) => $get('inventory_item_type') === 'App\\Models\\Supply')
                                    ->required(fn (Get $get) => $get('inventory_item_type') === 'App\\Models\\Supply')
                                    ->columnSpan(8),
                            ]),

                        // Bloco: QUEM FAZ
                        Grid::make(12)
                            ->schema([
                                Select::make('assignment_type')
                                    ->label('Quem deve fazer?')
                                    ->options([
                                        'all' => 'Todos',
                                        'roles' => 'Cargos Específicos',
                                        'users' => 'Pessoas Específicas',
                                    ])
                                    ->default('all')
                                    ->live()
                                    ->required()
                                    ->columnSpan(4),

                                Select::make('target_roles')
                                    ->label('Quais Cargos?')
                                    ->multiple()
                                    ->options([
                                        'kitchen' => 'Cozinha (KDS)',
                                        'waiter' => 'Garçons / Salão',
                                        'cashier' => 'Caixa / Atendimento',
                                        'driver' => 'Entregadores',
                                        'admin' => 'Gestão',
                                    ])
                                    ->visible(fn (Get $get) => $get('assignment_type') === 'roles')
                                    ->required(fn (Get $get) => $get('assignment_type') === 'roles')
                                    ->columnSpan(4),

                                Select::make('target_users')
                                    ->label('Quais Pessoas?')
                                    ->multiple()
                                    ->options(fn () => User::where('is_active', true)->pluck('name', 'id'))
                                    ->searchable()
                                    ->visible(fn (Get $get) => $get('assignment_type') === 'users')
                                    ->required(fn (Get $get) => $get('assignment_type') === 'users')
                                    ->columnSpan(4),

                                Select::make('completion_type')
                                    ->label('Dinâmica de Conclusão')
                                    ->options([
                                        'shared' => 'Compartilhada (Um faz por todos da equipe)',
                                        'individual' => 'Individual (Cada pessoa selecionada precisa fazer a sua)',
                                    ])
                                    ->default('shared')
                                    ->visible(fn (Get $get) => in_array($get('assignment_type'), ['all', 'roles', 'users']))
                                    ->columnSpan(4),
                            ]),

                        // Bloco: QUANDO FAZ E DEADLINES
                        Grid::make(12)
                            ->schema([
                                Select::make('trigger_type')
                                    ->label('Momento do Disparo')
                                    ->options([
                                        'manual' => 'Livre (Fica disponível o dia todo)',
                                        'shift_start' => 'Vinculado à Abertura do Turno',
                                        'shift_end' => 'Vinculado ao Fechamento do Turno',
                                        'scheduled' => 'Agendado (Horário Fixo)',
                                    ])
                                    ->default('manual')
                                    ->live()
                                    ->required()
                                    ->columnSpan(4),

                                TimePicker::make('trigger_time')
                                    ->label('Horário Exato')
                                    ->required(fn (Get $get) => $get('trigger_type') === 'scheduled')
                                    ->visible(fn (Get $get) => $get('trigger_type') === 'scheduled')
                                    ->seconds(false)
                                    ->columnSpan(4),

                                TextInput::make('deadline_minutes')
                                    ->label('Tolerância (Minutos)')
                                    ->numeric()
                                    ->default(60)
                                    ->helperText(fn (Get $get) =>
                                        $get('trigger_type') === 'shift_start'
                                            ? 'Tempo limite após a Abertura do Turno (baseado nos horários da loja).'
                                            : 'Tempo limite após o Fechamento do Turno (baseado nos horários da loja).'
                                    )
                                    ->visible(fn (Get $get) => in_array($get('trigger_type'), ['shift_start', 'shift_end']))
                                    ->required(fn (Get $get) => in_array($get('trigger_type'), ['shift_start', 'shift_end']))
                                    ->columnSpan(4),

                                Select::make('recurrence_type')
                                    ->label('Recorrência')
                                    ->options([
                                        'daily' => 'Diária (Todos os dias)',
                                        'weekly' => 'Semanal (Dias específicos)',
                                        'monthly' => 'Mensal (Dias específicos)',
                                        'once' => 'Pontual (Uma única vez)',
                                    ])
                                    ->default('daily')
                                    ->live()
                                    ->required()
                                    ->columnSpan(4),

                                Select::make('recurrence_days')
                                    ->label('Dias da semana')
                                    ->multiple()
                                    ->options([
                                        0 => 'Domingo', 1 => 'Segunda-feira', 2 => 'Terça-feira',
                                        3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado',
                                    ])
                                    ->visible(fn (Get $get) => $get('recurrence_type') === 'weekly')
                                    ->required(fn (Get $get) => $get('recurrence_type') === 'weekly')
                                    ->columnSpan(8),

                                Select::make('recurrence_days')
                                    ->label('Dias do mês')
                                    ->multiple()
                                    ->options(array_combine(range(1, 31), range(1, 31)))
                                    ->visible(fn (Get $get) => $get('recurrence_type') === 'monthly')
                                    ->required(fn (Get $get) => $get('recurrence_type') === 'monthly')
                                    ->columnSpan(8),

                                DateTimePicker::make('deadline_date')
                                    ->label('Data e Hora Limite (Deadline)')
                                    ->helperText('Prazo final irrevogável para a conclusão desta tarefa pontual.')
                                    ->visible(fn (Get $get) => $get('recurrence_type') === 'once')
                                    ->required(fn (Get $get) => $get('recurrence_type') === 'once')
                                    ->seconds(false)
                                    ->columnSpan(4),
                            ]),
                        ]),
                ]),
        ];
    }
}
