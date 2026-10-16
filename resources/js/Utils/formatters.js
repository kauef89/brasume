/**
 * Formata um valor numérico para o padrão de moeda brasileiro (BRL).
 * Trata tanto números nativos quanto strings BRL ("55,00") ou banco ("55.00").
 */
export const formatCurrency = (value) => {
    if (value === null || value === undefined || value === '') return 'R$ 0,00';

    let num = value;
    if (typeof value === 'string') {
        const cleaned = value.trim();
        if (cleaned.includes(',')) {
            // Formato brasileiro: "1.250,50" ou "55,00"
            num = Number(cleaned.replace(/\s/g, '').replace(/\./g, '').replace(',', '.'));
        } else {
            // Formato padrão/banco: "1250.50" ou "55.00"
            num = Number(cleaned);
        }
    }

    if (isNaN(num)) return 'R$ 0,00';

    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(num);
};

/**
 * 🚀 O NOVO DIVISOR GLOBAL DE MILHAR
 * Formata números inteiros adicionando o ponto como separador de milhar.
 * Ideal para IDs de pedidos, senhas visíveis, contadores ou quantidades inteiras (Ex: 1019 -> 1.019)
 */
export const formatNumber = (value) => {
    if (value === null || value === undefined || isNaN(value)) return '0';
    return new Intl.NumberFormat('pt-BR').format(value);
};

/**
 * 🚀 CALCULADORA BLINDADA DE FRAÇÕES E DECIMAIS
 * Converte um número decimal numa fração APENAS se for menor que 1 (Ex: 0.5 -> 1/2).
 * Se for maior que 1, mantém o formato decimal limpo com vírgula (Ex: 1.5 -> 1,5).
 */
export const formatFraction = (value, denominator = 1) => {
    // Força a conversão de String (Vinda do MySQL) para Número Javascript
    const num = Number(value);
    const den = Number(denominator);

    if (isNaN(num) || isNaN(den) || den === 0) return "0";

    const decimalValue = num / den;

    // Se for um número inteiro redondo (ex: 1, 2, 3), retorna direto
    if (Number.isInteger(decimalValue)) {
        return decimalValue.toString();
    }

    // 🚀 A REGRA DE NEGÓCIO (Se > 1, exibe como 1,5 / 2,3 etc)
    if (decimalValue > 1) {
        let decimalStr = decimalValue.toFixed(3);
        decimalStr = decimalStr.replace(/0+$/, "").replace(/\.$/, ""); // Remove zeros inúteis no fim
        return decimalStr.replace(".", ","); // Formato BR
    }

    // Se passou daqui, o valor é MENOR que 1. Vamos tentar achar uma fração "famosa".
    const tolerance = 1.0e-4; // Tolerância para erros de flutuação do JS

    if (Math.abs(decimalValue - 0.5) < tolerance) return "1/2";
    if (Math.abs(decimalValue - 0.25) < tolerance) return "1/4";
    if (Math.abs(decimalValue - 0.75) < tolerance) return "3/4";
    if (Math.abs(decimalValue - 0.3333) < tolerance) return "1/3";
    if (Math.abs(decimalValue - 0.6667) < tolerance) return "2/3";
    if (Math.abs(decimalValue - 0.2) < tolerance) return "1/5";

    // Fallback para valores < 1 que não se encaixem perfeitamente nas frações acima (Ex: 0.1)
    let decimalStr = decimalValue.toFixed(3);
    decimalStr = decimalStr.replace(/0+$/, "").replace(/\.$/, "");
    return decimalStr.replace(".", ",");
};

/**
 * Formata um número de telefone (Fixo ou Celular do Brasil).
 * Possui normalizador inteligente que limpa prefixos internacionais (+55)
 * e injeta o 9º dígito automaticamente caso o número antigo seja colado.
 *
 * Funciona perfeitamente tanto para exibição (tabelas) quanto para inputs (digitação).
 */
export const formatPhone = (value) => {
    if (!value) return '';
    let text = String(value).trim();

    // 🚀 Normalizador Inteligente (+55 e nono dígito)
    if (text.startsWith('+')) {
        let d = text.replace(/\D/g, '');
        // Se começar com DDI do Brasil
        if (d.startsWith('55')) {
            d = d.substring(2);
            // Se sobrou 10 dígitos (DDD + Número antigo), injeta o 9
            if (d.length === 10) {
                d = d.substring(0, 2) + '9' + d.substring(2);
            }
            text = d;
        }
    }

    // Extrai apenas os números resultantes
    let digits = text.replace(/\D/g, '');

    if (digits.length === 0) return '';

    // Digitação progressiva (útil para inputs)
    if (digits.length <= 2) return `(${digits}`;
    if (digits.length <= 6) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;

    // Telefone Fixo (10 dígitos)
    if (digits.length === 10) {
        return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`;
    }

    // Celular Padrão / Fallback progressivo (Limita estritamente a 11 dígitos)
    return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7, 11)}`;
};

/**
 * Formata CPF ou CNPJ aplicando as máscaras regulamentares da LGPD/Sefaz
 */
export const formatCpfCnpj = (value) => {
    if (!value) return '';
    const cleaned = value.replace(/\D/g, '');

    if (cleaned.length <= 11) {
        // CPF: 000.000.000-00
        return cleaned.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
    }
    // CNPJ: 00.000.000/0000-00
    return cleaned.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, "$1.$2.$3/$4-$5");
};

/**
 * Abrevia o nome (Primeiro Nome + Inicial do Último Sobrenome)
 * Ex: "João Silva Sauro" -> "João S."
 * Nota: Proteção de dados nativa (LGPD) para telas públicas ou de entregadores
 */
export const abbreviateName = (fullName) => {
    if (!fullName) return "";

    // Separa por espaços, ignorando múltiplos espaços acidentais
    const parts = fullName.trim().split(/\s+/);

    if (parts.length === 1) return parts[0];

    const firstName = parts[0];
    const lastNameInitial = parts[parts.length - 1].charAt(0).toUpperCase();

    return `${firstName} ${lastNameInitial}.`;
};

/**
 * Extrai as primeiras letras do primeiro e do último nome para avatares compactos
 * Ex: "Pastelaria Nativa" -> "PN"
 */
export const getInitials = (name) => {
    if (!name || typeof name !== 'string') return "?";
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
};

/**
 * Remove acentos, diacríticos (til, cedilha, agudo, circunflexo)
 * e converte para minúsculas para buscas insensíveis.
 * Ex: "São Sebastião" -> "sao sebastiao"
 */
export const normalizeSearchText = (text) => {
    return String(text || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();
};
