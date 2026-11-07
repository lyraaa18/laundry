<?php
$services = $db->readFiltered('services');
?>

<header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px;">
    <div class="header-title">
        <h2 style="font-size: 1.8rem; font-weight: 800; color: var(--text-dark);">Daftar Layanan</h2>
        <p style="color: var(--text-muted);">Atur jenis jasa laundry dan tarif Anda</p>
    </div>
    <div class="header-actions">
        <?php if($_SESSION['role'] == 'owner'): ?>
        <button class="btn-primary" onclick="document.getElementById('addServiceModal').style.display='block'"><i class="fas fa-plus"></i> Tambah Layanan</button>
        <?php endif; ?>
    </div>
</header>

<div class="services-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 24px;">
    <?php foreach($services as $s): 
        $cat = $s['category'] ?? 'kiloan';
        $unit = $s['unit'] ?? 'kg';
        $color = ($cat == 'express') ? 'var(--danger)' : 'var(--primary)';
    ?>
    <div class="card" style="position: relative; overflow: hidden; border-top: 5px solid <?php echo $color; ?>;">
        <h3 style="font-size: 1.1rem; margin-bottom: 8px;"><?php echo htmlspecialchars($s['service_name']); ?></h3>
        <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 20px;">Kategori: <span style="text-transform: capitalize;"><?php echo $cat; ?></span></p>
        <div style="font-size: 1.25rem; font-weight: 800; color: var(--text-dark); margin-bottom: 20px;">
            Rp <?php echo number_format($s['price_per_unit'] ?? 0, 0, ',', '.'); ?> <span style="font-size: 0.8rem; color: var(--text-muted);">/ <?php echo htmlspecialchars($unit); ?></span>
        </div>
        <div style="display: flex; justify-content: <?php echo ($_SESSION['role'] == 'owner') ? 'space-between' : 'flex-start'; ?>; align-items: center;">
            <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;"><i class="far fa-clock"></i> <?php echo $s['estimated_days'] ?? 2; ?> Hari</span>
            <?php if($_SESSION['role'] == 'owner'): ?>
            <a href="actions.php?action=delete_service&id=<?php echo $s['id']; ?>" style="color: var(--danger); text-decoration: none;" onclick="return confirm('Hapus layanan ini?')"><i class="fas fa-trash"></i></a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Modal Tambah Layanan -->
<div id="addServiceModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Layanan Baru</h3>
            <span class="close" onclick="this.parentElement.parentElement.parentElement.style.display='none'">&times;</span>
        </div>
        <form action="actions.php?action=add_service" method="POST">
            <div class="form-group">
                <label>Nama Layanan</label>
                <input type="text" name="service_name" required placeholder="Contoh: Cuci Setrika Regular">
            </div>
            <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label>Kategori</label>
                    <select name="category">
                        <option value="kiloan">Kiloan</option>
                        <option value="satuan">Satuan</option>
                        <option value="express">Express</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Satuan</label>
                    <input type="text" name="unit" required placeholder="kg / pcs">
                </div>
            </div>
            <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label>Harga per Unit</label>
                    <input type="number" name="price" required>
                </div>
                <div class="form-group">
                    <label>Estimasi (Hari)</label>
                    <input type="number" name="days" value="2" required>
                </div>
            </div>
            <button type="submit" class="btn-primary" style="width: 100%; justify-content: center;">Simpan Layanan</button>
        </form>
    </div>
</div>
