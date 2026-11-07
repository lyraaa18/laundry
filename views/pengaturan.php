<div class="header-title" style="margin-bottom: 24px;">
    <h2 style="font-size: 1.5rem; font-weight: 800;">Pengaturan & Manajemen</h2>
    <p style="color: var(--text-muted); font-size: 0.85rem;">Kelola profil toko, outlet, dan tim karyawan Anda.</p>
</div>

<div class="grid-2">
    <!-- Pengaturan Toko -->
    <div class="card">
        <h3 style="margin-bottom: 20px;"><i class="fas fa-store" style="color: var(--primary);"></i> Identitas Toko</h3>
        <form action="actions.php?action=update_settings" method="POST">
            <?php $is_readonly = ($_SESSION['role'] == 'staff') ? 'readonly' : ''; ?>
            <div class="form-group">
                <label>Nama Laundry</label>
                <input type="text" name="business_name" value="<?php echo htmlspecialchars($settings['business_name'] ?? ''); ?>" required <?php echo $is_readonly; ?>>
            </div>
            <div class="form-group">
                <label>Nomor WhatsApp</label>
                <input type="text" name="business_phone" value="<?php echo htmlspecialchars($settings['phone'] ?? ''); ?>" placeholder="Contoh: 08123456789" <?php echo $is_readonly; ?>>
            </div>
            <div class="form-group">
                <label>Alamat Toko</label>
                <textarea name="business_address" style="height: 80px;" <?php echo $is_readonly; ?>><?php echo htmlspecialchars($settings['address'] ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                <label style="color: var(--danger);">Syarat & Ketentuan Nota (S&K)</label>
                <textarea name="receipt_note" style="height: 100px;" placeholder="Contoh: Kain luntur bukan tanggung jawab kami." <?php echo $is_readonly; ?>><?php echo htmlspecialchars($settings['receipt_note'] ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                <label style="color: var(--primary);">Template WA (Pesanan Selesai)</label>
                <textarea name="wa_template" style="height: 100px;" <?php echo $is_readonly; ?>><?php echo htmlspecialchars($settings['wa_template'] ?? "Halo [NAMA],\n\nPesanan Anda [NOTA] sudah selesai dan siap diambil. \n\nTerima kasih!"); ?></textarea>
            </div>
            
            <?php if($_SESSION['role'] == 'owner'): ?>
                <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; padding: 14px;">Simpan Perubahan</button>
            <?php else: ?>
                <div style="background: #fff1f2; color: #e11d48; padding: 15px; border-radius: 12px; font-size: 0.85rem; text-align: center; font-weight: 700; border: 1px solid #fda4af;">
                    <i class="fas fa-lock"></i> Mode Baca-Saja: Hanya Owner yang dapat mengubah identitas toko.
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Manajemen Outlet (Tanpa Karyawan) -->
    <div class="card-group">
        <!-- Daftar Outlet -->
        <div class="card" style="margin-bottom: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3><i class="fas fa-map-marker-alt" style="color: var(--danger);"></i> Outlet</h3>
                <?php if($_SESSION['role'] == 'owner'): ?>
                    <button class="btn-primary" style="padding: 6px 12px; font-size: 0.75rem;" onclick="document.getElementById('outletModal').style.display='block'">+ Tambah</button>
                <?php endif; ?>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>NAMA OUTLET</th>
                            <?php if($_SESSION['role'] == 'owner'): ?><th style="text-align: right;">AKSI</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $outlets = $db->readFiltered('outlets');
                        foreach($outlets as $o): 
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($o['name']); ?></td>
                            <?php if($_SESSION['role'] == 'owner'): ?>
                            <td style="text-align: right;">
                                <a href="actions.php?action=delete_outlet&id=<?php echo $o['id']; ?>" style="color: var(--danger);" onclick="return confirm('Hapus outlet ini?')"><i class="fas fa-trash"></i></a>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal-Modal Pengaturan -->
<div id="outletModal" class="modal">
    <div class="modal-content">
        <h3>Tambah Outlet Baru</h3>
        <form action="actions.php?action=add_outlet" method="POST">
            <div class="form-group">
                <label>Nama Outlet</label>
                <input type="text" name="name" required style="width: 100%; padding: 12px; border-radius: 12px; border: 1px solid #e2e8f0; margin-top: 8px;">
            </div>
            <div class="form-group" style="margin-top: 15px;">
                <label>Alamat</label>
                <input type="text" name="address" required style="width: 100%; padding: 12px; border-radius: 12px; border: 1px solid #e2e8f0; margin-top: 8px;">
            </div>
            <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; margin-top: 20px;">Simpan Outlet</button>
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
