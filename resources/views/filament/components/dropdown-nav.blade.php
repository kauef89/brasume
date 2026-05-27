{{-- 🚀 Trava de CSS: Mata as abas e remove o gap inútil da página --}}
<style>
    /* Esconde as abas nativas */
    .fi-tabs,
    .fi-sc-tabs > nav,
    nav[role="tablist"].fi-tabs {
        display: none !important;
    }

    /* 🚀 Remove o recuo vertical entre o header e o form/content */
    .fi-page-content {
        row-gap: 0 !important;
    }

    /* Ajuste fino para o container das abas combinadas não ocupar espaço */
    .fi-sc-tabs {
        margin-bottom: 0 !important;
    }
</style>

<div id="product-dropdown-nav-container" class="inline-block" style="display: none;" wire:ignore>
    <div class="custom-tabs-dropdown relative inline-block">

        <button id="tabs-dropdown-trigger" type="button"
                class="flex items-center justify-between w-full min-w-[240px] max-w-[320px] gap-2 rounded-lg bg-white px-3 py-2 text-sm font-semibold text-gray-950 shadow-sm ring-1 ring-gray-950/10 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 dark:bg-white/5 dark:text-white dark:hover:bg-white/10 dark:ring-white/20 transition-all cursor-pointer">

            <span id="tabs-dropdown-label" class="flex items-center gap-2 truncate text-left w-full">
                {{-- Ícone de loading inicial --}}
                <svg class="w-5 h-5 text-gray-400 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                <span>Navegação...</span>
            </span>

            <svg class="w-5 h-5 text-gray-400 dark:text-gray-500 flex-shrink-0 ml-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>
        </button>

        <div id="tabs-dropdown-panel" class="hidden absolute right-0 z-20 mt-1 min-w-[15rem] rounded-lg bg-white shadow-lg ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 transition-all overflow-hidden">
            <div id="tabs-dropdown-list" class="fi-dropdown-list p-1 flex flex-col gap-0.5"></div>
        </div>
    </div>
</div>

<script>
    (function() {
        function getIconElement(tabNode) {
            const originalSvg = tabNode.querySelector('svg, .fi-icon');
            if (originalSvg) {
                const cloned = originalSvg.cloneNode(true);
                cloned.setAttribute('class', 'w-5 h-5 flex-shrink-0 text-gray-400 dark:text-gray-500');
                return cloned;
            }
            const fallback = document.createElement('div');
            fallback.innerHTML = `<svg class="w-5 h-5 flex-shrink-0 text-gray-400 dark:text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" /></svg>`;
            return fallback.firstChild;
        }

        function setDropdownFace(text, iconNode, isPrimary = false) {
            const labelContainer = document.getElementById('tabs-dropdown-label');
            if (!labelContainer) return;
            labelContainer.innerHTML = '';
            const faceIcon = iconNode.cloneNode(true);
            if (isPrimary) {
                faceIcon.classList.remove('text-gray-400', 'dark:text-gray-500');
                faceIcon.classList.add('text-primary-600', 'dark:text-primary-400');
            }
            labelContainer.appendChild(faceIcon);
            const spanText = document.createElement('span');
            spanText.textContent = text;
            spanText.className = `truncate ${isPrimary ? 'text-primary-600 dark:text-primary-400' : ''}`;
            labelContainer.appendChild(spanText);
        }

        function syncDropdown() {
            try {
                const container = document.getElementById('product-dropdown-nav-container');
                const nav = document.querySelector('.fi-tabs') || document.querySelector('nav[role="tablist"]');
                const panel = document.getElementById('tabs-dropdown-panel');
                const list = document.getElementById('tabs-dropdown-list');

                if (!container || !nav || !panel || !list) {
                    if (container) container.style.display = 'none';
                    return;
                }

                const tabsNodes = Array.from(nav.querySelectorAll('.fi-tabs-item, [role="tab"]'));
                if (tabsNodes.length === 0) {
                    container.style.display = 'none';
                    return;
                }

                // O CSS já esconde, mas mantemos o JS para garantir
                container.style.display = 'inline-block';

                const activeTab = tabsNodes.find(t =>
                    t.getAttribute('aria-selected') === 'true' ||
                    t.getAttribute('aria-current') === 'page' ||
                    t.classList.contains('fi-active') ||
                    t.classList.contains('fi-tabs-item-active')
                ) || tabsNodes[0];

                setDropdownFace(activeTab.innerText.trim().replace(/\n/g, ' - '), getIconElement(activeTab), true);

                list.innerHTML = '';
                tabsNodes.forEach((tabNode) => {
                    const isLink = tabNode.tagName.toLowerCase() === 'a';
                    const item = document.createElement(isLink ? 'a' : 'button');
                    if (isLink) item.href = tabNode.href; else item.type = 'button';

                    item.className = 'fi-dropdown-list-item flex w-full items-center gap-2 whitespace-nowrap rounded-md p-2 text-sm transition-colors duration-75 outline-none hover:bg-gray-50 focus-visible:bg-gray-50 dark:hover:bg-white/5 dark:focus-visible:bg-white/5 cursor-pointer';

                    const cleanText = tabNode.innerText.trim().replace(/\n/g, ' - ');
                    const itemIcon = getIconElement(tabNode);
                    item.appendChild(itemIcon);

                    const span = document.createElement('span');
                    span.className = 'fi-dropdown-list-item-label flex-1 truncate text-start text-gray-700 dark:text-gray-200';
                    span.textContent = cleanText;
                    item.appendChild(span);

                    if (tabNode === activeTab) {
                        item.classList.add('bg-gray-50', 'dark:bg-white/5', 'font-bold');
                        span.classList.add('text-primary-600', 'dark:text-primary-400');
                        itemIcon.classList.add('text-primary-600', 'dark:text-primary-400');
                    }

                    if (!isLink) {
                        item.addEventListener('click', () => {
                            panel.classList.add('hidden');
                            setDropdownFace(cleanText, itemIcon, true);
                            tabNode.click();
                        });
                    }
                    list.appendChild(item);
                });
            } catch (e) { console.warn("Erro syncDropdown:", e); }
        }

        function initEvents() {
            const trigger = document.getElementById('tabs-dropdown-trigger');
            const panel = document.getElementById('tabs-dropdown-panel');
            const container = document.getElementById('product-dropdown-nav-container');

            if (trigger && panel && !trigger.dataset.hasEvents) {
                trigger.addEventListener('click', (e) => {
                    e.stopPropagation();
                    panel.classList.toggle('hidden');
                });
                document.addEventListener('click', (e) => {
                    if (container && !container.contains(e.target)) panel.classList.add('hidden');
                });
                trigger.dataset.hasEvents = "true";
            }
        }

        function runPipeline() { syncDropdown(); initEvents(); }

        document.addEventListener("DOMContentLoaded", runPipeline);
        document.addEventListener("livewire:navigated", runPipeline);
        if (window.Livewire) {
            Livewire.hook('commit', ({ succeed }) => { succeed(() => setTimeout(runPipeline, 10)); });
        }
    })();
</script>
