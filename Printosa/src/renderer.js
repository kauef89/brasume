const { ipcRenderer } = require('electron');

const loginScreen = document.getElementById('login-screen');
const mappingScreen = document.getElementById('mapping-screen');
const dashboardScreen = document.getElementById('dashboard-screen');

const inputApiUrl = document.getElementById('apiUrl');
const inputApiToken = document.getElementById('apiToken');
const btnConnect = document.getElementById('btnConnect');

const printersList = document.getElementById('printers-list');
const btnSaveMapping = document.getElementById('btnSaveMapping');
const activityLog = document.getElementById('activity-log');
const mappedCount = document.getElementById('mapped-count');
const btnTestPrint = document.getElementById('btnTestPrint');
const btnRemap = document.getElementById('btnRemap');
const btnStop = document.getElementById('btnStop');

const btnShowLogs = document.getElementById('btnShowLogs');
const logModal = document.getElementById('log-modal');
const btnCloseLogs = document.getElementById('btnCloseLogs');

function showScreen(screenId) {
    loginScreen.classList.add('hidden');
    mappingScreen.classList.add('hidden');
    dashboardScreen.classList.add('hidden');

    const target = document.getElementById(screenId);
    if (target) target.classList.remove('hidden');
}

function addLog(message, type = 'system') {
    if (!activityLog) return;
    const entry = document.createElement('div');
    entry.className = `log-entry ${type}`;
    const time = new Date().toLocaleTimeString();
    entry.textContent = `[${time}] ${message}`;
    activityLog.appendChild(entry);
    activityLog.scrollTop = activityLog.scrollHeight;
}

let cloudPrinters = [];
let localHardwarePrinters = [
    { id: 'none', name: '-- Não Conectada --' },
    { id: '/dev/usb/lp0', name: 'Porta USB 1 (/dev/usb/lp0)' },
    { id: '/dev/usb/lp1', name: 'Porta USB 2 (/dev/usb/lp1)' },
    { id: 'POS-80', name: 'Fila CUPS (POS-80)' },
    { id: '192.168.1.100', name: 'Rede: EPSON (192.168.1.100)' }
];

window.addEventListener('DOMContentLoaded', () => {
    ipcRenderer.send('request-saved-credentials');
});

ipcRenderer.on('saved-credentials', (event, creds) => {
    if (creds?.domain) inputApiUrl.value = creds.domain;
    if (creds?.bridgeToken) inputApiToken.value = creds.bridgeToken;

    if (creds?.domain && creds?.bridgeToken) {
        showScreen('dashboard-screen');
        addLog("Conexão restaurada com sucesso.", "success");
    } else {
        showScreen('login-screen');
    }
});

ipcRenderer.on('bridge-status', (event, status) => {
    if (status.success) {
        showScreen('dashboard-screen');
        addLog("Motor de Impressão Brasume conectado e operando.", "success");
    } else {
        showScreen('login-screen');
        addLog(`Falha na conexão: ${status.message}`, "error");
        btnConnect.innerText = "Conectar à Brasume";
    }
});

ipcRenderer.on('deeplink-received', (event, data) => {
    if (data.domain) inputApiUrl.value = data.domain;
    if (data.token) inputApiToken.value = data.token;
    addLog(`Deep link processado para ${data.domain}. Conectando...`);
});

btnConnect.addEventListener('click', async () => {
    const domain = inputApiUrl.value.trim().replace(/\/$/, '');
    const token = inputApiToken.value.trim();

    if (!domain || !token) {
        alert("Preencha o Endereço do Sistema e o Token.");
        return;
    }

    btnConnect.innerText = "Conectando...";
    addLog("Consultando impressoras cadastradas na nuvem...");

    try {
        const response = await ipcRenderer.invoke('api-fetch-printers', { domain, token });
        cloudPrinters = response.printers || [];

        if (cloudPrinters.length > 0) {
            renderMatchmaker();
            showScreen('mapping-screen');
        } else {
            ipcRenderer.send('start-bridge', { domain, bridgeToken: token, printMode: 'os' });
        }
    } catch (err) {
        alert("Erro ao buscar impressoras: " + err.message);
        btnConnect.innerText = "Conectar à Brasume";
    }
});

function renderMatchmaker() {
    printersList.innerHTML = '';

    cloudPrinters.forEach(printer => {
        const row = document.createElement('div');
        row.className = 'printer-row';

        const optionsHtml = localHardwarePrinters.map(local => {
            const selected = (printer.os_name === local.id) ? 'selected' : '';
            return `<option value="${local.id}" ${selected}>${local.name}</option>`;
        }).join('');

        row.innerHTML = `
            <div class="cloud-info">
                <div class="cloud-name">${printer.name} ${printer.is_cashier ? '💰' : '🍳'}</div>
                <div class="cloud-meta">Tipo: ${printer.type} | Papel: ${printer.paper_width}mm</div>
            </div>
            <div class="local-select">
                <select id="map-${printer.id}">
                    ${optionsHtml}
                </select>
            </div>
        `;

        printersList.appendChild(row);
    });

    if (mappedCount) mappedCount.textContent = cloudPrinters.length;
}

btnSaveMapping.addEventListener('click', () => {
    const domain = inputApiUrl.value.trim().replace(/\/$/, '');
    const token = inputApiToken.value.trim();

    let hasDirectUsb = false;
    cloudPrinters.forEach(printer => {
        const select = document.getElementById(`map-${printer.id}`);
        if (select && select.value.startsWith('/dev/')) {
            hasDirectUsb = true;
        }
    });

    const printMode = hasDirectUsb ? 'raw' : 'os';
    addLog(`Ligando motor no modo: ${printMode.toUpperCase()}...`);

    ipcRenderer.send('start-bridge', {
        domain,
        bridgeToken: token,
        printMode: printMode
    });
});

if (btnTestPrint) {
    btnTestPrint.addEventListener('click', () => {
        addLog("Disparando comando de teste de impressão...", "system");
        ipcRenderer.send('test-print');
    });
}

if (btnShowLogs && logModal) {
    btnShowLogs.addEventListener('click', () => logModal.classList.remove('hidden'));
}

if (btnCloseLogs && logModal) {
    btnCloseLogs.addEventListener('click', () => logModal.classList.add('hidden'));
}

if (btnRemap) {
    btnRemap.addEventListener('click', async () => {
        const domain = inputApiUrl.value.trim();
        const token = inputApiToken.value.trim();
        const res = await ipcRenderer.invoke('api-fetch-printers', { domain, token });
        cloudPrinters = res.printers || [];
        renderMatchmaker();
        showScreen('mapping-screen');
    });
}

if (btnStop) {
    btnStop.addEventListener('click', () => {
        showScreen('login-screen');
        btnConnect.innerText = "Conectar à Brasume";
        addLog("Motor desconectado.", "system");
    });
}
