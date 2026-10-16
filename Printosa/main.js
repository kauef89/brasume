import PrinterDriver from "@thiagoelg/node-printer";
import axios from 'axios';
import { app, BrowserWindow, ipcMain, Menu, Tray } from 'electron';
import Store from 'electron-store';
import path from 'path';
import { fileURLToPath } from 'url';
import BrasumeBridge from './src/bridge.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

let store;
let mainWindow;
let tray = null;
let bridgeInstance = null;
let pendingDeepLinkUrl = null;

const SAAS_REVERB_HOST = 'ws.brasume.com.br';
const SAAS_REVERB_KEY = 'a4px8txpz0axv9h0xhb6';

function log(message) {
    console.log(message);
    if (mainWindow && mainWindow.webContents) {
        mainWindow.webContents.executeJavaScript(`console.log("💻 MAIN SYSTEM:", \`${message}\`)`).catch(() => {});
    }
}

const gotTheLock = app.requestSingleInstanceLock();
if (!gotTheLock) {
    app.quit();
} else {
    app.on('second-instance', (event, commandLine) => {
        if (mainWindow) {
            if (mainWindow.isMinimized()) mainWindow.restore();
            mainWindow.show();
            mainWindow.focus();
        }
        const urlArg = commandLine.find(arg => arg.startsWith('brasumeprint://'));
        if (urlArg) handleDeepLink(urlArg);
    });

    app.on('open-url', (event, url) => {
        event.preventDefault();
        pendingDeepLinkUrl = url;
        if (app.isReady() && mainWindow) {
            handleDeepLink(pendingDeepLinkUrl);
            pendingDeepLinkUrl = null;
        }
    });

    app.whenReady().then(async () => {
        store = new Store();

        if (process.defaultApp && process.argv.length >= 2) {
            app.setAsDefaultProtocolClient('brasumeprint', process.execPath, [path.resolve(process.argv[1])]);
        } else {
            app.setAsDefaultProtocolClient('brasumeprint');
        }

        createWindow();
        createTray();

        const savedDomain = store.get('domain');
        const savedToken = store.get('bridgeToken');
        const savedPrintMode = store.get('printMode') || 'os';

        if (savedDomain && savedToken && !pendingDeepLinkUrl) {
            log(`Credenciais salvas encontradas. Religando no modo: ${savedPrintMode}...`);
            startBridgeProcess(savedDomain, savedToken, savedPrintMode);
        }

        if (process.platform === 'darwin' && pendingDeepLinkUrl) {
            handleDeepLink(pendingDeepLinkUrl);
            pendingDeepLinkUrl = null;
        }

        if (process.platform === 'win32' && process.argv.length > 1) {
            const urlArg = process.argv.find(arg => arg.startsWith('brasumeprint://'));
            if (urlArg) handleDeepLink(urlArg);
        }
    });
}

function handleDeepLink(rawUrl) {
    if (!rawUrl || !rawUrl.toLowerCase().includes('brasumeprint://')) return;
    rawUrl = rawUrl.replace(/&amp;/g, '&');

    try {
        const parsedUrl = new URL(rawUrl);
        const domain = parsedUrl.searchParams.get('domain');
        const token = parsedUrl.searchParams.get('token');

        if (domain && token) {
            const sendDataToView = () => {
                mainWindow.show();
                mainWindow.webContents.send('deeplink-received', { domain, token });
            };

            if (mainWindow.webContents.isLoading()) {
                mainWindow.webContents.once('did-finish-load', sendDataToView);
            } else {
                sendDataToView();
            }

            const currentMode = store ? (store.get('printMode') || 'os') : 'os';
            startBridgeProcess(domain, token, currentMode);
        }
    } catch (e) {
        log(`Falha ao ler Deep Link: ${e.message}`);
    }
}

function createWindow() {
    mainWindow = new BrowserWindow({
        width: 450,
        height: 550,
        resizable: false,
        autoHideMenuBar: true,
        webPreferences: {
            nodeIntegration: true,
            contextIsolation: false
        }
    });
    mainWindow.loadFile('src/index.html');

    mainWindow.on('close', (event) => {
        if (!app.isQuitting) {
            event.preventDefault();
            mainWindow.hide();
        }
    });
}

function createTray() {
    const iconPath = path.join(__dirname, 'src', 'printosa-logo-512.png');
    tray = new Tray(iconPath);
    tray.setToolTip('Printosa - Motor de Impressão');
    tray.setContextMenu(Menu.buildFromTemplate([
        { label: 'Mostrar Painel', click: () => mainWindow.show() },
        { type: 'separator' },
        { label: 'Sair Completamente', click: () => { app.isQuitting = true; app.quit(); } }
    ]));

    tray.on('click', () => mainWindow.show());
    tray.on('double-click', () => mainWindow.show());
}

ipcMain.handle('api-fetch-printers', async (event, args) => {
    const domain = args?.domain || (store ? store.get('domain') : null);
    const token = args?.token || (store ? store.get('bridgeToken') : null);

    let cloudPrinters = [];
    if (domain && token) {
        try {
            const url = domain.startsWith('http') ? domain : `https://${domain}`;
            const res = await axios.get(`${url}/api/bridge/printers`, {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'X-Bridge-Token': token,
                    'Accept': 'application/json'
                },
                timeout: 7000
            });
            cloudPrinters = res.data?.printers || [];
        } catch (e) {
            log(`Aviso ao consultar impressoras na nuvem: ${e.message}`);
        }
    }

    let localPrinters = [];
    try {
        if (typeof PrinterDriver?.getPrinters === 'function') {
            localPrinters = PrinterDriver.getPrinters();
        }
    } catch (_) {}

    return {
        printers: cloudPrinters,
        localPrinters: localPrinters
    };
});

ipcMain.on('request-saved-credentials', (event) => {
    if (store) {
        event.reply('saved-credentials', {
            domain: store.get('domain'),
            bridgeToken: store.get('bridgeToken'),
            printMode: store.get('printMode') || 'os'
        });
    }
});

ipcMain.on('start-bridge', async (event, creds) => {
    const chosenMode = creds.printMode || 'os';
    await startBridgeProcess(creds.domain, creds.bridgeToken, chosenMode, event);
});

ipcMain.on('test-print', async () => {
    if (!bridgeInstance) {
        log("⚠️ Bridge inativa: impossível enviar teste.");
        return;
    }

    const testJob = {
        id: `TESTE-LOCAL-${Date.now()}`,
        job_type: 'test_ticket',
        os_name: '/dev/usb/lp1',
        blueprint: [
            { cmd: 'align', val: 'center' },
            { cmd: 'text', val: 'PRINTOSA - BRASUME', bold: true, size: '2x' },
            { cmd: 'text', val: 'TESTE DE IMPRESSAO LOCAL', bold: true },
            { cmd: 'text', val: '================================', bold: false },
            { cmd: 'align', val: 'left' },
            { cmd: 'text', val: `Data/Hora: ${new Date().toLocaleString('pt-BR')}` },
            { cmd: 'text', val: `Modo Ativo: ${store ? store.get('printMode') || 'raw' : 'raw'}` },
            { cmd: 'text', val: 'Porta Testada: /dev/usb/lp1' },
            { cmd: 'align', val: 'center' },
            { cmd: 'text', val: '--------------------------------' },
            { cmd: 'text', val: 'Hardware Comunicando OK!' },
            { cmd: 'cut' }
        ]
    };

    await bridgeInstance.processJob(testJob);
});

async function startBridgeProcess(domain, bridgeToken, printMode = 'os', ipcEvent = null) {
    if (bridgeInstance) {
        try {
            log("🔄 Desconectando instância anterior da bridge...");
            bridgeInstance.stop();
        } catch (e) {
            log(`Aviso ao encerrar bridge antiga: ${e.message}`);
        }
        bridgeInstance = null;
    }

    try {
        log(`Instanciando a Bridge (Modo base: ${printMode})...`);
        bridgeInstance = new BrasumeBridge(domain, SAAS_REVERB_HOST, SAAS_REVERB_KEY, bridgeToken, printMode, log);
        await bridgeInstance.start();

        if (store) {
            store.set('domain', domain);
            store.set('bridgeToken', bridgeToken);
            store.set('printMode', printMode);
        }

        const replyPayload = { success: true };
        if (ipcEvent) ipcEvent.reply('bridge-status', replyPayload);
        else if (mainWindow) mainWindow.webContents.send('bridge-status', replyPayload);
    } catch (err) {
        const errMsg = err.message || 'Falha na conexão.';
        log(`Erro repassado: ${errMsg}`);
        const replyPayload = { success: false, message: errMsg };
        if (ipcEvent) ipcEvent.reply('bridge-status', replyPayload);
        else if (mainWindow) mainWindow.webContents.send('bridge-status', replyPayload);
    }
}
