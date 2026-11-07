<?php
$transactions = $db->readFiltered('transactions');
$customers = $db->readFiltered('customers');
$orders = $db->readFiltered('orders');

// Filter Logic
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

$totalIncome = 0;
$totalExpense = 0;
$filteredTransactions = [];

foreach ($transactions as $t) {
    $tDate = date('Y-m-d', strtotime($t['transaction_date']));
    if ($tDate >= $startDate && $tDate <= $endDate) {
        $filteredTransactions[] = $t;
        if (($t['type'] ?? '') == 'income') $totalIncome += ($t['amount'] ?? 0);
        else $totalExpense += ($t['amount'] ?? 0);
    }
}

// Filter Kategori Layanan
$catFilter = $_GET['cat'] ?? 'Semua';

// Hitung Statistik Berat per Layanan dari Orders
$serviceStats = [];
$totalWeightLunas = 0;
$totalWeightBelum = 0;

$availableCategories = [];

foreach ($orders as $o) {
    $oDate = date('Y-m-d', strtotime($o['order_date']));
    if ($oDate >= $startDate && $oDate <= $endDate) {
        $paid = (int)($o['paid_amount'] ?? 0) + (int)($o['balance_used'] ?? 0);
        $total = (int)($o['grand_total'] ?? 0);
        $isLunas = ($paid >= $total);

        if (isset($o['services']) && is_array($o['services']) && count($o['services']) > 0) {
            foreach ($o['services'] as $s) {
                $name = $s['name'] ?? 'Lain-lain';
                if (!in_array($name, $availableCategories)) {
                    $availableCategories[] = $name;
                }

                $weight = (float)($s['weight'] ?? 0);
                
                if ($catFilter === 'Semua' || $catFilter === $name) {
                    if (!isset($serviceStats[$name])) {
                        $serviceStats[$name] = ['lunas' => 0, 'belum' => 0];
                    }
                    if ($isLunas) {
                        $serviceStats[$name]['lunas'] += $weight;
                        $totalWeightLunas += $weight;
                    } else {
                        $serviceStats[$name]['belum'] += $weight;
                        $totalWeightBelum += $weight;
                    }
                }
            }
        } else {
            $weight = (float)($o['total_weight'] ?? 0);
            if ($weight > 0) {
                $name = 'Layanan Umum';
                if (!in_array($name, $availableCategories)) {
                    $availableCategories[] = $name;
                }

                if ($catFilter === 'Semua' || $catFilter === $name) {
                    if (!isset($serviceStats[$name])) {
                        $serviceStats[$name] = ['lunas' => 0, 'belum' => 0];
                    }
                    if ($isLunas) {
                        $serviceStats[$name]['lunas'] += $weight;
                        $totalWeightLunas += $weight;
                    } else {
                        $serviceStats[$name]['belum'] += $weight;
                        $totalWeightBelum += $weight;
                    }
                }
            }
        }
    }
}
sort($availableCategories);

// Gunakan array_reverse agar transaksi terbaru muncul paling atas
$latestTransactions = array_reverse($filteredTransactions);

// Hitung Total Deposit Pelanggan
$totalDeposits = 0;
foreach ($customers as $c) {
    $totalDeposits += ($c['balance'] ?? 0);
}

// Urutkan pelanggan berdasarkan saldo tertinggi
usort($customers, function($a, $b) {
    return ($b['balance'] ?? 0) - ($a['balance'] ?? 0);
});
$topBalances = array_slice($customers, 0, 5);
?>

<style>
    .print-header { display: none; }
    @media print {
        body { background: white !important; padding: 0 !important; margin: 0 !important; }
        .sidebar, .bottom-nav, .header-actions, .filter-section, .btn-primary, .no-print, header, .card:has(.fas.fa-star) { display: none !important; }
        .content { margin: 0 !important; padding: 0 !important; width: 100% !important; }
        .app-container { display: block !important; }
        .print-header { display: block !important; text-align: center; margin-bottom: 30px; border-bottom: 2px solid #eee; padding-bottom: 20px; }
        .stats-grid-responsive { display: grid !important; grid-template-columns: 1fr 1fr !important; gap: 15px !important; margin-bottom: 30px !important; }
        .stat-card { border: 1px solid #e2e8f0 !important; box-shadow: none !important; page-break-inside: avoid; }
        .card { box-shadow: none !important; border: 1px solid #e2e8f0 !important; width: 100% !important; }
        .grid-2 { display: block !important; }
        .table-container { overflow: visible !important; }
        table { width: 100% !important; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e2e8f0 !important; padding: 12px !important; text-align: left; }
    }
    .stats-grid-responsive {
        display: grid;
        grid-template-columns: 1fr;
        gap: 12px;
        margin-bottom: 24px;
    }
    @media (min-width: 768px) {
        .stats-grid-responsive {
            grid-template-columns: 1fr 1fr;
        }
    }
</style>

<div class="print-header">
    <h1 style="margin: 0; font-size: 1.8rem; color: #1e293b;">LAPORAN KEUANGAN</h1>
    <p style="margin: 5px 0 0; color: #64748b; font-weight: 600;">Periode: <?php echo date('d M Y', strtotime($startDate)); ?> - <?php echo date('d M Y', strtotime($endDate)); ?></p>
    <p style="margin: 5px 0 0; font-size: 0.8rem;"><?php echo $settings['business_name']; ?></p>
</div>



<header style="margin-bottom: 24px;">
    <div class="header-title">
        <h2 style="font-size: 1.5rem; font-weight: 800;">Laporan Keuangan</h2>
        <p style="font-size: 0.85rem; color: var(--text-muted);">Periode: <?php echo date('d M Y', strtotime($startDate)); ?> - <?php echo date('d M Y', strtotime($endDate)); ?></p>
    </div>
</header>

<div class="filter-section card" style="margin-bottom: 12px; padding: 12px; border-radius: 16px;">
    <form action="index.php" method="GET" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; align-items: end;">
        <input type="hidden" name="page" value="laporan">
        <div class="form-group" style="margin:0;">
            <label style="font-size: 0.6rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 4px;">Dari</label>
            <input type="date" name="start_date" value="<?php echo $startDate; ?>" onchange="this.form.submit()" class="input-premium" style="height: 40px; padding: 0 8px; font-size: 0.75rem; border-radius: 10px; border-width: 1px;">
        </div>
        <div class="form-group" style="margin:0;">
            <label style="font-size: 0.6rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 4px;">Sampai</label>
            <input type="date" name="end_date" value="<?php echo $endDate; ?>" onchange="this.form.submit()" class="input-premium" style="height: 40px; padding: 0 8px; font-size: 0.75rem; border-radius: 10px; border-width: 1px;">
        </div>
        <div class="form-group" style="margin:0;">
            <label style="font-size: 0.6rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 4px;">Kategori</label>
            <select name="cat" onchange="this.form.submit()" class="input-premium" style="height: 40px; padding: 0 8px; font-size: 0.75rem; border-radius: 10px; border-width: 1px; width: 100%; appearance: auto;">
                <option value="Semua" <?php echo $catFilter === 'Semua' ? 'selected' : ''; ?>>Semua Kategori</option>
                <?php foreach($availableCategories as $catName): ?>
                <option value="<?php echo htmlspecialchars($catName); ?>" <?php echo $catFilter === $catName ? 'selected' : ''; ?>><?php echo htmlspecialchars($catName); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<div class="no-print" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 24px;">
    <button class="btn-primary" style="background: #64748b; height: 45px; justify-content: center; font-weight: 700; border-radius: 12px; font-size: 0.8rem;" onclick="window.print()">
        <i class="fas fa-file-pdf"></i> Cetak PDF
    </button>
    <button class="btn-primary" style="background: var(--danger); height: 45px; justify-content: center; font-weight: 700; border-radius: 12px; font-size: 0.8rem;" onclick="toggleModal('expenseModal', true)">
        <i class="fas fa-minus-circle"></i> Catat Biaya
    </button>
</div>

<div class="stats-grid-responsive">
    <div class="stat-card" style="border-left: 4px solid var(--secondary); flex-direction: row; align-items: center;">
        <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--secondary); width: 44px; height: 44px;">
            <i class="fas fa-arrow-down"></i>
        </div>
        <div class="stat-info" style="display: flex; justify-content: space-between; align-items: center; flex: 1;">
            <span class="label" style="margin-bottom: 0;">Total Pemasukan</span>
            <h3 class="value" style="color: var(--secondary); font-size: 1.2rem;">Rp <?php echo number_format($totalIncome, 0, ',', '.'); ?></h3>
        </div>
    </div>

    <div class="stat-card" style="border-left: 4px solid var(--danger); flex-direction: row; align-items: center;">
        <div class="stat-icon" style="background: rgba(239, 68, 68, 0.1); color: var(--danger); width: 44px; height: 44px;">
            <i class="fas fa-arrow-up"></i>
        </div>
        <div class="stat-info" style="display: flex; justify-content: space-between; align-items: center; flex: 1;">
            <span class="label" style="margin-bottom: 0;">Total Pengeluaran</span>
            <h3 class="value" style="color: var(--danger); font-size: 1.2rem;">Rp <?php echo number_format($totalExpense, 0, ',', '.'); ?></h3>
        </div>
    </div>

    <div class="stat-card" style="border-left: 4px solid var(--primary); flex-direction: row; align-items: center;">
        <div class="stat-icon" style="background: rgba(99, 102, 241, 0.1); color: var(--primary); width: 44px; height: 44px;">
            <i class="fas fa-wallet"></i>
        </div>
        <div class="stat-info" style="display: flex; justify-content: space-between; align-items: center; flex: 1;">
            <span class="label" style="margin-bottom: 0;">Laba Bersih</span>
            <h3 class="value" style="color: var(--primary); font-size: 1.2rem;">Rp <?php echo number_format($totalIncome - $totalExpense, 0, ',', '.'); ?></h3>
        </div>
    </div>

    <div class="stat-card" style="border-left: 4px solid var(--warning); flex-direction: row; align-items: center;">
        <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--warning); width: 44px; height: 44px;">
            <i class="fas fa-hand-holding-usd"></i>
        </div>
        <div class="stat-info" style="display: flex; justify-content: space-between; align-items: center; flex: 1;">
            <span class="label" style="margin-bottom: 0;">Total Saldo Member</span>
            <h3 class="value" style="color: var(--warning); font-size: 1.2rem;">Rp <?php echo number_format($totalDeposits, 0, ',', '.'); ?></h3>
        </div>
    </div>
</div>

<div class="card" style="margin-bottom: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="font-size: 1.1rem;"><i class="fas fa-balance-scale"></i> Total Berat (Kg) Berdasarkan Layanan</h3>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>KATEGORI LAYANAN</th>
                    <th style="text-align: center;">SUDAH LUNAS</th>
                    <th style="text-align: center;">BELUM LUNAS</th>
                    <th style="text-align: right;">TOTAL BERAT</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($serviceStats)): ?>
                <tr>
                    <td colspan="4" style="text-align: center; color: var(--text-muted); font-size: 0.85rem;">Tidak ada data pada periode ini</td>
                </tr>
                <?php else: ?>
                    <?php foreach($serviceStats as $name => $stat): ?>
                    <tr>
                        <td style="font-weight: 700; font-size: 0.9rem; color: var(--text-dark);"><?php echo htmlspecialchars($name); ?></td>
                        <td style="text-align: center; color: #10b981; font-weight: 800;"><?php echo number_format($stat['lunas'], 1, ',', '.'); ?> Kg</td>
                        <td style="text-align: center; color: #ef4444; font-weight: 800;"><?php echo number_format($stat['belum'], 1, ',', '.'); ?> Kg</td>
                        <td style="text-align: right; font-weight: 900; color: var(--primary);"><?php echo number_format($stat['lunas'] + $stat['belum'], 1, ',', '.'); ?> Kg</td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if(!empty($serviceStats)): ?>
            <tfoot>
                <tr style="background: var(--bg-light); border-top: 2px solid var(--border-color);">
                    <td style="font-weight: 900; font-size: 0.95rem;">TOTAL KESELURUHAN</td>
                    <td style="text-align: center; color: #10b981; font-weight: 900;"><?php echo number_format($totalWeightLunas, 1, ',', '.'); ?> Kg</td>
                    <td style="text-align: center; color: #ef4444; font-weight: 900;"><?php echo number_format($totalWeightBelum, 1, ',', '.'); ?> Kg</td>
                    <td style="text-align: right; font-weight: 900; color: var(--primary); font-size: 1.1rem;"><?php echo number_format($totalWeightLunas + $totalWeightBelum, 1, ',', '.'); ?> Kg</td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<div class="grid-2" style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="font-size: 1.1rem;"><i class="fas fa-history"></i> Riwayat Transaksi</h3>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>TANGGAL</th>
                        <th>KATEGORI</th>
                        <th>KETERANGAN</th>
                        <th style="text-align: right;">NOMINAL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($latestTransactions as $t): ?>
                    <tr>
                        <td style="font-size: 0.75rem; color: var(--text-muted);"><?php echo date('d/m/Y H:i', strtotime($t['transaction_date'])); ?></td>
                        <td>
                            <span style="background: <?php echo $t['type'] == 'income' ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)'; ?>; color: <?php echo $t['type'] == 'income' ? '#10b981' : '#ef4444'; ?>; padding: 4px 8px; border-radius: 6px; font-size: 0.65rem; font-weight: 800;">
                                <?php echo strtoupper($t['category'] ?? 'Lain-lain'); ?>
                            </span>
                        </td>
                        <td style="font-size: 0.85rem; font-weight: 500;"><?php echo htmlspecialchars($t['description']); ?></td>
                        <td style="text-align: right; font-weight: 800; color: <?php echo $t['type'] == 'income' ? '#10b981' : '#ef4444'; ?>;">
                            <?php echo ($t['type'] == 'income' ? '+' : '-') . ' ' . number_format($t['amount'], 0, ',', '.'); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h3 style="font-size: 1.1rem; margin-bottom: 20px;"><i class="fas fa-star" style="color: var(--warning);"></i> Saldo Terbesar</h3>
        <div style="display: flex; flex-direction: column; gap: 16px;">
            <?php foreach($topBalances as $tb): if(($tb['balance'] ?? 0) <= 0) continue; ?>
            <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 12px; border-bottom: 1px solid var(--bg-light);">
                <div>
                    <span style="display: block; font-weight: 700; font-size: 0.9rem;"><?php echo htmlspecialchars($tb['name']); ?></span>
                    <span style="font-size: 0.75rem; color: var(--text-muted);"><?php echo $tb['phone']; ?></span>
                </div>
                <span style="font-weight: 800; color: var(--secondary); font-size: 0.9rem;">Rp <?php echo number_format($tb['balance'], 0, ',', '.'); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <a href="index.php?page=pelanggan" style="display: block; text-align: center; margin-top: 20px; font-size: 0.8rem; color: var(--primary); text-decoration: none; font-weight: 700;">Lihat Semua Member →</a>
    </div>
</div>

<!-- Modal Catat Pengeluaran -->
<div id="expenseModal" class="modal-pro">
    <div class="modal-pro-content" style="max-width: 450px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
            <h3 style="font-weight: 800; color: var(--text-dark);">Catat Pengeluaran Baru</h3>
            <span style="font-size: 1.5rem; cursor: pointer; color: #94a3b8;" onclick="toggleModal('expenseModal', false)">&times;</span>
        </div>
        <form action="actions.php?action=add_expense" method="POST">
            <div class="form-group">
                <label>Kategori Biaya</label>
                <div class="select-premium" onclick="toggleDrop('dropExpCat')">
                    <span id="txtExpCat">Operasional (Sabun, dll)</span>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <input type="hidden" name="category" id="valExpCat" value="operasional">
                <div id="dropExpCat" class="dropdown-box">
                    <div class="drop-item" onclick="selItem('ExpCat', 'operasional', 'Operasional (Sabun, dll)')">Operasional (Sabun, dll)</div>
                    <div class="drop-item" onclick="selItem('ExpCat', 'utilitas', 'Listrik / Air / Gas')">Listrik / Air / Gas</div>
                    <div class="drop-item" onclick="selItem('ExpCat', 'gaji', 'Gaji Karyawan')">Gaji Karyawan</div>
                    <div class="drop-item" onclick="selItem('ExpCat', 'sewa', 'Sewa Tempat')">Sewa Tempat</div>
                    <div class="drop-item" onclick="selItem('ExpCat', 'lainnya', 'Lain-lain')">Lain-lain</div>
                </div>
            </div>
            <div class="form-group">
                <label>Keterangan Biaya</label>
                <input type="text" name="description" required placeholder="Contoh: Beli Deterjen 5 Liter" class="input-premium">
            </div>
            <div class="form-group">
                <label>Nominal (Rp)</label>
                <input type="number" name="amount" required placeholder="0" class="input-premium" style="height: 60px; font-size: 1.25rem; font-weight: 800; color: var(--danger);">
            </div>
            <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; background: var(--danger); height: 55px; border-radius: 16px; font-weight: 800; margin-top: 10px;">SIMPAN PENGELUARAN</button>
        </form>
    </div>
</div>