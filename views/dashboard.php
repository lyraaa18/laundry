<?php
// views/dashboard.php - Dashboard Pintar & Aman

// Safety: pastikan semua variabel tersedia dari index.php
if (!isset($db)) {
    $db = new JsonDB();
}

$current_role = $_SESSION['role'] ?? '';
$current_owner = $_SESSION['owner_id'] ?? null;

// --- VIEW KHUSUS SUPER ADMIN (ISOLASI TOTAL) ---
if ($current_role == 'superadmin') {
    $all_users = $db->read('users');
    $owners = array_filter($all_users, function($u) { return ($u['role'] ?? '') == 'owner'; });
    $owner_count = count($owners);
    $active_owners = 0; $expired_owners = 0;
    $today = date('Y-m-d');
    foreach ($owners as $o) {
        if (isset($o['expired_at'])) {
            if ($o['expired_at'] >= $today) $active_owners++;
            else $expired_owners++;
        }
    }
    usort($owners, function($a, $b) { 
        $idA = (int)preg_replace('/[^0-9]/', '', $a['id'] ?? '0');
        $idB = (int)preg_replace('/[^0-9]/', '', $b['id'] ?? '0');
        return $idB - $idA;
    });
    $recent_owners = array_slice($owners, 0, 5);
    ?>
    <style>
        @media (max-width: 768px) {
            .desktop-only-table { display: none !important; }
            .mobile-only-list { display: block !important; }
            .sa-stats-grid { grid-template-columns: 1fr !important; }
            .sa-main-grid { grid-template-columns: 1fr !important; }
        }
        .mobile-only-list { display: none; }
        .mitra-card-mobile { background: #f8fafc; padding: 15px; border-radius: 16px; margin-bottom: 12px; border: 1px solid #e2e8f0; }
    </style>
    <header style="margin-bottom: 32px;">
        <div class="header-title">
            <h2 style="font-size: 1.8rem; font-weight: 900; color: #1e293b;"><i class="fas fa-server" style="color: #6366f1;"></i> Panel Infrastruktur SaaS</h2>
            <p style="color: #64748b;">Monitoring lisensi dan status sistem global.</p>
        </div>
    </header>
    <div class="sa-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; margin-bottom: 32px;">
        <div style="background: white; padding: 30px; border-radius: 28px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; display: flex; align-items: center; gap: 20px;">
            <div style="background: #e0e7ff; width: 70px; height: 70px; border-radius: 22px; display: flex; align-items: center; justify-content: center; color: #6366f1; font-size: 2rem;"><i class="fas fa-store"></i></div>
            <div>
                <div style="color: #64748b; font-size: 0.85rem; font-weight: 700; text-transform: uppercase;">Total Mitra</div>
                <div style="font-size: 1.8rem; font-weight: 900; color: #1e293b;"><?php echo $owner_count; ?> <span style="font-size: 0.9rem; color: #64748b; font-weight: 500;">Toko</span></div>
            </div>
        </div>
        <div style="background: white; padding: 30px; border-radius: 28px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; display: flex; align-items: center; gap: 20px;">
            <div style="background: #dcfce7; width: 70px; height: 70px; border-radius: 22px; display: flex; align-items: center; justify-content: center; color: #10b981; font-size: 2rem;"><i class="fas fa-check-circle"></i></div>
            <div>
                <div style="color: #64748b; font-size: 0.85rem; font-weight: 700; text-transform: uppercase;">Status Berlangganan</div>
                <div style="font-size: 1.5rem; font-weight: 900; color: #1e293b;"><span style="color: #10b981;"><?php echo $active_owners; ?> Aktif</span> / <span style="color: #ef4444;"><?php echo $expired_owners; ?> Mati</span></div>
            </div>
        </div>
    </div>
    <div class="sa-main-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
        <div class="card" style="padding: 30px; border-radius: 28px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="font-weight: 800; color: #1e293b;">Mitra Terbaru</h3>
                <a href="index.php?page=superadmin" style="font-size: 0.8rem; font-weight: 800; color: #6366f1; text-decoration: none;">SEMUA <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="table-responsive desktop-only-table">
                <table class="table-premium">
                    <thead><tr><th>Nama Toko</th><th>Username</th><th>Expired At</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach($recent_owners as $ro): ?>
                        <tr>
                            <td style="font-weight: 700;"><?php echo htmlspecialchars($ro['name']); ?></td>
                            <td><?php echo htmlspecialchars($ro['username']); ?></td>
                            <td><?php echo date('d M Y', strtotime($ro['expired_at'])); ?></td>
                            <td><?php echo ($ro['expired_at'] >= $today) ? '<span class="badge badge-success">AKTIF</span>' : '<span class="badge badge-danger">EXPIRED</span>'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mobile-only-list">
                <?php foreach($recent_owners as $ro): ?>
                <div class="mitra-card-mobile">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                        <div style="font-weight: 800; color: #1e293b;"><?php echo htmlspecialchars($ro['name']); ?></div>
                        <?php echo ($ro['expired_at'] >= $today) ? '<span style="color:#10b981; font-size:0.7rem; font-weight:800;">● AKTIF</span>' : '<span style="color:#ef4444; font-size:0.7rem; font-weight:800;">● EXPIRED</span>'; ?>
                    </div>
                    <div style="font-size: 0.8rem; color: #64748b;">
                        <div><i class="fas fa-user" style="width: 15px;"></i> <?php echo htmlspecialchars($ro['username']); ?></div>
                        <div style="margin-top: 5px;"><i class="fas fa-calendar-alt" style="width: 15px;"></i> Expired: <?php echo date('d M Y', strtotime($ro['expired_at'])); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="card" style="padding: 30px; border-radius: 28px; background: #0f172a; color: white;">
            <i class="fas fa-database" style="font-size: 2rem; color: #38bdf8; margin-bottom: 15px;"></i>
            <h3 style="font-weight: 800; margin-bottom: 10px;">Backup Core</h3>
            <p style="font-size: 0.85rem; color: #94a3b8; margin-bottom: 20px;">Cadangkan database terenkripsi untuk pemulihan bencana.</p>
            <button onclick="window.location='actions.php?action=backup_data'" class="btn-primary" style="background: #38bdf8; color: #0f172a; width: 100%; justify-content: center; font-weight: 800; border: none;"><i class="fas fa-download"></i> BACKUP SEKARANG</button>
        </div>
    </div>
    <?php return;
}

// --- VIEW OWNER & STAFF ---
// Safety: pastikan semua variabel tersedia
// Safety: pastikan semua variabel tersedia
if (!isset($db)) { $db = new JsonDB(); }
if (!isset($settings)) { $settings = ['business_name' => 'Laundry Pro']; }

$orders     = $db->readFiltered('orders');
$customers  = $db->readFiltered('customers');
$transactions = $db->readFiltered('transactions');

// Mapping Customer Names
$custMap = [];
foreach ($customers as $c) {
    $custMap[$c['id']] = $c['name'];
}

$income_today = 0;
$income_month = 0;
foreach ($transactions as $t) {
    if (($t['type'] ?? '') !== 'income') continue;
    $tdate = date('Y-m-d', strtotime($t['transaction_date']));
    if ($tdate === date('Y-m-d')) $income_today += (int)($t['amount'] ?? 0);
    if (substr($tdate, 0, 7) === date('Y-m')) $income_month += (int)($t['amount'] ?? 0);
}

$active_orders = array_filter($orders, function($o) {
    return !in_array($o['status'] ?? '', ['Selesai', 'Diambil', 'completed', 'Dibatalkan']);
});
$pending_orders = array_filter($orders, function($o) {
    return ($o['status'] ?? '') === 'Menunggu';
});

// Hitung statistik harian (berat & jumlah pcs)
$weight_today = 0;
$items_today = 0;
$today_str = date('Y-m-d');
foreach ($orders as $o) {
    $odate = substr($o['order_date'] ?? '', 0, 10);
    if ($odate === $today_str && ($o['status'] ?? '') !== 'Dibatalkan') {
        $weight_today += (float)($o['total_weight'] ?? 0);
        if (isset($o['items_detail']) && is_array($o['items_detail'])) {
            foreach ($o['items_detail'] as $qty) { $items_today += (int)$qty; }
        }
    }
}

// 5 pesanan terbaru
        usort($orders, function($a, $b) { 
            $idA = (int)preg_replace('/[^0-9]/', '', $a['id'] ?? '0');
            $idB = (int)preg_replace('/[^0-9]/', '', $b['id'] ?? '0');
            return $idB - $idA;
        });
$recent_orders = array_slice($orders, 0, 5);
?>

<header style="margin-bottom: 20px;">
    <div class="header-title">
        <h2 style="font-size: 1.5rem; font-weight: 800;"><?php echo htmlspecialchars($settings['business_name'] ?? 'Laundry'); ?></h2>
        <p style="font-size: 0.85rem; color: #64748b;">Halo, <b><?php echo htmlspecialchars($_SESSION['name']); ?></b>! Berikut performa toko hari ini.</p>
    </div>
</header>

<!-- Quick Action Menu (Mobile Style) -->
<div class="super-app-container-combined">
    <div class="main-nav-row">
        <button class="nav-item" onclick="toggleModal('orderModal', true)">
            <div class="nav-icon bg-blue"><i class="fas fa-plus"></i></div>
            <span>Kasir</span>
        </button>
        <a href="index.php?page=pelanggan" class="nav-item">
            <div class="nav-icon bg-green"><i class="fas fa-users"></i></div>
            <span>Member</span>
        </a>
        <a href="index.php?page=pesanan" class="nav-item">
            <div class="nav-icon bg-orange"><i class="fas fa-clipboard-list"></i></div>
            <span>Pesanan</span>
        </a>
        <button class="nav-item" onclick="document.getElementById('menuDrawer').classList.toggle('active')">
            <div class="nav-icon bg-purple"><i class="fas fa-th-large"></i></div>
            <span>Lainnya</span>
        </button>
    </div>
    <div id="menuDrawer" class="expanded-drawer">
        <div class="drawer-grid-content">
            <a href="index.php?page=layanan" class="nav-item">
                <div class="nav-icon bg-blue" style="width: 44px; height: 44px;"><i class="fas fa-concierge-bell"></i></div>
                <span>Layanan</span>
            </a>
            <?php if($current_role == 'owner'): ?>
            <a href="index.php?page=karyawan" class="nav-item">
                <div class="nav-icon bg-green" style="width: 44px; height: 44px;"><i class="fas fa-user-tie"></i></div>
                <span>Karyawan</span>
            </a>
            <a href="index.php?page=laporan" class="nav-item">
                <div class="nav-icon bg-orange" style="width: 44px; height: 44px;"><i class="fas fa-wallet"></i></div>
                <span>Laporan</span>
            </a>
            <?php endif; ?>
            <a href="index.php?page=parfum" class="nav-item">
                <div class="nav-icon bg-purple" style="width: 44px; height: 44px;"><i class="fas fa-spray-can"></i></div>
                <span>Parfum</span>
            </a>
            <?php if($current_role == 'owner'): ?>
            <a href="index.php?page=pengaturan" class="nav-item">
                <div class="nav-icon bg-gray" style="width: 44px; height: 44px;"><i class="fas fa-cog"></i></div>
                <span>Setting</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Stat Cards -->
<style>
    .stats-grid-responsive {
        display: grid; 
        grid-template-columns: repeat(2, 1fr); 
        gap: 12px; 
        margin-bottom: 24px;
    }
    @media (display-mode: standalone), (max-width: 480px) {
        .stats-grid-responsive {
            grid-template-columns: 1fr !important;
        }
    }
</style>
<div class="stats-grid-responsive">
    <div class="stat-card" style="border-left: 4px solid var(--secondary); flex-direction: row; align-items: center;">
        <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--secondary);">
            <i class="fas fa-money-bill-wave"></i>
        </div>
        <div class="stat-info" style="display: flex; justify-content: space-between; align-items: center; flex: 1;">
            <span class="label" style="margin-bottom: 0;">Omzet</span>
            <h3 class="value" style="color: var(--secondary); font-size: 1.2rem;">Rp <?php echo number_format($income_today, 0, ',', '.'); ?></h3>
        </div>
    </div>

    <div class="stat-card" style="border-left: 4px solid var(--primary); flex-direction: row; align-items: center;">
        <div class="stat-icon" style="background: rgba(99, 102, 241, 0.1); color: var(--primary);">
            <i class="fas fa-clipboard-list"></i>
        </div>
        <div class="stat-info" style="display: flex; justify-content: space-between; align-items: center; flex: 1;">
            <span class="label" style="margin-bottom: 0;">Aktif</span>
            <h3 class="value" style="color: var(--primary); font-size: 1.2rem;"><?php echo count($active_orders); ?> Order</h3>
        </div>
    </div>

    <div class="stat-card" style="border-left: 4px solid var(--warning); flex-direction: row; align-items: center;">
        <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);">
            <i class="fas fa-weight-hanging"></i>
        </div>
        <div class="stat-info" style="display: flex; justify-content: space-between; align-items: center; flex: 1;">
            <span class="label" style="margin-bottom: 0;">Berat/Qty</span>
            <h3 class="value" style="color: var(--warning); font-size: 1.2rem;"><?php echo $weight_today; ?> Kg</h3>
        </div>
    </div>

    <div class="stat-card" style="border-left: 4px solid var(--danger); flex-direction: row; align-items: center;">
        <div class="stat-icon" style="background: rgba(239, 68, 68, 0.1); color: var(--danger);">
            <i class="fas fa-tshirt"></i>
        </div>
        <div class="stat-info" style="display: flex; justify-content: space-between; align-items: center; flex: 1;">
            <span class="label" style="margin-bottom: 0;">Total Pcs</span>
            <h3 class="value" style="color: var(--danger); font-size: 1.2rem;"><?php echo $items_today; ?> Pcs</h3>
        </div>
    </div>
</div>

<!-- Tabel Pesanan Terbaru -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3>Pesanan Terbaru</h3>
        <a href="index.php?page=pesanan" class="btn-primary" style="padding: 8px 16px; font-size: 0.8rem; background: #f1f5f9; color: #6366f1; border: none;">Lihat Semua <i class="fas fa-arrow-right"></i></a>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>No. Nota</th>
                    <th>Pelanggan</th>
                    <th>Total</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($recent_orders)): ?>
                <tr><td colspan="4" style="text-align: center; color: #94a3b8; padding: 30px;">Belum ada pesanan.</td></tr>
                <?php else: ?>
                <?php foreach($recent_orders as $ord):
                    $status = $ord['status'] ?? '';
                    if (in_array($status, ['Selesai', 'Diambil', 'completed'])) {
                        $statusColor = 'completed';
                    } elseif ($status == 'Dibatalkan') {
                        $statusColor = 'cancelled';
                    } else {
                        $statusColor = 'pending';
                    }

                ?>
                <tr>
                    <td style="font-weight: 700; color: #6366f1;"><?php echo htmlspecialchars($ord['order_number'] ?? '#'.$ord['id']); ?></td>
                    <td><?php echo htmlspecialchars($custMap[$ord['customer_id']] ?? 'Pelanggan'); ?></td>
                    <td>Rp <?php echo number_format($ord['grand_total'] ?? 0, 0, ',', '.'); ?></td>
                    <td><span class="status <?php echo $statusColor; ?>"><?php echo htmlspecialchars($ord['status'] ?? 'Proses'); ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
