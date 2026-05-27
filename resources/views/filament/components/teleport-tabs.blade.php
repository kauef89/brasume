<script>
    /* --- C) TELETRANSPORTE PARA O CABEÇALHO DA TABELA (.fi-ta-header-toolbar) --- */
    function teleportTabsToTable() {
        // Pega o componente original das abas
        const tabs = document.querySelector(".fi-sc-tabs:has(.fi-tabs)");

        // O alvo: O container de ações da tabela
        const tableActions = document.querySelector(".fi-ta-header-toolbar") || document.querySelector(".fi-ta-header");

        if (tabs && tableActions && tabs.parentElement !== tableActions) {

            // Limpa abas duplicadas pelo Livewire
            const oldTeleported = tableActions.querySelector(".teleported-tabs");
            if (oldTeleported && oldTeleported !== tabs) {
                oldTeleported.remove();
            }

            tabs.classList.add("teleported-tabs");

            // Insere as abas no INÍCIO do container de ações
            tableActions.insertBefore(tabs, tableActions.firstChild);
        }
    }

    // Eventos para disparar a manobra
    document.addEventListener("DOMContentLoaded", teleportTabsToTable);
    document.addEventListener("livewire:navigated", teleportTabsToTable);

    document.addEventListener("livewire:init", () => {
        Livewire.hook("morph.updated", () => {
            setTimeout(teleportTabsToTable, 10);
        });
    });
</script>
