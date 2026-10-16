<?php

namespace App\Services\Accounting;

class AccountingService
{
    /**
     * Retorna as Categorias Principais do DRE
     * @return array<string,string>
     */
    public static function getCategories(): array
    {
        return [
            'insumos' => '01. Mercadorias (Insumos)',
            'embalagens' => '02. Embalagens',
            'equipe' => '03. Pessoal e Equipe',
            'infra' => '04. Utilidades',
            'manutencao' => '05. Manutenção',
            'marketing' => '06. Marketing',
            'adm' => '07. Adm e Impostos',
            'aquisicoes' => '08. Aquisições',
            'socios' => '09. Uso Pessoal / Sócios',
        ];
    }

    /**
     * Retorna as Subcategorias baseadas na Categoria selecionada
     * @return array|array<string,string>
     */
    public static function getSubcategories(?string $category): array
    {
        $subcategories = [
            'insumos' => [
                'geral' => 'Geral',
                'carnes' => 'Carnes',
                'hortifruti' => 'Hortifruti',
                'bebidas' => 'Bebidas',
                'mercearia' => 'Mercearia',
                'padaria' => 'Padaria',
                'laticinios' => 'Laticínios',
            ],
            'embalagens' => [
                'papelao' => 'Papelão',
                'plasticos' => 'Plásticos e Sacolas',
                'descartaveis' => 'Descartáveis',
            ],
            'equipe' => [
                'folha' => 'Folha de Pagamento',
                'transporte' => 'Vale Transporte',
                'alimentacao' => 'Alimentação',
                'extras' => 'Extras e Gorjetas',
                'saude' => 'Saúde Ocupacional'
            ],
            'infra' => [
                'agua' => 'Água / Esgoto',
                'energia' => 'Energia Elétrica',
                'internet' => 'Internet / Telefone',
                'gas' => 'Gás',
                'aluguel' => 'Aluguel',
            ],
            'manutencao' => [
                'equipamentos' => 'Equipamentos',
                'predial' => 'Predial',
                'veiculos' => 'Veículos',
                'limpeza' => 'Limpeza',
            ],
            'marketing' => [
                'trafego' => 'Tráfego Pago',
                'impressos' => 'Impressos',
                'eventos' => 'Eventos',
            ],
            'adm' => [
                'contabilidade' => 'Contabilidade',
                'sistemas' => 'Sistemas e Software',
                'taxas' => 'Taxas Bancárias',
                'impostos' => 'Impostos',
                'escritorio' => 'Material de Escritório',
            ],
            'aquisicoes' => [
                'maquinas' => 'Máquinas',
                'reformas' => 'Reformas',
                'mobiliario' => 'Mobiliário',
                'utilidades' => 'Utilidades (Copos, Panelas)',
            ],
            'socios' => [
                'pessoal' => 'Despesa Pessoal',
                'prolabore' => 'Adiantamento / Pró-labore',
            ],
        ];

        if (!$category || !isset($subcategories[$category])) {
            return [];
        }

        return $subcategories[$category];
    }
}
