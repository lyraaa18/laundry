<?php
if ($_SESSION['role'] != 'superadmin') {
    echo "<script>window.location='index.php';</script>";
    exit;
}

$all_users = $db->read('users');
$all_transactions = $db->read('transactions');
$owners = array_filter($all_users, function($u) {
    return $u['role'] == 'owner';
});
?>

<?php if(isset($_GET['status']) && $_GET['status'] == 'pw_changed'): ?>
<div style="background: #dcfce7; color: #166534; padding: 15px; border-radius: 16px; margin-bottom: 20px; border: 1px solid #bbf7d0;">
    <i class="fas fa-check-circle"></i> Password Anda berhasil diperbarui!
</div>
<?php endif; ?>

<header style="margin-bottom: 32px;">
    <div class="header-title">
        <h2 style="font-size: 1.8rem; font-weight: 800; color: var(--text-dark);">Manajemen Akun Toko</h2>
        <p style="color: var(--text-muted);">Kelola pendaftaran mitra dan keamanan akun mereka.</p>
    </div>
</header>

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3>Daftar Member (Owner)</h3>
        <button class="btn-primary" onclick="document.getElementById('ownerModal').style.display='block'">
            <i class="fas fa-plus"></i> Tambah Toko Baru
        </button>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>NAMA TOKO</th>
                    <th>LOGIN INFO</th>
                    <th>PENDAPATAN (OMZET)</th>
                    <th>LABA BERSIH</th>
                    <th>MASA BERLAKU</th>
                    <th>STATUS</th>
                    <th style="text-align: right;">AKSI</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($owners as $o): 
                    $is_expired = (isset($o['expired_at']) && date('Y-m-d') > $o['expired_at']);
                    $formatted_date = isset($o['expired_at']) ? date('d M Y', strtotime($o['expired_at'])) : 'Belum Set';
                    
                    // Hitung Pendapatan & Laba Bersih per Toko
                    $target_owner_id = $o['id'] ?? '';
                    $toko_income = 0;
                    $toko_expense = 0;
                    foreach($all_transactions as $t) {
                        $t_owner = $t['owner_id'] ?? 'owner_1';
                        if ($t_owner === $target_owner_id) {
                            if (($t['type'] ?? '') === 'income') {
                                $toko_income += (int)($t['amount'] ?? 0);
                            } elseif (($t['type'] ?? '') === 'expense') {
                                $toko_expense += (int)($t['amount'] ?? 0);
                            }
                        }
                    }
                    $toko_laba_bersih = $toko_income - $toko_expense;
                ?>
                <tr>
                    <td style="font-weight: 700; color: var(--text-dark);"><?php echo htmlspecialchars($o['name']); ?></td>
                    <td><code><?php echo htmlspecialchars($o['username']); ?></code></td>
                    <td style="font-weight: 700; color: #0284c7;">
                        Rp <?php echo number_format($toko_income, 0, ',', '.'); ?>
                    </td>
                    <td style="font-weight: 800; color: <?php echo ($toko_laba_bersih >= 0) ? '#16a34a' : '#dc2626'; ?>;">
                        Rp <?php echo number_format($toko_laba_bersih, 0, ',', '.'); ?>
                    </td>
                    <td>
                        <span style="font-size: 0.85rem; font-weight: 600; color: #475569;">
                            <i class="far fa-calendar-alt" style="margin-right: 5px; color: #94a3b8;"></i>
                            <?php echo $formatted_date; ?>
                        </span>
                    </td>
                    <td>
                        <?php if($is_expired): ?>
                            <span class="status cancelled">Expired</span>
                        <?php else: ?>
                            <span class="status completed">Aktif</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: right;">
                        <div style="display: flex; gap: 8px; justify-content: flex-end;">
                            <button class="btn-primary" style="background: #eef2ff; color: #6366f1; border: 1px solid #e0e7ff; padding: 8px 16px; font-size: 0.8rem; border-radius: 99px;" 
                                    onclick="openEditModal('<?php echo $o['id']; ?>', '<?php echo htmlspecialchars($o['name']); ?>', '<?php echo htmlspecialchars($o['username']); ?>', '<?php echo $o['expired_at'] ?? ''; ?>')">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <a href="actions.php?action=delete_owner&id=<?php echo $o['id']; ?>" class="btn-primary" style="background: #fff1f2; color: #e11d48; border: 1px solid #ffe4e6; padding: 8px 16px; font-size: 0.8rem; border-radius: 99px; text-decoration: none;" onclick="return confirm('Hapus seluruh data toko ini?')">
                                <i class="fas fa-trash"></i> Hapus
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah Owner -->
<div id="ownerModal" class="modal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header">
            <h3>Daftarkan Toko Baru</h3>
            <span class="close" onclick="document.getElementById('ownerModal').style.display='none'">&times;</span>
        </div>
        <div class="modal-body">
            <form action="actions.php?action=add_owner" method="POST">
                <div class="form-group">
                    <label>Nama Toko / Laundry</label>
                    <input type="text" name="name" placeholder="Contoh: TKH Laundry" required>
                </div>
                <div class="form-group">
                    <label>Username Admin Toko</label>
                    <input type="text" name="username" placeholder="owner_laundry_a" required>
                </div>
                <div class="form-group">
                    <label>Password Awal</label>
                    <input type="password" name="password" required>
                </div>
                <div class="form-group">
                    <label>Masa Berlaku s/d</label>
                    <input type="date" name="expired_at" value="<?php echo date('Y-m-d', strtotime('+1 month')); ?>" required>
                </div>
                <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; margin-top: 20px;">Daftarkan Mitra Sekarang</button>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Owner -->
<div id="editOwnerModal" class="modal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header">
            <h3>Edit Lisensi Toko</h3>
            <span class="close" onclick="document.getElementById('editOwnerModal').style.display='none'">&times;</span>
        </div>
        <div class="modal-body">
            <form action="actions.php?action=edit_owner" method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label>Nama Toko</label>
                    <input type="text" name="name" id="edit_name" required>
                </div>
                <div class="form-group">
                    <label>Username (Tetap)</label>
                    <input type="text" id="edit_username" disabled style="background: #f1f5f9;">
                </div>
                <div class="form-group">
                    <label>Update Password (Kosongkan jika tidak diubah)</label>
                    <input type="password" name="password" placeholder="••••••••">
                </div>
                <div class="form-group">
                    <label>Perpanjang Masa Berlaku</label>
                    <input type="date" name="expired_at" id="edit_expired_at" required>
                </div>
                <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; margin-top: 20px;">Simpan Perubahan</button>
            </form>
        </div>
    </div>
</div>

<script>
function openEditModal(id, name, username, expired) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_expired_at').value = expired;
    document.getElementById('editOwnerModal').style.display = 'block';
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.className == 'modal' || event.target.className == 'modal-pro') {
        event.target.style.display = 'none';
    }
}
</script>
