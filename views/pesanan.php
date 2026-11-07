<?php
$orders = $db->readFiltered('orders');
$customers = $db->readFiltered('customers');
$services = $db->readFiltered('services');

// Mapping Data untuk Efisiensi
$custMap = [];
$phoneMap = [];
$custBalanceMap = [];
foreach ($customers as $c) {
    $custMap[$c['id']] = $c['name'];
    $phoneMap[$c['id']] = preg_replace('/[^0-9]/', '', $c['phone']);
    $custBalanceMap[$c['id']] = $c['balance'] ?? 0;
}

$svcMap = [];
foreach ($services as $s) $svcMap[$s['id']] = $s['service_name'];

// Filter jika ada parameter dari URL
if (isset($_GET['customer_id'])) {
    $orders = array_filter($orders, function($o) {
        return $o['customer_id'] == $_GET['customer_id'];
    });
}
if (isset($_GET['status']) && $_GET['status'] == 'unpaid') {
    $orders = array_filter($orders, function($o) {
        $is_paid = (($o["paid_amount"] ?? 0) + ($o["balance_used"] ?? 0) >= ($o["grand_total"] ?? 0));
        return !$is_paid;
    });
}

// Urutkan Pesanan Terbaru di Atas
usort($orders, function($a, $b) {
    return strcmp($b['order_date'], $a['order_date']);
});
?>

<header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; gap: 15px;">
    <div class="header-title">
        <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--text-dark); margin: 0; white-space: nowrap;">Daftar Pesanan</h2>
    </div>
    <div class="header-actions">
        <button class="btn-primary" onclick="toggleModal('orderModal', true)" style="padding: 10px 20px; font-size: 0.85rem; border-radius: 12px; white-space: nowrap;">
            <i class="fas fa-plus"></i> Pesanan Baru
        </button>
    </div>
</header>

<div class="card">
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>NOTA</th>
                    <th>PELANGGAN</th>
                    <th>LAYANAN</th>
                    <th>STATUS</th>
                    <th style="text-align: right;">AKSI</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($orders as $o): 
                    $cName = $custMap[$o['customer_id']] ?? 'Pelanggan';
                    $cPhone = $phoneMap[$o['customer_id']] ?? '';
                    
                    $svcDisplay = '';
                    if(isset($o['services']) && is_array($o['services'])) {
                        $count = count($o['services']);
                        $svcDisplay = $o['services'][0]['name'] . ($count > 1 ? " (+$count)" : "");
                        $weightDisplay = ($o['total_weight'] ?? 0) . ' Qty';
                    } else {
                        $svcDisplay = $svcMap[$o['service_id'] ?? ''] ?? 'Layanan';
                        $weightDisplay = ($o['weight'] ?? 0) . ' Kg';
                    }

                    $waMsg = $settings['wa_template'] ?? "Pesanan [NOTA] sudah selesai!";
                    $waMsg = str_replace('[NAMA]', $cName, $waMsg);
                    $waMsg = str_replace('[NOTA]', $o['order_number'], $waMsg);
                    $waUrl = "https://wa.me/$cPhone?text=" . urlencode($waMsg);
                    
                    $is_paid = (($o["paid_amount"] ?? 0) + ($o["balance_used"] ?? 0) >= ($o["grand_total"] ?? 0));
                ?>
                <tr id="row-<?php echo $o['id']; ?>">
                    <td>
                        <strong style="color: var(--primary);">#<?php echo $o['order_number']; ?></strong>
                        <div style="font-size: 0.7rem; color: #94a3b8;"><?php echo date('d M, H:i', strtotime($o['order_date'])); ?></div>
                    </td>
                    <td style="font-weight: 700;"><?php echo htmlspecialchars($cName); ?></td>
                    <td>
                        <div style="font-weight: 600; font-size: 0.85rem;"><?php echo htmlspecialchars($svcDisplay); ?></div>
                        <div style="font-size: 0.75rem; color: #64748b;"><?php echo $weightDisplay; ?></div>
                    </td>
                    <td>
                        <span id="status-<?php echo $o['id']; ?>" class="status <?php echo strtolower($o['status']); ?>" style="display: block; margin-bottom: 4px;"><?php echo ucfirst($o['status']); ?></span>
                        <?php if($is_paid): ?>
                            <span style="display: inline-block; font-size: 0.65rem; background: #dcfce7; color: #166534; padding: 2px 8px; border-radius: 4px; font-weight: 800;">LUNAS</span>
                        <?php else: ?>
                            <span style="display: inline-block; font-size: 0.65rem; background: #fef2f2; color: #dc2626; padding: 2px 8px; border-radius: 4px; font-weight: 800;">BELUM LUNAS</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: right;">
                        <div style="display: flex; gap: 8px; justify-content: flex-end;">
                            <button onclick="openPrintModal('<?php echo $o['id']; ?>')" class="btn-primary" style="background: #f1f5f9; color: #1e293b; padding: 8px 12px; font-size: 0.85rem; border: 1px solid #e2e8f0;"><i class="fas fa-print"></i> Nota</button>
                            
                            <?php if(!$is_paid && $o['status'] != 'Dibatalkan' && $o['status'] != 'cancelled'): ?>
                                <button onclick="payOrder('<?php echo $o['id']; ?>', <?php echo $o['grand_total'] - ($o['paid_amount'] + $o['balance_used']); ?>)" class="btn-primary" style="background: #f59e0b; padding: 8px 12px; font-size: 0.85rem;"><i class="fas fa-money-bill-wave"></i> Pelunasan</button>
                            <?php endif; ?>

                            <?php if(strtolower($o['status']) == 'processing' || strtolower($o['status']) == 'proses'): ?>
                                <a href="actions.php?action=complete_order&id=<?php echo $o['id']; ?>" class="btn-primary" style="background: #10b981; color: white; padding: 8px 12px; font-size: 0.85rem; border: none; text-decoration: none;" onclick="return confirm('Tandai pesanan ini sebagai SELESAI?')">
                                    <i class="fas fa-check-circle"></i> Selesai
                                </a>
                            <?php endif; ?>

                            <?php 
                            $can_delete = ($_SESSION['role'] == 'owner' || ($_SESSION['role'] == 'staff' && ($o['created_by_role'] ?? 'staff') == 'staff'));
                            if($can_delete): ?>
                                <a href="actions.php?action=delete_order&id=<?php echo $o['id']; ?>" class="btn-primary" style="background: #fef2f2; color: #dc2626; padding: 8px 12px; font-size: 0.85rem; border: 1px solid #fee2e2; text-decoration: none;" onclick="return confirm('Hapus pesanan ini?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
var orders = <?php echo json_encode($orders); ?>;
var custs = <?php echo json_encode($custMap); ?>;
var custBalances = <?php echo json_encode($custBalanceMap); ?>;
var svcs = <?php echo json_encode($svcMap); ?>;
var receiptNoteText = <?php echo json_encode($settings['receipt_note'] ?? 'Terima kasih sudah laundry di tempat kami.'); ?>;
var businessAddressText = <?php echo json_encode($settings['address'] ?? '-'); ?>;
var businessPhoneText = <?php echo json_encode($settings['phone'] ?? '-'); ?>;

function sanitizeForPrinter(text) {
    return (text || '')
        .toString()
        .replace(/[^\x20-\x7E\n\r]/g, '')
        .trim();
}

function formatRp(num) {
    return `Rp ${Number(num || 0).toLocaleString('id-ID')}`;
}

function escposLine(text = '') {
    return sanitizeForPrinter(text) + '\n';
}

function escposQr(data) {
    const text = sanitizeForPrinter(data || '');
    if (!text) return '';

    const bytes = Array.from(text).map(ch => ch.charCodeAt(0) & 0xff);
    const storeLen = bytes.length + 3;
    const pL = storeLen & 0xff;
    const pH = (storeLen >> 8) & 0xff;

    let out = '';
    out += String.fromCharCode(0x1d, 0x28, 0x6b, 0x04, 0x00, 0x31, 0x41, 0x32, 0x00); // model 2
    out += String.fromCharCode(0x1d, 0x28, 0x6b, 0x03, 0x00, 0x31, 0x43, 0x06); // module size
    out += String.fromCharCode(0x1d, 0x28, 0x6b, 0x03, 0x00, 0x31, 0x45, 0x31); // ECC
    out += String.fromCharCode(0x1d, 0x28, 0x6b, pL, pH, 0x31, 0x50, 0x30); // store data
    out += String.fromCharCode(...bytes);
    out += String.fromCharCode(0x1d, 0x28, 0x6b, 0x03, 0x00, 0x31, 0x51, 0x30); // print QR
    return out;
}

function bytesToBinaryString(bytes) {
    let out = '';
    for (let i = 0; i < bytes.length; i++) out += String.fromCharCode(bytes[i]);
    return out;
}

function escposRasterFromImageData(imageData, width, height) {
    const bytesPerRow = Math.ceil(width / 8);
    const data = new Uint8Array(bytesPerRow * height);
    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            const idx = (y * width + x) * 4;
            const r = imageData[idx];
            const g = imageData[idx + 1];
            const b = imageData[idx + 2];
            const a = imageData[idx + 3];
            const gray = (r * 0.299 + g * 0.587 + b * 0.114);
            const isBlack = a > 0 && gray < 160;
            if (isBlack) {
                const byteIndex = y * bytesPerRow + (x >> 3);
                data[byteIndex] |= (0x80 >> (x & 7));
            }
        }
    }
    const xL = bytesPerRow & 0xff;
    const xH = (bytesPerRow >> 8) & 0xff;
    const yL = height & 0xff;
    const yH = (height >> 8) & 0xff;
    const header = String.fromCharCode(0x1d, 0x76, 0x30, 0x00, xL, xH, yL, yH);
    return header + bytesToBinaryString(data);
}

async function escposQrRaster(url) {
    const qrImg = new Image();
    qrImg.crossOrigin = 'anonymous';
    qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=180x180&margin=2&data=${encodeURIComponent(url)}`;
    await new Promise((resolve, reject) => {
        qrImg.onload = resolve;
        qrImg.onerror = reject;
    });

    const canvas = document.createElement('canvas');
    canvas.width = qrImg.width;
    canvas.height = qrImg.height;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(qrImg, 0, 0);
    const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
    return escposRasterFromImageData(img.data, canvas.width, canvas.height);
}

function getPrintFallbackMode() {
    const isPWA = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
    return isPWA ? 'rawbt-intent' : 'window-print';
}

function showToast(msg, isError = false) {
    let t = document.getElementById('bt-toast');
    if (!t) {
        t = document.createElement('div');
        t.id = 'bt-toast';
        t.style.cssText = 'position:fixed;bottom:80px;left:50%;transform:translateX(-50%);padding:10px 20px;border-radius:12px;font-size:0.85rem;font-weight:700;z-index:9999;transition:opacity 0.4s;pointer-events:none;';
        document.body.appendChild(t);
    }
    t.style.background = isError ? '#ef4444' : '#22c55e';
    t.style.color = '#fff';
    t.style.opacity = '1';
    t.innerText = msg;
    clearTimeout(t._timer);
    t._timer = setTimeout(() => { t.style.opacity = '0'; }, 2500);
}

async function buildCustomerReceiptEscpos(o) {
    const customerName = custs[o.customer_id] || 'Pelanggan';
    const totalPaid = (parseInt(o.paid_amount) || 0) + (parseInt(o.balance_used) || 0);
    const grandTotal = parseInt(o.grand_total) || 0;
    const remaining = Math.max(0, grandTotal - totalPaid);
    const isPaid = remaining === 0;
    const lineW = 32;
    const sep = '-'.repeat(lineW);
    const money = (n) => Number(n || 0).toLocaleString('id-ID');
    const twoCol = (left, right) => {
        const l = sanitizeForPrinter(left || '');
        const r = sanitizeForPrinter(right || '');
        const space = Math.max(1, lineW - l.length - r.length);
        return `${l}${' '.repeat(space)}${r}\n`;
    };
    const lines = [];

    lines.push('\x1B\x40');
    lines.push('\x1B\x61\x01');
    lines.push('\x1B\x21\x30'); // Double height + Double width + Bold
    lines.push(escposLine('<?php echo strtoupper($settings['business_name']); ?>'));
    lines.push('\x1B\x21\x00'); // Reset font
    lines.push(escposLine(businessAddressText));
    lines.push(escposLine(`Telp: ${businessPhoneText}`));
    lines.push('\x1B\x61\x00');
    lines.push(escposLine(sep));
    
    // Nama customer di atas No Nota, di rata tengah & ukuran besar
    lines.push('\x1B\x61\x01');
    lines.push('\x1B\x21\x30');
    lines.push(escposLine(customerName));
    lines.push('\x1B\x21\x00');
    lines.push('\x1B\x61\x00');
    
    lines.push(twoCol('No Nota', `#${o.order_number}`));
    lines.push(twoCol('Tanggal', o.order_date));
    lines.push(twoCol('Status', isPaid ? 'LUNAS' : 'BELUM LUNAS'));
    lines.push(escposLine(sep));

    if (Array.isArray(o.services) && o.services.length > 0) {
        o.services.forEach(s => {
            lines.push(escposLine(`${s.name}`));
            lines.push(twoCol(` ${s.weight} x ${money(s.price)}`, money(s.subtotal || (s.weight * s.price) || 0)));
        });
    } else {
        lines.push(twoCol(`Layanan ${o.weight || 0} Kg`, money(grandTotal)));
    }

    lines.push(escposLine(sep));
    lines.push('\x1B\x45\x01');
    lines.push(twoCol('TOTAL', formatRp(grandTotal)));
    lines.push('\x1B\x45\x00');
    lines.push(twoCol('DIBAYAR', formatRp(totalPaid)));
    if (!isPaid) lines.push(twoCol('SISA', formatRp(remaining)));
    
    const currentBalance = parseInt(custBalances[o.customer_id] || 0);
    if (currentBalance > 0) {
        lines.push(twoCol('DEPOSIT KEMBALIAN', formatRp(currentBalance)));
    }
    
    lines.push(twoCol('PARFUM', o.parfum || 'Standard'));
    lines.push(twoCol('PETUGAS', o.staff_ironing || 'Admin'));
    if (o.items_detail) {
        const itemParts = [];
        Object.keys(o.items_detail).forEach(key => {
            const qty = o.items_detail[key];
            if (qty > 0) {
                const name = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                itemParts.push(`${name}(${qty})`);
            }
        });
        if (itemParts.length > 0) lines.push(escposLine(`Item: ${itemParts.join(', ')}`));
    }
    lines.push(escposLine(''));
    lines.push(escposLine(sep));
    lines.push(escposLine(receiptNoteText));
    const trackUrl = `${window.location.origin}${window.location.pathname.replace('index.php', '')}track.php?nota=${encodeURIComponent(o.order_number)}`;
    lines.push('\x1B\x61\x01');
    lines.push(escposLine('Scan untuk cek status'));
    try {
        const qrRaster = await escposQrRaster(trackUrl);
        lines.push(qrRaster);
    } catch (err) {
        lines.push(escposQr(trackUrl)); // fallback native jika raster gagal
    }
    lines.push(escposLine(''));
    lines.push('\x1B\x61\x01');
    lines.push(escposLine('Terima kasih'));
    lines.push('\x1B\x61\x00');
    lines.push('\n\x1D\x56\x00'); // Reduced from \n\n\n

    return lines.join('');
}

async function buildBasketReceiptEscpos(o) {
    const customerName = custs[o.customer_id] || 'Pelanggan';
    const lines = [];
    lines.push('\x1B\x40');
    lines.push('\x1B\x61\x01');
    lines.push('\x1B\x21\x30'); // Double height + Double width + Bold
    lines.push(escposLine('LABEL KERANJANG'));
    lines.push('\x1B\x21\x00'); // Reset font
    lines.push(escposLine(`#${o.order_number}`));
    lines.push('\x1B\x61\x00');
    lines.push(escposLine('--------------------------------'));
    lines.push(escposLine(`NAMA   : ${customerName}`));
    lines.push(escposLine(`PARFUM : ${o.parfum || 'Tanpa Parfum'}`));
    lines.push(escposLine(`PETUGAS: ${o.staff_ironing || 'Admin'}`));
    lines.push(escposLine('--------------------------------'));
    lines.push(escposLine('RINCIAN:'));
    if (o.items_detail) {
        Object.keys(o.items_detail).forEach(key => {
            const qty = o.items_detail[key];
            if (qty > 0) {
                const name = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                lines.push(escposLine(`- ${name}: ${qty}`));
            }
        });
    }
    lines.push(escposLine('--------------------------------'));
    const finishUrl = `FINISH:${o.id}`;
    lines.push('\x1B\x61\x01');
    lines.push(escposLine('SCAN UNTUK SELESAI'));
    try {
        const qrRaster = await escposQrRaster(finishUrl);
        lines.push(qrRaster);
    } catch (err) {
        lines.push(escposQr(finishUrl)); // fallback native jika raster gagal
    }
    lines.push(escposLine(''));
    lines.push('\n\x1D\x56\x00'); // Reduced from \n\n\n
    return lines.join('');
}

async function tryBluetoothPrint(orderData, type) {
    if (!window.isSecureContext || !navigator.bluetooth) return false;
    const ready = await ensureBluetoothReady();
    if (!ready) return false;
    const payload = type === 'basket' ? await buildBasketReceiptEscpos(orderData) : await buildCustomerReceiptEscpos(orderData);
    await writeToBluetoothPrinter(payload);
    return true;
}

async function testBluetoothPrinter() {
    if (!window.isSecureContext || !navigator.bluetooth) {
        alert('Web Bluetooth tidak tersedia di browser ini.');
        return;
    }
    const ready = await ensureBluetoothReady();
    if (!ready) {
        alert('Printer belum terhubung. Connect printer dulu.');
        return;
    }
    try {
        const now = new Date();
        const stamp = `${String(now.getDate()).padStart(2, '0')}/${String(now.getMonth() + 1).padStart(2, '0')}/${now.getFullYear()} ${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
        const pname = typeof getBluetoothPrinterDisplayName === 'function' ? getBluetoothPrinterDisplayName() : 'Bluetooth Printer';
        const testLines = [
            '\x1B\x40',
            '\x1B\x61\x01',
            escposLine('TEST PRINTER'),
            '\x1B\x61\x00',
            escposLine('------------------------------'),
            escposLine(`Waktu  : ${stamp}`),
            escposLine(`Toko   : <?php echo strtoupper($settings['business_name']); ?>`),
            escposLine(`Printer: ${pname}`),
            escposLine('Status : Koneksi OK'),
            escposLine('------------------------------'),
            escposLine('Jika struk ini keluar,'),
            escposLine('printer siap dipakai cetak nota.'),
            '\n\n\n\x1D\x56\x00'
        ].join('');
        await writeToBluetoothPrinter(testLines);
        alert('Test print berhasil dikirim.');
    } catch (err) {
        alert('Test print gagal: ' + (err.message || err));
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (typeof toggleModal !== 'function') return;
    const modalBt = document.getElementById('bluetoothMenuModal');
    const params = new URLSearchParams(window.location.search);
    if (modalBt && params.get('open_bt') === '1') {
        toggleModal('bluetoothMenuModal', true);
        params.delete('open_bt');
        const nextQuery = params.toString();
        const nextUrl = `${window.location.pathname}${nextQuery ? '?' + nextQuery : ''}${window.location.hash}`;
        window.history.replaceState({}, '', nextUrl);
    }

});

async function payOrder(id, sisa) {
    const o = orders.find(x => x.id == id);
    if(!o) return;
    
    document.getElementById('pay-order-id').value = id;
    document.getElementById('pay-total-info').innerText = 'Rp ' + sisa.toLocaleString('id-ID');
    document.getElementById('pay-amount').value = sisa;
    document.getElementById('pay-amount').max = sisa;
    
    const balance = custBalances[o.customer_id] || 0;
    const balanceUsed = Math.min(sisa, balance);
    document.getElementById("pay-deposit-info").style.display = (balance > 0) ? "block" : "none";
    document.getElementById("pay-balance-used").innerText = "- Rp " + balanceUsed.toLocaleString("id-ID");
    document.getElementById("pay-val-balance-used").value = balanceUsed;
    document.getElementById("pay-grand-total").innerText = "Rp " + (sisa - balanceUsed).toLocaleString("id-ID");
    document.getElementById("pay-input").value = sisa - balanceUsed;
    
    calculateChange();
    toggleModal('payModal', true);
}

function calculateChange() {
    const sisa = parseInt(document.getElementById("pay-amount").value) || 0;
    const balanceUsed = parseInt(document.getElementById("pay-val-balance-used").value) || 0;
    const grandTotal = sisa - balanceUsed;
    const paid = parseInt(document.getElementById("pay-input").value) || 0;
    const change = Math.max(0, paid - grandTotal);
    document.getElementById("pay-change-info").innerText = "Rp " + change.toLocaleString("id-ID");
}

async function processPayment() {
    const id = document.getElementById("pay-order-id").value;
    const amount = document.getElementById("pay-input").value;
    const method = document.getElementById("valPayMethod").value;
    const saveToBalance = document.getElementById("save-change-to-balance").checked ? 1 : 0;
    const balanceUsed = document.getElementById("pay-val-balance-used").value;
    const url = `actions.php?action=pay_order&id=${id}&amount=${amount}&method=${method}&save_to_balance=${saveToBalance}&balance_used=${balanceUsed}`;
    const res = await fetch(url);
    if(res.ok) {
        if (typeof window.spaReload === 'function') window.spaReload();
        else window.location.reload();
    }
    else alert("Gagal memproses pembayaran");
}

var selectedOrderId = null;
function openPrintModal(id) {
    selectedOrderId = id;
    toggleModal('printChoiceModal', true);
}

function printReceipt(type) {
    const id = selectedOrderId;
    toggleModal('printChoiceModal', false);
    const o = orders.find(x => x.id == id);
    if(!o) return;
    ensureBluetoothReady()
        .then(() => tryBluetoothPrint(o, type))
        .then((printed) => {
            if (!printed) {
                alert('Printer Bluetooth belum terhubung atau browser tidak mendukung Web Bluetooth.');
            }
        })
        .catch((err) => {
            alert('Cetak Bluetooth gagal: ' + err.message);
        });
}

async function completeOrder(id, waUrl) {
    if(!confirm("Selesaikan pesanan?")) return;
    const res = await fetch("actions.php?action=complete_order_silent&id=" + id);
    if(res.ok) {
        const badge = document.getElementById("status-"+id);
        if(badge) {
            badge.className = "status completed";
            badge.innerText = "Completed";
        }
        window.open(waUrl, "_blank");
    }
}
</script>

<!-- Modal Pilihan Cetak -->
<div id="printChoiceModal" class="modal-pro">
    <div class="modal-content" style="max-width: 350px; text-align: center;">
        <h3 style="margin-bottom: 20px;">Opsi Cetak Nota</h3>
        <div style="display: grid; gap: 12px;">
            <button onclick="printReceipt('customer')" class="btn-primary" style="justify-content: center; padding: 15px;">
                <i class="fas fa-user"></i> Cetak untuk Customer
            </button>
            <button onclick="printReceipt('basket')" class="btn-primary" style="justify-content: center; padding: 15px; background: var(--secondary);">
                <i class="fas fa-shopping-basket"></i> Cetak untuk Keranjang
            </button>
            <button onclick="toggleModal('printChoiceModal', false)" style="background: none; border: none; color: #94a3b8; cursor: pointer; margin-top: 10px;">Batal</button>
        </div>
    </div>
</div>

<div id="bluetoothMenuModal" class="modal-pro">
    <div class="modal-content" style="max-width: 380px; text-align: left;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3 style="margin:0;">Printer Bluetooth</h3>
            <span style="font-size:1.4rem; cursor:pointer; color:#94a3b8;" onclick="toggleModal('bluetoothMenuModal', false)">&times;</span>
        </div>
        <div id="bt-printer-status" style="font-size: 0.85rem; color: #334155; margin-bottom: 12px;">Belum terhubung ke printer Bluetooth.</div>
        <div style="margin-bottom: 10px;">
            <select id="bt-printer-picker" class="input-premium" style="height: 48px; font-size: 0.85rem;">
                <option value="">-- Pilih Printer Tersimpan --</option>
            </select>
        </div>

        <div style="display: grid; gap: 10px;">
            <button id="bt-connect-btn" onclick="connectBluetoothPrinter()" class="btn-primary" style="justify-content: center; padding: 12px;">
                <i class="fas fa-bluetooth"></i> Tambah / Connect Printer
            </button>

            <button id="bt-test-btn" onclick="testBluetoothPrinter()" class="btn-primary" style="justify-content: center; padding: 12px; background: #16a34a;">
                <i class="fas fa-vial"></i> Test Printer
            </button>
            <button id="bt-disconnect-btn" onclick="disconnectBluetoothPrinter()" class="btn-primary" style="justify-content: center; padding: 12px; background: #dc2626; display: none;">
                <i class="fas fa-link-slash"></i> Disconnect Printer
            </button>
        </div>
        
        <div style="margin-top: 15px; padding: 12px; background: #fffbeb; border: 1px dashed #f59e0b; border-radius: 12px; font-size: 0.75rem; color: #b45309; line-height: 1.5;">
            <strong style="display:block; margin-bottom:4px;"><i class="fas fa-lightbulb"></i> Tips Auto-Reconnect:</strong>
            Demi keamanan, browser memblokir klik tautan setting. Silakan <b>Salin (Copy)</b> teks di bawah ini dan <b>Tempel (Paste)</b> di tab/jendela baru Chrome Anda, lalu ubah jadi <i>Enabled</i> dan klik Relaunch.
            <div style="display:flex; margin-top:8px; gap:5px;">
                <input type="text" readonly value="chrome://flags/#enable-web-bluetooth-new-permissions-backend" id="copyFlag2" style="flex:1; padding:6px; font-size:0.7rem; border:1px solid #fcd34d; border-radius:6px; background:#fef3c7; color:#92400e;">
                <button type="button" onclick="var copyText = document.getElementById('copyFlag2'); copyText.select(); copyText.setSelectionRange(0, 99999); navigator.clipboard.writeText(copyText.value); alert('Link berhasil disalin! Silakan paste di tab baru.');" style="padding:6px 10px; background:#f59e0b; color:white; border:none; border-radius:6px; cursor:pointer; font-weight:bold; font-size:0.7rem;"><i class="fas fa-copy"></i> Salin</button>
            </div>
        </div>
    </div>
</div>

<div id="print-area" style="display:none;">

    <!-- Layout Customer -->
    <div id="r-customer-layout" style="width:100%; padding:4mm 8mm 0 8mm; margin:0 auto; box-sizing:border-box; font-size:14px; line-height:1.4; color:#000; background:#fff; overflow:hidden;">
        
        <!-- HEADER -->
        <div style="text-align:center; padding-bottom:5px; border-bottom:1px solid #000; margin-bottom:5px;">
            <div style="font-size:32px; font-weight:bold; letter-spacing:0.5px;"><?php echo strtoupper($settings['business_name']); ?></div>
            <div style="font-size:16px; margin-top:2px; font-weight:bold;"><?php echo $settings['address']; ?></div>
            <div style="font-size:16px; font-weight:bold;">Telp: <?php echo $settings['phone']; ?></div>
        </div>

        <div id="r-cust-header" style="text-align:center; font-size:32px; font-weight:bold; letter-spacing:0.5px; margin-bottom: 5px;"></div>

        <!-- ORDER INFO -->
        <table style="width:100%; font-size:14px; border-collapse:collapse; line-height:1.4;">
            <tr><td>Order ID</td><td style="text-align:right; font-weight:bold;" id="r-nota"></td></tr>
            <tr><td>Kasir</td><td style="text-align:right;" id="r-kasir"></td></tr>
            <tr><td>Tgl Pesan</td><td style="text-align:right;" id="r-tgl"></td></tr>
            <tr id="row-est"><td>Est Selesai</td><td style="text-align:right;" id="r-est"></td></tr>
        </table>

        <div style="border-top:1px dashed #000; margin:3px 0;"></div>

        <!-- LAYANAN -->
        <table id="r-svc-list" style="width:100%; font-size:22px; border-collapse:collapse; line-height:1.4;"></table>

        <div style="border-top:1px dashed #000; margin:5px 0;"></div>

        <!-- TOTAL -->
        <table style="width:100%; font-size:22px; border-collapse:collapse; line-height:1.4;">
            <tr>
                <td style="font-weight:bold;">Total Harga</td>
                <td style="text-align:right; font-weight:bold;" id="r-total-harga"></td>
            </tr>
            <tr><td>Dibayar</td><td style="text-align:right;" id="r-dibayar"></td></tr>
            <tr><td>Kembalian</td><td style="text-align:right;" id="r-kembalian"></td></tr>
        </table>

        <!-- DETAIL BAYAR -->
        <div id="r-pay-detail-wrap" style="margin-top:2px; font-size:22px; color:#222;">
            <div style="font-weight:bold;">Detail Pembayaran:</div>
            <div id="r-pay-detail"></div>
        </div>

        <!-- METADATA (hanya muncul jika ada isinya) -->
        <div id="r-meta-wrap" style="margin-top:2px; font-size:14px;">
            <div style="border-top:1px dashed #000; margin:4px 0;"></div>
            <table style="width:100%; font-size:14px; border-collapse:collapse; line-height:1.4;">
                <tr id="row-item"><td>Item</td><td style="text-align:right;"><span id="r-item-count"></span> Item</td></tr>
                <tr id="row-parfum"><td>Parfum</td><td style="text-align:right;" id="r-parfum"></td></tr>
            </table>
            <div id="row-items-list" style="margin-top:3px; display:none; border-top:1px dashed #ccc; padding-top:2px; font-size:12px; font-weight:normal;">
                Rincian Item: <span id="r-items-list"></span>
            </div>
            <div id="row-catatan" style="margin-top:3px; display:none; border-top:1px dashed #ccc; padding-top:2px; font-size:12px; font-weight:normal;">
                Note: <span id="r-catatan"></span>
            </div>
        </div>

        <!-- CATATAN NOTA -->
        <?php if(!empty($settings['receipt_note'])): ?>
        <div style="border-top:1px dashed #000; margin:5px 0; font-size:12px; font-style:italic; line-height:1.4;">
            <?php echo nl2br(htmlspecialchars($settings['receipt_note'])); ?>
        </div>
        <?php endif; ?>

        <!-- FOOTER + QR CODE -->
        <div style="text-align:center; margin-top:8px; border-top:1px solid #000; padding-top:6px;">
            <div style="font-size:14px; font-weight:bold; letter-spacing:1px;">TERIMA KASIH</div>
            <div style="font-size:12px; margin-top:2px; color:#333;">Scan QR untuk cek status pesanan</div>
            <div style="margin-top:6px;">
                <img id="r-qr" src="" style="width:44mm; height:44mm;">
            </div>
            <div id="r-qr-text" style="font-size:10px; margin-top:2px; word-break:break-all;"></div>
        </div>
    </div>

    <!-- Layout Keranjang -->
    <div id="r-basket-layout" style="width:100%; padding:8mm 8mm 0 8mm; margin:0 auto; box-sizing:border-box; text-align:center; background:#fff; overflow:hidden; display:none;">
        <div style="font-size:18px; font-weight:bold; letter-spacing:1px; margin-bottom:5px;">LABEL KERANJANG</div>
        <div style="border-top:1px dashed #000; margin:5px 0;"></div>
        
        <div id="r-nota-basket" style="font-size:28px; font-weight:bold; margin:8px 0; letter-spacing:1px;"></div>
        
        <div id="r-cust-basket" style="background:#000; color:#fff; padding:10px; font-weight:bold; font-size:20px; margin-bottom:12px; text-transform:uppercase;"></div>
        
        <table style="width:100%; font-size:13px; border-collapse:collapse; text-align:left; line-height:1.5;">
            <tr>
                <td style="width:65px;">Petugas</td>
                <td style="font-weight:bold;">: <span id="r-staff-basket"></span></td>
            </tr>
            <tr>
                <td>Parfum</td>
                <td style="font-weight:bold;">: <span id="r-parfum-basket"></span></td>
            </tr>
        </table>

        <div style="border-top:1px dashed #000; margin:8px 0;"></div>
        
        <div style="text-align:left; font-size:13px; line-height:1.4;">
            <div style="font-weight:bold; border-bottom:1px solid #eee; padding-bottom:2px; margin-bottom:4px;">RINCIAN ITEM:</div>
            <div id="r-items-basket" style="font-style:normal;"></div>
        </div>

        <div style="margin-top:10px; border-top:1px solid #000; padding-top:10px;">
            <img id="r-qr-basket" src="" style="width:100%; height:auto; max-width:42mm;">
            <div style="font-size:9px; margin-top:5px; opacity:0.7; margin-bottom: 0;">SCAN UNTUK SELESAIKAN</div>
        </div>
    </div>
</div>
<div id="payModal" class="modal-pro">
    <div class="modal-pro-content" style="max-width: 400px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="font-weight: 800;">💰 Pelunasan Pesanan</h3>
            <span style="font-size: 1.5rem; cursor: pointer;" onclick="toggleModal('payModal', false)">&times;</span>
        </div>
        <div style="background: #f8fafc; padding: 20px; border-radius: 15px; margin-bottom: 20px; text-align: center;">
            <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 5px;">Sisa Tagihan</div>
            <div id="pay-total-info" style="font-size: 1.75rem; font-weight: 900; color: var(--primary);">Rp 0</div>
            <div id="pay-deposit-info" style="display: none; margin-top: 10px; padding-top: 10px; border-top: 1px dashed #e2e8f0;">
                <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: #166534; font-weight: 700;">
                    <span>Gunakan Saldo Deposit:</span>
                    <span id="pay-balance-used">- Rp 0</span>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 1rem; color: #1e293b; font-weight: 800; margin-top: 5px;">
                    <span>Sisa Harus Bayar:</span>
                    <span id="pay-grand-total">Rp 0</span>
                </div>
            </div>
        </div>
        <input type="hidden" id="pay-val-balance-used" value="0">
        <input type="hidden" id="pay-order-id">
        <input type="hidden" id="pay-amount">
        <div style="display: grid; gap: 15px;">
            <div class="form-group">
                <label>Nominal Bayar Tunai</label>
                <input type="number" id="pay-input" class="input-premium" oninput="calculateChange()" style="height: 50px; font-size: 1.2rem; font-weight: 800;" placeholder="0">
            </div>
            <div style="display: flex; align-items: center; gap: 10px; margin: 15px 0;">
                <input type="checkbox" id="save-change-to-balance" style="width: 20px; height: 20px;">
                <label for="save-change-to-balance" style="font-weight: 700; font-size: 0.85rem;">Simpan Kembalian ke Deposit?</label>
            </div>
            <div class="form-group">
                <label>Metode Pembayaran</label>
                <div class="select-premium" onclick="toggleDrop('dropPayMethod')">
                    <span id="txtPayMethod">Cash (Tunai)</span>
                    <i class="fas fa-wallet"></i>
                </div>
                <input type="hidden" id="valPayMethod" value="Cash">
                <div id="dropPayMethod" class="dropdown-box">
                    <div class="drop-item" onclick="selItem('PayMethod', 'Cash', 'Cash (Tunai)')">Cash (Tunai)</div>
                    <div class="drop-item" onclick="selItem('PayMethod', 'Transfer', 'Transfer Bank')">Transfer Bank</div>
                    <div class="drop-item" onclick="selItem('PayMethod', 'QRIS', 'QRIS')">QRIS</div>
                </div>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 15px; background: #f0fdf4; border-radius: 12px; border: 1px solid #bbf7d0;">
                <span style="font-weight: 700; color: #166534;">Kembalian:</span>
                <span id="pay-change-info" style="font-weight: 900; color: #166534; font-size: 1.2rem;">Rp 0</span>
            </div>
            <button onclick="processPayment()" class="btn-primary" style="justify-content: center; padding: 18px; font-weight: 800; font-size: 1.1rem; margin-top: 10px; border-radius: 18px;">
                <i class="fas fa-check-circle"></i> PROSES PELUNASAN
            </button>
        </div>
    </div>
</div>

<style>
#print-area td, #print-area th {
    padding: 0 !important;
    font-size: inherit !important;
    border: none !important;
    white-space: normal !important;
}

#print-area, #print-area * {
    -webkit-font-smoothing: none !important;
    text-rendering: crispEdges !important;
    font-family: 'Courier New', Courier, monospace !important;
    font-weight: normal;
}

@media print {
    @page { 
        margin: 0;
        size: 58mm auto; 
    }
    body * { 
        visibility: hidden; 
    }
    #print-area, #print-area * { 
        visibility: visible; 
    }
    #print-area { 
        position: absolute; 
        left: 0; 
        top: 0; 
        width: 100% !important; 
        display: block !important; 
        padding: 0; 
        margin: 0;
        background: white;
    }
}
</style>
