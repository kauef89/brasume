export function useAudioAlert() {
    const playBeep = (type = 'cashier') => {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;

            const ctx = new AudioContext();

            if (type === 'cashier') {
                // 🛎️ Som de campainha de balcão (Ding-Dong suave)
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);

                osc.type = 'sine';
                osc.frequency.setValueAtTime(783.99, ctx.currentTime); // G5 (Ding)
                osc.frequency.setValueAtTime(659.25, ctx.currentTime + 0.3); // E5 (Dong)

                gain.gain.setValueAtTime(0.5, ctx.currentTime);
                gain.gain.setTargetAtTime(0.01, ctx.currentTime + 0.8, 0.1);

                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + 1);
            }
            else if (type === 'kds') {
                // 🖨️ Som de impressora de cozinha (3 beeps agudos e curtos)
                for (let i = 0; i < 3; i++) {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.connect(gain);
                    gain.connect(ctx.destination);

                    osc.type = 'square';
                    osc.frequency.setValueAtTime(800, ctx.currentTime + (i * 0.2));

                    gain.gain.setValueAtTime(0, ctx.currentTime);
                    gain.gain.setValueAtTime(0.2, ctx.currentTime + (i * 0.2));
                    gain.gain.setValueAtTime(0, ctx.currentTime + (i * 0.2) + 0.1);

                    osc.start(ctx.currentTime + (i * 0.2));
                    osc.stop(ctx.currentTime + (i * 0.2) + 0.15);
                }
            }
        } catch (e) {
            console.warn("Áudio não suportado ou bloqueado pelo navegador:", e);
        }
    };

    return { playBeep };
}
