<?php
$customers = $db->readFiltered('customers');
$orders = $db->readFiltered('orders');

// Hitung total piutang per pelanggan
$debtMap = [];
foreach($orders as $o) {
    $total_paid = (int)($o['paid_amount'] ?? 0) + (int)($o['balance_used'] ?? 0);
    $total_bill = (int)($o['grand_total'] ?? 0);
    if ($total_paid < $total_bill) {
        $debt = $total_bill - $total_paid;
        $debtMap[$o['customer_id']] = ($debtMap[$o['customer_id']] ?? 0) + $debt;
    }
}

usort($customers, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});
?>

<header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; gap: 15px;">
    <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--text-dark); margin: 0; white-space: nowrap;">Data Pelanggan</h2>
    <button class="btn-primary" onclick="document.getElementById('addCustomerModal').style.display='block'" style="padding: 10px 20px; font-size: 0.85rem; border-radius: 12px; white-space: nowrap;">
        <i class="fas fa-plus"></i> Baru
    </button>
</header>

<div class="card">
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>NAMA PELANGGAN</th>
                    <th>KONTAK / WHATSAPP</th>
                    <th>SALDO DEPOSIT</th>
                    <th>KURANG BAYAR (UTANG)</th>
                    <th style="text-align: right;">AKSI</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($customers as $c): 
                    $total_debt = $debtMap[$c['id']] ?? 0;
                ?>
                <tr>
                    <td>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="width: 40px; height: 40px; background: #e0e7ff; color: #4338ca; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.9rem;">
                                <?php echo strtoupper(substr($c['name'], 0, 1)); ?>
                            </div>
                            <span style="font-weight: 700; color: var(--text-dark);"><?php echo htmlspecialchars($c['name']); ?></span>
                        </div>
                    </td>
                    <td>
                        <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $c['phone']); ?>" target="_blank" style="text-decoration: none; color: #059669; font-weight: 600;">
                            <i class="fab fa-whatsapp"></i> <?php echo htmlspecialchars($c['phone']); ?>
                        </a>
                    </td>
                    <td>
                        <div style="font-size: 1.1rem; font-weight: 800; color: var(--primary);">
                            Rp <?php echo number_format($c['balance'], 0, ',', '.'); ?>
                        </div>
                        <span style="font-size: 0.65rem; color: #94a3b8; text-transform: uppercase;">Saldo Tersimpan</span>
                    </td>
                    <td>
                        <?php if($total_debt > 0): ?><div style="display: flex; align-items: center; justify-content: space-between;"><div>
                            <div style="font-size: 1.1rem; font-weight: 800; color: #dc2626;">
                                Rp <?php echo number_format($total_debt, 0, ',', '.'); ?>
                            </div>
                            <span style="font-size: 0.65rem; background: #fee2e2; color: #dc2626; padding: 2px 6px; border-radius: 4px; font-weight: 800; text-transform: uppercase;">Harus Ditagih</span></div><a href="index.php?page=pesanan&customer_id=<?php echo $c["id"]; ?>&status=unpaid" class="btn-primary" style="padding: 6px 12px; font-size: 0.7rem; background: #dc2626; border-radius: 8px; text-decoration: none;">LUNASI <i class="fas fa-arrow-right"></i></a></div>
                        <?php else: ?>
                            <div style="color: #94a3b8; font-weight: 600; font-size: 0.9rem;">Lunas</div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: right;">
                        <?php if($_SESSION['role'] == 'owner' || ($c['created_by'] ?? '') == $_SESSION['user_id']): ?>
                            <a href="actions.php?action=delete_customer&id=<?php echo $c['id']; ?>" class="btn-primary" style="padding: 8px 12px; background: #fef2f2; color: #dc2626; border: 1px solid #fee2e2; text-decoration: none;" onclick="return confirm('Hapus pelanggan ini?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        <?php else: ?>
                            <i class="fas fa-lock" style="color: #cbd5e1;" title="Dikunci oleh Owner"></i>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah Pelanggan -->
<div id="addCustomerModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Pendaftaran Member Baru</h3>
            <span class="close" onclick="this.parentElement.parentElement.parentElement.style.display='none'">&times;</span>
        </div>
        <form action="actions.php?action=add_customer" method="POST">
            <div class="form-group">
                <label>Nama Lengkap</label>
                <input type="text" name="name" required placeholder="Contoh: Budi Santoso" style="width: 100%; padding: 12px; border-radius: 12px; border: 1px solid #e2e8f0;">
            </div>
            <div class="form-group">
                <label>Nomor WhatsApp</label>
                <input type="text" name="phone" required placeholder="0812xxxx" style="width: 100%; padding: 12px; border-radius: 12px; border: 1px solid #e2e8f0;">
            </div>
            <div class="form-group">
                <label>Alamat (Opsional)</label>
                <textarea name="address" style="width: 100%; padding: 12px; border-radius: 12px; border: 1px solid #e2e8f0; height: 80px;"></textarea>
            </div>
            <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; margin-top: 15px; padding: 15px;">Daftarkan Pelanggan</button>
        </form>
    </div>
</div>

<script>
window.onclick = function(event) {
    if (event.target.className === 'modal') {
        event.target.style.display = "none";
    }
}
</script>
