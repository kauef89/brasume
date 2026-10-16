<?php

namespace App\Filament\Resources\LegacyProducts\Actions;

use App\Models\LegacyProduct;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Hidden;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class BatchResolveProductsAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return "batch_resolve";
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label("Associar Automaticamente")
            ->icon("heroicon-o-sparkles")
            ->color("primary")
            ->modalHeading("Mapeamento em Lote")
            ->modalDescription(
                "O sistema agrupou os itens pendentes por similaridade e está a aprender com as suas associações anteriores. Desmarque o que não for correspondente.",
            )
            ->modalSubmitActionLabel("Vincular Selecionados")
            ->modalWidth("2xl")
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
                    ->content(
                        new HtmlString(
                            '<div class="text-center text-gray-500 py-4">Nenhuma sugestão óbvia encontrada (abaixo de 60%).<br>Os itens restantes precisam ser resolvidos manualmente.</div>',
                        ),
                    ),
            ];
        }

        $allOfferedKeys = collect($matches["items"])
            ->map(fn($m) => $m["legacy_id"] . "|" . $m["product_id"])
            ->toArray();

        return [
            Placeholder::make("confidence_badge")
                ->hiddenLabel()
                ->content(
                    new HtmlString(
                        "<div class='text-lg font-medium text-primary-600 mb-2'>🔥 Lote de Confiança Atual: ~{$matches["confidence"]}%</div>",
                    ),
                ),

            CheckboxList::make("approved_matches")
                ->hiddenLabel()
                ->options(
                    collect($matches["items"])->mapWithKeys(function ($match) {
                        return [
                            $match["legacy_id"] . "|" . $match["product_id"] => "{$match["legacy_name"]} ➔ {$match["product_name"]}",
                        ];
                    }),
                )
                ->default($allOfferedKeys)
                ->bulkToggleable()
                ->columns(1),

            Hidden::make("all_suggested_pairs")->default(json_encode($allOfferedKeys)),
        ];
    }

    protected function performBatchAction(array $data): void
    {
        $approved = $data["approved_matches"] ?? [];
        $allSuggested = json_decode($data["all_suggested_pairs"] ?? '[]', true);

        $rejected = array_diff($allSuggested, $approved);

        // 1. SESSÃO: Salvar a "Lista Negra" na sessão atual do usuário
        if (!empty($rejected)) {
            $cachedRejects = session()->get('rejected_legacy_matches', []);
            $mergedRejects = array_unique(array_merge($cachedRejects, array_values($rejected)));
            session()->put('rejected_legacy_matches', $mergedRejects);
        }

        // 2. GRAVAÇÃO DOS APROVADOS
        $count = 0;
        foreach ($approved as $combined) {
            [$legacyId, $productId] = explode("|", $combined);
            LegacyProduct::where("id", $legacyId)->update([
                "current_product_id" => $productId,
                "is_ignored" => false,
            ]);
            $count++;
        }

        Notification::make()
            ->title("{$count} itens vinculados com sucesso!")
            ->success()
            ->send();
    }

    /**
     * 🚀 O NORMALIZADOR: Remove acentos, pontuação, espaços duplos e atira para maiúsculas.
     */
    private function normalizeText(?string $text): string
    {
        if (!$text) return '';
        $text = Str::ascii($text); // Remove acentos (Á => A)
        $text = preg_replace('/[^A-Za-z0-9\s]/', ' ', $text); // Troca símbolos por espaço
        $text = preg_replace('/\s+/', ' ', $text); // Mata espaços duplos
        return strtoupper(trim($text));
    }

    private function getNextBatchMatches(): array
    {
        $pendingLegacy = LegacyProduct::whereNull("current_product_id")
            ->where("is_ignored", false)
            ->get();

        if ($pendingLegacy->isEmpty()) {
            return ["confidence" => 0, "items" => []];
        }

        $products = Product::select("id", "name")
            ->whereHas('category', function ($query) {
                $query->where('type', 'external');
            })
            ->get();

        if ($products->isEmpty()) {
            return ["confidence" => 0, "items" => []];
        }

        // 🚀 A MENTE COLETIVA: Puxa o histórico de resoluções para usar como "Apelidos"
        $resolvedLegacyNames = LegacyProduct::whereNotNull("current_product_id")
            ->where("is_ignored", false)
            ->get()
            ->groupBy('current_product_id')
            ->map(function ($items) {
                return $items->pluck('name')->toArray();
            });

        $rejectedPairs = session()->get('rejected_legacy_matches', []);
        $allBestMatches = [];

        foreach ($pendingLegacy as $legacy) {
            $bestMatch = null;
            $highestSim = 0;
            $normalizedLegacyName = $this->normalizeText($legacy->name);

            foreach ($products as $product) {
                $pairKey = $legacy->id . "|" . $product->id;

                if (in_array($pairKey, $rejectedPairs)) {
                    continue;
                }

                // 1. Testa contra o nome oficial do produto novo
                $normalizedProductName = $this->normalizeText($product->name);
                similar_text($normalizedLegacyName, $normalizedProductName, $percentOfficial);
                $localMaxSim = $percentOfficial;

                // 2. 🚀 Testa contra TODOS os apelidos que você já ensinou ao sistema
                if ($resolvedLegacyNames->has($product->id)) {
                    foreach ($resolvedLegacyNames->get($product->id) as $pastName) {
                        similar_text($normalizedLegacyName, $this->normalizeText($pastName), $percentAlias);
                        if ($percentAlias > $localMaxSim) {
                            $localMaxSim = $percentAlias;
                        }
                    }
                }

                if ($localMaxSim > $highestSim) {
                    $highestSim = $localMaxSim;
                    $bestMatch = $product;
                }

                // Atalho de performance: se bateu 100% com algo, não precisa olhar mais nada para este produto!
                if ($highestSim == 100) {
                    break;
                }
            }

            if ($bestMatch && $highestSim >= 60) {
                $groupSim = floor($highestSim);
                if ($groupSim % 2 !== 0) {
                    $groupSim--; // Agrupa aos pares
                }

                $allBestMatches[$groupSim][] = [
                    "legacy_id" => $legacy->id,
                    "legacy_name" => $legacy->name,
                    "product_id" => $bestMatch->id,
                    "product_name" => $bestMatch->name,
                ];
            }
        }

        if (empty($allBestMatches)) {
            return ["confidence" => 0, "items" => []];
        }

        krsort($allBestMatches);
        $highestConfidence = array_key_first($allBestMatches);

        return [
            "confidence" => $highestConfidence,
            "items" => $allBestMatches[$highestConfidence],
        ];
    }
}
