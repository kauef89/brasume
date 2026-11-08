import { useClipboard } from "@/Composables/useClipboard";
import { formatCurrency } from "@/Utils/formatters";

export function useWhatsAppMenuText() {
    const { copyToClipboard } = useClipboard();

    const isAvailable = (p) => {
        if (!p) return false;
        if (p.is_available === false || p.is_available === 0) return false;
        if (p.is_active === false || p.is_active === 0) return false;
        if (["out_of_stock", "unavailable", "paused", "inactive", "disabled"].includes(String(p.status || "").toLowerCase())) return false;
        if (p.track_inventory && parseFloat(p.current_stock ?? 0) <= 0) return false;
        return true;
    };

    const getPrice = (p) => {
        const val = Number(p.delivery_price > 0 ? p.delivery_price : (p.promo_price > 0 ? p.promo_price : (p.price ?? p.dine_in_price ?? 0)));
        return formatCurrency(val);
    };

    const normalizeType = (type) => {
        const t = String(type || "").toLowerCase().trim();
        if (t === "option" || t === "opcao" || t === "ingredient" || t === "ingrediente") return "option";
        if (t === "add_on" || t === "additional" || t === "adicional" || t === "addon") return "add_on";
        return "other";
    };

    const generateCategoryMenuText = (selectedCategoryId, allCategories = [], allProducts = []) => {
        const availableProducts = (allProducts || []).filter(isAvailable);
        const categories = (allCategories || []).filter(c => c && c.id);

        let lines = [];

        const appendCategorySection = (cat) => {
            const catProds = availableProducts.filter(p => String(p.category_id) === String(cat.id));
            if (catProds.length === 0) return;

            lines.push(`*${String(cat.name || "").toUpperCase().trim()}*`);
            catProds.forEach(prod => {
                lines.push(`• *${prod.name.trim()}* — ${getPrice(prod)}`);
                if (prod.description && prod.description.trim()) {
                    lines.push(`  _${prod.description.trim()}_`);
                }
            });
            lines.push("");
        };

        if (!selectedCategoryId) {
            const roots = categories.filter(c => !c.parent_id || Number(c.parent_id) === 0);
            roots.forEach(root => {
                const subs = categories.filter(c => String(c.parent_id) === String(root.id));
                if (subs.length > 0) {
                    lines.push(`━━━ *${String(root.name || "").toUpperCase().trim()}* ━━━\n`);
                    subs.forEach(appendCategorySection);
                } else {
                    appendCategorySection(root);
                }
            });

            const uncatProds = availableProducts.filter(p => !p.category_id || p.category_id === 0);
            if (uncatProds.length > 0) {
                lines.push("*OUTROS ITENS*");
                uncatProds.forEach(prod => {
                    lines.push(`• *${prod.name.trim()}* — ${getPrice(prod)}`);
                    if (prod.description && prod.description.trim()) {
                        lines.push(`  _${prod.description.trim()}_`);
                    }
                });
                lines.push("");
            }
        } else {
            const currentCat = categories.find(c => String(c.id) === String(selectedCategoryId));
            const childCategories = categories.filter(c => String(c.parent_id) === String(selectedCategoryId));

            if (childCategories.length > 0) {
                if (currentCat) {
                    lines.push(`━━━ *${String(currentCat.name || "").toUpperCase().trim()}* ━━━\n`);
                }
                childCategories.forEach(appendCategorySection);
                if (currentCat) {
                    appendCategorySection(currentCat);
                }
            } else if (currentCat) {
                appendCategorySection(currentCat);
            }
        }

        return lines.join("\n").trim();
    };

    const generateProductText = (product) => {
        if (!product) return "";

        let lines = [];
        lines.push(`*${String(product.name || "").toUpperCase().trim()}* — ${getPrice(product)}`);

        if (product.description && product.description.trim()) {
            lines.push(`_${product.description.trim()}_\n`);
        } else {
            lines.push("");
        }

        const modifiers = product.modifiers || product.modifier_groups || [];

        const ingredientGroups = modifiers.filter(g => ["option", "ingredient"].includes(normalizeType(g.type)));
        if (ingredientGroups.length > 0) {
            ingredientGroups.forEach(group => {
                const opts = (group.options || []).filter(opt => opt.is_active !== false);
                if (opts.length === 0) return;
                const gName = group.name || group.title || "Ingredientes";
                lines.push(`*${gName}:*`);
                opts.forEach(opt => {
                    const priceExtra = Number(opt.price || 0);
                    const priceLabel = priceExtra > 0 ? ` (+ ${formatCurrency(priceExtra)})` : "";
                    lines.push(`• ${opt.name}${priceLabel}`);
                });
                lines.push("");
            });
        }

        const addonGroups = modifiers.filter(g => normalizeType(g.type) === "add_on");
        if (addonGroups.length > 0) {
            addonGroups.forEach(group => {
                const opts = (group.options || []).filter(opt => opt.is_active !== false);
                if (opts.length === 0) return;
                const gName = group.name || group.title || "Adicionais";
                lines.push(`*${gName}:*`);
                opts.forEach(opt => {
                    const priceExtra = Number(opt.price || 0);
                    const priceLabel = priceExtra > 0 ? ` (+ ${formatCurrency(priceExtra)})` : "";
                    lines.push(`• ${opt.name}${priceLabel}`);
                });
                lines.push("");
            });
        }

        return lines.join("\n").trim();
    };

    const generateComboText = (comboProduct, allProducts = []) => {
        if (!comboProduct) return "";

        let lines = [];
        lines.push(`🔥 *${String(comboProduct.name || "").toUpperCase().trim()}* — A partir de ${getPrice(comboProduct)}`);

        if (comboProduct.description && comboProduct.description.trim()) {
            lines.push(`_${comboProduct.description.trim()}_\n`);
        } else {
            lines.push("");
        }

        const groups = comboProduct.combo_groups || [];
        const productsDict = {};
        (allProducts || []).forEach(p => { productsDict[p.id] = p; });

        groups.forEach((group, index) => {
            const min = Number(group.min_choices ?? 1);
            const max = Number(group.max_choices ?? 1);
            const qtyLabel = min === max ? `${max} ${max === 1 ? "item" : "itens"}` : `De ${min} a ${max} itens`;

            lines.push(`*${index + 1}. ${group.name}* _(${qtyLabel})_:`);

            const items = group.items || group.combo_items || [];
            items.forEach(item => {
                const addPrice = Number(item.additional_price || item.extra_price || 0);
                const priceLabel = addPrice > 0 ? ` (+ ${formatCurrency(addPrice)})` : "";

                if (item.category_id && !item.product_id) {
                    const prods = (allProducts || []).filter(p => String(p.category_id) === String(item.category_id) && isAvailable(p));
                    prods.forEach(p => {
                        lines.push(`• ${p.name}${priceLabel}`);
                    });
                } else {
                    const pName = item.product?.name || item.name || item.product_name || productsDict[item.product_id]?.name || "Opção";
                    lines.push(`• ${pName}${priceLabel}`);
                }
            });
            lines.push("");
        });

        return lines.join("\n").trim();
    };

    const copyCategoryMenu = async (categoryId, allCategories, allProducts) => {
        const text = generateCategoryMenuText(categoryId, allCategories, allProducts);
        if (!text) return false;
        return await copyToClipboard(text, "Cardápio copiado em texto para o WhatsApp!");
    };

    const copyProductOrCombo = async (product, allProducts) => {
        if (!product) return false;
        const isCombo = product.type === "combo" || (product.combo_groups && product.combo_groups.length > 0);
        const text = isCombo
            ? generateComboText(product, allProducts)
            : generateProductText(product);

        if (!text) return false;
        return await copyToClipboard(text, "Detalhes copiados em texto para o WhatsApp!");
    };

    return {
        generateCategoryMenuText,
        generateProductText,
        generateComboText,
        copyCategoryMenu,
        copyProductOrCombo
    };
}