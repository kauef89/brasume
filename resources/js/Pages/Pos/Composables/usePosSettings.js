import { useStorage } from "@vueuse/core";
import { ref } from "vue";

// 🚀 OPÇÕES FIXAS (Não são mais ajustáveis pelo usuário)
const categoryLayout = ref("grid");
const productCardLayout = ref("square");
const categoryHierarchy = ref("levels");

// 🚀 OPÇÕES SOBREVIVENTES
const addressLookupMode = useStorage("pos_address_lookup_mode", "street");
const customerRegistrationMode = useStorage("pos_customer_registration_mode", "basic");
const outOfStockMode = useStorage("pos_out_of_stock_mode", "disable_bottom");
const estimateMode = useStorage("pos_estimate_mode", "range");
const estimateMin = useStorage("pos_estimate_min", 10);
const estimateMax = useStorage("pos_estimate_max", 90);
const estimateStep = useStorage("pos_estimate_step", 15);
const estimateBusy = useStorage("pos_estimate_busy", false);
const categorySort = useStorage("pos_category_sort", "alpha");
const productSort = useStorage("pos_product_sort", "alpha");
const kdsLayout = useStorage("pos_kds_layout", "ticket");

export function usePosSettings() {
    return {
        categoryLayout, productCardLayout, categoryHierarchy, addressLookupMode,
        customerRegistrationMode, outOfStockMode, estimateMode, estimateMin,
        estimateMax, estimateStep, estimateBusy, categorySort, productSort, kdsLayout
    };
}
