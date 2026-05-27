<div class="pb-6">
    {{-- Aqui nós injetamos o nosso Widget mágico dentro da aba! --}}
    @livewire(\App\Filament\Resources\Products\Widgets\ProductSimulatorWidget::class, ['record' => $record])
</div>
