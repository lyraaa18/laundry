/**
 * Koneksi Web Bluetooth untuk printer ESC/POS (BLE).
 * Dimuat global dari index.php agar reconnect bisa jalan tiap navigasi/reload —
 * tidak hanya di halaman pesanan.
 */
(function (global) {
    'use strict';

    var scope = global.__LAUNDRY_BT_SCOPE__ || global.__LAUNDRY_BT_SCOPE_LEGACY__ || 'global';
    var BT_STORAGE_KEY = 'laundry_bt_printer_id_' + scope;
    var BT_DEVICE_LIST_KEY = 'laundry_bt_printer_ids_' + scope;
    var legacyScope = global.__LAUNDRY_BT_SCOPE_LEGACY__ || '';
    var LEGACY_BT_STORAGE_KEY = legacyScope ? 'laundry_bt_printer_id_' + legacyScope : '';
    var LEGACY_BT_DEVICE_LIST_KEY = legacyScope ? 'laundry_bt_printer_ids_' + legacyScope : '';
    var GLOBAL_BT_STORAGE_KEY = 'laundry_bt_printer_id_global';
    var GLOBAL_BT_DEVICE_LIST_KEY = 'laundry_bt_printer_ids_global';
    var DEFAULT_BT_CONFIG = {
        serviceUuids: [0xFFE0, '49535343-fe7d-4ae5-8fa9-9fafd205e455'],
        writeCharUuids: [0xFFE1, '49535343-8841-43f4-a8d4-ecbe34729bb3']
    };

    function normalizeGattConfig(value) {
        if (!value) return [];
        if (Array.isArray(value)) return value;
        return [value];
    }

    function getGattConfig(key) {
        var override = normalizeGattConfig(global.__LAUNDRY_BT_GATT__ && global.__LAUNDRY_BT_GATT__[key]);
        return override.length ? override : DEFAULT_BT_CONFIG[key];
    }

    var BT_CONFIG = {
        serviceUuids: getGattConfig('serviceUuids'),
        writeCharUuids: getGattConfig('writeCharUuids')
    };

    var btDevice = null;
    var btServer = null;
    var btWriteCharacteristic = null;
    var btReady = false;
    var btKnownDevices = [];
    var btReconnectTimer = null;
    var btReconnectAttempts = 0;
    var btKeepAliveTimer = null;
    var BT_MAX_RECONNECT_ATTEMPTS = 15;
    var startupReconnectInFlight = null;
    var startupReconnectRetryTimer = null;

    function migrateLegacyScopeData() {
        if (!LEGACY_BT_STORAGE_KEY || legacyScope === scope) return;
        try {
            var currentActive = global.localStorage.getItem(BT_STORAGE_KEY);
            var legacyActive = global.localStorage.getItem(LEGACY_BT_STORAGE_KEY);
            if (!currentActive && legacyActive) {
                global.localStorage.setItem(BT_STORAGE_KEY, legacyActive);
            }

            var currentRaw = global.localStorage.getItem(BT_DEVICE_LIST_KEY);
            var legacyRaw = global.localStorage.getItem(LEGACY_BT_DEVICE_LIST_KEY);
            var currentList = currentRaw ? JSON.parse(currentRaw) : [];
            var legacyList = legacyRaw ? JSON.parse(legacyRaw) : [];
            if (!Array.isArray(currentList)) currentList = [];
            if (!Array.isArray(legacyList)) legacyList = [];

            if (legacyList.length) {
                var merged = currentList.slice();
                legacyList.forEach(function (id) {
                    if (merged.indexOf(id) === -1) merged.push(id);
                });
                global.localStorage.setItem(BT_DEVICE_LIST_KEY, JSON.stringify(merged));
            }
        } catch (err) {}
    }

    function getStorageCandidates() {
        var keys = [BT_STORAGE_KEY, LEGACY_BT_STORAGE_KEY, GLOBAL_BT_STORAGE_KEY];
        for (var i = 0; i < keys.length; i++) {
            if (!keys[i]) continue;
            var v = global.localStorage.getItem(keys[i]);
            if (v) return v;
        }
        return '';
    }

    function getMergedSavedPrinterIds() {
        var all = [];
        [BT_DEVICE_LIST_KEY, LEGACY_BT_DEVICE_LIST_KEY, GLOBAL_BT_DEVICE_LIST_KEY].forEach(function (key) {
            if (!key) return;
            try {
                var raw = global.localStorage.getItem(key);
                var arr = raw ? JSON.parse(raw) : [];
                if (Array.isArray(arr)) {
                    arr.forEach(function (id) {
                        if (all.indexOf(id) === -1) all.push(id);
                    });
                }
            } catch (err) {}
        });
        return all;
    }

    migrateLegacyScopeData();

    function startBtKeepAlive() {
        stopBtKeepAlive();
        btKeepAliveTimer = setInterval(function () {
            if (!btReady || !btWriteCharacteristic) {
                stopBtKeepAlive();
                return;
            }
            var ping = new Uint8Array([0x10, 0x04, 0x01]);
            btWriteCharacteristic.writeValue(ping).catch(function () {});
        }, 5000);
    }

    function stopBtKeepAlive() {
        if (btKeepAliveTimer) {
            clearInterval(btKeepAliveTimer);
            btKeepAliveTimer = null;
        }
    }

    function canUseWebBluetooth() {
        return global.isSecureContext && !!navigator.bluetooth;
    }

    function hasGetDevicesSupport() {
        return !!(navigator.bluetooth && typeof navigator.bluetooth.getDevices === 'function');
    }

    function getAutoReconnectBlocker() {
        if (!global.isSecureContext) return 'HALAMAN_TIDAK_SECURE';
        if (!navigator.bluetooth) return 'WEB_BLUETOOTH_TIDAK_TERSEDIA';
        if (!hasGetDevicesSupport()) return 'GET_DEVICES_TIDAK_DIDUKUNG';
        if (!global.localStorage.getItem(BT_STORAGE_KEY) && !getStorageCandidates() && getMergedSavedPrinterIds().length === 0) {
            return 'BELUM_ADA_PRINTER_TERSIMPAN';
        }
        return '';
    }

    function setBtDebugState(extra) {
        try {
            global.__LAUNDRY_BT_DEBUG__ = Object.assign(
                {
                    scope: scope,
                    secureContext: !!global.isSecureContext,
                    hasBluetoothApi: !!navigator.bluetooth,
                    hasGetDevices: hasGetDevicesSupport(),
                    activeId: global.localStorage.getItem(BT_STORAGE_KEY) || '',
                    activeIdCandidate: getStorageCandidates() || '',
                    gattServiceUuids: BT_CONFIG.serviceUuids.slice(0, 5),
                    gattWriteCharUuids: BT_CONFIG.writeCharUuids.slice(0, 5),
                    savedIdsCount: getMergedSavedPrinterIds().length,
                    ready: !!btReady,
                    blocker: getAutoReconnectBlocker() || ''
                },
                extra || {}
            );
        } catch (_) {}
    }

    function getBluetoothEnvInfo() {
        return {
            origin: global.location.origin || '-',
            secureContext: global.isSecureContext ? 'Ya' : 'Tidak',
            bluetoothApi: typeof navigator.bluetooth !== 'undefined' ? 'Tersedia' : 'Tidak tersedia',
            userAgent: navigator.userAgent || '-'
        };
    }

    function updateBluetoothUi() {
        var statusEl = document.getElementById('bt-printer-status');
        var connectBtn = document.getElementById('bt-connect-btn');
        var disconnectBtn = document.getElementById('bt-disconnect-btn');
        var picker = document.getElementById('bt-printer-picker');
        var envEl = document.getElementById('bt-env-debug');
        if (!statusEl || !connectBtn || !disconnectBtn) return;
        if (envEl) {
            envEl.style.display = 'none';
        }

        var supported = canUseWebBluetooth();
        if (!supported) {
            statusEl.innerText = 'Web Bluetooth tidak tersedia di browser ini.';
            connectBtn.disabled = true;
            disconnectBtn.disabled = true;
            if (picker) picker.disabled = true;
            return;
        }

        if (!btReady && getAutoReconnectBlocker() === 'GET_DEVICES_TIDAK_DIDUKUNG') {
            statusEl.innerText =
                'Browser ini belum support reconnect otomatis (navigator.bluetooth.getDevices). Gunakan tombol Connect Printer.';
        }

        if (btReady && btDevice) {
            statusEl.innerText = 'Terhubung: ' + (btDevice.name || 'Bluetooth Printer');
            connectBtn.style.display = 'none';
            disconnectBtn.style.display = 'inline-flex';
            disconnectBtn.disabled = false;
            if (picker) picker.disabled = false;
            return;
        }

        statusEl.innerText = 'Belum terhubung ke printer Bluetooth.';
        connectBtn.style.display = 'inline-flex';
        disconnectBtn.style.display = 'none';
        connectBtn.disabled = false;
        if (picker) picker.disabled = false;
    }

    function getSavedPrinterIds() {
        try {
            var raw = global.localStorage.getItem(BT_DEVICE_LIST_KEY);
            var parsed = raw ? JSON.parse(raw) : [];
            return Array.isArray(parsed) ? parsed : [];
        } catch (err) {
            return [];
        }
    }

    function savePrinterId(id) {
        if (!id) return;
        var current = getSavedPrinterIds();
        if (current.indexOf(id) === -1) {
            current.push(id);
            global.localStorage.setItem(BT_DEVICE_LIST_KEY, JSON.stringify(current));
        }
    }

    function refreshKnownPrinters() {
        var picker = document.getElementById('bt-printer-picker');
        if (!picker || !canUseWebBluetooth() || !hasGetDevicesSupport()) return Promise.resolve();

        return navigator.bluetooth.getDevices().then(function (devices) {
            var savedIds = getSavedPrinterIds();
            btKnownDevices = devices.filter(function (d) {
                return savedIds.indexOf(d.id) !== -1;
            });

            picker.innerHTML = '<option value="">-- Pilih Printer Tersimpan --</option>';
            btKnownDevices.forEach(function (d) {
                var opt = document.createElement('option');
                opt.value = d.id;
                opt.textContent = d.name || 'Printer ' + d.id.slice(0, 8);
                picker.appendChild(opt);
            });

            var activeId = global.localStorage.getItem(BT_STORAGE_KEY) || getStorageCandidates();
            if (activeId) picker.value = activeId;
        }).catch(function () {});
    }

    function connectKnownPrinter() {
        if (!btDevice) return;
        btDevice.removeEventListener('gattserverdisconnected', handleBtDisconnected);
        btDevice.addEventListener('gattserverdisconnected', handleBtDisconnected);
    }

    function resolveWriteCharacteristic(server) {
        return BT_CONFIG.serviceUuids.reduce(function (chain, serviceUuid) {
            return chain.then(function (found) {
                if (found) return found;
                return server.getPrimaryService(serviceUuid).then(function (service) {
                    return BT_CONFIG.writeCharUuids.reduce(function (cChain, charUuid) {
                        return cChain.then(function (wc) {
                            if (wc) return wc;
                            return service.getCharacteristic(charUuid).catch(function () {
                                return null;
                            });
                        });
                    }, Promise.resolve(null)).then(function (writable) {
                        if (writable) return writable;
                        return service.getCharacteristics().then(function (chars) {
                            var w = null;
                            for (var i = 0; i < chars.length; i++) {
                                if (chars[i].properties.write || chars[i].properties.writeWithoutResponse) {
                                    w = chars[i];
                                    break;
                                }
                            }
                            return w;
                        });
                    });
                }).catch(function () {
                    return null;
                });
            });
        }, Promise.resolve(null)).then(function (resolved) {
            if (resolved) return resolved;
            return server.getPrimaryServices().then(function (services) {
                var iter = function (idx) {
                    if (idx >= services.length) return Promise.resolve(null);
                    return services[idx].getCharacteristics().then(function (chars) {
                        for (var i = 0; i < chars.length; i++) {
                            if (chars[i].properties.write || chars[i].properties.writeWithoutResponse) {
                                return chars[i];
                            }
                        }
                        return iter(idx + 1);
                    });
                };
                return iter(0);
            });
        });
    }

    function connectPrinterDevice(device) {
        btDevice = device;
        connectKnownPrinter();
        if (!btDevice.gatt) {
            return Promise.reject(new Error('Printer ini tidak mendukung BLE GATT (kemungkinan Classic Bluetooth/SPP only).'));
        }

        function tryConnectGatt(attemptsLeft) {
            return btDevice.gatt.connect().catch(function (err) {
                if (attemptsLeft <= 1) throw err;
                return new Promise(function (r) {
                    setTimeout(r, 350);
                }).then(function () {
                    return tryConnectGatt(attemptsLeft - 1);
                });
            });
        }

        return tryConnectGatt(2).then(function (server) {
            btServer = server;
            return resolveWriteCharacteristic(btServer);
        }).then(function (wc) {
            btWriteCharacteristic = wc;
            if (!btWriteCharacteristic) {
                throw new Error('Characteristic tulis printer tidak ditemukan.');
            }
            btReady = true;
            btReconnectAttempts = 0;
            if (startupReconnectRetryTimer) {
                clearTimeout(startupReconnectRetryTimer);
                startupReconnectRetryTimer = null;
            }
            if (btReconnectTimer) {
                clearTimeout(btReconnectTimer);
                btReconnectTimer = null;
            }
            global.localStorage.setItem(BT_STORAGE_KEY, btDevice.id);
            savePrinterId(btDevice.id);
            startBtKeepAlive();
            updateBluetoothUi();
            updateFloatingBtStatus();
            return refreshKnownPrinters();
        });
    }

    function handleBtDisconnected() {
        btReady = false;
        btServer = null;
        btWriteCharacteristic = null;
        if (startupReconnectRetryTimer) {
            clearTimeout(startupReconnectRetryTimer);
            startupReconnectRetryTimer = null;
        }
        stopBtKeepAlive();
        updateBluetoothUi();
        updateFloatingBtStatus();
        scheduleAutoReconnect();
    }

    function scheduleAutoReconnect() {
        if (!canUseWebBluetooth() || btReconnectAttempts >= BT_MAX_RECONNECT_ATTEMPTS) return;
        var targetId = global.localStorage.getItem(BT_STORAGE_KEY);
        if (!targetId) return;

        btReconnectAttempts += 1;
        var delay = 700 * btReconnectAttempts;
        var statusEl = document.getElementById('bt-printer-status');
        if (statusEl) {
            statusEl.innerText =
                'Koneksi putus. Mencoba reconnect (' + btReconnectAttempts + '/' + BT_MAX_RECONNECT_ATTEMPTS + ')...';
        }

        btReconnectTimer = setTimeout(function () {
            silentReconnect(targetId).catch(function () {});
        }, delay);
    }

    global.connectBluetoothPrinter = function () {
        if (!canUseWebBluetooth()) {
            global.alert(
                'Web Bluetooth tidak tersedia di browser ini. Gunakan mode cetak fallback (RawBT/window.print).'
            );
            return;
        }
        navigator.bluetooth
            .requestDevice({
                filters: [
                    { services: ['000018f0-0000-1000-8000-00805f9b34fb'] },
                    { services: ['e7810a71-73ae-499d-8c15-faa9aef0c3f2'] },
                    { namePrefix: 'PT' },
                    { namePrefix: 'MTP' },
                    { namePrefix: 'RP' },
                    { namePrefix: 'Bluetooth' }
                ],
                optionalServices: BT_CONFIG.serviceUuids
            })
            .then(function (device) {
                return connectPrinterDevice(device);
            })
            .then(function () {
                global.alert('Printer Bluetooth berhasil terhubung.');
            })
            .catch(function (err) {
                if (err && err.name !== 'NotFoundError') {
                    var detail = err && err.message ? err.message : String(err);
                    var hint = '';
                    if (detail.toLowerCase().indexOf('unknown error when connecting') !== -1) {
                        hint =
                            '\nKemungkinan: printer sedang terhubung ke device lain, printer bukan BLE, atau BLE printer tidak support Web Bluetooth.';
                    }
                    global.alert('Gagal konek printer: ' + detail + hint);
                }
                updateBluetoothUi();
            });
    };

    function silentReconnect(targetDeviceId) {
        if (!canUseWebBluetooth() || !hasGetDevicesSupport()) {
            return Promise.resolve(false);
        }
        try {
            var savedId =
                targetDeviceId != null && targetDeviceId !== ''
                    ? targetDeviceId
                    : (global.localStorage.getItem(BT_STORAGE_KEY) || getStorageCandidates());
            var mergedSavedIds = getMergedSavedPrinterIds();
            return navigator.bluetooth.getDevices().then(function (devices) {
                var knownDevice = null;
                if (savedId) {
                    for (var i = 0; i < devices.length; i++) {
                        if (devices[i].id === savedId) {
                            knownDevice = devices[i];
                            break;
                        }
                    }
                }
                if (!knownDevice && mergedSavedIds.length) {
                    for (var j = 0; j < devices.length; j++) {
                        if (mergedSavedIds.indexOf(devices[j].id) !== -1) {
                            knownDevice = devices[j];
                            break;
                        }
                    }
                }
                if (!knownDevice && devices.length === 1) knownDevice = devices[0];
                if (!knownDevice) return false;
                return connectPrinterDevice(knownDevice).then(function () {
                    return true;
                });
            });
        } catch (_) {
            updateBluetoothUi();
            updateFloatingBtStatus();
            return Promise.resolve(false);
        }
    }

    async function reconnectBluetoothPrinter(targetDeviceId) {
        if (!canUseWebBluetooth()) {
            global.alert('Web Bluetooth tidak tersedia di browser ini.');
            return;
        }
        if (!hasGetDevicesSupport()) {
            global.alert('Browser ini belum support reconnect otomatis (navigator.bluetooth.getDevices).');
            return;
        }
        var ok = await silentReconnect(targetDeviceId || null).catch(function () {
            return false;
        });
        if (typeof global.showToast === 'function') {
            global.showToast(
                ok ? '✅ Printer Bluetooth tersambung kembali.' : '❌ Reconnect gagal. Pastikan printer menyala.',
                !ok
            );
        } else if (!ok) {
            global.alert('Reconnect gagal. Pastikan printer menyala.');
        }
    }

    global.reconnectBluetoothPrinter = reconnectBluetoothPrinter;

    global.reconnectSelectedPrinter = async function () {
        var picker = document.getElementById('bt-printer-picker');
        var selected = picker ? picker.value : '';
        if (!selected) {
            global.alert('Pilih printer tersimpan dulu.');
            return;
        }
        await reconnectBluetoothPrinter(selected);
    };

    global.ensureBluetoothReady = async function ensureBluetoothReady() {
        if (btReady && btWriteCharacteristic) return true;
        var savedId = global.localStorage.getItem(BT_STORAGE_KEY);
        if (!savedId) return false;
        var delays = [0, 200, 500, 1000, 2000, 3000];
        for (var i = 0; i < delays.length; i++) {
            if (delays[i]) {
                await new Promise(function (r) {
                    setTimeout(r, delays[i]);
                });
            }
            var ok = await silentReconnect(savedId).catch(function () {
                return false;
            });
            if (ok) return true;
        }
        return false;
    };

    function updateFloatingBtStatus() {
        var bar = document.getElementById('bt-float-bar');
        if (!bar) return;
        var name = btDevice ? btDevice.name || 'Bluetooth Printer' : null;
        if (btReady && name) {
            bar.style.display = 'none';
        } else {
            var savedId = global.localStorage.getItem(BT_STORAGE_KEY) || getStorageCandidates();
            var blocker = getAutoReconnectBlocker();
            if (savedId) {
                if (blocker === 'GET_DEVICES_TIDAK_DIDUKUNG') {
                    bar.innerHTML =
                        '<span style="color:#f59e0b;">●</span> Printer putus — Auto reconnect tidak didukung di browser ini. Buka Menu Printer untuk Connect.';
                } else {
                    bar.innerHTML =
                        '<span style="color:#f59e0b;">●</span> Printer putus — <u style="cursor:pointer;" onclick="reconnectFloating()">Sambung ulang</u>';
                }
                bar.style.display = 'block';
            } else {
                bar.innerHTML = '<span style="color:#64748b;">●</span> Printer belum dipasang';
                bar.style.display = 'block';
            }
            bar.style.background = 'rgba(15,23,42,0.92)';
            bar.style.cursor = 'default';
        }
        setBtDebugState({ phase: 'ui_update' });
    }

    global.updateFloatingBtStatus = updateFloatingBtStatus;

    global.reconnectFloating = async function reconnectFloating() {
        var bar = document.getElementById('bt-float-bar');
        if (bar) bar.innerHTML = '<span style="color:#f59e0b;">⟳</span> Menyambungkan...';
        var ok = await silentReconnect().catch(function () {
            return false;
        });
        if (!ok && bar) {
            bar.innerHTML =
                '<span style="color:#ef4444;">●</span> Gagal — <u style="cursor:pointer;" onclick="reconnectFloating()">Coba lagi</u>';
        }
        updateFloatingBtStatus();
        updateBluetoothUi();
    };

    global.disconnectBluetoothPrinter = function disconnectBluetoothPrinter() {
        if (btDevice && btDevice.gatt && btDevice.gatt.connected) {
            btDevice.gatt.disconnect();
        }
        btReady = false;
        btServer = null;
        btWriteCharacteristic = null;
        stopBtKeepAlive();
        updateBluetoothUi();
        updateFloatingBtStatus();
    };

    global.getBluetoothPrinterDisplayName = function () {
        return btDevice && btDevice.name ? btDevice.name : 'Bluetooth Printer';
    };

    global.writeToBluetoothPrinter = async function writeToBluetoothPrinter(content) {
        var runWrite = async function () {
            if (!btReady || !btWriteCharacteristic) {
                throw new Error('Printer Bluetooth belum terhubung.');
            }
            if (btDevice && btDevice.gatt && !btDevice.gatt.connected) {
                btReady = false;
                btWriteCharacteristic = null;
                throw new Error('Printer Bluetooth terputus.');
            }
            var bytes;
            if (content instanceof Uint8Array) {
                bytes = content;
            } else if (typeof content === 'string') {
                bytes = Uint8Array.from(content.split('').map(function (ch) {
                    return ch.charCodeAt(0) & 0xff;
                }));
            } else {
                throw new Error('Format data cetak tidak didukung.');
            }
            var chunkSize = 128;
            var useWithoutResponse =
                btWriteCharacteristic.properties &&
                btWriteCharacteristic.properties.writeWithoutResponse &&
                typeof btWriteCharacteristic.writeValueWithoutResponse === 'function';

            for (var i = 0; i < bytes.length; i += chunkSize) {
                var chunk = bytes.slice(i, i + chunkSize);
                if (useWithoutResponse) {
                    await btWriteCharacteristic.writeValueWithoutResponse(chunk);
                } else {
                    await btWriteCharacteristic.writeValue(chunk);
                }
                await new Promise(function (r) {
                    setTimeout(r, 50);
                });
            }
        };

        try {
            await runWrite();
        } catch (errFirst) {
            btReady = false;
            btWriteCharacteristic = null;
            updateBluetoothUi();
            updateFloatingBtStatus();
            var sid = global.localStorage.getItem(BT_STORAGE_KEY);
            if (
                !sid ||
                !(await silentReconnect(sid).catch(function () {
                    return false;
                }))
            ) {
                throw errFirst;
            }
            await runWrite();
        }
    };

    async function warmupBluetoothReconnect(maxRetries) {
        if (!canUseWebBluetooth() || !hasGetDevicesSupport()) return false;
        if (!global.localStorage.getItem(BT_STORAGE_KEY) && !getStorageCandidates() && getMergedSavedPrinterIds().length === 0) {
            return false;
        }
        if (btReady && btWriteCharacteristic) return true;
        if (startupReconnectInFlight) return startupReconnectInFlight;

        var retries = typeof maxRetries === 'number' ? maxRetries : 12;
        var retryDelays = [0, 200, 500, 1000, 1500, 2000, 3000, 4000, 5000, 6000, 8000, 10000];
        setBtDebugState({ phase: 'warmup_start' });
        startupReconnectInFlight = (async function () {
            for (var i = 0; i <= retries; i++) {
                if (btReady && btWriteCharacteristic) return true;
                var delay = retryDelays[Math.min(i, retryDelays.length - 1)];
                if (delay > 0) {
                    await new Promise(function (resolve) {
                        startupReconnectRetryTimer = setTimeout(resolve, delay);
                    });
                    startupReconnectRetryTimer = null;
                }
                setBtDebugState({ phase: 'warmup_attempt', attempt: i + 1 });
                var ok = await silentReconnect().catch(function () {
                    return false;
                });
                if (ok) return true;
            }
            return false;
        })();

        var okFinal = await startupReconnectInFlight.finally(function () {
            startupReconnectInFlight = null;
        });
        setBtDebugState({ phase: 'warmup_done', ok: okFinal });
        updateFloatingBtStatus();
        updateBluetoothUi();
        return okFinal;
    }

    global.addEventListener('DOMContentLoaded', function () {
        if (!document.getElementById('bt-float-bar')) {
            var bar = document.createElement('div');
            bar.id = 'bt-float-bar';
            bar.style.cssText =
                'position:fixed;bottom:14px;right:14px;z-index:8888;padding:7px 14px;border-radius:20px;font-size:0.78rem;font-weight:700;color:#fff;background:rgba(15,23,42,0.92);backdrop-filter:blur(6px);box-shadow:0 2px 12px rgba(0,0,0,0.3);letter-spacing:0.3px;transition:all 0.3s;';
            document.body.appendChild(bar);
        }
        updateBluetoothUi();
        updateFloatingBtStatus();
        refreshKnownPrinters().then(function () {
            warmupBluetoothReconnect().catch(function () {});
        });
    });

    global.addEventListener('pageshow', function () {
        if (!canUseWebBluetooth()) return;
        warmupBluetoothReconnect().catch(function () {});
        refreshKnownPrinters().catch(function () {});
    });

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            if (!btReady) {
                warmupBluetoothReconnect().catch(function () {});
            }
            updateBluetoothUi();
            updateFloatingBtStatus();
        }
    });

    global.addEventListener('focus', function () {
        if (!btReady) {
            warmupBluetoothReconnect().catch(function () {});
        }
        updateBluetoothUi();
        updateFloatingBtStatus();
    });
})(typeof window !== 'undefined' ? window : this);