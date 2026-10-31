import { useToast } from "primevue/usetoast";

export function useClipboard() {
    let toast = null;
    try {
        toast = useToast();
    } catch (e) {}

    const copyToClipboard = async (text, successMessage = "Copiado com sucesso!") => {
        try {
            if (navigator?.clipboard?.writeText) {
                await navigator.clipboard.writeText(text);
            } else {
                const textArea = document.createElement("textarea");
                textArea.value = text;
                textArea.style.position = "fixed";
                textArea.style.left = "-999999px";
                textArea.style.top = "-999999px";
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                const successful = document.execCommand("copy");
                document.body.removeChild(textArea);
                if (!successful) throw new Error("Fallback falhou");
            }

            if (toast) {
                toast.add({ severity: "success", summary: "Copiado!", detail: successMessage, life: 3000 });
            }
            return true;
        } catch (err) {
            try {
                const textArea = document.createElement("textarea");
                textArea.value = text;
                textArea.style.position = "fixed";
                textArea.style.left = "-999999px";
                textArea.style.top = "-999999px";
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                const successful = document.execCommand("copy");
                document.body.removeChild(textArea);
                if (successful) {
                    if (toast) {
                        toast.add({ severity: "success", summary: "Copiado!", detail: successMessage, life: 3000 });
                    }
                    return true;
                }
            } catch (fallbackErr) {}

            console.error("Erro ao copiar para a área de transferência:", err);
            if (toast) {
                toast.add({ severity: "error", summary: "Erro", detail: "Não foi possível copiar o texto.", life: 4000 });
            }
            return false;
        }
    };

    return { copyToClipboard };
}