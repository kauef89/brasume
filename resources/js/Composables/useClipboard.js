import { useToast } from 'primevue/usetoast';

export function useClipboard() {
    const toast = useToast();

    const copyToClipboard = async (text, successMessage = "Copiado com sucesso!") => {
        try {
            await navigator.clipboard.writeText(text);
            toast.add({ severity: "success", summary: "Copiado!", detail: successMessage, life: 3000 });
            return true;
        } catch (err) {
            toast.add({ severity: "error", summary: "Erro", detail: "Não foi possível copiar o texto. O seu navegador pode estar a bloquear.", life: 4000 });
            return false;
        }
    };

    return { copyToClipboard };
}
