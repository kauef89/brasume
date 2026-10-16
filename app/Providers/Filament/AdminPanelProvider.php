<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Enums\UserMenuPosition;
use Filament\Navigation\NavigationGroup;
use Illuminate\Support\Facades\Blade;
use Filament\View\PanelsRenderHook;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // ==========================================
        // MONTAGEM DO PAINEL
        // ==========================================
        return $panel
            ->id("store")
            ->path("admin")
            ->default()
            ->login()
            ->topbar(true) // 🚀 Topbar ativada nativamente

            // Branding e Layout
            ->brandName(fn() => strtoupper(tenant("id") ?? "Gestão"))
            ->sidebarCollapsibleOnDesktop()
            ->userMenu(position: UserMenuPosition::Sidebar)
            ->sidebarWidth("15rem")
            ->collapsedSidebarWidth("1.5rem")

            // 🚀 Cor padrão (será substituída dinamicamente pelo ApplyTenantTheme Middleware)
            ->colors(["primary" => Color::Amber])

            ->font(
                "Stack Sans Text",
                provider: \Filament\FontProviders\LocalFontProvider::class,
            )
            ->viteTheme("resources/css/filament/store/theme.css")

            // ==========================================
            // 1. ESTILIZAÇÃO E COMPORTAMENTO DA BUSCA GLOBAL (TOPBAR)
            // ==========================================
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn(): string => Blade::render('
                    <style>
                        .fi-global-search-field {
                            max-width: none !important;
                            width: 45rem !important;
                        }
                        @media (max-width: 1024px) {
                            .fi-global-search-field { width: 100% !important; }
                        }
                    </style>
                    <script>
                        document.addEventListener("DOMContentLoaded", () => {
                            const globalSearch = document.querySelector(".fi-global-search-field input");
                            if (globalSearch) {
                                globalSearch.placeholder = "Pesquise por produtos, ingredientes, clientes ou o que quiser!";
                            }
                        });
                    </script>
                '),
            )

            // ==========================================
            // 2. BUSCA DE PÁGINAS (RODAPÉ DA SIDEBAR)
            // ==========================================
            ->renderHook(
                PanelsRenderHook::SIDEBAR_FOOTER,
                fn(): string => Blade::render('
                    <div id="search-wrapper" class="px-4 pb-4 pt-4 relative group border-t border-gray-200 dark:border-gray-800">
                        <div class="nav-searchbar flex items-center p-2 gap-2 transition-colors duration-200 cursor-text" onclick="document.getElementById(\'menu-search\').focus()">
                            <svg class="fi-icon fi-size-md text-gray-500" style="width: 1.1rem; height: 1.1rem;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                            <input type="text" id="menu-search" placeholder="Pesquisar páginas" class="w-full bg-transparent border-none p-0 text-sm text-gray-900 dark:text-white placeholder:text-primary-500/50 dark:placeholder:text-primary-400/50 focus:ring-0" autocomplete="off">
                            <button id="clear-search" type="button" class="hidden text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 focus:outline-none">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 1rem; height: 1rem;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <script>
                        document.addEventListener("DOMContentLoaded", () => {
                            const searchInput = document.getElementById("menu-search");
                            const clearBtn = document.getElementById("clear-search");
                            if(searchInput && clearBtn) {
                                clearBtn.addEventListener("click", (e) => {
                                    e.stopPropagation();
                                    searchInput.value = "";
                                    searchInput.dispatchEvent(new Event("input"));
                                    searchInput.focus();
                                });
                                searchInput.addEventListener("input", function() {
                                    const term = this.value.toLowerCase();
                                    if(term.length > 0) { clearBtn.classList.remove("hidden"); }
                                    else { clearBtn.classList.add("hidden"); }
                                    document.querySelectorAll(".fi-sidebar-item").forEach(item => {
                                        const text = item.textContent.toLowerCase();
                                        if(text.includes(term)) {
                                            item.style.display = "";
                                            if(term.length > 0) {
                                                const parentUl = item.closest("ul");
                                                if(parentUl) parentUl.style.display = "block";
                                            }
                                        } else { item.style.display = "none"; }
                                    });
                                    document.querySelectorAll(".fi-sidebar-group").forEach(group => {
                                        const visibleItemsCount = Array.from(group.querySelectorAll(".fi-sidebar-item")).filter(item => item.style.display !== "none").length;
                                        group.style.display = (visibleItemsCount === 0 && term.length > 0) ? "none" : "";
                                    });
                                    if(term.length === 0) {
                                        document.querySelectorAll(".fi-sidebar-group ul").forEach(ul => { ul.style.display = ""; });
                                    }
                                });
                            }
                        });
                    </script>
                '),
            )

            // ==========================================
            // 3. WIDGETS DA TOPBAR (Quick Create e Notificações)
            // ==========================================
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn(): string => Blade::render('
                    <div class="flex items-center gap-2 mr-4">
                        @livewire("quick-create-menu")
                        @livewire("system-alerts")
                    </div>
                '),
            )

            // ==========================================
                        // 4. DROPDOWN DE NAVEGAÇÃO DE PRODUTOS/INSUMOS (ABAS)
                        // ==========================================
                        ->renderHook(
                            PanelsRenderHook::PAGE_HEADER_ACTIONS_BEFORE,
                            // Recebemos os 'scopes' (que no Filament contém a classe da página atual)
                            fn (array $scopes = []): string => Blade::render('
                                @php
                                    $hasTeleport = false;

                                    // 1. Tenta identificar pelo Scope ativo passado pelo Filament
                                    foreach ($scopes as $scope) {
                                        if (is_string($scope) && class_exists($scope)) {
                                            if (in_array($teleportTrait, class_uses_recursive($scope))) {
                                                $hasTeleport = true;
                                                break;
                                            }
                                        }
                                    }

                                    // 2. Fallback de segurança: captura o controller da rota ativa
                                    if (!$hasTeleport) {
                                        $action = request()->route()?->getActionName();
                                        if (is_string($action)) {
                                            $class = current(explode("@", $action));
                                            if (class_exists($class) && in_array($teleportTrait, class_uses_recursive($class))) {
                                                $hasTeleport = true;
                                            }
                                        }
                                    }
                                @endphp

                                @if(!$hasTeleport)
                                    @include("filament.components.dropdown-nav")
                                @endif
                            ', [
                                "scopes" => $scopes,
                                "teleportTrait" => \App\Filament\Traits\TeleportsTabs::class
                            ])
                        )

                        // ==========================================
                        // 5. LOGIN COM GOOGLE
                        // ==========================================
                        ->renderHook(
                            PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                            fn(): string => Blade::render('
                                <div class="mt-6">
                                    <div class="relative flex items-center justify-center">
                                        <div class="grow border-t border-gray-300 dark:border-gray-700"></div>
                                        <span class="shrink mx-4 text-gray-400 text-sm">Ou entre com</span>
                                        <div class="grow border-t border-gray-300 dark:border-gray-700"></div>
                                    </div>
                                    <!-- 🚀 AQUI: Adicionado a "intent" => "admin" para o GoogleController saber de onde viemos! -->
                                    <a href="{{ route("google.redirect", ["host" => request()->getHost(), "intent" => "admin"]) }}"
                                        class="mt-4 flex w-full items-center justify-center gap-3 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24">
                                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
                                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.66l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                                        </svg>
                                        Google
                                    </a>
                                </div>
                            '),
                        )

            // ==========================================
            // CONFIGURAÇÃO GLOBAL DOS GRUPOS
            // ==========================================
            ->navigationGroups([
                NavigationGroup::make("Vendas")->icon("heroicon-o-shopping-cart")->collapsed(),
                NavigationGroup::make("Atendimento")->icon("heroicon-o-bell-alert")->collapsed(),
                NavigationGroup::make("Cardápio")->icon("heroicon-o-book-open")->collapsed(),
                NavigationGroup::make("Financeiro")->icon("heroicon-o-banknotes")->collapsed(),
                NavigationGroup::make("Estoque & Compras")->icon("heroicon-o-archive-box")->collapsed(),
                NavigationGroup::make("CRM")->icon("heroicon-o-user-group")->collapsed(),
                NavigationGroup::make("Logística")->icon("heroicon-o-truck")->collapsed(),
                NavigationGroup::make("Legado Fiscal")->icon("heroicon-o-archive-box-arrow-down")->collapsed(),
                NavigationGroup::make("Configurações")->icon("heroicon-o-cog-6-tooth")->collapsed(),
            ])

            ->discoverResources(in: app_path("Filament/Resources"), for: "App\\Filament\\Resources")
            ->discoverPages(in: app_path("Filament/Pages"), for: "App\\Filament\\Pages")
            ->discoverWidgets(in: app_path("Filament/Widgets"), for: "App\\Filament\\Widgets")

            ->plugins([
                \Saade\FilamentFullCalendar\FilamentFullCalendarPlugin::make()
                    ->selectable()
                    ->editable()
                    ->locale("pt-br"),
            ])
            ->middleware([
                \Illuminate\Cookie\Middleware\EncryptCookies::class,
                \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
                \Illuminate\Session\Middleware\StartSession::class,
                \Illuminate\Session\Middleware\AuthenticateSession::class,
                \Illuminate\View\Middleware\ShareErrorsFromSession::class,
                \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
                \Illuminate\Routing\Middleware\SubstituteBindings::class,
                \Filament\Http\Middleware\DisableBladeIconComponents::class,
                \Filament\Http\Middleware\DispatchServingFilamentEvent::class,
                \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
                \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,

                // 🚀 MIDDLEWARE DE TEMA ADICIONADO AQUI
                \App\Http\Middleware\ApplyTenantTheme::class,
            ])
            ->authMiddleware([\Filament\Http\Middleware\Authenticate::class]);
    }

    public function boot(): void
    {
        // Limpamos todo o motor de injeção daqui e deixamos apenas as configurações nativas de Boot
        \Filament\Actions\Action::configureUsing(function ($action) {
            $action->modalFooterActionsAlignment(\Filament\Support\Enums\Alignment::End);
        });
    }
}
