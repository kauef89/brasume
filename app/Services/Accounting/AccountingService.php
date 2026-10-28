<?php

namespace App\Services\Accounting;

use App\Enums\AccountingCategory;

class AccountingService
{
    public static function getCategories(): array
    {
        $categories = [];
        foreach (AccountingCategory::cases() as $case) {
            $categories[$case->value] = $case->getLabel();
        }
        return $categories;
    }

    public static function getSubcategories(?string $category): array
    {
        $subcategories = [
            AccountingCategory::INGREDIENTS->value => [
                'general'   => 'Geral / Mercearia',
                'meats'     => 'Carnes & Recheios',
                'dairy'     => 'Queijos & Laticínios',
                'bakery'    => 'Massa & Farinhas',
                'produce'   => 'Hortifrúti',
                'beverages' => 'Bebidas',
                'seafood'   => 'Pescados & Frutos do Mar',
            ],
            AccountingCategory::PACKAGING->value => [
                'cardboard'   => 'Caixas & Papelão',
                'bags'        => 'Sacos & Plásticos',
                'disposables' => 'Descartáveis & Guardanapos',
            ],
            AccountingCategory::PAYROLL->value => [
                'payroll'     => 'Folha de Pagamento',
                'freelancers' => 'Diaristas & Freelancers',
                'transport'   => 'Vale Transporte',
                'food'        => 'Alimentação',
                'health'      => 'Saúde Ocupacional',
            ],
            AccountingCategory::UTILITIES->value => [
                'water'       => 'Água & Saneamento',
                'electricity' => 'Energia Elétrica',
                'internet'    => 'Internet & Telefonia',
                'gas'         => 'Gás GLP',
                'rent'        => 'Aluguel do Ponto',
            ],
            AccountingCategory::MAINTENANCE->value => [
                'equipment'   => 'Fritadeiras & Equipamentos',
                'building'    => 'Predial & Reformas Leves',
                'cleaning'    => 'Produtos de Limpeza',
            ],
            AccountingCategory::MARKETING->value => [
                'ads'         => 'Tráfego Pago & Anúncios',
                'print'       => 'Panfletos & Cardápios',
                'events'      => 'Ações & Eventos',
            ],
            AccountingCategory::ADMINISTRATIVE->value => [
                'software'    => 'Sistemas & Assinaturas',
                'accounting'  => 'Contabilidade',
                'bank_fees'   => 'Taxas Bancárias',
                'taxes'       => 'Impostos',
                'office'      => 'Material de Escritório',
            ],
            AccountingCategory::CAPEX->value => [
                'facade'      => 'Fachada & Comunicação Visual',
                'machinery'   => 'Máquinas Pesadas / Câmaras',
                'furniture'   => 'Mesas, Cadeiras & Mobiliário',
            ],
            AccountingCategory::PARTNERS->value => [
                'personal'    => 'Despesa Pessoal / Cartão Familiar',
                'drawings'    => 'Retirada de Lucro / Pró-labore',
            ],
        ];

        return $subcategories[$category] ?? [];
    }
}
