<?php
// views/parfum.php - Manajemen Aroma Parfum
$parfums = $db->readFiltered('parfums');
?>

<header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; gap: 15px;">
    <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--text-dark); margin: 0; white-space: nowrap;">Koleksi Parfum</h2>
    <button class="btn-primary" onclick="toggleModal('addParfumModal', true)" style="padding: 10px 20px; font-size: 0.85rem; border-radius: 12px; white-space: nowrap;">
        <i class="fas fa-plus"></i> Tambah Aroma
    </button>
</header>

<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 24px;">
    <?php foreach($parfums as $p): ?>
    <div class="card" style="position: relative; overflow: hidden; border-top: 5px solid #f59e0b;">
        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
            <div style="width: 55px; height: 55px; background: #fef3c7; color: #d97706; border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0;">
                <i class="fas fa-spray-can"></i>
            </div>
            <div>
                <h4 style="margin: 0; font-weight: 800; color: var(--text-dark); font-size: 1rem;"><?php echo htmlspecialchars($p['name']); ?></h4>
                <p style="margin: 4px 0 0; font-size: 0.8rem; color: var(--text-muted);">Status: <span style="color: #22c55e; font-weight: 700;">Tersedia</span></p>
            </div>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="actions.php?action=delete_parfum&id=<?php echo $p['id']; ?>" class="btn-primary" style="background: #fff1f2; color: #e11d48; border: 1px solid #ffe4e6; flex: 1; justify-content: center; font-size: 0.8rem;" onclick="return confirm('Hapus aroma ini?')">
                <i class="fas fa-trash"></i> Hapus
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if(empty($parfums)): ?>
<div style="text-align: center; padding: 100px 20px; color: var(--text-muted);">
    <i class="fas fa-spray-can" style="font-size: 4rem; opacity: 0.2; margin-bottom: 20px;"></i>
    <p>Belum ada koleksi parfum. Klik "Tambah Aroma" untuk memulai.</p>
</div>
<?php endif; ?>

<!-- Modal Tambah Parfum Premium -->
<div id="addParfumModal" class="modal-pro">
    <div class="modal-pro-content" style="max-width: 420px; border-radius: 32px; padding: 30px;">
        <div style="text-align: center; margin-bottom: 25px;">
            <div style="width: 70px; height: 70px; background: linear-gradient(135deg, #f59e0b, #d97706); color: white; border-radius: 24px; display: inline-flex; align-items: center; justify-content: center; font-size: 2rem; margin-bottom: 15px; box-shadow: 0 10px 20px rgba(217,119,6,0.3);">
                <i class="fas fa-spray-can"></i>
            </div>
            <h3 style="font-weight: 900; color: var(--text-dark); margin: 0;">Tambah Aroma Baru</h3>
            <p style="color: #64748b; font-size: 0.85rem; margin-top: 5px;">Berikan pilihan wangi terbaik untuk pelanggan.</p>
        </div>

        <form action="actions.php?action=create_parfum" method="POST">
            <div class="form-group" style="margin-bottom: 25px;">
                <label style="font-weight: 700; color: var(--text-dark); margin-bottom: 10px; display: block;">Nama Aroma Parfum</label>
                <div style="position: relative;">
                    <i class="fas fa-magic" style="position: absolute; left: 15px; top: 15px; color: #94a3b8;"></i>
                    <input type="text" name="name" required placeholder="Contoh: Sakura Premium / Lavender Soft" 
                        style="width: 100%; padding: 15px 15px 15px 45px; border-radius: 16px; border: 2px solid #f1f5f9; background: #f8fafc; font-weight: 600; outline: none; transition: all 0.3s ease;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 12px;">
                <button type="button" onclick="toggleModal('addParfumModal', false)" 
                    style="padding: 15px; border-radius: 16px; border: none; background: #f1f5f9; color: #64748b; font-weight: 700; cursor: pointer;">
                    Batal
                </button>
                <button type="submit" class="btn-primary" style="justify-content: center; padding: 15px; border-radius: 16px; font-weight: 800; box-shadow: 0 10px 20px rgba(99,102,241,0.2);">
                    Simpan Aroma
                </button>
            </div>
        </form>
    </div>
</div>
