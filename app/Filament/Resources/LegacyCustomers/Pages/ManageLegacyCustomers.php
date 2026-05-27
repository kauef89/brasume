<?php

namespace App\Filament\Resources\LegacyCustomers\Pages;

use App\Filament\Resources\LegacyCustomers\LegacyCustomerResource;
use App\Models\Customer;
use App\Models\LegacyCustomer;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageLegacyCustomers extends ManageRecords
{
    protected static string $resource = LegacyCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // 🚀 1. MATCH EXATO (Mantido)
            Action::make("auto_match")
                ->label("Match Automático")
                ->icon("heroicon-o-bolt")
                ->color("success")
                ->requiresConfirmation()
                ->modalHeading("Vincular Clientes Automaticamente")
                ->modalDescription(
                    "O sistema procurará clientes na V3 com o nome exato da V1. Se encontrar apenas 1 cliente correspondente, fará o vínculo automático.",
                )
                ->modalSubmitActionLabel("Iniciar Match")
                ->action(function () {
                    $unmappedCustomers = LegacyCustomer::whereNull(
                        "customer_id",
                    )
                        ->where("is_ignored", false)
                        ->get();
                    $matchCount = 0;

                    foreach ($unmappedCustomers as $legacy) {
                        $cleanName = trim($legacy->legacy_name);
                        $v3Matches = Customer::where("name", $cleanName)->get();

                        if ($v3Matches->count() === 1) {
                            $legacy->update([
                                "customer_id" => $v3Matches->first()->id,
                            ]);
                            $matchCount++;
                        }
                    }

                    if ($matchCount > 0) {
                        Notification::make()
                            ->title("Match Concluído!")
                            ->body(
                                "🎉 <b>{$matchCount} clientes</b> foram vinculados automaticamente.",
                            )
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title("Nenhum match exato encontrado.")
                            ->info()
                            ->send();
                    }
                }),

            // 🚀 2. MATCH INTELIGENTE (COM EFEITO ESTEIRA E PHPACTOR FELIZ)
            Action::make("smart_match")
                ->label("Match Inteligente (Checklist)")
                ->icon("heroicon-o-clipboard-document-check")
                ->color("warning")
                ->modalHeading("Sugestões de Mapeamento (Lote de 10 registros)")
                ->modalDescription(
                    'Avalie as sugestões de nomes semelhantes abaixo. Se marcar "Pular", o cliente não aparecerá mais nesta lista.',
                )
                ->modalSubmitActionLabel("Confirmar Lote e Avançar")
                ->modalWidth("5xl")
                ->form(function () {
                    // 🚀 Adicionado o query() para o PHPactor não reclamar
                    $unmappedLegacy = LegacyCustomer::query()
                        ->whereNull("customer_id")
                        ->where("is_ignored", false)
                        ->get();

                    if ($unmappedLegacy->isEmpty()) {
                        return [
                            Placeholder::make("empty")
                                ->hiddenLabel()
                                ->content(
                                    "Não há clientes pendentes para mapear.",
                                ),
                        ];
                    }

                    $allV3Customers = Customer::all(["id", "name"]);
                    $suggestions = [];
                    $limitCounter = 0;

                    foreach ($unmappedLegacy as $legacy) {
                        if ($limitCounter >= 10) {
                            break;
                        }

                        $legacyName = trim($legacy->legacy_name);
                        $legacyParts = array_values(
                            array_filter(explode(" ", $legacyName)),
                        );

                        if (count($legacyParts) < 2) {
                            continue;
                        }

                        $legacyFirst = mb_strtolower($legacyParts[0]);
                        $legacyLast = mb_strtolower(end($legacyParts));

                        $matches = [];
                        foreach ($allV3Customers as $v3) {
                            $v3Name = trim($v3->name);
                            $v3Parts = array_values(
                                array_filter(explode(" ", $v3Name)),
                            );

                            if (count($v3Parts) < 2) {
                                continue;
                            }

                            $v3First = mb_strtolower($v3Parts[0]);
                            $v3Last = mb_strtolower(end($v3Parts));

                            if (
                                $legacyFirst === $v3First &&
                                $legacyLast === $v3Last
                            ) {
                                $matches[$v3->id] = "🤝 " . $v3Name;
                            }
                        }

                        if (!empty($matches)) {
                            $suggestions[$legacy->id] = [
                                "legacy_name" => $legacyName,
                                "matches" => $matches,
                            ];
                            $limitCounter++;
                        }
                    }

                    if (empty($suggestions)) {
                        return [
                            Placeholder::make("empty")
                                ->hiddenLabel()
                                ->content(
                                    "Nenhuma sugestão inteligente pendente com esta regra.",
                                ),
                        ];
                    }

                    $schema = [];
                    foreach ($suggestions as $legacyId => $data) {
                        $options = $data["matches"];
                        $options["skip"] = "Pular";

                        $schema[] = Grid::make(12)
                            ->extraAttributes([
                                "class" =>
                                    "items-center py-2 border-b border-gray-100 dark:border-gray-800 gap-4",
                            ])
                            ->schema([
                                Placeholder::make("label_{$legacyId}")
                                    ->hiddenLabel()
                                    ->content($data["legacy_name"])
                                    ->extraAttributes([
                                        "class" =>
                                            "text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase truncate pt-1",
                                    ])
                                    ->columnSpan(4),

                                ToggleButtons::make("match_{$legacyId}")
                                    ->hiddenLabel()
                                    ->options($options)
                                    ->default("skip")
                                    ->inline()
                                    ->icons([
                                        "skip" => "heroicon-m-x-mark",
                                    ])
                                    ->extraAttributes([
                                        "class" =>
                                            "ms-auto flex justify-end max-w-max",
                                    ])
                                    ->columnSpan(8),
                            ]);
                    }

                    return [
                        Section::make("Painel de Mapeamento Rápido")
                            ->description(
                                "Os nomes à esquerda são da V1, os botões à direita são as opções da V3.",
                            )
                            ->schema([Grid::make(1)->schema($schema)->gap(0)]),
                    ];
                })
                ->action(function (array $data) {
                    $countMatched = 0;
                    $countIgnored = 0;

                    foreach ($data as $key => $value) {
                        if (str_starts_with($key, "match_")) {
                            $legacyId = str_replace("match_", "", $key);

                            if ($value === "skip") {
                                // 🚀 query() adicionado
                                LegacyCustomer::query()
                                    ->where("id", $legacyId)
                                    ->update(["is_ignored" => true]);
                                $countIgnored++;
                            } else {
                                // 🚀 query() adicionado
                                LegacyCustomer::query()
                                    ->where("id", $legacyId)
                                    ->update(["customer_id" => $value]);
                                $countMatched++;
                            }
                        }
                    }

                    Notification::make()
                        ->title("Lote Processado")
                        ->body(
                            "<b>{$countMatched}</b> vinculados e <b>{$countIgnored}</b> ignorados.",
                        )
                        ->success()
                        ->send();
                })
                // 🚀 O SEGREDO ESTÁ AQUI: O HOOK "AFTER" (Roda depois que o modal principal fecha)
                ->after(function (\Livewire\Component $livewire) {
                    $hasMore = false;
                    $unmappedLegacy = LegacyCustomer::query()
                        ->whereNull("customer_id")
                        ->where("is_ignored", false)
                        ->get();
                    $allV3Customers = Customer::all(["id", "name"]);

                    foreach ($unmappedLegacy as $legacy) {
                        $legacyParts = array_values(
                            array_filter(
                                explode(" ", trim($legacy->legacy_name)),
                            ),
                        );
                        if (count($legacyParts) < 2) {
                            continue;
                        }
                        $legacyFirst = mb_strtolower($legacyParts[0]);
                        $legacyLast = mb_strtolower(end($legacyParts));

                        foreach ($allV3Customers as $v3) {
                            $v3Parts = array_values(
                                array_filter(explode(" ", trim($v3->name))),
                            );
                            if (count($v3Parts) < 2) {
                                continue;
                            }
                            if (
                                $legacyFirst === mb_strtolower($v3Parts[0]) &&
                                $legacyLast === mb_strtolower(end($v3Parts))
                            ) {
                                $hasMore = true;
                                break 2;
                            }
                        }
                    }

                    if ($hasMore) {
                        // 🚀 Reabre a ação, criando o "Efeito Esteira" perfeito e dinâmico!
                        $livewire->mountAction("smart_match");
                    }
                }),

            CreateAction::make()->modalWidth("md"),
        ];
    }
}
