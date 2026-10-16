<?php

namespace App\Filament\Pages\ImportInvoice\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Actions\Action;
use Illuminate\Support\HtmlString;
use Filament\Notifications\Notification;

class ItemsSection
{
    public static function make(): Section
    {
        return Section::make('Itens da Nota')
            ->description('Associe os produtos do XML aos seus insumos e valide as conversões.')
            ->icon('heroicon-m-clipboard-document-list')
            ->headerActions([self::getMarkAllPersonalAction()])
            ->schema([
                self::getSuccessPlaceholder(),

                Repeater::make("xml_items")
                    ->hiddenLabel()
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->visible(fn(Get $get) => !empty($get("xml_items")))
                    ->itemLabel(fn(array $state): ?string => $state["xml_name"] ?? null)
                    ->columns(6)
                    ->schema(ItemRowSchema::schema()) // 🚀 Delega os campos para a classe filha!
            ]);
    }

    private static function getSuccessPlaceholder(): Placeholder
    {
        return Placeholder::make('all_processed_success')
            ->hiddenLabel()
            ->visible(fn (Get $get) => empty($get('xml_items')))
            ->content(new HtmlString('
                <div class="flex flex-col items-center justify-center p-8 text-center rounded-2xl bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/20">
                    <div class="w-16 h-16 bg-green-100 dark:bg-green-500/20 text-green-600 dark:text-green-400 rounded-full flex items-center justify-center mb-4 shadow-sm">
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.125 2.25h-4.5c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125v-9M10.125 2.25h.375a9 9 0 0 1 9 9v.375M10.125 2.25A3.375 3.375 0 0 1 13.5 5.625v1.5c0 .621.504 1.125 1.125 1.125h1.5a3.375 3.375 0 0 1 3.375 3.375M9 15l2.25 2.25L15 12" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-medium text-green-800 dark:text-green-400">Tudo Pronto!</h3>
                    <p class="text-sm text-green-700 dark:text-green-300 mt-2 max-w-lg">
                        Todos os itens desta nota foram processados e direcionados com sucesso.
                        Agora basta clicar no botão azul <strong>"Finalizar Importação"</strong> logo abaixo para concluir o processo e voltar à tela inicial.
                    </p>
                </div>
            '));
    }

    private static function getMarkAllPersonalAction(): Action
    {
        return Action::make("marcar_todos_pessoal")
            ->label("Marcar Tudo como Despesa Pessoal")
            ->icon("heroicon-m-banknotes")
            ->color("warning")
            ->visible(fn(Get $get) => !empty($get("xml_items")))
            ->requiresConfirmation()
            ->modalHeading("Ação em Lote")
            ->modalDescription('Isto irá marcar TODOS os itens pendentes como "Uso Pessoal", limpando qualquer produto associado. Deseja continuar?')
            ->action(function (Get $get, Set $set) {
                $items = $get("xml_items") ?? [];
                if (empty($items)) return;
                foreach ($items as $key => $item) {
                    $items[$key]["is_personal"] = true;
                    $items[$key]["product_id"] = null;
                    $items[$key]["confirmation_step"] = 0;
                }
                $set("xml_items", $items);
                Notification::make()->title("Todos os itens marcados como Pessoal!")->success()->send();
            });
    }
}
