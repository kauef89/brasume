import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

// 🚀 SINGLETON GLOBAL: Permite que um configurador aninhado dite o formato exato da ilha
export const dynamicIslandTypeOverride = ref(null);

export function useDynamicIsland(props, emit) {
    const islandRef = ref(null);
    const contentRef = ref(null);
    const islandWidth = ref(null);
    const islandHeight = ref(null);

    let resizeObserver = null;
    let isUpdating = false;

    // Identidade visual idêntica para todas as telas (blur e cor de superfície unificados)
    const baseIslandClass = "fixed bottom-6 left-1/2 z-[70] backdrop-blur-md bg-surface-1/70 border border-white/5 dark:border-white/5 shadow-[0_18px_40px_rgba(0,0,0,0.8)] flex items-center justify-center max-w-[calc(100vw-2rem)] select-none will-change-[width,height,border-radius]";

    // Formato da cápsula: rounded-3xl para 2 linhas e rounded-full para linha única
    const shapeIslandClass = computed(() => {
        const currentType = dynamicIslandTypeOverride.value || (props ? props.configuratorType : null);

        if (currentType === 'two-rows' || currentType === 'combo-expanded') {
            return 'rounded-3xl';
        }
        return 'rounded-full';
    });

    const updateDimensions = () => {
        if (isUpdating) return;
        const target = contentRef.value;
        if (!target) return;

        const rect = target.getBoundingClientRect();
        const targetW = Math.ceil(rect.width);
        const targetH = Math.ceil(rect.height);

        const diffW = Math.abs(targetW - (islandWidth.value || 0));
        const diffH = Math.abs(targetH - (islandHeight.value || 0));

        if (targetW > 0 && targetH > 0 && (diffW >= 2 || diffH >= 2)) {
            isUpdating = true;
            islandWidth.value = targetW;
            islandHeight.value = targetH;

            requestAnimationFrame(() => {
                isUpdating = false;
            });
        }
    };

    const islandStyle = computed(() => {
        const baseStyle = {
            transform: 'translate3d(-50%, 0, 0)',
            transition: 'width 0.35s cubic-bezier(0.16, 1, 0.3, 1), height 0.35s cubic-bezier(0.16, 1, 0.3, 1), border-radius 0.35s cubic-bezier(0.16, 1, 0.3, 1)'
        };

        if (!islandWidth.value || !islandHeight.value) {
            return {
                ...baseStyle,
                width: 'max-content',
                height: 'max-content'
            };
        }

        return {
            ...baseStyle,
            width: `${islandWidth.value}px`,
            height: `${islandHeight.value}px`
        };
    });

    const handleTabClick = (tab) => {
        if (props && 'orderContext' in props && props.orderContext) {
            emit('close-context');
        }
        if (props && tab === 'menu' && props.activeTab === 'menu') {
            emit('reset-menu');
        } else if (emit) {
            emit('update:activeTab', tab);
        }
    };

    onMounted(() => {
        nextTick(() => {
            updateDimensions();
            if (contentRef.value && typeof ResizeObserver !== 'undefined') {
                resizeObserver = new ResizeObserver(() => {
                    updateDimensions();
                });
                resizeObserver.observe(contentRef.value);
            }
        });
    });

    onUnmounted(() => {
        if (resizeObserver) {
            resizeObserver.disconnect();
            resizeObserver = null;
        }
    });

    if (props) {
        watch(
            () => [props.activeTab, props.cartLength, props.configuratorType, props.orderContext, dynamicIslandTypeOverride.value],
            () => {
                nextTick(updateDimensions);
            },
            { deep: true }
        );
    }

    return {
        baseIslandClass,
        shapeIslandClass,
        islandStyle,
        islandRef,
        contentRef,
        handleTabClick
    };
}
