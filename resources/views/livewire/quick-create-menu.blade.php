<div class="mr-2">

    {{-- Renderiza as ações dentro de um Dropdown elegante --}}
    {{
        \Filament\Actions\ActionGroup::make([
            $this->createIngredientAction,
            $this->createSupplyAction,
            $this->createProductAction,
        ])
        ->label('Criar')
        ->icon('heroicon-o-plus-circle')
        ->color('primary')
        ->button()
    }}

    {{-- Motor obrigatório do Filament para renderizar Modals e Slide-overs --}}
    <x-filament-actions::modals />

</div>
