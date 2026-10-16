<?php

namespace App\Filament\Resources\LegacyProducts\Schemas;

use App\Models\Product;
use App\Models\LegacyProduct;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Notifications\Notification;

class LegacyProductForm
{
    protected static array $suggestionsMemory = [];

    public static function build(Schema $schema): Schema
    {
        return $schema->components([
            Hidden::make("confirming_sugg_id")
                ->dehydrated(false)
                ->default(null),

            // 🚀 SEÇÃO 1: Nome Original (Ocupando a tela toda, limpa e direta)
            Section::make("Dados do XML (Histórico)")
                ->columnSpanFull()
                ->schema([
                    TextInput::make("name")->label("Nome Original")->disabled(),
                ]),

            // 🚀 SEÇÃO 2: Mapeamento (Ocupando a tela toda, abaixo da Seção 1)
            Section::make("Mapeamento de Produto")
                ->description(
                    "Selecione um produto, use as sugestões ou marque para ignorar. Clique em Salvar para avançar.",
                )
                ->columnSpanFull()
                ->schema([
                    Select::make("current_product_id")
                        ->label("Pesquisa Manual")
                        ->relationship("currentProduct", "name")
                        ->searchable()
                        ->preload()
                        // 🚀 CHAMA O FORMULÁRIO COMPLETO COM UMA LINHA DE CÓDIGO!
                        ->createOptionForm(
                            \App\Filament\Resources\Products\Schemas\ProductForm::getFormSchema(),
                        )
                        // E para garantir que a grade interna do Modal fique certinha com 4 colunas:
                        ->createOptionModalHeading("Criar Novo Produto")
                        ->columns(4),

                    Toggle::make("is_ignored")
                        ->label("Ignorar este item")
                        ->helperText("Marque se virou adicional ou não importa mais.",
                        )
                        ->inline(false),

                    Actions::make([
                        self::buildSuggestionButton(1),
                        self::buildSuggestionButton(2),
                        self::buildSuggestionButton(3),
                    ])->columnSpanFull(),
                ]),
        ]);
    }

    private static function getSuggestions($record)
    {
        if (!$record || $record->current_product_id || $record->is_ignored) {
            return collect();
        }

        $key = $record->id;
        if (isset(self::$suggestionsMemory[$key])) {
            return self::$suggestionsMemory[$key];
        }

        $legacyName = strtoupper($record->name);

        $suggestions = Product::query()
            ->select("id", "name")
            ->get()
            ->map(function ($item) use ($legacyName) {
                similar_text($legacyName, strtoupper($item->name), $percent);
                $item->similarity = $percent;
                return $item;
            })
            ->filter(fn($item) => $item->similarity >= 65)
            ->sortByDesc("similarity")
            ->values()
            ->take(3);

        self::$suggestionsMemory[$key] = $suggestions;
        return $suggestions;
    }

    private static function buildSuggestionButton(int $index): Action
    {
        $arrayIndex = $index - 1;

        return Action::make("suggestion_btn_{$index}")
            ->button()
            ->hidden(function ($record) use ($arrayIndex) {
                if (
                    !$record ||
                    $record->current_product_id ||
                    $record->is_ignored
                ) {
                    return true;
                }
                return !isset(self::getSuggestions($record)[$arrayIndex]);
            })
            ->outlined(
                fn($get, $record) => $get("confirming_sugg_id") !==
                    (self::getSuggestions($record)[$arrayIndex]->id ?? null),
            )
            ->icon(function ($get, $record) use ($arrayIndex) {
                $sugg = self::getSuggestions($record)[$arrayIndex] ?? null;
                if (!$sugg) {
                    return "heroicon-m-check-circle";
                }
                return $get("confirming_sugg_id") === $sugg->id
                    ? "heroicon-m-check-badge"
                    : "heroicon-m-check-circle";
            })
            ->color(function ($get, $record) use ($arrayIndex) {
                $sugg = self::getSuggestions($record)[$arrayIndex] ?? null;
                if (!$sugg) {
                    return "info";
                }
                if ($get("confirming_sugg_id") === $sugg->id) {
                    return "success";
                }
                return $sugg->similarity >= 90 ? "success" : "info";
            })
            ->label(function ($get, $record) use ($arrayIndex) {
                $sugg = self::getSuggestions($record)[$arrayIndex] ?? null;
                if (!$sugg) {
                    return "";
                }
                if ($get("confirming_sugg_id") === $sugg->id) {
                    return "CONFIRMAR: {$sugg->name}";
                }
                return "{$sugg->name} (" . round($sugg->similarity) . "%)";
            })
            ->action(function ($get, $set, $record) use ($arrayIndex) {
                $sugg = self::getSuggestions($record)[$arrayIndex] ?? null;
                if (!$sugg) {
                    return;
                }

                if ($get("confirming_sugg_id") !== $sugg->id) {
                    $set("confirming_sugg_id", $sugg->id);
                    return;
                }

                $record->update([
                    "current_product_id" => $sugg->id,
                    "is_ignored" => false,
                ]);

                $set("confirming_sugg_id", null);
                return self::goToNextRecord($record);
            });
    }

    public static function goToNextRecord($record)
    {
        Notification::make()->title("Salvo!")->success()->send();

        $referer = request()->header("Referer");
        if (!$referer) {
            return redirect("/admin/legacy-products");
        }

        $urlParts = parse_url($referer);
        $baseUrl =
            ($urlParts["scheme"] ?? "https") .
            "://" .
            ($urlParts["host"] ?? "") .
            (isset($urlParts["port"]) ? ":" . $urlParts["port"] : "") .
            ($urlParts["path"] ?? "");
        parse_str($urlParts["query"] ?? "", $queryParams);

        $next = LegacyProduct::query()
            ->whereNull("current_product_id")
            ->where("is_ignored", false)
            ->where("id", "!=", $record->id)
            ->first();

        if ($next) {
            $queryParams["tableAction"] = "edit";
            $queryParams["tableActionRecord"] = $next->id;
        } else {
            unset(
                $queryParams["tableAction"],
                $queryParams["tableActionRecord"],
            );
            Notification::make()
                ->title("Fim da Fila!")
                ->body("Todos os produtos foram resolvidos.")
                ->success()
                ->send();
        }

        return redirect(
            $baseUrl .
                (count($queryParams) > 0
                    ? "?" . http_build_query($queryParams)
                    : ""),
        );
    }
}
