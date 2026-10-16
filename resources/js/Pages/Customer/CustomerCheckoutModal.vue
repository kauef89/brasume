<template>
    <AdaptiveModal :visible="visible" @update:visible="handleModalVisibilityChange">
        <div class="flex flex-col w-full h-auto max-h-[90dvh] md:max-h-[85vh] overflow-hidden relative select-none">

            <!-- CABEÇALHO ÚNICO E FIXO -->
            <div class="px-6 pt-5 pb-3 shrink-0 z-20 flex flex-col gap-3">
                <div class="flex items-center justify-between w-full">
                    <button
                        @click="goBack"
                        class="c-btn-icon !bg-white/5 text-white/70 hover:text-white shrink-0 outline-none border-none"
                        type="button"
                    >
                        <i class="pi pi-arrow-left text-sm"></i>
                    </button>

                    <ProgressBar
                        :current="headerState?.current || 1"
                        :max="headerState?.max || 1"
                        mode="wizard"
                        :is-glass="true"
                    />
                </div>

                <div class="flex items-center justify-center text-center w-full min-w-0">
                    <h2 class="c-title !text-xl truncate">
                        {{ headerState?.title || 'Carregando...' }}
                    </h2>
                </div>
            </div>

            <!-- O CORPO DINÂMICO PRESERVADO COM KEEPALIVE -->
            <div class="flex-1 overflow-y-auto hide-scrollbar flex flex-col relative w-full">
                <Transition name="slide-fade" mode="out-in">
                    <KeepAlive>
                        <CheckoutStepAuth
                            v-if="state.step === 'auth'"
                            key="auth"
                            :ref="(el) => $emit('bind-ref', el)"
                            :allow-guest="loginConfig.allowGuest"
                            :auth-methods="loginConfig.customerAuthMethods"
                            :needs-email-fallback="loginConfig.needsEmailFallback"
                            :is-submitting-auth="isSubmitting"
                            :sub-step="state.authSubStep"
                            :guest-name="state.guestName"
                            :guest-phone="state.guestPhone"
                            @update:sub-step="state.authSubStep = $event"
                            @update:guest-name="state.guestName = $event"
                            @update:guest-phone="state.guestPhone = $event"
                            @login="$emit('checkout-login',$event)"
                            @open-register="$emit('open-register')"
                            @guest-registered="state.step = 'logistics'"
                        />

                        <CheckoutStepLogistics
                            v-else-if="state.step === 'logistics'"
                            key="logistics"
                            :ref="(el) => $emit('bind-ref', el)"
                            :customer="customer"
                            :selected-order-type="state.orderType"
                            @update:selectedOrderType="state.orderType = $event"
                            @routing-change="handleRoutingChange"
                            @open-new-address="state.step = 'new_address'"
                            @next="state.step = 'payment'"
                        />

                        <CheckoutStepNewAddress
                            v-else-if="state.step === 'new_address'"
                            key="new_address"
                            :ref="(el) => $emit('bind-ref', el)"
                            mode="inline"
                            :visible="true"
                            :customer-id="customer?.id"
                            :is-guest="isGuest"
                            :is-customer-mode="true"
                            @substep="$emit('update-substep', 'address',$event)"
                            @save="(data, callbacks) => $emit('save-address', data, callbacks)"
                            @success="handleNewAddressSuccess"
                            class="p-5"
                        />

                        <CheckoutStepPayment
                            v-else-if="state.step === 'payment'"
                            key="payment"
                            :ref="(el) => $emit('bind-ref', el)"
                            :is-guest="isGuest"
                            :base-subtotal="financials.subtotal"
                            :delivery-fee="financials.deliveryFee"
                            :active-discount="financials.discount"
                            :subtotal-with-fees-and-coupons="financials.subtotal + financials.deliveryFee - financials.discount"
                            :final-total-with-discount="financials.finalTotal"
                            :selected-order-type="state.orderType"
                            :is-typing-coupon="state.isTypingCoupon"
                            :available-methods="paymentMethods"
                            :selected-method="state.selectedMethod"
                            :cash-change-target="state.cashChangeTarget"
                            :is-loyalty-active="loyalty.isActive"
                            :use-cashback-toggle="state.useCashbackToggle"
                            :available-balance="loyalty.balance"
                            :customer-points="loyalty.points"
                            :can-redeem="loyalty.canRedeem"
                            :missing-amount="loyalty.missing"
                            :cashback-discount="financials.cashbackDiscount"
                            :is-submitting="isSubmitting"
                            @apply-coupon="applyCoupon"
                            @remove-coupon="removeCoupon"
                            @update:useCashbackToggle="state.useCashbackToggle = $event"
                            @update:isTypingCoupon="state.isTypingCoupon = $event"
                            @update:selectedMethod="state.selectedMethod = $event"
                            @update:cashChangeTarget="state.cashChangeTarget = $event"
                            @upgrade-guest="$emit('open-register')"
                            @confirm="$emit('confirm')"
                        />

                        <CheckoutStepPix
                            v-else-if="state.step === 'pix'"
                            key="pix"
                            :ref="(el) => $emit('bind-ref', el)"
                            :pending-order-id="pendingOrderId"
                            :pix-data="pixData"
                            :is-generating-pix="isSubmitting"
                            @payment-approved="$emit('pix-success')"
                        />
                    </KeepAlive>
                </Transition>
            </div>
        </div>
    </AdaptiveModal>
</template>

<script setup>
import AdaptiveModal from '@/Components/UI/AdaptiveModal.vue';
import ProgressBar from '@/Components/UI/ProgressBar.vue';
import CheckoutStepAuth from '@/Pages/Customer/Checkout/CheckoutStepAuth.vue';
import CheckoutStepLogistics from '@/Pages/Customer/Checkout/CheckoutStepLogistics.vue';
import CheckoutStepPayment from '@/Pages/Customer/Checkout/CheckoutStepPayment.vue';
import CheckoutStepPix from '@/Pages/Customer/Checkout/CheckoutStepPix.vue';
import CheckoutStepNewAddress from '@/Pages/Customer/Checkout/CheckoutStepNewAddress.vue';
import { useCoupons } from '@/Pages/Shared/Composables/useCoupons';

const { applyCoupon, removeCoupon } = useCoupons();

const props = defineProps({
    visible: Boolean,
    customer: Object,
    isGuest: { type: Boolean, default: false },
    paymentMethods: { type: Array, default: () => [] },
    loginConfig: { type: Object, default: () => ({}) },
    state: { type: Object, required: true },
    financials: { type: Object, required: true },
    loyalty: { type: Object, required: true },
    isSubmitting: Boolean,
    headerState: { type: Object, required: true },
    goBack: { type: Function, required: true },
    pendingOrderId: [String, Number],
    pixData: [Object, String]
});

const emit = defineEmits(['update:visible', 'confirm', 'open-register', 'pix-success', 'save-address', 'checkout-login', 'bind-ref', 'update-substep']);

const handleRoutingChange = (payload) => {
    if (!payload) return;
    const metaData = payload.meta ? payload.meta : payload;
    props.state.routingMeta = metaData;
    if (metaData.type) props.state.orderType = metaData.type;
};

// 🛑 BLINDAGEM: Fechar o modal durante o Pix apenas oculta a janela sem matar o pedido
const handleModalVisibilityChange = (newVisible) => {
    emit('update:visible', newVisible);
};

const handleNewAddressSuccess = (createdAddress) => {
    const addr = createdAddress || {};
    const streetName = addr.street?.name || addr.street || '';
    const neighborhoodName = addr.neighborhood?.name || addr.neighborhood || '';
    const numberStr = addr.no_number ? 'S/N' : (addr.number || 'S/N');
    const comp = addr.complement ? ` (${addr.complement})` : '';

    const completeAddress = {
        ...addr,
        id: addr.id || null,
        street: streetName,
        neighborhood: neighborhoodName,
        number: numberStr,
        full_address: addr.full_address || `${streetName}, ${numberStr} - ${neighborhoodName}${comp}`
    };

    if (!props.state.routingMeta) props.state.routingMeta = {};
    props.state.routingMeta.type = 'delivery';
    props.state.routingMeta.address = completeAddress;
    props.state.routingMeta.address_id = completeAddress.id || null;
    props.state.orderType = 'delivery';

    if (props.customer) {
        if (!Array.isArray(props.customer.addresses)) {
            props.customer.addresses = [];
        }
        props.customer.addresses.push(completeAddress);
    }

    props.state.step = 'payment';
};
</script>

<style scoped>
.slide-fade-enter-active, .slide-fade-leave-active { transition: all 0.25s ease-out; }
.slide-fade-enter-from { transform: translateX(20px); opacity: 0; }
.slide-fade-leave-to { transform: translateX(-20px); opacity: 0; }
</style>
