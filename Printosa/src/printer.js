import PrinterDriver from "@thiagoelg/node-printer";
import axios from 'axios';
import escpos from 'escpos';
import escposNetwork from 'escpos-network';
import escposUsb from 'escpos-usb';
import fs from 'fs';
import { CharacterSet, PrinterTypes, ThermalPrinter } from "node-thermal-printer";
import os from 'os';
import path from 'path';

escpos.USB = escposUsb;
escpos.Network = escposNetwork;

class LinuxRawDevice {
    constructor(devicePath) {
        this.devicePath = devicePath;
        this.stream = null;
    }
    open(callback) {
        fs.access(this.devicePath, fs.constants.W_OK, (err) => {
            if (err) {
                return callback(new Error(`Sem permissão na porta ${this.devicePath}. No terminal: sudo chmod 666 ${this.devicePath}`));
            }
            try {
                this.stream = fs.createWriteStream(this.devicePath);
                this.stream.on('error', (e) => console.error('[LINUX DEVICE ERROR]', e));
                callback(null);
            } catch (openErr) {
                callback(openErr);
            }
        });
    }
    write(data, callback) {
        if (this.stream) this.stream.write(data, callback);
        else callback(new Error("Stream de hardware fechada"));
    }
    close(callback) {
        if (this.stream) {
            this.stream.end(() => {
                this.stream = null;
                if (callback) callback();
            });
        } else if (callback) {
            callback();
        }
    }
}

function printHardwareQrCode(printer, text, size = 4) {
    const dataBuffer = Buffer.from(text, 'utf-8');
    const len = dataBuffer.length + 3;
    const pL = len & 0xFF;
    const pH = (len >> 8) & 0xFF;
    const moduleSize = Math.min(Math.max(size || 4, 1), 16);

    const buffer = Buffer.concat([
        Buffer.from([0x1D, 0x28, 0x6B, 0x04, 0x00, 0x31, 0x41, 0x32, 0x00]),
        Buffer.from([0x1D, 0x28, 0x6B, 0x03, 0x00, 0x31, 0x43, moduleSize]),
        Buffer.from([0x1D, 0x28, 0x6B, 0x03, 0x00, 0x31, 0x45, 0x30]),
        Buffer.from([0x1D, 0x28, 0x6B, pL, pH, 0x31, 0x50, 0x30]),
        dataBuffer,
        Buffer.from([0x1D, 0x28, 0x6B, 0x03, 0x00, 0x31, 0x51, 0x30]),
        Buffer.from([0x0A])
    ]);

    printer.raw(buffer);
}

let printQueue = Promise.resolve();

class PrinterService {
    static async executeJob(jobData, mode = 'os') {
        return new Promise((resolve, reject) => {
            printQueue = printQueue
                .catch(() => {})
                .then(async () => {
                    try {
                        const result = await PrinterService._runJob(jobData, mode);
                        resolve(result);
                    } catch (err) {
                        reject(err);
                    }
                });
        });
    }

    static async _runJob(jobData, mode = 'os') {
        return new Promise(async (resolve, reject) => {
            const startTime = Date.now();
            const printerPath = jobData.os_name || jobData.printer_path || '';
            const effectiveMode = printerPath.startsWith('/dev/') ? 'raw' : (mode || 'os');

            if (effectiveMode === 'raw') {
                try {
                    let device;
                    let printer;

                    if (jobData.printer_type === 'network') {
                        device = new escpos.Network(printerPath || '192.168.1.100');
                    } else if (printerPath.startsWith('/dev/')) {
                        console.log(`[LINUX-RAW] Conectando diretamente ao descritor: ${printerPath}`);
                        device = new LinuxRawDevice(printerPath);
                    } else {
                        const usbDevices = escpos.USB.findPrinter();
                        if (!usbDevices || usbDevices.length === 0) {
                            return reject(new Error('Nenhuma impressora USB encontrada fisicamente.'));
                        }
                        device = new escpos.USB();
                    }

                    const options = { encoding: "GB18030" };
                    printer = new escpos.Printer(device, options);

                    device.open(async function (error) {
                        if (error) return reject(error);

                        printer.raw(Buffer.from([
                            0x1B, 0x40,
                            0x1D, 0x21, 0x00,
                            0x1B, 0x20, 0x00
                        ]));

                        let currentFont = 'A';

                        for (const line of jobData.blueprint) {
                            switch (line.cmd) {
                                case 'align':
                                    const rawVal = (line.val || '').toLowerCase().trim();
                                    if (rawVal === 'ct' || rawVal === 'center' || rawVal === 'c') printer.align('CT');
                                    else if (rawVal === 'rt' || rawVal === 'right' || rawVal === 'r') printer.align('RT');
                                    else printer.align('LT');
                                    break;

                                case 'font':
                                    currentFont = (line.val || '').toUpperCase() === 'B' ? 'B' : 'A';
                                    if (currentFont === 'B') {
                                        printer.font('B');
                                        printer.raw(Buffer.from([0x1B, 0x4D, 0x01, 0x1B, 0x21, 0x01]));
                                    } else {
                                        printer.font('A');
                                        printer.raw(Buffer.from([0x1B, 0x4D, 0x00, 0x1B, 0x21, 0x00]));
                                    }
                                    break;

                                case 'text':
                                    if (line.bold) printer.style('B');
                                    else printer.style('NORMAL');

                                    if (line.invert) {
                                        if (typeof printer.inverse === 'function') printer.inverse(true);
                                    } else {
                                        if (typeof printer.inverse === 'function') printer.inverse(false);
                                    }

                                    if (line.size === '2x') {
                                        printer.raw(Buffer.from([0x1D, 0x21, 0x11]));
                                    } else if (line.size === '1,0') {
                                        printer.raw(Buffer.from([0x1D, 0x21, 0x10]));
                                    } else {
                                        printer.raw(Buffer.from([0x1D, 0x21, 0x00]));
                                    }

                                    if (currentFont === 'B') {
                                        printer.raw(Buffer.from([0x1B, 0x4D, 0x01]));
                                    }

                                    printer.text(line.val);
                                    break;

                                case 'image':
                                    try {
                                        const res = await axios.get(line.url, { responseType: 'arraybuffer', timeout: 10000 });
                                        const tempFile = path.join(os.tmpdir(), `raw_img_${Date.now()}.png`);
                                        fs.writeFileSync(tempFile, Buffer.from(res.data));
                                        await new Promise((resImg) => {
                                            escpos.Image.load(tempFile, function (image) {
                                                try { printer.image(image, 'd24'); } catch (e) {}
                                                try { fs.unlinkSync(tempFile); } catch (_) {}
                                                resImg();
                                            });
                                        });
                                    } catch (e) {
                                        console.error("Erro no download da imagem (raw):", e.message);
                                    }
                                    break;

                                case 'qrcode':
                                    printer.align('CT');
                                    printHardwareQrCode(printer, line.data, line.size || 4);
                                    break;

                                case 'cut':
                                    printer.raw(Buffer.from([0x1B, 0x64, 0x02]));
                                    printer.cut();
                                    break;

                                case 'beep':
                                    printer.beep(2, 2);
                                    break;
                            }
                        }

                        printer.close(() => {
                            setTimeout(() => {
                                resolve(`Impressão enviada em ${Date.now() - startTime}ms (Modo RAW)!`);
                            }, 80);
                        });
                    });
                } catch (err) {
                    reject(err);
                }
            } else {
                try {
                    const targetName = printerPath || "POS-80";
                    let interfaceConfig = jobData.printer_type === 'network'
                        ? `tcp://${targetName}:9100`
                        : `printer:${targetName}`;

                    const maxColumns = (jobData.paper_width === 58) ? 42 : 64;

                    const printer = new ThermalPrinter({
                        type: PrinterTypes.EPSON,
                        interface: interfaceConfig,
                        driver: PrinterDriver,
                        characterSet: CharacterSet.PC860_PORTUGUESE,
                        removeSpecialCharacters: false,
                        lineCharacter: "-",
                        width: maxColumns,
                        options: { timeout: 8000 }
                    });

                    const isConnected = await printer.isPrinterConnected();
                    if (!isConnected) return reject(new Error(`A impressora '${targetName}' não foi encontrada no Sistema.`));

                    for (const line of jobData.blueprint) {
                        switch (line.cmd) {
                            case 'align':
                                const alignVal = (line.val || '').toLowerCase().trim();
                                if (alignVal === 'ct' || alignVal === 'center' || alignVal === 'c') printer.alignCenter();
                                else if (alignVal === 'rt' || alignVal === 'right' || alignVal === 'r') printer.alignRight();
                                else printer.alignLeft();
                                break;

                            case 'font':
                                const isB = (line.val || '').toUpperCase() === 'B';
                                if (isB) {
                                    if (typeof printer.setTypeFontB === 'function') printer.setTypeFontB();
                                } else {
                                    if (typeof printer.setTypeFontA === 'function') printer.setTypeFontA();
                                }
                                break;

                            case 'text':
                                printer.bold(line.bold ? true : false);
                                if (line.invert) printer.invert(true);
                                else printer.invert(false);

                                if (line.size === '2x') printer.setTextSize(1, 1);
                                else if (line.size === '1,0') printer.setTextSize(1, 0);
                                else printer.setTextSize(0, 0);

                                printer.println(line.val);
                                break;

                            case 'image':
                                try {
                                    const response = await axios.get(line.url, { responseType: 'arraybuffer', timeout: 10000 });
                                    const imgBuffer = Buffer.from(response.data);

                                    if (typeof printer.printImageBuffer === 'function') {
                                        await printer.printImageBuffer(imgBuffer);
                                    } else {
                                        const tempFile = path.join(os.tmpdir(), `printosa_img_${Date.now()}.png`);
                                        fs.writeFileSync(tempFile, imgBuffer);
                                        try { await printer.printImage(tempFile); }
                                        finally { try { fs.unlinkSync(tempFile); } catch (_) {} }
                                    }
                                } catch (imgError) {}
                                break;

                            case 'qrcode':
                                printer.alignCenter();
                                printer.printQR(line.data, { cellSize: line.size || 6 });
                                break;

                            case 'cut':
                                printer.newLine();
                                printer.newLine();
                                printer.cut();
                                break;

                            case 'beep':
                                printer.beep();
                                break;
                        }
                    }

                    await printer.execute();
                    resolve(`Impressão enviada em ${Date.now() - startTime}ms (Modo OS/CUPS)!`);
                } catch (err) {
                    reject(err);
                }
            }
        });
    }
}

export default PrinterService;
