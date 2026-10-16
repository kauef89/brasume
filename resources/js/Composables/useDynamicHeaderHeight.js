import { ref, onMounted, onUnmounted, watch } from 'vue';

export function useDynamicHeaderHeight(targetRef, options = {}) {
    const {
        cssVarName = '--app-header-height',
        fallbackHeight = 112, // ~7rem (pt-28)
        extraOffset = 0       // Folga opcional em pixels
    } = options;

    const headerHeight = ref(fallbackHeight);
    let observer = null;

    const updateHeight = (el) => {
        if (!el) return;
        const rect = el.getBoundingClientRect();
        const height = Math.ceil(rect.height) + extraOffset;

        if (height > 0 && height !== headerHeight.value) {
            headerHeight.value = height;
            document.documentElement.style.setProperty(cssVarName, `${height}px`);
        }
    };

    onMounted(() => {
        const el = targetRef?.value?.$el || targetRef?.value;
        if (!el) return;

        updateHeight(el);

        if (typeof ResizeObserver !== 'undefined') {
            observer = new ResizeObserver(() => updateHeight(el));
            observer.observe(el);
        }

        window.addEventListener('resize', () => updateHeight(el), { passive: true });
    });

    onUnmounted(() => {
        if (observer) {
            observer.disconnect();
            observer = null;
        }
        window.removeEventListener('resize', () => {});
        document.documentElement.style.removeProperty(cssVarName);
    });

    return {
        headerHeight
    };
}
