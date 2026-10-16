import { onBeforeUnmount, ref, watch } from 'vue';

export function useSlideToConfirm(props, emit, trackRef, thumbRef) {
    const isDragging = ref(false);
    const isAnimatingHint = ref(false);
    const thumbOpacity = ref(1);
    const dragOffset = ref(0);
    const startX = ref(0);
    const hasMoved = ref(false);
    let activeTouchId = null;

    const getMaxOffset = () => {
        if (trackRef.value && thumbRef.value) {
            const calculated = trackRef.value.offsetWidth - thumbRef.value.offsetWidth - 8;
            return Math.max(calculated, 40);
        }
        return 80;
    };

    const resetSlider = () => {
        isDragging.value = false;
        dragOffset.value = 0;
        thumbOpacity.value = 1;
        cleanupGlobalListeners();
    };

    watch(() => props.isLoading, (loading, oldLoading) => {
        if (!loading && oldLoading && !props.isSuccess) {
            resetSlider();
        }
    });

    const triggerHintAnimation = () => {
        if (isAnimatingHint.value || isDragging.value) return;
        isAnimatingHint.value = true;
        const maxOffset = getMaxOffset();
        dragOffset.value = Math.min(maxOffset, 45);

        setTimeout(() => {
            thumbOpacity.value = 0;
            setTimeout(() => {
                dragOffset.value = 0;
                setTimeout(() => {
                    thumbOpacity.value = 1;
                    setTimeout(() => { isAnimatingHint.value = false; }, 200);
                }, 100);
            }, 200);
        }, 300);
    };

    const handleTrackInteraction = () => {
        if (props.disabled || props.isLoading || props.isSuccess || isAnimatingHint.value || isDragging.value) return;
        triggerHintAnimation();
    };

    const onDrag = (e) => {
        if (!isDragging.value) return;

        let clientX = 0;
        if (e.type.includes('touch')) {
            const touch = Array.from(e.touches).find(t => t.identifier === activeTouchId) || e.touches[0];
            if (!touch) return;
            clientX = touch.clientX;
            if (e.cancelable) e.preventDefault();
        } else {
            clientX = e.clientX;
        }

        let newOffset = clientX - startX.value;
        if (Math.abs(newOffset) > 5) hasMoved.value = true;

        const maxOffset = getMaxOffset();
        if (newOffset < 0) newOffset = 0;
        if (newOffset > maxOffset) newOffset = maxOffset;

        dragOffset.value = newOffset;
    };

    const endDrag = () => {
        if (!isDragging.value) return;
        isDragging.value = false;
        cleanupGlobalListeners();

        const maxOffset = getMaxOffset();
        const target = maxOffset * 0.70;

        if (dragOffset.value >= target) {
            dragOffset.value = maxOffset;
            emit('confirm');
        } else if (!hasMoved.value || dragOffset.value < 10) {
            triggerHintAnimation();
        } else {
            dragOffset.value = 0;
        }
    };

    const cancelDrag = () => {
        if (!isDragging.value) return;
        resetSlider();
    };

    const startDrag = (e) => {
        if (props.disabled || props.isLoading || props.isSuccess) return;

        isAnimatingHint.value = false;
        isDragging.value = true;
        hasMoved.value = false;

        if (e.type.includes('touch')) {
            const touch = e.touches[0];
            activeTouchId = touch.identifier;
            startX.value = touch.clientX;

            window.addEventListener('touchmove', onDrag, { passive: false });
            window.addEventListener('touchend', endDrag);
            window.addEventListener('touchcancel', cancelDrag);
        } else {
            startX.value = e.clientX;
            window.addEventListener('mousemove', onDrag);
            window.addEventListener('mouseup', endDrag);
        }

        if (e.cancelable) e.preventDefault();
    };

    const cleanupGlobalListeners = () => {
        activeTouchId = null;
        window.removeEventListener('mousemove', onDrag);
        window.removeEventListener('mouseup', endDrag);
        window.removeEventListener('touchmove', onDrag);
        window.removeEventListener('touchend', endDrag);
        window.removeEventListener('touchcancel', cancelDrag);
    };

    onBeforeUnmount(() => {
        cleanupGlobalListeners();
    });

    return {
        isDragging, isAnimatingHint, dragOffset,
        handleTrackInteraction, startDrag, onDrag, endDrag, triggerHintAnimation, resetSlider
    };
}
