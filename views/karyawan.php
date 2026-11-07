<?php
if ($_SESSION['role'] != 'owner') {
    echo "<script>window.location='index.php';</script>";
    exit;
}

$all_users = $db->read('users');
$owner_id = $_SESSION['owner_id'];

// Hanya tampilkan staff milik owner ini
$staffs = array_filter($all_users, function($u) use ($owner_id) {
    return ($u['owner_id'] ?? '') == $owner_id && $u['role'] == 'staff';
});
?>

<style>
/* Style Modal Khusus jika belum terload */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    backdrop-filter: blur(4px);
}
.modal-content {
    background-color: white;
    margin: 10% auto;
    padding: 30px;
    border-radius: 24px;
    width: 90%;
    max-width: 400px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    position: relative;
    animation: modalFade 0.3s ease-out;
}
@keyframes modalFade {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}
.close {
    position: absolute;
    right: 20px;
    top: 15px;
    font-size: 28px;
    font-weight: bold;
    color: #94a3b8;
    cursor: pointer;
}
</style>

<header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px;">
    <div class="header-title">
        <h2 style="font-size: 1.8rem; font-weight: 800; color: var(--text-dark);">Manajemen Karyawan</h2>
        <p style="color: var(--text-muted);">Kelola hak akses login staff Anda.</p>
    </div>
    <button class="btn-primary" onclick="document.getElementById('staffModal').style.display='block'">
        <i class="fas fa-user-plus"></i> Tambah Karyawan
    </button>
</header>

<div class="card">
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>NAMA KARYAWAN</th>
                    <th>USERNAME</th>
                    <th>HAK AKSES</th>
                    <th style="text-align: right;">AKSI</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($staffs)): ?>
                <tr>
                    <td colspan="4" style="text-align: center; padding: 40px; color: var(--text-muted);">
                        Belum ada karyawan. Silakan tambah karyawan baru.
                    </td>
                </tr>
                <?php endif; ?>
                <?php foreach($staffs as $s): ?>
                <tr>
                    <td style="font-weight: 700;"><?php echo htmlspecialchars($s['name']); ?></td>
                    <td><code><?php echo htmlspecialchars($s['username']); ?></code></td>
                    <td><span class="status completed" style="background: rgba(99, 102, 241, 0.1); color: var(--primary);">Staff Kasir</span></td>
                    <td style="text-align: right;">
                        <a href="actions.php?action=delete_staff&id=<?php echo $s['id']; ?>" class="btn-icon" style="color: var(--danger);" onclick="return confirm('Hapus akses login karyawan ini?')">
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah Karyawan -->
<div id="staffModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Tambah Karyawan Baru</h3>
            <span class="close" onclick="document.getElementById('staffModal').style.display='none'">&times;</span>
        </div>
        <div class="modal-body">
            <form action="actions.php?action=add_staff" method="POST">
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="name" placeholder="Nama asli karyawan" required style="width: 100%; padding: 12px; border-radius: 12px; border: 1px solid #e2e8f0; margin-top: 8px;">
                </div>
                <div class="form-group" style="margin-top: 15px;">
                    <label>Username (untuk Login)</label>
                    <input type="text" name="username" placeholder="karyawan_toko_a" required style="width: 100%; padding: 12px; border-radius: 12px; border: 1px solid #e2e8f0; margin-top: 8px;">
                </div>
                <div class="form-group" style="margin-top: 15px;">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="••••••••" required style="width: 100%; padding: 12px; border-radius: 12px; border: 1px solid #e2e8f0; margin-top: 8px;">
                </div>
                <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; margin-top: 25px; padding: 15px;">
                    Simpan & Beri Akses
                </button>
            </form>
        </div>
    </div>
</div>
