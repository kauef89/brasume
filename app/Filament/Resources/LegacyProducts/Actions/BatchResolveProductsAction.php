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
                "O sistema agrupou os itens pendentes por similaridade. Desmarque o que não for correspondente. O sistema vai lembrar das suas rejeições no próximo lote.",
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
                        "<div class='text-lg font-bold text-primary-600 mb-2'>🔥 Lote de Confiança Atual: ~{$matches["confidence"]}%</div>",
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
            // Usa session() em vez de Cache, resolvendo o problema do stancl/tenancy
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

        // 🚀 Puxa a lista de rejeitados da Sessão do Usuário
        $rejectedPairs = session()->get('rejected_legacy_matches', []);

        $allBestMatches = [];

        foreach ($pendingLegacy as $legacy) {
            $bestMatch = null;
            $highestSim = 0;
            $legacyName = strtoupper(trim($legacy->name));

            foreach ($products as $product) {
                $pairKey = $legacy->id . "|" . $product->id;

                if (in_array($pairKey, $rejectedPairs)) {
                    continue;
                }

                similar_text(
                    $legacyName,
                    strtoupper(trim($product->name)),
                    $percent,
                );

                if ($percent > $highestSim) {
                    $highestSim = $percent;
                    $bestMatch = $product;
                }
                if ($highestSim == 100) {
                    break;
                }
            }

            if ($bestMatch && $highestSim >= 60) {
                $groupSim = floor($highestSim);
                if ($groupSim % 2 !== 0) {
                    $groupSim--;
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
