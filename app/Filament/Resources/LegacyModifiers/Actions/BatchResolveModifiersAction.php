<?php

namespace App\Filament\Resources\LegacyModifiers\Actions;

use App\Models\LegacyModifier;
use App\Models\ModifierOption;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Hidden;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

class BatchResolveModifiersAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return "batch_resolve_modifiers";
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label("Mapeamento Inteligente")
            ->icon("heroicon-o-sparkles")
            ->color("primary")
            ->modalHeading("Mapeamento em Lote (Sabores e Adicionais)")
            ->modalDescription("Verifique os cruzamentos abaixo. Se o sabor bater com o grupo correto, mantenha marcado!")
            ->modalSubmitActionLabel("Vincular Selecionados")
            ->modalWidth("3xl") // 🚀 Um pouco mais largo para caber o nome do grupo confortavelmente
            ->form(fn() => $this->getBatchForm())
            ->action(fn(array $data) => $this->performBatchAction($data));
    }

    protected function getBatchForm(): array
    {
        $matches = $this->getNextBatchMatches();

        if (empty($matches["items"])) {
            return [
                Placeholder::make("empty")
                    ->hiddenLabel()
                    ->content(new HtmlString('<div class="text-center text-gray-500 py-4">Nenhuma sugestão óbvia encontrada.<br>Os itens restantes precisam ser mapeados manualmente na tabela.</div>')),
            ];
        }

        $allOfferedKeys = collect($matches["items"])
            ->map(fn($m) => $m["legacy_id"] . "|" . $m["option_id"])
            ->toArray();

        return [
            Placeholder::make("confidence_badge")
                ->hiddenLabel()
                ->content(new HtmlString("<div class='text-lg font-medium text-primary-600 mb-2'>🔥 Lote de Confiança Atual: ~{$matches["confidence"]}%</div>")),

            CheckboxList::make("approved_matches")
                ->hiddenLabel()
                ->options(collect($matches["items"])->mapWithKeys(function ($match) {
                    // 🚀 Agora a exibição inclui o grupo da Opção! (Ex: Carne ➔ Carne (Sabores de Pastel))
                    return [$match["legacy_id"] . "|" . $match["option_id"] => "{$match["legacy_name"]} ➔ {$match["option_name"]}"];
                }))
                ->default($allOfferedKeys)
                ->bulkToggleable()
                ->columns(1)
                ->extraAttributes([
                    // 🚀 ROLAGEM INTERNA: A lista agora rola por dentro, preservando o modal!
                    'class' => 'max-h-[60vh] overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-xl p-4 bg-gray-50 dark:bg-gray-900 shadow-inner'
                ]),

            Hidden::make("all_suggested_pairs")->default(json_encode($allOfferedKeys)),
        ];
    }

    protected function performBatchAction(array $data): void
    {
        $approved = $data["approved_matches"] ?? [];
        $allSuggested = json_decode($data["all_suggested_pairs"] ?? '[]', true);

        $rejected = array_diff($allSuggested, $approved);

        if (!empty($rejected)) {
            $cachedRejects = session()->get('rejected_legacy_mod_matches', []);
            $mergedRejects = array_unique(array_merge($cachedRejects, array_values($rejected)));
            session()->put('rejected_legacy_mod_matches', $mergedRejects);
        }

        $count = 0;
        foreach ($approved as $combined) {
            [$legacyId, $optionId] = explode("|", $combined);
            LegacyModifier::where("id", $legacyId)->update([
                "modifier_option_id" => $optionId,
                "is_ignored" => false,
            ]);
            $count++;
        }

        Notification::make()
            ->title("{$count} opções vinculadas com sucesso!")
            ->success()
            ->send();
    }

    private function getNextBatchMatches(): array
    {
        $pendingLegacy = LegacyModifier::whereNull("modifier_option_id")
            ->where("is_ignored", false)
            ->get();

        if ($pendingLegacy->isEmpty()) return ["confidence" => 0, "items" => []];

        // 🚀 O JOIN MÁGICO: Traz o nome da opção e o nome do grupo ("modifier_groups") ao qual ela pertence
        $options = ModifierOption::join('modifier_groups', 'modifier_groups.id', '=', 'modifier_options.modifier_group_id')
            ->select('modifier_options.id', 'modifier_options.name as option_name', 'modifier_groups.name as group_name')
            ->get();

        if ($options->isEmpty()) return ["confidence" => 0, "items" => []];

        $rejectedPairs = session()->get('rejected_legacy_mod_matches', []);
        $allBestMatches = [];

        foreach ($pendingLegacy as $legacy) {
            $bestMatch = null;
            $highestSim = 0;
            $legacyName = strtoupper(trim($legacy->name));

            foreach ($options as $opt) {
                $pairKey = $legacy->id . "|" . $opt->id;
                if (in_array($pairKey, $rejectedPairs)) continue;

                $optName = strtoupper(trim($opt->option_name));
                similar_text($legacyName, $optName, $percent);

                if (str_contains($optName, $legacyName) || str_contains($legacyName, $optName)) {
                    $percent += 20;
                }

                if ($percent > $highestSim) {
                    $highestSim = $percent;
                    $bestMatch = $opt;
                }
                if ($highestSim >= 100) break;
            }

            if ($bestMatch && $highestSim >= 50) {
                $groupSim = floor($highestSim);
                if ($groupSim % 2 !== 0) $groupSim--;

                $allBestMatches[$groupSim][] = [
                    "legacy_id" => $legacy->id,
                    "legacy_name" => $legacy->name,
                    "option_id" => $bestMatch->id,
                    // 🚀 Montando o nome visual com o grupo!
                    "option_name" => $bestMatch->option_name . " (" . $bestMatch->group_name . ")",
                ];
            }
        }

        if (empty($allBestMatches)) return ["confidence" => 0, "items" => []];

        krsort($allBestMatches);
        $highestConfidence = array_key_first($allBestMatches);

        return [
            "confidence" => $highestConfidence > 100 ? 100 : $highestConfidence,
            "items" => $allBestMatches[$highestConfidence],
        ];
    }
}
