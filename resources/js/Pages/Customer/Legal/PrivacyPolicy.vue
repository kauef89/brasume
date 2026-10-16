<template>
    <div class="min-h-screen bg-surface-ground text-surface-text p-4 md:p-8 flex flex-col items-center">
        <div class="w-full max-w-3xl flex flex-col gap-6">

            <!-- CABEÇALHO -->
            <div class="flex items-center gap-4 border-b border-surface-border pb-4">
                <button
                    @click="goBack"
                    class="w-10 h-10 rounded-full bg-surface-1 flex items-center justify-center text-surface-text hover:bg-surface-2 transition-colors cursor-pointer border-none outline-none"
                >
                    <i class="pi pi-arrow-left"></i>
                </button>
                <div>
                    <h1 class="text-xl md:text-2xl font-black">Termos de Uso e Privacidade</h1>
                    <p class="text-xs md:text-sm text-surface-muted">Última atualização: Setembro de 2026</p>
                </div>
            </div>

            <!-- CONTEÚDO -->
            <div class="prose prose-invert max-w-none text-sm leading-relaxed space-y-6 text-surface-muted">

                <section>
                    <h2 class="text-base font-bold text-surface-text mb-2">1. Sobre o Serviço</h2>
                    <p>
                        Este sistema de cardápio digital e pedidos online é disponibilizado pelo estabelecimento parceiro utilizando a plataforma tecnológica <strong>Brasume</strong>. O estabelecimento atua como <strong>Controlador</strong> dos seus dados pessoais, enquanto a infraestrutura atua como <strong>Operadora</strong> tecnológica conforme a Lei Geral de Proteção de Dados (Lei nº 13.709/2018 - LGPD).
                    </p>
                </section>

                <section>
                    <h2 class="text-base font-bold text-surface-text mb-2">2. Dados Coletados e Finalidade</h2>
                    <p>Coletamos apenas as informações estritamente necessárias para a prestação do serviço:</p>
                    <ul class="list-disc pl-5 space-y-1 mt-2">
                        <li><strong>Identificação e Contato:</strong> Nome e telefone (WhatsApp), utilizados para identificação do pedido, status de entrega e contato operacional.</li>
                        <li><strong>Dados de Entrega:</strong> Endereço completo, bairro e pontos de referência, necessários para o despacho por entregadores.</li>
                        <li><strong>Dados Fiscais:</strong> CPF (opcional ou quando exigido por lei) informado voluntariamente para emissão de Nota Fiscal de Consumidor Eletrônica (NFC-e).</li>
                        <li><strong>Dados Técnicos de Navegação:</strong> Endereço IP, tipo de dispositivo e navegador, para fins de segurança e prevenção a fraudes.</li>
                    </ul>
                </section>

                <section>
                    <h2 class="text-base font-bold text-surface-text mb-2">3. Modalidade de Pedido como Visitante (Guest)</h2>
                    <p>
                        Você pode realizar pedidos sem criar uma senha formal ("Modo Visitante"). Nesses casos, os dados informados são armazenados exclusivamente para registrar a venda, viabilizar a entrega e manter o histórico de consumo associado ao seu número de telefone.
                    </p>
                </section>

                <section>
                    <h2 class="text-base font-bold text-surface-text mb-2">4. Pagamentos</h2>
                    <p>
                        Pagamentos via PIX são processados diretamente por instituições financeiras autorizadas via API bancária. Nenhuma informação bancária sensível ou senha é armazenada em nossos servidores. Pagamentos no ato da entrega (dinheiro ou cartão na maquininha) são processados presencialmente.
                    </p>
                </section>

                <section>
                    <h2 class="text-base font-bold text-surface-text mb-2">5. Programa de Fidelidade e Cashback</h2>
                    <p>
                        Caso o estabelecimento utilize o módulo de fidelidade, seu saldo de pontos/cashback é vinculado ao seu número de telefone cadastrado, aplicando regras de expiração e limites definidos pelo estabelecimento.
                    </p>
                </section>

                <section>
                    <h2 class="text-base font-bold text-surface-text mb-2">6. Cookies e Tecnologias de Métricas</h2>
                    <p>
                        Utilizamos cookies e tecnologias similares para:
                    </p>
                    <ul class="list-disc pl-5 space-y-1 mt-2">
                        <li><strong>Essenciais:</strong> Manter itens salvos no carrinho e preservar sua sessão de navegação.</li>
                        <li><strong>Métricas e Publicidade:</strong> Compreender a eficácia de nossas campanhas em plataformas como Meta Ads (Facebook/Instagram) e Google Ads (Google Analytics 4), capturando parâmetros de URL (UTMs e identificadores de clique) e disparos de conversão de ponta a ponta.</li>
                    </ul>
                    <p class="mt-2">
                        Ao prosseguir para o cardápio, você manifesta ciência quanto ao uso de cookies operacionais e de mensuração de desempenho.
                    </p>
                </section>

                <section>
                    <h2 class="text-base font-bold text-surface-text mb-2">7. Seus Direitos (LGPD)</h2>
                    <p>
                        Você possui o direito de confirmar a existência de tratamento, acessar seus dados, solicitar correção de cadastros incompletos ou requerer a exclusão de seus dados pessoais do cadastro do estabelecimento (salvo retenções exigidas por obrigações fiscais e legais vigentes). Solicitações devem ser encaminhadas ao canal de suporte oficial do estabelecimento.
                    </p>
                </section>

            </div>

            <!-- RODAPÉ -->
            <div class="border-t border-surface-border pt-4 text-center">
                <button
                    @click="goBack"
                    class="px-6 py-2.5 bg-primary-500 hover:bg-primary-600 text-white font-bold text-sm rounded-full transition-colors cursor-pointer border-none outline-none"
                >
                    Voltar ao Cardápio
                </button>
            </div>

        </div>
    </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3';

const goBack = () => {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        router.visit('/cardapio');
    }
};
</script>
