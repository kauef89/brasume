import axios from 'axios';
import PusherPkg from 'pusher-js';
import PrinterService from './printer.js';

let Pusher = PusherPkg;
while (Pusher && typeof Pusher !== 'function' && Pusher.default) {
    Pusher = Pusher.default;
}
if (Pusher && typeof Pusher !== 'function' && Pusher.Pusher) {
    Pusher = Pusher.Pusher;
}

class BrasumeBridge {
    constructor(domain, reverbHost, reverbKey, bridgeToken, printMode, logger) {
        this.printMode = printMode;
        this.apiUrl = domain.startsWith('http') ? domain : `https://${domain}`;
        this.reverbHost = reverbHost;
        this.reverbKey = reverbKey;
        this.bridgeToken = bridgeToken;
        this.version = '1.0.0';
        this.tenantId = null;
        this.pusher = null;
        this.log = logger || console.log;

        this.api = axios.create({
            baseURL: this.apiUrl,
            headers: {
                'X-Bridge-Token': this.bridgeToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        });
    }

    async start() {
        this.log(`🚀 Iniciando BrasumePrint Bridge v${this.version}...`);

        try {
            this.log("🤝 Fazendo handshake com o servidor...");
            const response = await this.api.post('/api/bridge/handshake', {
                version: this.version
            });

            if (response.data.status === 'outdated') {
                this.log(`❌ ${response.data.message}`);
                throw new Error(response.data.message);
            }

            this.tenantId = response.data.tenant_id;
            this.log(`✅ Handshake aceite! Tenant ID: ${this.tenantId}`);
            this.log("🔌 Conectando o Pusher Nativo ao servidor Reverb...");

            this.pusher = new Pusher(this.reverbKey, {
                wsHost: this.reverbHost,
                wsPort: 80,
                wssPort: 443,
                forceTLS: true,
                disableStats: true,
                enabledTransports: ['ws', 'wss'],
                cluster: 'mt1'
            });

            this.pusher.connection.bind('connected', () => {
                this.log(`🎧 Antena ligada com SUCESSO! Escutando a loja ID: ${this.tenantId}...`);
            });

            this.pusher.connection.bind('error', (err) => {
                const errorMsg = err?.error?.data?.message || err?.message || String(err) || "Erro desconhecido de socket";
                const errorCode = err?.error?.data?.code || err?.code || "N/A";
                this.log(`⚠️ Aviso na conexão Pusher: [Code ${errorCode}] ${errorMsg}`);
            });

            const channel = this.pusher.subscribe(`tenant.${this.tenantId}.printers`);

            channel.bind('print.job.created', async (job) => {
                await this.processJob(job);
            });

            return true;
        } catch (error) {
            this.log(`❌ Falha fatal: ${error.response?.data?.error || error.message}`);
            throw error;
        }
    }

    stop() {
        if (this.pusher) {
            try {
                this.log("🛑 Desconectando antena Pusher/Reverb...");
                if (this.tenantId) {
                    this.pusher.unsubscribe(`tenant.${this.tenantId}.printers`);
                }
                this.pusher.disconnect();
            } catch (e) {
                this.log(`Aviso ao desconectar: ${e.message}`);
            }
            this.pusher = null;
        }
    }

    async processJob(job) {
        const jobId = job.print_job_id || job.id;
        const jobType = job.job_type || job.type;
        const isLocalTest = String(jobId).startsWith('TESTE-LOCAL');

        this.log(`\n   NOVO TRABALHO RECEBIDO! ID: #${jobId} | Tipo: ${jobType}`);

        try {
            const result = await PrinterService.executeJob(job, this.printMode);
            this.log(`  Impressão Sucesso: ${result}`);

            if (!isLocalTest) {
                await this.reportStatus(jobId, 'success');
            }
        } catch (error) {
            this.log(`❌ Erro na impressão: ${error.message}`);

            if (!isLocalTest) {
                await this.reportStatus(jobId, 'failed', error.message);
            }
        }
    }

    async reportStatus(jobId, status, errorMessage = null) {
        try {
            await this.api.post(`/api/bridge/jobs/${jobId}/status`, {
                status: status,
                error_message: errorMessage
            });
        } catch (e) {
            this.log(`Falha ao reportar status ao servidor: ${e.message}`);
        }
    }
}

export default BrasumeBridge;
