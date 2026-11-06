<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AccountingSubcategory: string implements HasLabel
{
    // 01. Insumos & Matéria-Prima (CMV)
    case GENERAL     = 'general';
    case MEATS       = 'meats';
    case DAIRY       = 'dairy';
    case BAKERY      = 'bakery';
    case PRODUCE     = 'produce';
    case BEVERAGES   = 'beverages';
    case SEAFOOD     = 'seafood';

    // 02. Embalagens (CMV)
    case CARDBOARD   = 'cardboard';
    case BAGS        = 'bags';
    case DISPOSABLES = 'disposables';

    // 03. Pessoal & Equipe
    case PAYROLL     = 'payroll';
    case FREELANCERS = 'freelancers';
    case TRANSPORT   = 'transport';
    case FOOD        = 'food';
    case HEALTH      = 'health';

    // 04. Utilidades & Ocupação (Infra)
    case WATER       = 'water';
    case ELECTRICITY = 'electricity';
    case INTERNET    = 'internet';
    case GAS         = 'gas';
    case RENT        = 'rent';

    // 05. Manutenção & Limpeza
    case EQUIPMENT   = 'equipment';
    case BUILDING    = 'building';
    case CLEANING    = 'cleaning';

    // 06. Marketing & Vendas
    case ADS         = 'ads';
    case PRINT       = 'print';
    case EVENTS      = 'events';

    // 07. Administrativo & Sistemas
    case SOFTWARE    = 'software';
    case ACCOUNTING  = 'accounting';
    case BANK_FEES   = 'bank_fees';
    case TAXES       = 'taxes';
    case OFFICE      = 'office';

    // 08. Investimentos / Fachada / Ativos (CAPEX)
    case FACADE      = 'facade';
    case MACHINERY   = 'machinery';
    case FURNITURE   = 'furniture';

    // 09. Uso Pessoal / Sócios
    case PERSONAL    = 'personal';
    case DRAWINGS    = 'drawings';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::GENERAL     => 'Geral / Mercearia',
            self::MEATS       => 'Carnes & Recheios',
            self::DAIRY       => 'Queijos & Laticínios',
            self::BAKERY      => 'Massa & Farinhas',
            self::PRODUCE     => 'Hortifrúti',
            self::BEVERAGES   => 'Bebidas',
            self::SEAFOOD     => 'Pescados & Frutos do Mar',

            self::CARDBOARD   => 'Caixas & Papelão',
            self::BAGS        => 'Sacos & Plásticos',
            self::DISPOSABLES => 'Descartáveis & Guardanapos',

            self::PAYROLL     => 'Folha de Pagamento',
            self::FREELANCERS => 'Diaristas & Freelancers',
            self::TRANSPORT   => 'Vale Transporte',
            self::FOOD        => 'Alimentação',
            self::HEALTH      => 'Saúde Ocupacional',

            self::WATER       => 'Água & Saneamento',
            self::ELECTRICITY => 'Energia Elétrica',
            self::INTERNET    => 'Internet & Telefonia',
            self::GAS         => 'Gás GLP',
            self::RENT        => 'Aluguel do Ponto',

            self::EQUIPMENT   => 'Fritadeiras & Equipamentos',
            self::BUILDING    => 'Predial & Reformas Leves',
            self::CLEANING    => 'Produtos de Limpeza',

            self::ADS         => 'Tráfego Pago & Anúncios',
            self::PRINT       => 'Panfletos & Cardápios',
            self::EVENTS      => 'Ações & Eventos',

            self::SOFTWARE    => 'Sistemas & Assinaturas',
            self::ACCOUNTING  => 'Contabilidade',
            self::BANK_FEES   => 'Taxas Bancárias',
            self::TAXES       => 'Impostos',
            self::OFFICE      => 'Material de Escritório',

            self::FACADE      => 'Fachada & Comunicação Visual',
            self::MACHINERY   => 'Máquinas Pesadas / Câmaras',
            self::FURNITURE   => 'Mesas, Cadeiras & Mobiliário',

            self::PERSONAL    => 'Despesa Pessoal / Cartão Familiar',
            self::DRAWINGS    => 'Retirada de Lucro / Pró-labore',
        };
    }

    public function category(): AccountingCategory
    {
        return match ($this) {
            self::GENERAL, self::MEATS, self::DAIRY, self::BAKERY, self::PRODUCE, self::BEVERAGES, self::SEAFOOD => AccountingCategory::INGREDIENTS,
            self::CARDBOARD, self::BAGS, self::DISPOSABLES => AccountingCategory::PACKAGING,
            self::PAYROLL, self::FREELANCERS, self::TRANSPORT, self::FOOD, self::HEALTH => AccountingCategory::PAYROLL,
            self::WATER, self::ELECTRICITY, self::INTERNET, self::GAS, self::RENT => AccountingCategory::UTILITIES,
            self::EQUIPMENT, self::BUILDING, self::CLEANING => AccountingCategory::MAINTENANCE,
            self::ADS, self::PRINT, self::EVENTS => AccountingCategory::MARKETING,
            self::SOFTWARE, self::ACCOUNTING, self::BANK_FEES, self::TAXES, self::OFFICE => AccountingCategory::ADMINISTRATIVE,
            self::FACADE, self::MACHINERY, self::FURNITURE => AccountingCategory::CAPEX,
            self::PERSONAL, self::DRAWINGS => AccountingCategory::PARTNERS,
        };
    }

    public static function forCategory(AccountingCategory|string|null $category): array
    {
        if (!$category) {
            return [];
        }

        $categoryValue = $category instanceof AccountingCategory ? $category->value : $category;

        return collect(self::cases())
            ->filter(fn(self $sub) => $sub->category()->value === $categoryValue)
            ->mapWithKeys(fn(self $sub) => [$sub->value => $sub->getLabel()])
            ->all();
    }
}