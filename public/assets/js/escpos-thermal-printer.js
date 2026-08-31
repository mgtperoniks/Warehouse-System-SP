/**
 * 🖨️ EscPosThermalPrinter - WMS Industrial ESC/POS 58mm Thermal Printer Engine
 * 
 * Target Printer: Xantri 58 mm Bluetooth Thermal Printer (and ESC/POS compatible devices)
 * Column Width: 32 columns (Standard Font A 12x24)
 * Transports:
 *   1. Web Bluetooth API (Primary: Browser BLE direct GATT connection)
 *   2. RawBT Android Intent (Universal Fallback for Classic Bluetooth SPP / RFCOMM)
 * 
 * Safety: Purely client-side presentation renderer. Zero database/stock mutation.
 */
(function (global) {
    'use strict';

    const COLS_58MM = 32;

    // Common Bluetooth LE GATT Service UUIDs used by 58mm POS thermal printers
    const BLE_SERVICES = [
        '000018f0-0000-1000-8000-00805f9b34fb', // Standard POS Printer Service
        '0000ffe0-0000-1000-8000-00805f9b34fb', // Common BLE Serial (Feasycom/HM-10/Xantri)
        '0000ff00-0000-1000-8000-00805f9b34fb', // Custom POS Service
        '0000fff0-0000-1000-8000-00805f9b34fb', // Custom POS Service
        '49535343-fe7d-4ae5-8fa9-9fafd205e455', // ISSC Transparent Serial
        'e7810a71-73ae-499d-8c15-faa9aef0c3f2', // Nordic UART Service (NUS)
        '6e400001-b5a3-f393-e0a9-e50e24dcca9e', // Nordic UART alternate
    ];

    // Pre-computed 1-bit monochrome raster header (384x56 dots): Small Peroni Logo + SPAREPART WAREHOUSE + STOCK OUT RECEIPT
    const PERONI_LOGO_RASTER_BASE64 = 'HXYwADAAOAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAHw/weD/j/n+Dwf8/8HHjjwf8f84cH4cOHw/4AAAAAAAAAAAAAAAAAAAAAAAAAAAAP4/4eD/z/n/Dwf+/8HHjjwf+f84cf8cOP4/4AAAAAAAAAAAAAAAAAAAAAAAAAAAAcc48fDhzgHHj4cODgDHjj4cOcA4c8ecOcc4AAAAAAAAAAAAAAAAAAAAAAAAAAAAAcc4c/DhzgHDn4cODgDnzH4cOcA4c4OcOcc4AAAAAAAAAAAAAAAAAAAAAAACAAAAAfA48zDhzgHHmYcODgDv3GYcOcA4c4HcOfA4AAAAAAAAAAAAAAAAAAAAQAAHAAAgAP4/5zj/z/n/Ocf+DgDu3Ocf+f8/8wHcOP4/4AAAAAAAAAAAAAAAAAAAAAAPgAAQAD8/xzj/D/n+Ocf4DgDs3Ocf4f8/8wHcOD8/4AAAAAAAAAAAAAAAAAAAgAQXAQAQAAc4B/znDgHAP+c4DgB8+P+c4cA4c4HcOAc4AAAAAAAAAAAAAAAAAAAAAAZf8wAAAYO4D/zjjgHAf+ccDgB8+f+cccA4c4OcOYO4AAAAAAAAAAAAAAAAAAABAAdFFgAIAcc4DhzjzgHAcOceDgB4ecOcecA4c8eeOcc4AAAAAAAAAAAAAAAAAAABAAP//gAIAP84HA7hz/3A4HcODgB4e4HcOf+4cf8P8P8/8AAAAAAAAAAAAAAAAAABAAP//gAIAH44HA7g7/3A4HcHDgA4c4HcHf+4cH4H4H4/8AAAAAAAAAAAAAAAAAABAAP//AAMAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACAAAAAAAEAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACDz7485sEAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACD77485sEAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACDbDf39sEAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACD/7/D/sEAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAADD777D/sMAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAADDzD7t7sMAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAADDD7Z+7sMAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAABDD7M8ZsMAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAABgAAAAAAIAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAABiBsiatgYAPz/j8D4YcA/DDv+D/H+Hw/zP5/wAAAAAAAAAAAAAAAAAAAAAAAAAAABjNkjTpsYAf7/n+H8Y4B/jDv+D/n+P4/zP9/wAAAAAAAAAAAAAAAAAAAAAAAAAAAAyMlzaIswAY4cPPOOZwDzzDhwDDmAccwDMcOAAAAAAAAAAAAAAAAAAAAAAAAAAAAAwAAAAAAwAcAcOHMEbwDhzDhwDDmAYIwDMMOAAAAAAAAAAAAAAAAAAAAAAAAAAAAA4AAhgABwAfAcMDMAfgDAzDhwDDn+YA/zMcOAAAAAAAAAAAAAAAAAAAAAAAAAAAAAcAAAAABgAPwcMDMAfgDAzDhwD/H+YA/zP8OAAAAAAAAAAAAAAAAAAAAAAAAAAAAAMAAAAADgAD4cMDMAfwDAzDhwD+GAYAwDP4OAAAAAAAAAAAAAAAAAAAAAAAAAAAAAOAAAAAHAAA4cOHMEc4DhzjhwDOGAYIwDMAOAAAAAAAAAAAAAAAAAAAAAAAAAAAAAHAAAAAOAAYYcPPOOY4DzzjhwDHGAccwDMAOAAAAAAAAAAAAAAAAAAAAAAAAAAAAADgAAAAcAAf4cH+H8YcB/h/BwDDn+P4/zMAOAAAAAAAAAAAAAAAAAAAAAAAAAAAAABwAAAA4AAPwcD8D4YOA/A+BwDB3+Hw/zMAOAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA8AAADwAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAfAAAHgAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAPwAA/AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAD+AH+AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAB///4AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAP//AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAB/4AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=';

    /**
     * ESC/POS Command Builder
     */
    class EscPosBuilder {
        constructor(cols = COLS_58MM) {
            this.cols = cols;
            this.buffer = [];
        }

        // --- Low Level Byte Operations ---
        addBytes(...bytes) {
            for (let b of bytes) {
                if (Array.isArray(b) || ArrayBuffer.isView(b) || (typeof b === 'object' && b !== null && typeof b.length === 'number')) {
                    for (let i = 0; i < b.length; i++) {
                        this.buffer.push(b[i] & 0xFF);
                    }
                } else {
                    this.buffer.push(b & 0xFF);
                }
            }
            return this;
        }

        addBase64(base64Str) {
            if (!base64Str) return this;
            let binary = '';
            if (typeof atob === 'function') {
                binary = atob(base64Str);
            } else if (typeof window !== 'undefined' && typeof window.atob === 'function') {
                binary = window.atob(base64Str);
            } else {
                binary = Buffer.from(base64Str, 'base64').toString('binary');
            }
            const len = binary.length;
            for (let i = 0; i < len; i++) {
                this.buffer.push(binary.charCodeAt(i) & 0xFF);
            }
            return this;
        }

        addPeroniLogo() {
            return this.addBase64(PERONI_LOGO_RASTER_BASE64);
        }

        // --- Native ESC/POS Commands ---
        init() {
            return this.addBytes(0x1B, 0x40); // ESC @
        }

        alignLeft() {
            return this.addBytes(0x1B, 0x61, 0x00); // ESC a 0
        }

        alignCenter() {
            return this.addBytes(0x1B, 0x61, 0x01); // ESC a 1
        }

        alignRight() {
            return this.addBytes(0x1B, 0x61, 0x02); // ESC a 2
        }

        bold(enable = true) {
            return this.addBytes(0x1B, 0x45, enable ? 0x01 : 0x00); // ESC E n
        }

        doubleHeight(enable = true) {
            return this.addBytes(0x1D, 0x21, enable ? 0x01 : 0x00); // GS ! n
        }

        doubleWidth(enable = true) {
            return this.addBytes(0x1D, 0x21, enable ? 0x10 : 0x00); // GS ! n
        }

        doubleSize(enable = true) {
            return this.addBytes(0x1D, 0x21, enable ? 0x11 : 0x00); // GS ! n
        }

        normalSize() {
            return this.addBytes(0x1D, 0x21, 0x00); // GS ! 0
        }

        lineFeed(lines = 1) {
            for (let i = 0; i < lines; i++) {
                this.addBytes(0x0A); // LF
            }
            return this;
        }

        feedAndCut() {
            this.lineFeed(4);
            // GS V 66 0 (Partial cut with feed)
            this.addBytes(0x1D, 0x56, 0x42, 0x00);
            return this;
        }

        // --- Text & Encoding ---
        text(str) {
            if (!str) return this;
            // Clean control characters (except newline)
            const cleanStr = String(str).replace(/[\x00-\x09\x0B-\x1F\x7F]/g, '');
            const encoder = new TextEncoder();
            const encoded = encoder.encode(cleanStr);
            return this.addBytes(encoded);
        }

        textLine(str = '') {
            return this.text(str).lineFeed(1);
        }

        // --- 32-Column Formatting Helpers ---
        divider(char = '=') {
            const line = char.repeat(this.cols).substring(0, this.cols);
            return this.alignLeft().textLine(line);
        }

        dashedDivider() {
            return this.divider('-');
        }

        centerText(str) {
            return this.alignCenter().textLine(str).alignLeft();
        }

        leftRight(leftStr, rightStr) {
            const left = String(leftStr || '');
            const right = String(rightStr || '');
            const availableSpace = this.cols - left.length - right.length;

            if (availableSpace >= 0) {
                const spaces = ' '.repeat(availableSpace);
                return this.textLine(left + spaces + right);
            } else {
                // If left string is too long, print left on first line, right-aligned on next line
                this.textLine(left);
                const spaces = ' '.repeat(Math.max(0, this.cols - right.length));
                return this.textLine(spaces + right);
            }
        }

        itemRow(name, erpCode, qty, unit) {
            // Line 1: Item Name (wrap if longer than 32 cols)
            const nameStr = String(name || 'UNKNOWN ITEM').trim().toUpperCase();
            const wrappedNameLines = EscPosBuilder.wrapText(nameStr, this.cols);
            for (let line of wrappedNameLines) {
                this.textLine(line);
            }

            // Line 2: ERP Code (left) and Qty (right)
            const codeStr = String(erpCode || '-').trim();
            const qtyStr = `x${qty} ${unit || 'PCS'}`;
            return this.leftRight(codeStr, qtyStr);
        }

        static wrapText(text, maxWidth = COLS_58MM) {
            const words = String(text).split(' ');
            const lines = [];
            let currentLine = '';

            for (let word of words) {
                if ((currentLine + (currentLine ? ' ' : '') + word).length <= maxWidth) {
                    currentLine += (currentLine ? ' ' : '') + word;
                } else {
                    if (currentLine) lines.push(currentLine);
                    // If a single word is longer than maxWidth, hard-slice it
                    if (word.length > maxWidth) {
                        let remainingWord = word;
                        while (remainingWord.length > maxWidth) {
                            lines.push(remainingWord.substring(0, maxWidth));
                            remainingWord = remainingWord.substring(maxWidth);
                        }
                        currentLine = remainingWord;
                    } else {
                        currentLine = word;
                    }
                }
            }
            if (currentLine) lines.push(currentLine);
            return lines.length > 0 ? lines : [''];
        }

        getUint8Array() {
            return new Uint8Array(this.buffer);
        }

        getBase64() {
            const bytes = this.getUint8Array();
            let binary = '';
            const len = bytes.byteLength;
            for (let i = 0; i < len; i++) {
                binary += String.fromCharCode(bytes[i]);
            }
            if (typeof window !== 'undefined' && typeof window.btoa === 'function') {
                return window.btoa(binary);
            }
            if (typeof btoa === 'function') {
                return btoa(binary);
            }
            return Buffer.from(binary, 'binary').toString('base64');
        }
    }

    /**
     * Stock Out Receipt Builder
     */
    class StockOutReceiptFormatter {
        /**
         * Clean item name by stripping duplicate ERP code or SKU suffixes (e.g. "BEARING SKF 6204 ZZ - 5.01.SKF.6204")
         * @param {string} name 
         * @param {string} erpCode 
         * @returns {string}
         */
        static cleanItemName(name, erpCode) {
            if (!name) return 'UNKNOWN ITEM';
            let clean = String(name).trim();
            if (erpCode) {
                const trimmedCode = String(erpCode).trim();
                if (trimmedCode) {
                    // Escape special regex chars in code
                    const escaped = trimmedCode.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                    // Remove trailing " - ERP_CODE" or " - SKU"
                    clean = clean.replace(new RegExp('\\s*-\\s*' + escaped + '$', 'i'), '');
                    // Remove trailing "[ERP_CODE]"
                    clean = clean.replace(new RegExp('\\s*\\[\\s*' + escaped + '\\s*\\]$', 'i'), '');
                    // Remove trailing "(ERP_CODE)"
                    clean = clean.replace(new RegExp('\\s*\\(\\s*' + escaped + '\\s*\\)$', 'i'), '');
                }
            }
            return clean.trim();
        }

        /**
         * Converts canonical StockTransaction payload to ESC/POS binary data
         * @param {Object} tx - Canonical transaction object
         * @returns {EscPosBuilder}
         */
        static buildReceipt(tx) {
            const builder = new EscPosBuilder(COLS_58MM);

            builder.init();

            // 1. Compact Horizontal Company Logo & Header (Small Peroni Logo + SPAREPART WAREHOUSE + STOCK OUT RECEIPT)
            builder.addPeroniLogo();

            builder.dashedDivider();

            // 2. Metadata Section (Asia/Jakarta Timezone, Compact single-line DATE and TIME, OP removed)
            builder.textLine(`TRX  : ${tx.code || 'N/A'}`);
            builder.leftRight(`DATE : ${tx.date || 'N/A'}`, `TIME : ${tx.time || 'N/A'}`);
            builder.textLine(`DEPT : ${(tx.department || 'N/A').toUpperCase()}`);
            if (tx.pic) {
                builder.textLine(`PIC  : ${tx.pic.toUpperCase()}`);
            }
            if (tx.reference) {
                builder.textLine(`REF  : ${tx.reference}`);
            }

            builder.dashedDivider();

            // 3. Column Header
            builder.leftRight('ITEM / ERP CODE', 'QTY');
            builder.dashedDivider();

            // 4. Items List (with clean deduplicated ERP code and multi-line wrapping)
            let totalItemLines = 0;
            let totalPieces = 0;

            if (Array.isArray(tx.items) && tx.items.length > 0) {
                for (let item of tx.items) {
                    totalItemLines++;
                    const qtyNum = parseInt(item.qty, 10) || 1;
                    totalPieces += qtyNum;

                    const rawName = item.name || item.item_name_snapshot || 'UNKNOWN ITEM';
                    const erpCode = item.erp_code || item.erp_code_snapshot || '-';
                    const unit = item.unit || item.unit_snapshot || 'PCS';

                    const cleanName = StockOutReceiptFormatter.cleanItemName(rawName, erpCode);

                    builder.itemRow(cleanName, erpCode, qtyNum, unit);
                }
            } else {
                builder.textLine('NO ITEMS RECORDED');
            }

            builder.dashedDivider();

            // 5. Summary Totals (minimal vertical spacing)
            builder.textLine(`TOTAL ITEMS: ${totalItemLines} (${totalPieces} PCS)`);

            // 6. Paper saving: Minimal 3-line feed and cut (no courtesy footer text)
            builder.lineFeed(3);
            builder.addBytes(0x1D, 0x56, 0x42, 0x00);

            return builder;
        }
    }

    /**
     * Bluetooth Transport Controller
     */
    class BluetoothThermalTransport {
        constructor() {
            this.device = null;
            this.server = null;
            this.characteristic = null;
            this.status = 'DISCONNECTED'; // DISCONNECTED | CONNECTING | CONNECTED | PRINTING | SUCCESS | ERROR
            this.statusListeners = [];
        }

        onStatusChange(callback) {
            if (typeof callback === 'function') {
                this.statusListeners.push(callback);
            }
        }

        setStatus(newStatus, detail = '') {
            this.status = newStatus;
            for (let listener of this.statusListeners) {
                try {
                    listener(newStatus, detail);
                } catch (e) {
                    console.error('Status listener error:', e);
                }
            }
        }

        isWebBluetoothSupported() {
            return !!(navigator.bluetooth && navigator.bluetooth.requestDevice);
        }

        /**
         * Connect to Bluetooth LE Thermal Printer
         */
        async connect() {
            if (!this.isWebBluetoothSupported()) {
                throw new Error('Web Bluetooth is not supported on this browser/environment. Use HTTPS or RawBT fallback.');
            }

            this.setStatus('CONNECTING', 'Scanning for Bluetooth devices...');

            try {
                // Request device with all standard thermal printer services
                this.device = await navigator.bluetooth.requestDevice({
                    acceptAllDevices: true,
                    optionalServices: BLE_SERVICES
                });

                this.device.addEventListener('gattserverdisconnected', () => {
                    this.characteristic = null;
                    this.server = null;
                    this.setStatus('DISCONNECTED', 'Printer disconnected.');
                });

                this.setStatus('CONNECTING', `Connecting to ${this.device.name || 'Printer'}...`);

                this.server = await this.device.gatt.connect();

                // Discover primary service and writable characteristic
                let foundChar = null;
                for (let serviceUuid of BLE_SERVICES) {
                    try {
                        const service = await this.server.getPrimaryService(serviceUuid);
                        const characteristics = await service.getCharacteristics();
                        for (let char of characteristics) {
                            if (char.properties.write || char.properties.writeWithoutResponse) {
                                foundChar = char;
                                break;
                            }
                        }
                        if (foundChar) break;
                    } catch (e) {
                        // Service not present on this specific hardware, continue searching
                    }
                }

                if (!foundChar) {
                    // Fallback: try getting all primary services
                    const services = await this.server.getPrimaryServices();
                    for (let service of services) {
                        try {
                            const characteristics = await service.getCharacteristics();
                            for (let char of characteristics) {
                                if (char.properties.write || char.properties.writeWithoutResponse) {
                                    foundChar = char;
                                    break;
                                }
                            }
                            if (foundChar) break;
                        } catch (e) {}
                    }
                }

                if (!foundChar) {
                    throw new Error('Could not find a writable GATT characteristic on this Bluetooth device.');
                }

                this.characteristic = foundChar;
                this.setStatus('CONNECTED', `Connected to ${this.device.name || 'Xantri 58mm'}`);
                return true;
            } catch (err) {
                this.setStatus('ERROR', err.message || 'Bluetooth connection failed.');
                throw err;
            }
        }

        isConnected() {
            return !!(this.device && this.device.gatt && this.device.gatt.connected && this.characteristic);
        }

        /**
         * Low-level method to send raw byte buffer with chunking over active Bluetooth characteristic
         * @param {Uint8Array} data 
         */
        async sendRawBytes(data) {
            if (!this.characteristic) {
                throw new Error('Printer characteristic not available.');
            }
            const CHUNK_SIZE = 100;
            const totalBytes = data.byteLength;

            for (let offset = 0; offset < totalBytes; offset += CHUNK_SIZE) {
                const chunk = data.slice(offset, offset + CHUNK_SIZE);
                if (this.characteristic.properties.writeWithoutResponse) {
                    await this.characteristic.writeValueWithoutResponse(chunk);
                } else {
                    await this.characteristic.writeValue(chunk);
                }
                await new Promise(r => setTimeout(r, 25));
            }
        }

        /**
         * Send single raw byte payload to printer
         * @param {Uint8Array} data 
         */
        async print(data) {
            if (!this.isConnected()) {
                await this.connect();
            }

            this.setStatus('PRINTING', 'Sending ESC/POS payload to printer...');

            try {
                await this.sendRawBytes(data);
                this.setStatus('SUCCESS', 'Receipt printed successfully!');
                return true;
            } catch (err) {
                this.setStatus('ERROR', `Print failed: ${err.message}`);
                throw err;
            }
        }

        /**
         * Batch print multiple receipts sequentially reusing ONE Bluetooth connection
         * @param {Array} receiptsList - Array of transaction objects or Uint8Array buffers
         * @param {Function} onProgress - Progress callback ({ current, total, status, error })
         */
        async printBatch(receiptsList, onProgress = null) {
            if (!Array.isArray(receiptsList) || receiptsList.length === 0) {
                throw new Error('No receipts provided for batch printing.');
            }

            if (!this.isConnected()) {
                await this.connect();
            }

            const total = receiptsList.length;
            let printedCount = 0;

            this.setStatus('PRINTING', `Batch printing 1 / ${total}...`);

            for (let i = 0; i < total; i++) {
                const item = receiptsList[i];
                if (typeof onProgress === 'function') {
                    onProgress({ current: i + 1, total, status: 'PRINTING', item });
                }
                this.setStatus('PRINTING', `Printing receipt ${i + 1} / ${total}...`);

                try {
                    let rawBytes;
                    if (item instanceof Uint8Array) {
                        rawBytes = item;
                    } else if (item instanceof EscPosBuilder) {
                        rawBytes = item.getUint8Array();
                    } else {
                        rawBytes = StockOutReceiptFormatter.buildReceipt(item).getUint8Array();
                    }

                    await this.sendRawBytes(rawBytes);
                    printedCount++;

                    // Safe pause between receipts for physical paper feed
                    await new Promise(r => setTimeout(r, 400));
                } catch (err) {
                    this.setStatus('ERROR', `Batch printing interrupted at ${printedCount} / ${total}: ${err.message}`);
                    if (typeof onProgress === 'function') {
                        onProgress({ current: printedCount, total, status: 'INTERRUPTED', error: err.message });
                    }
                    return { success: false, printed: printedCount, total, error: err.message };
                }
            }

            this.setStatus('SUCCESS', `Batch complete: ${total} / ${total} receipts printed.`);
            if (typeof onProgress === 'function') {
                onProgress({ current: total, total, status: 'SUCCESS' });
            }
            return { success: true, printed: total, total };
        }

        /**
         * Print single receipt via RawBT Android App Intent (Universal Fallback)
         * @param {EscPosBuilder} builder 
         */
        printViaRawBt(builder) {
            const base64Data = builder.getBase64();
            const rawBtUrl = `intent:base64,${base64Data}#Intent;scheme=rawbt;package=ru.a402d.rawbtprinter;end;`;
            window.location.href = rawBtUrl;
            this.setStatus('SUCCESS', 'Dispatched to RawBT print service.');
        }

        /**
         * Batch print multiple receipts via RawBT Android App Intent
         * @param {Array} receiptsList 
         */
        printBatchViaRawBt(receiptsList) {
            if (!Array.isArray(receiptsList) || receiptsList.length === 0) {
                return;
            }
            const combinedBuilder = new EscPosBuilder(COLS_58MM);
            for (let item of receiptsList) {
                let singleBuilder = (item instanceof EscPosBuilder) 
                    ? item 
                    : StockOutReceiptFormatter.buildReceipt(item);
                combinedBuilder.addBytes(singleBuilder.getUint8Array());
            }
            this.printViaRawBt(combinedBuilder);
        }
    }

    // Export to global scope
    global.EscPosBuilder = EscPosBuilder;
    global.StockOutReceiptFormatter = StockOutReceiptFormatter;
    global.BluetoothThermalTransport = BluetoothThermalTransport;

})(typeof window !== 'undefined' ? window : (typeof global !== 'undefined' ? global : this));
