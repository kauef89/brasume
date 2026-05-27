/**
 * Formata um valor numérico para o padrão de moeda brasileiro (BRL).
 */
export const formatCurrency = (value) => {
    return new Intl.NumberFormat("pt-BR", {
        style: "currency",
        currency: "BRL",
    }).format(value);
};

/**
 * Converte um número decimal numa fração legível ou num decimal mais "limpo".
 * Ideal para exibir rendimentos de receitas (Ex: 1.5 -> 1 1/2)
 */
export const formatFraction = (decimalValue) => {
    if (typeof decimalValue !== "number" || isNaN(decimalValue)) return "0";

    if (Number.isInteger(decimalValue)) {
        return decimalValue.toString();
    }

    const integerPart = Math.floor(decimalValue);
    const fractionalPart = decimalValue - integerPart;

    // Tolerância para erros de ponto flutuante (ex: 0.3333333333333333)
    const tolerance = 1.0e-6;

    let fractionString = "";

    if (Math.abs(fractionalPart - 0.5) < tolerance) {
        fractionString = "1/2";
    } else if (Math.abs(fractionalPart - 0.25) < tolerance) {
        fractionString = "1/4";
    } else if (Math.abs(fractionalPart - 0.75) < tolerance) {
        fractionString = "3/4";
    } else if (Math.abs(fractionalPart - 0.333333) < tolerance) {
        fractionString = "1/3";
    } else if (Math.abs(fractionalPart - 0.666666) < tolerance) {
        fractionString = "2/3";
    } else {
        // Se não for uma fração "famosa", devolve o decimal limpo com 2 ou 3 casas
        let decimalStr = decimalValue.toFixed(3);
        // Remove zeros à direita e o ponto/vírgula se ficar sozinho
        decimalStr = decimalStr.replace(/0+$/, "").replace(/\.$/, "");
        return decimalStr.replace(".", ",");
    }

    if (integerPart === 0) {
        return fractionString;
    }

    return `${integerPart} ${fractionString}`;
};

/**
 * Formata um número de telefone (Fixo ou Celular do Brasil)
 */
export const formatPhone = (phone) => {
    if (!phone) return "";
    const clean = phone.replace(/\D/g, ""); // Remove tudo que não for número

    if (clean.length === 11) {
        // Celular: (XX) 9 XXXX-XXXX
        return clean.replace(/^(\d{2})(\d{1})(\d{4})(\d{4})$/, "($1) $2 $3-$4");
    } else if (clean.length === 10) {
        // Fixo: (XX) XXXX-XXXX
        return clean.replace(/^(\d{2})(\d{4})(\d{4})$/, "($1) $2-$3");
    }

    // Se tiver um tamanho esquisito, devolve como está (para não destruir números internacionais)
    return phone;
};

/**
 * Formata CPF ou CNPJ
 */
export const formatCpfCnpj = (doc) => {
    if (!doc) return "";
    const clean = doc.replace(/\D/g, "");

    if (clean.length === 11) {
        return clean.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})$/, "$1.$2.$3-$4");
    } else if (clean.length === 14) {
        return clean.replace(
            /^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/,
            "$1.$2.$3/$4-$5",
        );
    }
    return doc;
};

/**
 * Abrevia o nome (Primeiro Nome + Inicial do Último Sobrenome)
 * Ex: "João Silva Sauro" -> "João S."
 * Nota: Use apenas para Pessoas Físicas (Clientes/Operadores).
 */
export const abbreviateName = (fullName) => {
    if (!fullName) return "";

    // Separa por espaços, ignorando múltiplos espaços acidentais
    const parts = fullName.trim().split(/\s+/);

    if (parts.length === 1) return parts[0]; // Se for só "João", devolve "João"

    const firstName = parts[0];
    const lastNameInitial = parts[parts.length - 1].charAt(0).toUpperCase();

    return `${firstName} ${lastNameInitial}.`;
};
