<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// FITUR KEAMANAN: Auto-Logout 15 Menit (Sesuai Permintaan)
$timeout_seconds = 900; // 15 menit
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout_seconds)) {
    session_unset();
    session_destroy();
    header("Location: login.php?status=timeout");
    exit;
}
$_SESSION['last_activity'] = time(); // Update waktu aktivitas terakhir

// ANTI-CACHE: Pastikan browser tidak menyimpan data sesi lama
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$db = new JsonDB();
$owner_id = $_SESSION['owner_id'] ?? null;
$role = $_SESSION['role'] ?? 'staff';

// Load Data
$all_outlets = $db->readFiltered('outlets');
$all_customers = $db->readFiltered('customers');
$all_staff = $db->readFiltered('staff');
$services = $db->readFiltered('services');
$parfums = $db->readFiltered('parfums');

// Muat Pengaturan Toko (Terisolasi per Owner)
$all_settings = $db->readFiltered('settings');
$settings = $all_settings[0] ?? [
    'business_name' => 'Laundry Pro',
    'address' => '-',
    'phone' => '-'
];
if ($role == 'superadmin') {
    $settings = [
        'business_name' => 'Laundry SaaS Central',
        'business_address' => 'Pusat Kendali Sistem',
        'business_phone' => '0800-SYSTEM',
        'receipt_note' => 'Dashboard Administrasi Global'
    ];
}

$page = $_GET['page'] ?? 'dashboard';
$bt_scope_owner = (string)($owner_id ?? 'ownerless');
$bt_scope_store = (string)($settings['business_name'] ?? 'store');
$bt_scope_host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
$bt_scope = preg_replace('/[^a-zA-Z0-9_\-]/', '_', strtolower($bt_scope_owner . '__' . $bt_scope_store . '__' . $bt_scope_host));
$bt_scope_legacy = (string)($_SESSION['owner_id'] ?? $_SESSION['user_id'] ?? 'global');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $settings['business_name']; ?> - Laundry</title>
    <link rel="icon" type="image/png" href="assets/logo%201.png">
    <link rel="manifest" href="manifest.json">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="assets/js/html5-qrcode.min.js"></script>
    <?php /* Web Bluetooth dipasang sekali untuk semua halaman: reconnect otomatis saat refresh/pindah menu */ ?>
    <script>
    window.__LAUNDRY_BT_SCOPE__ = <?php echo json_encode($bt_scope); ?>;
    window.__LAUNDRY_BT_SCOPE_LEGACY__ = <?php echo json_encode($bt_scope_legacy); ?>;
    </script>
    <script src="assets/js/bt-printer-connection.js"></script>
</head>
<body>
<style>
    .sidebar.sa-sidebar { background: #0f172a !important; border-right: 1px solid #1e293b !important;  }
    .sa-sidebar .logo span, .sa-sidebar nav a { color: #94a3b8 !important;  }
    .sa-sidebar .logo i { color: #818cf8 !important;  }
    .sa-sidebar nav a:hover, .sa-sidebar nav a.active { background: rgba(99, 102, 241, 0.15) !important; color: #ffffff !important;  }
    .sa-sidebar .sidebar-footer { border-top: 1px solid #1e293b !important;  }
    .sa-sidebar .sidebar-footer a { color: #f87171 !important;  }
    .sa-sidebar .sidebar-footer a:hover { background: rgba(239, 68, 68, 0.1) !important;  }

    .modal-pro { display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(8px); align-items: center; justify-content: center;  }
    .modal-pro.active { display: flex;  }
    .modal-pro-content { background: white; width: 95%; max-width: 550px; padding: 25px; border-radius: 28px; box-shadow: 0 25px 50px rgba(0,0,0,0.3); max-height: 90vh; overflow-y: auto;  }
    
    .form-group { position: relative; margin-bottom: 15px;  }
    .form-group label { display: block; font-size: 0.8rem; font-weight: 700; color: #475569; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;  }
    
    .select-premium, .input-premium { background: #f8fafc; border: 2px solid #e2e8f0; padding: 0 18px; height: 58px; border-radius: 16px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; transition: 0.3s; width: 100%; font-size: 0.95rem; color: #1e293b; font-weight: 500;  }
    .input-premium { cursor: text; outline: none;  }
    .select-premium:hover, .input-premium:focus { border-color: var(--primary); background: white; box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);  }
    
    .dropdown-box { display: none; position: absolute; top: 100%; left: 0; background: white; border: 1px solid #e2e8f0; border-radius: 16px; margin-top: 6px; box-shadow: 0 15px 35px rgba(0,0,0,0.15); max-height: 250px; overflow-y: auto; padding: 8px; width: 100%; z-index: 5000; box-sizing: border-box; }
    .dropdown-box.show { display: block;  }
    .drop-item { padding: 12px 15px; border-radius: 12px; cursor: pointer; display: flex; align-items: center; gap: 12px; transition: 0.2s; font-size: 0.9rem;  }
    .drop-item:hover { background: #eff6ff; color: var(--primary); font-weight: 600;  }
    
    .deposit-switch { background: #f1f5f9; padding: 15px; border-radius: 16px; display: flex; align-items: center; justify-content: space-between; margin-top: 10px; cursor: pointer;  }
    .deposit-switch.active { background: #dcfce7; border: 1px solid #22c55e;  }
    
    .balance-badge { background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 99px; font-size: 0.75rem; font-weight: 800;  }
</style>

    <div class="app-container">
                <!-- Sidebar -->
        <aside class="sidebar <?php echo ($role == 'superadmin') ? 'sa-sidebar' : ''; ?>">
            <div class="sidebar-header">
                <div class="logo">
                    <?php if($role == 'superadmin'): ?>
                        <i class="fas fa-shield-alt" style="color: #818cf8;"></i>
                        <span>SaaS Admin</span>
                        <div style="font-size: 0.5rem; background: #818cf8; color: white; padding: 2px 6px; border-radius: 4px; position: absolute; top: 12px; right: 20px; letter-spacing: 1px;">GLOBAL</div>
                    <?php else: ?>
                        <i class="fas fa-wind"></i>
                        <span><?php echo htmlspecialchars($settings['business_name']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <nav class="sidebar-menu" style="padding: 0 15px;">
                <?php if($role == 'superadmin'): ?>
                    <a href="index.php?page=dashboard" class="<?php echo ($page == 'dashboard') ? 'active' : ''; ?>">
                        <i class="fas fa-chart-line"></i> <span>Statistik Global</span>
                    </a>
                    <a href="index.php?page=superadmin" class="<?php echo ($page == 'superadmin') ? 'active' : ''; ?>">
                        <i class="fas fa-store-alt"></i> <span>Kelola Toko</span>
                    </a>
                    <a href="actions.php?action=backup_data" onclick="return confirm('Unduh backup database JSON sekarang?');" style="color: #fbbf24;">
                        <i class="fas fa-download"></i> <span>Backup Data</span>
                    </a>
                    <a href="javascript:void(0)" onclick="document.getElementById('myPwModal').classList.add('active')" style="color: #38bdf8;">
                        <i class="fas fa-key"></i> <span>Ganti Password</span>
                    </a>
                <?php else: ?>
                    <a href="index.php?page=dashboard" class="<?php echo ($page == 'dashboard') ? 'active' : ''; ?>">
                        <i class="fas fa-th-large"></i> <span>Dashboard</span>
                    </a>
                    <a href="index.php?page=pesanan" class="<?php echo ($page == 'pesanan') ? 'active' : ''; ?>">
                        <i class="fas fa-clipboard-list"></i> <span>Pesanan</span>
                    </a>
                    <a href="index.php?page=pelanggan" class="<?php echo ($page == 'pelanggan') ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i> <span>Pelanggan</span>
                    </a>
                    <?php if($role == 'owner'): ?>
                    <a href="index.php?page=laporan" class="<?php echo ($page == 'laporan') ? 'active' : ''; ?>">
                        <i class="fas fa-wallet" style="color: #6366f1;"></i> <span>Laporan</span>
                    </a>
                    <a href="index.php?page=karyawan" class="<?php echo ($page == 'karyawan') ? 'active' : ''; ?>">
                        <i class="fas fa-user-tie" style="color: #10b981;"></i> <span>Karyawan</span>
                    </a>
                    <?php endif; ?>
                    <a href="index.php?page=layanan" class="<?php echo ($page == 'layanan') ? 'active' : ''; ?>">
                        <i class="fas fa-concierge-bell" style="color: #f59e0b;"></i> <span>Layanan</span>
                    </a>
                    <a href="index.php?page=parfum" class="<?php echo ($page == 'parfum') ? 'active' : ''; ?>">
                        <i class="fas fa-spray-can" style="color: #d97706;"></i> <span>Parfum</span>
                    </a>
                    <?php if($role == 'owner'): ?>
                    <a href="index.php?page=pengaturan" class="<?php echo ($page == 'pengaturan') ? 'active' : ''; ?>">
                        <i class="fas fa-cog" style="color: #64748b;"></i> <span>Pengaturan</span>
                    </a>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div class="sidebar-footer">
                    <a href="logout.php" style="color: #ef4444;"><i class="fas fa-power-off"></i> Logout</a>
                </div>
            </nav>
        </aside>

                <main class="content">
            <?php 
            // PROTEKSI AKSES: Super Admin dilarang mengakses halaman operasional toko
            $restricted_pages = ['pesanan', 'pelanggan', 'layanan', 'parfum', 'laporan', 'karyawan', 'pengaturan'];
            if ($role == 'superadmin' && in_array($page, $restricted_pages)) {
                $page = 'dashboard'; 
            }

            // PROTEKSI AKSES STAFF: Staff tidak boleh lihat Laporan & Karyawan
            $staff_restricted = ['laporan', 'karyawan', 'pengaturan'];
            if ($role == 'staff' && in_array($page, $staff_restricted)) {
                $page = 'dashboard';
            }

            switch($page) {
                case 'superadmin': include 'views/superadmin.php'; break;
                case 'pesanan': include 'views/pesanan.php'; break;
                case 'pelanggan': include 'views/pelanggan.php'; break;
                case 'layanan': include 'views/layanan.php'; break;
                case 'parfum': include 'views/parfum.php'; break;
                case 'laporan': include 'views/laporan.php'; break;
                case 'karyawan': include 'views/karyawan.php'; break;
                case 'pengaturan': include 'views/pengaturan.php'; break;
                default: include 'views/dashboard.php'; break;
            }
            ?>
        </main>
    </div>

    <!-- Modal Kasir Smart -->
    <div id="orderModal" class="modal-pro">
        <div class="modal-pro-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <h2 style="font-weight: 800; color: var(--text-dark);"><i class="fas fa-cash-register" style="color: var(--primary);"></i> Kasir Pintar</h2>
                <span style="font-size: 1.5rem; cursor: pointer; color: #94a3b8;" onclick="toggleModal('orderModal', false)">&times;</span>
            </div>
            
            <form action="actions.php?action=create_order" method="POST" id="orderForm">
                <div class="form-group">
                    <label>Pilih Pelanggan</label>
                    <div class="select-premium" onclick="toggleDrop('dropCust')">
                        <span id="txtCust">-- Pilih Pelanggan --</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <input type="hidden" name="customer_id" id="valCust" required>
                    <input type="hidden" id="cust_balance_hidden" value="0">
                    <div id="dropCust" class="dropdown-box">
                        <div style="padding: 10px; border-bottom: 1px solid #f1f5f9;">
                            <input type="text" placeholder="Cari nama/HP..." class="input-premium" style="height: 40px; font-size: 0.85rem;" oninput="filterDrop('dropCust', this.value)">
                        </div>
                        <?php if(empty($all_customers)): ?>
                        <div style="padding: 12px; text-align: center; color: #94a3b8; font-size: 0.85rem;">
                            Belum ada data pelanggan di toko ini.
                        </div>
                        <?php else: ?>
                        <?php foreach($all_customers as $cs): ?>
                        <div class="drop-item" onclick="selCust('<?php echo $cs['id']; ?>', '<?php echo htmlspecialchars($cs['name']); ?>', <?php echo $cs['balance'] ?? 0; ?>)">
                            <i class="fas fa-user-circle"></i>
                            <div style="flex: 1;">
                                <div style="font-weight: 700;"><?php echo htmlspecialchars($cs['name']); ?></div>
                                <div style="font-size: 0.75rem; color: #94a3b8;"><?php echo $cs['phone']; ?></div>
                            </div>
                            <?php if(($cs['balance'] ?? 0) > 0): ?>
                                <span class="balance-badge">Rp <?php echo number_format($cs['balance'], 0, ',', '.'); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        <a href="index.php?page=pelanggan" style="display: block; text-align: center; padding: 12px; color: var(--primary); font-weight: 700; font-size: 0.85rem; text-decoration: none;">+ Tambah Pelanggan Baru</a>
                    </div>
                </div>

                <div id="serviceRows">
                    <!-- Row Pertama -->
                    <div class="service-row" style="background: white; padding: 20px; border-radius: 24px; border: 2px solid #e2e8f0; margin-bottom: 15px; position: relative; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="font-size: 0.7rem;">Layanan Laundry</label>
                                <div class="select-premium svc-dropdown-trigger" onclick="toggleRowDrop(this)" style="padding: 0 15px;">
                                    <span class="svc-text">-- Pilih Layanan --</span>
                                    <i class="fas fa-concierge-bell" style="color: var(--primary);"></i>
                                </div>
                                <input type="hidden" class="svc-select" value="">
                                <div class="dropdown-box svc-dropdown-list">
                                    <div style="padding: 8px; border-bottom: 1px solid #f1f5f9;">
                                        <input type="text" placeholder="Cari layanan..." class="input-premium" style="height: 35px; font-size: 0.8rem; border-radius: 10px;" oninput="filterRowDrop(this)">
                                    </div>
                                    <?php if(empty($services)): ?>
                                    <div style="padding: 12px; text-align: center; color: #94a3b8; font-size: 0.85rem;">
                                        Belum ada layanan di toko ini.<br>
                                        <a href="index.php?page=layanan" style="color: var(--primary); font-weight: 700; font-size: 0.8rem; text-decoration: none; display: inline-block; margin-top: 5px;">+ Tambah Layanan</a>
                                    </div>
                                    <?php else: ?>
                                    <?php foreach($services as $sv): ?>
                                    <div class="drop-item" onclick="selRowSvc(this, '<?php echo $sv['id']; ?>', '<?php echo htmlspecialchars($sv['service_name']); ?>', <?php echo (int)($sv['price_per_unit'] ?? 0); ?>)">
                                        <i class="fas fa-tag" style="font-size: 0.8rem; opacity: 0.5;"></i>
                                        <div style="flex: 1;">
                                            <div style="font-weight: 700; font-size: 0.9rem;"><?php echo htmlspecialchars($sv['service_name']); ?></div>
                                            <div style="font-size: 0.75rem; color: #64748b;">Rp <?php echo number_format((float)($sv['price_per_unit'] ?? 0), 0, ',', '.'); ?></div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="font-size: 0.7rem;">Berat/Qty</label>
                                <input type="number" step="0.01" class="svc-weight input-premium" value="1" oninput="recalc()" style="text-align: center;">
                            </div>
                        </div>
                    </div>
                </div>

                <button type="button" onclick="addServiceRow()" class="btn-primary" style="width: 100%; justify-content: center; background: #6366f1; border-radius: 16px; margin-bottom: 20px; height: 45px; font-size: 0.85rem; opacity: 0.9;">
                    <i class="fas fa-plus-circle"></i> TAMBAH BARIS LAYANAN
                </button>

                <input type="hidden" name="services_json" id="valServicesJson">

                <!-- Area item pesanan lama dihapus karena sudah pakai dynamic rows -->

                <div class="form-group">
                    <label>Pilihan Parfum</label>
                    <div class="select-premium" onclick="toggleDrop('dropParfum')">
                        <span id="txtParfum">-- Pilih Parfum --</span>
                        <i class="fas fa-spray-can"></i>
                    </div>
                    <input type="hidden" name="parfum" id="valParfum" required>
                    <div id="dropParfum" class="dropdown-box">
                        <?php foreach($parfums as $pf): ?>
                        <div class="drop-item" onclick="selItem('Parfum', '<?php echo htmlspecialchars($pf['name']); ?>', '<?php echo htmlspecialchars($pf['name']); ?>')">
                            <?php echo htmlspecialchars($pf['name']); ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Toggle Rincian Item Pakaian -->
                <button type="button" onclick="toggleItemDetails()" class="btn-primary" style="background: #f1f5f9; color: #475569; width: 100%; justify-content: center; margin-bottom: 15px; font-size: 0.85rem; height: 45px;">
                    <i class="fas fa-tshirt"></i> <span id="btnItemTxt">Tambah Rincian Pakaian (Opsional)</span>
                </button>

                <div id="itemDetailsArea" style="display:none; background: #f8fafc; padding: 15px; border-radius: 16px; margin-bottom: 15px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <?php 
                        $common_items = ['Baju/Kaos', 'Celana', 'Jaket/Hoodie', 'Selimut', 'Bedcover', 'Handuk', 'Sprei', 'Sarung Bantal', 'Mukena', 'Kerudung', 'Pakaian Dalam', 'Lain-lain'];
                        foreach($common_items as $itm): 
                            $key = str_replace(['/', ' '], '_', strtolower($itm));
                        ?>
                        <div style="display: flex; align-items: center; gap: 10px; background: white; padding: 8px 12px; border-radius: 10px; border: 1px solid #e2e8f0;">
                            <span style="font-size: 0.75rem; font-weight: 600; flex: 1; line-height: 1.1;"><?php echo $itm; ?></span>
                            <input type="number" name="items[<?php echo $key; ?>]" value="0" 
                                onfocus="if(this.value=='0')this.value=''" 
                                onblur="if(this.value=='')this.value='0'"
                                style="width: 40px; border: none; font-weight: 800; text-align: center; outline: none; background: transparent;">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="background: #f0f9ff; padding: 20px; border-radius: 20px; margin-top: 10px; border: 1px solid #bae6fd;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <span style="font-weight: 700; color: #0369a1;">Total Biaya:</span>
                        <span id="displayTotal" style="font-weight: 900; font-size: 1.25rem; color: #0369a1;">Rp 0</span>
                    </div>

                    <div id="depositInfo" style="display: none; border-top: 1px dashed #bae6fd; padding-top: 10px; margin-top: 10px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-wallet" style="color: #10b981;"></i>
                                <span style="font-weight: 700; font-size: 0.85rem; color: #10b981;">Potongan Deposit:</span>
                            </div>
                            <span id="displayBalanceUsed" style="font-weight: 800; color: #10b981;">- Rp 0</span>
                        </div>
                        <div id="lblDepositMax" style="font-size: 0.7rem; color: #64748b; text-align: right; margin-top: 2px;">Tersedia: Rp 0</div>
                        <input type="hidden" name="balance_used" id="valBalanceUsed" value="0">
                        <!-- Checkbox disembunyikan tapi tetap ada untuk kompatibilitas logika JS jika diperlukan -->
                        <input type="checkbox" id="chkUseDeposit" name="use_deposit" style="display:none" checked>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px; padding-top: 10px; border-top: 1px solid #bae6fd;">
                        <span style="font-weight: 800; color: var(--primary);">Sisa Bayar:</span>
                        <span id="displayGrandTotal" style="font-weight: 900; font-size: 1.4rem; color: var(--primary);">Rp 0</span>
                    </div>
                    <input type="hidden" name="grand_total" id="valGrandTotal" value="0">
                </div>

                <div class="form-group" style="margin-top: 15px;">
                    <label>Metode Pembayaran</label>
                    <div class="select-premium" onclick="toggleDrop('dropPayment')">
                        <span id="txtPayment">Cash (Tunai)</span>
                        <i class="fas fa-wallet"></i>
                    </div>
                    <input type="hidden" name="payment_method" id="valPayment" value="Cash">
                    <div id="dropPayment" class="dropdown-box">
                        <div class="drop-item" onclick="selItem('Payment', 'Cash', 'Cash (Tunai)')">
                            <i class="fas fa-money-bill-wave" style="color: #10b981; width: 20px;"></i> Cash (Tunai)
                        </div>
                        <div class="drop-item" onclick="selItem('Payment', 'Transfer', 'Transfer Bank (TF)')">
                            <i class="fas fa-exchange-alt" style="color: #6366f1; width: 20px;"></i> Transfer Bank (TF)
                        </div>
                        <div class="drop-item" onclick="selItem('Payment', 'QRIS', 'QRIS')">
                            <i class="fas fa-qrcode" style="color: #f59e0b; width: 20px;"></i> QRIS
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 15px;">
                    <label>Nominal Bayar</label>
                    <input type="number" name="paid_amount" id="paid_val" oninput="recalc()" placeholder="0" class="input-premium" style="height: 65px; font-size: 1.5rem; font-weight: 900; color: var(--primary);" min="0" onkeypress="return event.charCode >= 48" onwheel="this.blur()">
                </div>

                <div style="display: flex; align-items: center; gap: 10px; margin-top: 10px; cursor: pointer;">
                    <input type="checkbox" name="save_change_to_balance" id="chkSave" value="yes" style="width: 20px; height: 20px; cursor: pointer;">
                    <label for="chkSave" style="font-weight: 700; font-size: 0.85rem; cursor: pointer; color: #1e293b;">Simpan Kembalian ke Deposit?</label>
                </div>

                <div id="change_area" style="display: none; justify-content: space-between; align-items: center; padding: 15px; background: #f0fdf4; border-radius: 12px; border: 1px solid #bbf7d0; margin-top: 15px;">
                    <span style="font-weight: 700; color: #166534;">Kembalian:</span>
                    <span id="displayChange" style="font-weight: 900; color: #166534; font-size: 1.2rem;">Rp 0</span>
                </div>

                <!-- Pemilihan Petugas (Hanya untuk Owner) -->
                <?php if($role == 'owner'): ?>
                <div class="form-group" style="margin-top: 15px;">
                    <label>Petugas Pelaksana</label>
                    <div class="select-premium" onclick="toggleDrop('dropStaff')">
                        <span id="txtStaff"><?php echo $_SESSION['name']; ?> (Anda)</span>
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <input type="hidden" name="staff_ironing" id="valStaff" value="<?php echo $_SESSION['name']; ?>">
                    <div id="dropStaff" class="dropdown-box">
                        <div class="drop-item" onclick="selItem('Staff', '<?php echo htmlspecialchars($_SESSION['name']); ?>', '<?php echo htmlspecialchars($_SESSION['name']); ?> (Anda)')">
                            <i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($_SESSION['name']); ?> (Owner)
                        </div>
                        <?php foreach($all_staff as $st): ?>
                        <div class="drop-item" onclick="selItem('Staff', '<?php echo htmlspecialchars($st['name']); ?>', '<?php echo htmlspecialchars($st['name']); ?>')">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($st['name']); ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                    <input type="hidden" name="staff_ironing" value="<?php echo $_SESSION['name']; ?>">
                <?php endif; ?>

                <button type="submit" class="btn-primary" style="width:100%; justify-content: center; margin-top: 25px; height: 60px; border-radius: 18px; font-weight: 800;">BUAT PESANAN</button>
            </form>
        </div>
    </div>



        <nav class="bottom-nav">
        <?php if($role == "superadmin"): ?>
            <a href="index.php?page=dashboard" class="<?php echo ($page == "dashboard") ? "active" : ""; ?>">
                <i class="fas fa-chart-line"></i>
            </a>
            <a href="index.php?page=superadmin" class="<?php echo ($page == "superadmin") ? "active" : ""; ?>">
                <i class="fas fa-store-alt"></i>
            </a>
            <div class="add-btn-container">
                <button class="add-btn-main" onclick="if(confirm('Unduh backup database JSON sekarang?')) window.location.href='actions.php?action=backup_data'" style="background: #f59e0b;">
                    <i class="fas fa-download"></i>
                </button>
            </div>
            <a href="logout.php" style="color: #ef4444;">
                <i class="fas fa-power-off"></i>
            </a>
            <a href="javascript:void(0)" onclick="document.getElementById('myPwModal').classList.add('active')">
                <i class="fas fa-eye"></i>
            </a>
        <?php else: ?>
            <a href="index.php?page=dashboard" class="<?php echo ($page == "dashboard") ? "active" : ""; ?>"><i class="fas fa-th-large"></i></a>
            <a href="index.php?page=pesanan" class="<?php echo ($page == "pesanan") ? "active" : ""; ?>"><i class="fas fa-clipboard-list"></i></a>
            <div class="add-btn-container">
                <button class="add-btn-main" onclick="openScanner()" style="background: #6366f1;"><i class="fas fa-qrcode"></i></button>
            </div>
            <a href="index.php?page=pelanggan" class="<?php echo ($page == "pelanggan") ? "active" : ""; ?>"><i class="fas fa-users"></i></a>
            <a href="javascript:void(0)" onclick="toggleModal('menuModal', true)"><i class="fas fa-bars"></i></a>
        <?php endif; ?>
    </nav>
    <!-- Modal Menu Drawer (Mobile) -->
    <div id="menuModal" class="modal-pro">
        <div class="modal-pro-content" style="max-width: 340px; border-radius: 32px; padding: 25px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="font-weight: 800; color: var(--text-dark); font-size: 1rem;">Menu Lainnya</h3>
                <span style="font-size: 1.5rem; cursor: pointer; color: #94a3b8; line-height: 1;" onclick="toggleModal('menuModal', false)">&times;</span>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 15px;">
                <?php if($role == 'owner'): ?>
                <a href="index.php?page=laporan" onclick="toggleModal('menuModal',false)" style="display:flex; flex-direction:column; align-items:center; gap:8px; background:#f0f4ff; padding:18px 10px; border-radius:20px; text-decoration:none; color:#334155; font-size:0.78rem; font-weight:700; text-align:center; transition:0.2s;">
                    <div style="width:48px;height:48px;background:#e0e7ff;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:#6366f1;">
                        <i class="fas fa-wallet"></i>
                    </div>
                    Laporan
                </a>
                <a href="index.php?page=karyawan" onclick="toggleModal('menuModal',false)" style="display:flex; flex-direction:column; align-items:center; gap:8px; background:#f0fdf4; padding:18px 10px; border-radius:20px; text-decoration:none; color:#334155; font-size:0.78rem; font-weight:700; text-align:center; transition:0.2s;">
                    <div style="width:48px;height:48px;background:#dcfce7;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:#10b981;">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    Karyawan
                </a>
                <?php endif; ?>

                <a href="index.php?page=layanan" onclick="toggleModal('menuModal',false)" style="display:flex; flex-direction:column; align-items:center; gap:8px; background:#fffbeb; padding:18px 10px; border-radius:20px; text-decoration:none; color:#334155; font-size:0.78rem; font-weight:700; text-align:center; transition:0.2s;">
                    <div style="width:48px;height:48px;background:#fef3c7;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:#f59e0b;">
                        <i class="fas fa-concierge-bell"></i>
                    </div>
                    Layanan
                </a>

                <a href="index.php?page=parfum" onclick="toggleModal('menuModal',false)" style="display:flex; flex-direction:column; align-items:center; gap:8px; background:#fff7ed; padding:18px 10px; border-radius:20px; text-decoration:none; color:#334155; font-size:0.78rem; font-weight:700; text-align:center; transition:0.2s;">
                    <div style="width:48px;height:48px;background:#ffedd5;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:#d97706;">
                        <i class="fas fa-spray-can"></i>
                    </div>
                    Parfum
                </a>

                <?php if($role == 'owner'): ?>
                <a href="index.php?page=pengaturan" onclick="toggleModal('menuModal',false)" style="display:flex; flex-direction:column; align-items:center; gap:8px; background:#f8fafc; padding:18px 10px; border-radius:20px; text-decoration:none; color:#334155; font-size:0.78rem; font-weight:700; text-align:center; transition:0.2s;">
                    <div style="width:48px;height:48px;background:#f1f5f9;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:#64748b;">
                        <i class="fas fa-cog"></i>
                    </div>
                    Pengaturan
                </a>
                <?php endif; ?>
                <?php if($page == 'pesanan'): ?>
                <a href="javascript:void(0)" onclick="toggleModal('menuModal',false); toggleModal('bluetoothMenuModal', true);" style="display:flex; flex-direction:column; align-items:center; gap:8px; background:#eff6ff; padding:18px 10px; border-radius:20px; text-decoration:none; color:#1e3a8a; font-size:0.78rem; font-weight:700; text-align:center; transition:0.2s;">
                    <div style="width:48px;height:48px;background:#dbeafe;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:#2563eb;">
                        <i class="fas fa-bluetooth-b"></i>
                    </div>
                    Printer BT
                </a>
                <?php else: ?>
                <a href="javascript:void(0)" onclick="toggleModal('menuModal',false); toggleModal('bluetoothMenuModalGlobal', true);" style="display:flex; flex-direction:column; align-items:center; gap:8px; background:#eff6ff; padding:18px 10px; border-radius:20px; text-decoration:none; color:#1e3a8a; font-size:0.78rem; font-weight:700; text-align:center; transition:0.2s;">
                    <div style="width:48px;height:48px;background:#dbeafe;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:#2563eb;">
                        <i class="fas fa-print"></i>
                    </div>
                    Buka Menu Printer
                </a>
                <?php endif; ?>
                <!-- Tombol Install PWA -->
                <div id="installBtnMobile" style="display:none; flex-direction:column; align-items:center; gap:8px; background:#fff1f2; padding:18px 10px; border-radius:20px; text-decoration:none; color:#e11d48; font-size:0.78rem; font-weight:700; text-align:center; transition:0.2s; cursor:pointer; border: 1px solid #fee2e2;">
                    <div style="width:48px;height:48px;background:#ffe4e6;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:#e11d48;">
                        <i class="fas fa-download"></i>
                    </div>
                    Unduh Aplikasi
                </div>
            </div>

            <a href="logout.php" style="display:flex; align-items:center; justify-content:center; gap:10px; background:#fff1f2; color:#e11d48; border:1px solid #ffe4e6; padding:14px; border-radius:16px; text-decoration:none; font-weight:800; font-size:0.9rem; width:100%; box-sizing:border-box;">
                <i class="fas fa-power-off"></i> KELUAR (LOGOUT)
            </a>
        </div>
    </div>

    <?php if($page != 'pesanan'): ?>
    <div id="bluetoothMenuModalGlobal" class="modal-pro">
        <div class="modal-content" style="max-width: 380px; text-align: left;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 style="margin:0;">Printer Bluetooth</h3>
                <span style="font-size:1.4rem; cursor:pointer; color:#94a3b8;" onclick="toggleModal('bluetoothMenuModalGlobal', false)">&times;</span>
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

                <button id="bt-test-btn" onclick="if (typeof testBluetoothPrinter === 'function') { testBluetoothPrinter(); } else { alert('Fitur test print tersedia di halaman Pesanan.'); }" class="btn-primary" style="justify-content: center; padding: 12px; background: #16a34a;">
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
                    <input type="text" readonly value="chrome://flags/#enable-web-bluetooth-new-permissions-backend" id="copyFlag1" style="flex:1; padding:6px; font-size:0.7rem; border:1px solid #fcd34d; border-radius:6px; background:#fef3c7; color:#92400e;">
                    <button type="button" onclick="var copyText = document.getElementById('copyFlag1'); copyText.select(); copyText.setSelectionRange(0, 99999); navigator.clipboard.writeText(copyText.value); alert('Link berhasil disalin! Silakan paste di tab baru.');" style="padding:6px 10px; background:#f59e0b; color:white; border:none; border-radius:6px; cursor:pointer; font-weight:bold; font-size:0.7rem;"><i class="fas fa-copy"></i> Salin</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>


    <!-- Modal Scanner QR -->
    <div id="scannerModal" class="modal-pro">
        <div class="modal-pro-content" style="max-width: 400px; text-align: center;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="font-weight: 800;">📷 Scan Keranjang</h3>
                <span style="font-size: 1.5rem; cursor: pointer;" onclick="closeScanner()">&times;</span>
            </div>
            <div id="reader" style="width: 100%; border-radius: 20px; overflow: hidden; background: #000;"></div>
            <p style="margin-top: 15px; font-size: 0.8rem; color: #64748b;">Arahkan kamera ke QR Code di Keranjang</p>
        </div>
    </div>

    <!-- Modal Notifikasi WA / Selesai -->
    <div id="finishConfirmModal" class="modal-pro">
        <div class="modal-pro-content" style="max-width: 350px; text-align: center;">
            <div id="finishModalIcon" style="font-size: 4rem; color: #10b981; margin-bottom: 20px;"><i class="fas fa-check-circle"></i></div>
            <h3 id="finishModalTitle" style="font-weight: 800; margin-bottom: 10px;">Pesanan Selesai?</h3>
            <p id="finishOrderInfo" style="color: #64748b; margin-bottom: 25px;">Apakah pesanan ini sudah benar-benar selesai dikerjakan?</p>
            <input type="hidden" id="finishOrderId">
            <div id="finishModalActions" style="display: grid; gap: 12px;">
                <button id="btnConfirmFinish" onclick="processFinishOrder()" class="btn-primary" style="justify-content: center; padding: 15px; font-weight: 800;">YA, SUDAH SELESAI</button>
                <button onclick="toggleModal('finishConfirmModal', false)" style="background: none; border: none; color: #94a3b8; cursor: pointer;">Batal</button>
            </div>
        </div>
    </div>
<script>
    let html5QrCode = null;

    function playTerimaKasih() {
        try {
            const audio = new Audio("https://translate.google.com/translate_tts?ie=UTF-8&q=Terima+kasih&tl=id&client=tw-ob");
            audio.play().catch(e => console.log(e));
        } catch (e) {}
    }

    function openScanner() {
        toggleModal('scannerModal', true);
        html5QrCode = new Html5Qrcode("reader");
        const config = { fps: 25, qrbox: { width: 220, height: 220 }, aspectRatio: 1.0 };
        html5QrCode.start({ facingMode: "environment" }, config, async (decodedText) => {
            if (decodedText.startsWith('FINISH:')) {
                playTerimaKasih();
                const orderId = decodedText.split(':')[1];
                const response = await fetch('actions.php?action=get_order_info&id=' + orderId);
                const data = await response.json();
                if (response.ok && data.status === 'success') {
                    closeScanner();
                    showFinishConfirm(orderId, data.order_number, data.customer_name, data.order_status, data.customer_phone, data.business_name);
                } else {
                    alert(data.message || "Pesanan tidak valid!");
                }
            }
        }).catch(err => {
            alert("Gagal mengakses kamera: " + err);
            closeScanner();
        });
    }

    function closeScanner() {
        if (html5QrCode) {
            html5QrCode.stop().then(() => {
                html5QrCode = null;
                toggleModal('scannerModal', false);
            }).catch(() => {
                html5QrCode = null;
                toggleModal('scannerModal', false);
            });
        } else toggleModal('scannerModal', false);
    }

    function showFinishConfirm(id, orderNum, custName, status, phone, bizName) {
        document.getElementById("finishOrderId").value = id;
        const icon = document.getElementById("finishModalIcon");
        const title = document.getElementById("finishModalTitle");
        const info = document.getElementById("finishOrderInfo");
        const actions = document.getElementById("finishModalActions");
        const role = '<?php echo $role; ?>';

        if (status === 'completed' || status === 'Selesai') {
            if (role === 'owner') {
                const waMsg = `Halo Kak ${custName}, pesanan laundry Anda di *${bizName}* dengan No. Nota *${orderNum}* telah SELESAI dan siap diambil. %0A%0ACek detail nota Anda di sini: %0Ahttps://${window.location.host}/track.php?nota=${orderNum}%0A%0ATerima kasih sudah mempercayakan pakaian Anda kepada kami!`;
                const waUrl = `https://api.whatsapp.com/send?phone=${phone}&text=${waMsg}`;
                
                icon.innerHTML = '<i class="fab fa-whatsapp" style="color: #25d366;"></i>';
                title.innerText = 'Sudah Selesai';
                info.innerHTML = `Pesanan <strong>${orderNum}</strong> sudah selesai.<br>Hubungi <strong>${custName}</strong> via WhatsApp?`;
                actions.innerHTML = `
                    <a href="${waUrl}" target="_blank" onclick="toggleModal('finishConfirmModal', false)" class="btn-primary" style="background: #25d366; color: white; justify-content: center; padding: 15px; font-weight: 800; text-decoration: none; display: flex; align-items: center; gap: 10px;">
                        <i class="fab fa-whatsapp"></i> HUBUNGI CUSTOMER
                    </a>
                    <button onclick="toggleModal('finishConfirmModal', false)" style="background: none; border: none; color: #94a3b8; cursor: pointer;">Tutup</button>
                `;
            } else {
                icon.innerHTML = '<i class="fas fa-check-double" style="color: #6366f1;"></i>';
                title.innerText = 'Pesanan Selesai';
                info.innerHTML = `Pesanan <strong>${orderNum}</strong> an. <strong>${custName}</strong> sudah berstatus Selesai.`;
                actions.innerHTML = `
                    <button onclick="toggleModal('finishConfirmModal', false)" class="btn-primary" style="justify-content: center; padding: 15px; font-weight: 800;">OK</button>
                `;
            }
        } else {
            icon.innerHTML = '<i class="fas fa-check-circle"></i>';
            title.innerText = 'Pesanan Selesai?';
            info.innerHTML = `Selesaikan Pesanan <br><strong>${orderNum}</strong><br>an. <strong>${custName}</strong>?`;
            actions.innerHTML = `
                <button onclick="processFinishOrder()" class="btn-primary" style="justify-content: center; padding: 15px; font-weight: 800;">YA, SUDAH SELESAI</button>
                <button onclick="toggleModal('finishConfirmModal', false)" style="background: none; border: none; color: #94a3b8; cursor: pointer;">Belum</button>
            `;
        }
        toggleModal('finishConfirmModal', true);
    }

    async function processFinishOrder() {
        const id = document.getElementById("finishOrderId").value;
        const res = await fetch("actions.php?action=complete_order_silent&id=" + id);
        if(res.ok) {
            alert("Pesanan berhasil diselesaikan!");
            location.reload();
        } else {
            alert("Gagal menyelesaikan pesanan.");
        }
    }
    // JS DASAR
    function toggleModal(id, s) { 
        const m = document.getElementById(id);
        if(m) m.classList.toggle("active", s); 
    }
    function toggleDrop(id) { 
        const isOpen = document.getElementById(id).classList.contains("show");
        document.querySelectorAll(".dropdown-box").forEach(d => d.classList.remove("show")); 
        if(!isOpen) document.getElementById(id).classList.add("show");
    }
    function filterDrop(id, q) { 
        const d = document.getElementById(id);
        if(!d) return;
        const items = d.querySelectorAll(".drop-item"); 
        items.forEach(it => { 
            const txt = it.innerText || ""; 
            it.style.display = txt.toLowerCase().includes(q.toLowerCase()) ? "flex" : "none"; 
        }); 
    }
    function selCust(id, name, balance) { 
        document.getElementById("valCust").value = id; 
        document.getElementById("txtCust").innerText = name; 
        document.getElementById("cust_balance_hidden").value = balance; 
        document.getElementById("depositInfo").style.display = balance > 0 ? "block" : "none"; 
        document.getElementById("lblDepositMax").innerText = "Tersedia: Rp " + balance.toLocaleString("id-ID"); 
        document.querySelectorAll(".dropdown-box").forEach(d => d.classList.remove("show"));
        recalc(); 
    }
    function selItem(type, val, txt) { 
        const v = document.getElementById("val"+type);
        const t = document.getElementById("txt"+type);
        if(v) v.value = val; 
        if(t) t.innerText = txt; 
        document.querySelectorAll(".dropdown-box").forEach(d => d.classList.remove("show")); 
    }
    
    function addServiceRow() {
        const container = document.getElementById("serviceRows");
        const firstRow = container.querySelector(".service-row");
        const newRow = firstRow.cloneNode(true);
        
        newRow.querySelector(".svc-select").value = "";
        newRow.querySelector(".svc-text").innerText = "-- Pilih Layanan --";
        newRow.querySelector(".svc-weight").value = 1;
        newRow.querySelector(".svc-dropdown-list").classList.remove("show");
        
        if (!newRow.querySelector(".remove-row-btn")) {
            const delBtn = document.createElement("button");
            delBtn.type = "button";
            delBtn.className = "remove-row-btn";
            delBtn.innerHTML = "<i class=\"fas fa-trash\"></i>";
            delBtn.style = "position: absolute; top: -10px; right: -10px; background: #ef4444; color: white; border: none; width: 28px; height: 28px; border-radius: 50%; cursor: pointer; font-size: 0.75rem; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 8px rgba(239, 68, 68, 0.3); z-index: 10;";
            delBtn.onclick = function() { this.closest(".service-row").remove(); recalc(); };
            newRow.appendChild(delBtn);
        } else {
            newRow.querySelector(".remove-row-btn").onclick = function() { this.closest(".service-row").remove(); recalc(); };
        }
        container.appendChild(newRow);
        recalc();
    }

    function toggleRowDrop(el) {
        const list = el.closest(".form-group").querySelector(".svc-dropdown-list");
        const isOpen = list.classList.contains("show");
        document.querySelectorAll(".dropdown-box").forEach(d => d.classList.remove("show"));
        if (!isOpen) {
            list.classList.add("show");
            const sInput = list.querySelector("input");
            if(sInput) { sInput.value = ""; sInput.focus(); filterRowDrop(sInput); }
        }
    }

    function filterRowDrop(input) {
        const q = input.value.toLowerCase();
        const items = input.closest(".svc-dropdown-list").querySelectorAll(".drop-item");
        items.forEach(it => {
            const txt = it.innerText.toLowerCase();
            it.style.display = txt.includes(q) ? "flex" : "none";
        });
    }

    function selRowSvc(el, id, name, price) {
        const row = el.closest(".service-row");
        const input = row.querySelector(".svc-select");
        const text = row.querySelector(".svc-text");
        
        input.value = id;
        input.setAttribute("data-price", price);
        input.setAttribute("data-name", name);
        text.innerText = name;
        
        document.querySelectorAll(".dropdown-box").forEach(d => d.classList.remove("show"));
        recalc();
    }

    function recalc() {
        const rows = document.querySelectorAll(".service-row");
        let total = 0;
        let cartData = [];
        rows.forEach(row => {
            const select = row.querySelector(".svc-select");
            const weightInput = row.querySelector(".svc-weight");
            const val = select.value;
            if (val) {
                const price = parseFloat(select.getAttribute("data-price")) || 0;
                const name = select.getAttribute("data-name");
                const weight = parseFloat(weightInput.value) || 0;
                const subtotal = Math.round(price * weight);
                total += subtotal;
                cartData.push({ id: val, name: name, price: price, weight: weight, subtotal: subtotal });
            }
        });
        document.getElementById("valServicesJson").value = JSON.stringify(cartData);
        document.getElementById("displayTotal").innerText = "Rp " + total.toLocaleString("id-ID");
        const avail = parseInt(document.getElementById("cust_balance_hidden").value) || 0;
        const balanceUsed = Math.min(total, avail);
        document.getElementById("valBalanceUsed").value = balanceUsed;
        document.getElementById("displayBalanceUsed").innerText = "- Rp " + balanceUsed.toLocaleString("id-ID");
        const grandTotal = total - balanceUsed;
        document.getElementById("displayGrandTotal").innerText = "Rp " + grandTotal.toLocaleString("id-ID");
        document.getElementById("valGrandTotal").value = grandTotal;

        // Hitung Kembalian
        const paid = parseInt(document.getElementById("paid_val").value) || 0;
        const change = Math.max(0, paid - grandTotal);
        document.getElementById("displayChange").innerText = "Rp " + change.toLocaleString("id-ID");
        document.getElementById("change_area").style.display = (paid > grandTotal) ? "flex" : "none";
    }

    function toggleItemDetails() {
        const area = document.getElementById("itemDetailsArea");
        const isHidden = area.style.display === "none";
        area.style.display = isHidden ? "block" : "none";
        document.getElementById("btnItemTxt").innerText = isHidden ? "Sembunyikan Rincian Pakaian" : "Tambah Rincian Pakaian (Opsional)";
    }

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('sw.js').then(reg => {
                console.log('SW Registered!', reg);
            }).catch(err => {
                console.log('SW Registration Failed!', err);
            });
        });
    }

    let deferredPrompt;
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        const installBtn = document.getElementById('installBtnMobile');
        if(installBtn) installBtn.style.display = 'flex';
    });

    document.addEventListener('click', async (e) => {
        if (e.target.closest('#installBtnMobile')) {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                if (outcome === 'accepted') {
                    document.getElementById('installBtnMobile').style.display = 'none';
                }
                deferredPrompt = null;
            }
        }
    });

    <?php if(isset($_GET['login_success'])): ?>
        localStorage.setItem('laundry_user_id', '<?php echo $_SESSION['user_id']; ?>');
    <?php endif; ?>

    window.onclick = function(event) {
        if (event.target.className === 'modal-pro' || event.target.className === 'modal') {
            event.target.style.display = 'none';
            event.target.classList.remove('active');
        }
    }
    </script>

    <!-- Modal Ganti Password SA -->
    <div id="myPwModal" class="modal-pro">
        <div class="modal-pro-content" style="max-width: 400px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <h3 style="font-weight: 800; color: var(--text-dark);">Ganti Password Saya</h3>
                <span class="close" onclick="document.getElementById('myPwModal').classList.remove('active')">&times;</span>
            </div>
            <form action="actions.php?action=change_password" method="POST">
                <div class="form-group">
                    <label>Password Baru</label>
                    <input type="password" name="new_password" placeholder="Masukkan password baru" required minlength="6" class="input-premium">
                </div>
                <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; margin-top: 15px;">Simpan Password Baru</button>
            </form>
        </div>
    </div>

    <!-- SPA Navigation Script untuk menjaga koneksi Web Bluetooth tidak terputus saat pindah menu -->
    <script>
    window.spaReload = function() {
        window.spaNavigate(window.location.href);
    };

    window.spaNavigate = function(url) {
        let contentEl = document.querySelector('.content');
        if (contentEl) {
            contentEl.innerHTML = '<div style="display:flex; justify-content:center; align-items:center; height:50vh;"><i class="fas fa-circle-notch fa-spin fa-3x" style="color:#3b82f6;"></i><div style="margin-left:15px; font-weight:700; color:#64748b;">Memuat...</div></div>';
        }
        
        if (url.includes('page=')) {
            document.querySelectorAll('.sidebar-menu a, .bottom-nav a').forEach(l => l.classList.remove('active'));
            let targetPage = url.split('page=')[1].split('&')[0];
            document.querySelectorAll('.sidebar-menu a, .bottom-nav a').forEach(l => {
                if (l.getAttribute('href') && l.getAttribute('href').includes('page=' + targetPage)) {
                    l.classList.add('active');
                }
            });
        }
        
        let sidebar = document.getElementById('sidebar');
        if (sidebar && sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
        }
        
        document.querySelectorAll('.modal-pro, .modal').forEach(m => {
            m.classList.remove('active');
            m.style.display = '';
        });

        return fetch(url)
            .then(res => res.text().then(html => ({html: html, finalUrl: res.url})))
            .then(data => {
                let parser = new DOMParser();
                let doc = parser.parseFromString(data.html, 'text/html');
                let newContent = doc.querySelector('.content');
                
                if (newContent && contentEl) {
                    contentEl.innerHTML = newContent.innerHTML;
                    
                    let newOrderModal = doc.querySelector('#orderModal');
                    let currentOrderModal = document.querySelector('#orderModal');
                    if (newOrderModal && currentOrderModal) {
                        currentOrderModal.innerHTML = newOrderModal.innerHTML;
                    }

                    let scripts = contentEl.querySelectorAll('script');
                    scripts.forEach(oldScript => {
                        let newScript = document.createElement('script');
                        Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                        let code = oldScript.innerHTML;
                        code = code.replace(/\bconst\s+/g, 'var ').replace(/\blet\s+/g, 'var ');
                        newScript.appendChild(document.createTextNode(code));
                        oldScript.parentNode.replaceChild(newScript, oldScript);
                    });
                    if (data.finalUrl && data.finalUrl.includes('index.php') && data.finalUrl !== window.location.href) {
                        history.pushState({url: data.finalUrl}, '', data.finalUrl);
                    }
                } else {
                    window.location.href = url;
                }
            })
            .catch(() => window.location.href = url);
    };

    window.spaReload = function() {
        window.spaNavigate(window.location.href);
    };

    document.addEventListener('DOMContentLoaded', () => {
        document.body.addEventListener('click', function(e) {
            let a = e.target.closest('a');
            if (a && a.getAttribute('href') && !a.hasAttribute('target') && !a.hasAttribute('download')) {
                let href = a.getAttribute('href');
                if (href.startsWith('index.php?page=') || href.startsWith('actions.php?')) {
                    if (e.defaultPrevented) return;
                    e.preventDefault();
                    window.spaNavigate(href);
                }
            }
        });

        document.body.addEventListener('submit', function(e) {
            let form = e.target;
            if (form && !form.hasAttribute('target')) {
                e.preventDefault();
                let submitBtn = form.querySelector('button[type="submit"]');
                let originalText = '';
                if (submitBtn) {
                    originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Proses...';
                    submitBtn.disabled = true;
                }
                
                let formData = new FormData(form);
                let url = form.getAttribute('action') || window.location.href;
                let method = form.getAttribute('method') || 'GET';
                
                let opts = { method: method.toUpperCase() };
                if (method.toUpperCase() === 'POST') {
                    opts.body = formData;
                } else {
                    let params = new URLSearchParams(formData);
                    url += (url.includes('?') ? '&' : '?') + params.toString();
                }

                fetch(url, opts)
                    .then(res => res.text().then(html => ({html: html, finalUrl: res.url})))
                    .then(data => {
                        let parser = new DOMParser();
                        let doc = parser.parseFromString(data.html, 'text/html');
                        let newContent = doc.querySelector('.content');
                        let contentEl = document.querySelector('.content');
                        
                        if (newContent && contentEl) {
                            document.querySelectorAll('.modal-pro, .modal').forEach(m => {
                                m.classList.remove('active');
                                m.style.display = '';
                            });
                            form.reset();
                            
                            contentEl.innerHTML = newContent.innerHTML;
                            
                            let newOrderModal = doc.querySelector('#orderModal');
                            let currentOrderModal = document.querySelector('#orderModal');
                            if (newOrderModal && currentOrderModal) {
                                currentOrderModal.innerHTML = newOrderModal.innerHTML;
                            }

                            let scripts = contentEl.querySelectorAll('script');
                            scripts.forEach(oldScript => {
                                let newScript = document.createElement('script');
                                Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                                let code = oldScript.innerHTML;
                                code = code.replace(/\bconst\s+/g, 'var ').replace(/\blet\s+/g, 'var ');
                                newScript.appendChild(document.createTextNode(code));
                                oldScript.parentNode.replaceChild(newScript, oldScript);
                            });
                            
                            if (data.finalUrl && data.finalUrl.includes('index.php') && data.finalUrl !== window.location.href) {
                                history.pushState({url: data.finalUrl}, '', data.finalUrl);
                            } else {
                                // If already on the same page, or no new URL, push is skipped
                            }
                        } else {
                            window.location.reload();
                        }
                    })
                    .catch(() => window.location.reload())
                    .finally(() => {
                        if (submitBtn) {
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                        }
                    });
            }
        });
        
        window.addEventListener('popstate', function() {
            window.location.reload();
        });
    });
    </script>
</body>
</html>
