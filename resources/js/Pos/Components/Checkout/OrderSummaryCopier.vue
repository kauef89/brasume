<template>
    <Button
        :icon="copied ? 'pi pi-check' : 'pi pi-whatsapp'"
        :label="copied ? 'Copiado' : ''"
        :severity="copied ? 'success' : 'secondary'"
        :variant="copied ? 'filled' : 'outlined'"
        size="small"
        class="transition-all w-10 h-10 p-0 flex items-center justify-center overflow-hidden"
        :class="{ 'w-24': copied }"
        title="Copiar Resumo para WhatsApp"
        @click="copyToClipboard"
    />
</template>

<script setup>
import { ref, defineProps } from "vue";
import Button from "primevue/button";
import { formatCurrency } from "@/Utils/formatters";

const props = defineProps({
    cart: Array,
    metadata: Object,
    totals: Object,
});

const copied = ref(false);

const generateSummary = () => {
    let text = `*RESUMO DO PEDIDO*\n\n`;

    // 1. Cabeçalho Dinâmico
    if (props.metadata.type === "delivery") {
        text += `*Cliente:* ${props.metadata.customer?.name || "Não informado"}\n`;
        text += `*Tipo:* Delivery 🛵\n`;
        text += `*Endereço:* ${props.metadata.address?.full_address || "Não informado"}\n\n`;
    } else if (props.metadata.type === "counter") {
        const clientName = props.metadata.customer
            ? props.metadata.customer.name
            : props.metadata.identifier;
        text += `*Cliente:* ${clientName || "Não informado"}\n`;
        text += `*Tipo:* Retirada no Balcão 🛍️\n\n`;
    }

    // 2. Itens do Pedido (Com indentação WhatsApp-friendly)
    text += `*ITENS DO PEDIDO:*\n`;
    props.cart.forEach((item) => {
        text += `*${item.qty}x ${item.name}* - ${formatCurrency(item.price * item.qty)}\n`;

        if (item.isCombo && item.comboItems) {
            item.comboItems.forEach((cItem) => {
                text += `  ├ ${cItem.name}\n`;
                if (cItem.modifiersText) {
                    cItem.modifiersText.split(" • ").forEach((mod) => {
                        text += `  │  └ _${mod.trim()}_\n`;
                    });
                }
            });
        } else if (item.modifiersText) {
            text += `  └ _${item.modifiersText}_\n`;
        }
    });

    text += `\n*RESUMO FINANCEIRO:*\n`;
    text += `Subtotal: ${formatCurrency(props.totals.subtotal)}\n`;

    if (props.totals.discount > 0) {
        text += `Desconto: - ${formatCurrency(props.totals.discount)}\n`;
    }

    if (props.metadata.type === "delivery") {
        text += `Taxa de Entrega: ${props.totals.deliveryFee === 0 ? "Grátis" : formatCurrency(props.totals.deliveryFee)}\n`;
    }

    text += `\n*TOTAL A PAGAR: ${formatCurrency(props.totals.finalTotal)}*`;

    return text;
};

const copyToClipboard = async () => {
    try {
        const textToCopy = generateSummary();
        await navigator.clipboard.writeText(textToCopy);

        // Feedback visual
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch (err) {
        console.error("Falha ao copiar:", err);
        alert("Não foi possível copiar para a área de transferência.");
    }
};
</script>
