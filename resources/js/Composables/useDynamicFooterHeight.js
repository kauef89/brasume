import { ref, onMounted, onUnmounted, watch } from 'vue';

export function useDynamicFooterHeight(targetRef, options = {}) {
    const {
        cssVarName = '--app-footer-height',
        fallbackHeight = 96,
        extraOffset = 16
    } = options;

    const footerHeight = ref(fallbackHeight);
    let observer = null;
    let currentObservedEl = null;

    const resolveElement = (target) => {
        if (!target) return null;
        const el = target.$el !== undefined ? target.$el : target;

        if (el && el.nodeType === 1 && typeof el.getBoundingClientRect === 'function') {
            return el;
        }
        return null;
    };

    const updateHeight = (el) => {
        if (!el || el.nodeType !== 1 || typeof el.getBoundingClientRect !== 'function' || !el.isConnected) return;

        const rect = el.getBoundingClientRect();

        // 🚀 O pulo do gato: apenas a altura do elemento + folga.
        // Nunca mais somamos window.innerHeight para não esmagar o app.
        const calculatedHeight = Math.ceil(rect.height) + extraOffset;

        if (calculatedHeight > 0 && calculatedHeight !== footerHeight.value) {
            footerHeight.value = calculatedHeight;
            document.documentElement.style.setProperty(cssVarName, `${calculatedHeight}px`);
        }
    };

    const handleResize = () => {
        if (currentObservedEl) {
            updateHeight(currentObservedEl);
        }
    };

    const setupObserver = (newEl) => {
        if (observer) {
            observer.disconnect();
            observer = null;
        }
        currentObservedEl = null;

        const el = resolveElement(newEl);
        if (!el) {
            // Em caso nulo, deixamos um fallback de segurança
            document.documentElement.style.setProperty(cssVarName, `${fallbackHeight}px`);
            footerHeight.value = fallbackHeight;
            return;
        }

        currentObservedEl = el;
        updateHeight(el);

        if (typeof ResizeObserver !== 'undefined') {
            observer = new ResizeObserver(() => updateHeight(el));
            observer.observe(el);
        }
    };

    watch(
        () => resolveElement(targetRef?.value),
        (newEl) => {
            setupObserver(newEl);
        },
        { immediate: true, flush: 'post' }
    );

    onMounted(() => {
        window.addEventListener('resize', handleResize, { passive: true });
    });

    onUnmounted(() => {
        if (observer) {
            observer.disconnect();
            observer = null;
        }
        window.removeEventListener('resize', handleResize);
        document.documentElement.style.removeProperty(cssVarName);
    });

    return {
        footerHeight
    };
}
