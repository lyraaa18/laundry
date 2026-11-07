<?php
ob_start();
error_reporting(E_ALL);
ini_set("display_errors", 1);
session_start(); // WAJIB: Pastikan sesi aktif di setiap aksi
require_once 'config.php';

$db = new JsonDB();
$action = $_GET['action'] ?? '';
$owner_id = $_SESSION['owner_id'] ?? null;

// Jika tidak ada owner_id dan bukan superadmin, blokir aksi
if (!$owner_id && $_SESSION['role'] != 'superadmin' && $action != 'login') {
    die("Sesi tidak valid. Silakan login kembali.");
}

// Sinkronisasi Aktivitas & Session Guard
if ($action == 'ping') {
    $_SESSION['last_activity'] = time();
    echo $_SESSION['user_id'] ?? 'none';
    exit;
}

function redirect($page, $status = '') {
    header("Location: index.php?page=$page&status=$status");
    exit;
}

// --- PARFUM ---
if ($action == 'create_parfum') {
    error_log("DEBUG PARFUM: Owner ID is " . $owner_id);
    $newData = [
        'id' => 'prm_' . time(),
        'name' => $_POST['name'],
        'owner_id' => $owner_id
    ];
    $res = $db->insert('parfums', $newData);
    error_log("DEBUG PARFUM: Insert result is " . ($res ? "SUCCESS ID: $res" : "FAILED"));
    redirect('parfum', 'success');
}

if ($action == 'delete_parfum') {
    $db->delete('parfums', $_GET['id']);
    redirect('parfum', 'success');
}

// --- LOGIKA PESANAN ---
if ($action == 'create_order' || $action == 'add_order') {
    $services_data = json_decode($_POST['services_json'], true);
    if (empty($services_data)) { redirect('pesanan', 'error_no_service'); }

    $raw_items = $_POST['items'] ?? [];
    $items_detail = [];
    foreach($raw_items as $k => $v) {
        if((int)$v > 0) $items_detail[$k] = (int)$v;
    }
    $total_weight = 0;
    foreach($services_data as $s) $total_weight += (float)($s['weight'] ?? 0);
    $balance_used = (int)($_POST['balance_used'] ?? 0);
    $final_pay = (int)$_POST['grand_total']; // Sisa bayar tunai

    // Hitung Estimasi Terlama
    $max_days = 2;
    $all_services = $db->read('services');
    foreach($services_data as $s) {
        foreach($all_services as $as) {
            if ($as['id'] == $s['id'] && isset($as['estimated_days'])) {
                if ($as['estimated_days'] > $max_days) $max_days = $as['estimated_days'];
            }
        }
    }
    $est_finish = date('Y-m-d H:i:s', strtotime("+$max_days days"));

    $order_data = [
        'order_number' => 'ORD-' . strtoupper(substr(md5(time() . rand()), 0, 6)),
        'outlet_id' => $_POST['outlet_id'],
        'customer_id' => $_POST['customer_id'],
        'services' => $services_data,
        'items_detail' => $items_detail,
        'parfum' => $_POST['parfum'] ?? 'Tanpa Parfum',
        'total_weight' => $total_weight,
        'staff_ironing' => $_POST['staff_ironing'] ?? $_SESSION['name'],
        'grand_total' => $final_pay + $balance_used,
        'paid_amount' => (int)($_POST['paid_amount'] ?? 0),
        'balance_used' => $balance_used,
        'status' => 'processing',
        'order_date' => date('Y-m-d H:i:s'),
        'est_finish_date' => $est_finish,
        'created_by_role' => $_SESSION['role'] ?? 'staff',
        'payment_method' => $_POST['payment_method'] ?? 'Cash',
        'owner_id' => $owner_id
    ];
    
    $db->insert('orders', $order_data);

    // Update Saldo Pelanggan
    $customers = $db->read('customers');
    foreach($customers as &$c) {
        if($c['id'] == $_POST['customer_id']) {
            $current_balance = (int)($c['balance'] ?? 0);
            if ($balance_used > 0) $current_balance -= $balance_used;
            
            if (isset($_POST['save_change_to_balance'])) {
                $paid = (int)($_POST['paid_amount'] ?? 0);
                if ($paid > $final_pay) {
                    $change = $paid - $final_pay;
                    $current_balance += $change;
                }
            }
            $c['balance'] = $current_balance;
        }
    }
    $db->write('customers', $customers);

    // Catat Transaksi Keuangan (Mencatat apa yang BENAR-BENAR dibayar sekarang)
    $actual_paid = (int)($_POST['paid_amount'] ?? 0);
    if ($actual_paid > 0) {
        $pay_method = $_POST['payment_method'] ?? 'Cash';
        $db->insert('transactions', [
            'type' => 'income',
            'category' => 'laundry',
            'amount' => $actual_paid,
            'description' => "DP/Pelunasan Awal {$order_data['order_number']} ($pay_method)",
            'transaction_date' => date('Y-m-d H:i:s'),
            'owner_id' => $owner_id
        ]);
    }

    // Pastikan tidak ada output sebelum redirect
    if (ob_get_length()) ob_clean();
    redirect('pesanan', 'success');
}

if ($action == 'get_order_info') {
    $id = $_GET['id'];
    $orders = $db->read('orders');
    $customers = $db->read('customers');
    $settings = $db->readFiltered('settings')[0] ?? [];
    
    $custInfo = [];
    foreach($customers as $c) $custInfo[$c['id']] = ['name' => $c['name'], 'phone' => $c['phone']];

    foreach ($orders as $o) {
        if ($o['id'] == $id) {
            if ($o['owner_id'] == $owner_id) {
                $c = $custInfo[$o['customer_id']] ?? ['name' => 'Pelanggan', 'phone' => ''];
                echo json_encode([
                    'status' => 'success',
                    'order_number' => $o['order_number'],
                    'customer_name' => $c['name'],
                    'customer_phone' => $c['phone'],
                    'order_status' => $o['status'],
                    'business_name' => $settings['business_name'] ?? 'Laundry'
                ]);
            } else {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Akses Ditolak: Pesanan ini milik toko lain!']);
            }
            exit;
        }
    }
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Pesanan tidak ditemukan.']);
    exit;
}



if ($action == 'complete_order_silent') {
    $id = $_GET['id'];
    $orders = $db->read('orders');
    $updated = false;
    foreach ($orders as &$o) {
        if ($o['id'] == $id && $o['owner_id'] == $owner_id) {
            $o['status'] = 'completed';
            $updated = true;
        }
    }
    if ($updated) {
        $db->write('orders', $orders);
        echo "OK";
    } else {
        http_response_code(403);
        echo "FAIL: Unauthorized or Not Found";
    }
    exit;
}

if ($action == 'complete_order') {
    $id = $_GET['id'];
    $orders = $db->read('orders');
    $updated = false;
    foreach ($orders as &$o) {
        if ($o['id'] == $id && $o['owner_id'] == $owner_id) {
            $o['status'] = 'completed';
            $updated = true;
        }
    }
    if ($updated) {
        $db->write('orders', $orders);
        redirect('pesanan', 'success');
    } else {
        redirect('pesanan', 'error');
    }
}

if ($action == "pay_order") {
    $id = $_GET['id'];
    $orders = $db->read('orders');
    $pay_amount = (int)($_GET['amount'] ?? 0);
    $method = $_GET['method'] ?? 'Cash';
    $save_to_balance = (int)($_GET['save_to_balance'] ?? 0);
    $balance_used = (int)($_GET['balance_used'] ?? 0);

    foreach ($orders as &$o) {
        if ($o['id'] == $id && $o['owner_id'] == $owner_id) {
            $remaining = $o['grand_total'] - ($o['paid_amount'] + $o['balance_used']);
            
            // Proses Saldo Deposit (jika digunakan)
            if ($balance_used > 0) {
                $o['balance_used'] += $balance_used;
                
                // Update Saldo Pelanggan (Kurangi)
                $customers = $db->read('customers');
                foreach($customers as &$c) {
                    if($c['id'] == $o['customer_id']) {
                        $c['balance'] = (int)($c['balance'] ?? 0) - $balance_used;
                    }
                }
                $db->write('customers', $customers);
                
                // Update sisa tagihan setelah dipotong saldo
                $remaining -= $balance_used;
            }

            $to_pay = ($pay_amount > 0) ? $pay_amount : $remaining;
            
            if ($to_pay > 0) {
                if ($pay_amount > $remaining && $save_to_balance) {
                    $change = $pay_amount - $remaining;
                    $o['paid_amount'] += $remaining;
                    
                    // Update Saldo Pelanggan (Tambah dari Kembalian)
                    $customers = $db->read('customers');
                    foreach($customers as &$c) {
                        if($c['id'] == $o['customer_id']) {
                            $c['balance'] = (int)($c['balance'] ?? 0) + $change;
                        }
                    }
                    $db->write('customers', $customers);
                } else {
                    $o['paid_amount'] += $to_pay;
                }
                $o['payment_method'] = $method;

                // Catat Transaksi Keuangan (Hanya yang dibayar cash/transfer)
                $db->insert('transactions', [
                    'type' => 'income',
                    'category' => 'laundry_payment',
                    'amount' => ($pay_amount > $remaining && $save_to_balance) ? $remaining : $to_pay,
                    'description' => 'Pelunasan Pesanan #' . $o['order_number'] . ' (' . $method . ')',
                    'transaction_date' => date('Y-m-d H:i:s'),
                    'owner_id' => $owner_id
                ]);
            }
        }
    }
    $db->write('orders', $orders);
    echo "OK";
    exit;
}
if ($action == 'delete_order') {
    $id = $_GET['id'];
    $order = null;
    $orders = $db->read('orders');
    foreach($orders as $o) if($o['id'] == $id) $order = $o;

    if ($order && $order['owner_id'] == $owner_id) {
        // Aturan: Staff tidak bisa hapus pesanan buatan Owner
        $can_delete = false;
        if ($_SESSION['role'] == 'owner') {
            $can_delete = true;
        } else if ($_SESSION['role'] == 'staff' && ($order['created_by_role'] ?? 'staff') == 'staff') {
            $can_delete = true;
        }

        if (!$can_delete) {
            die("Akses Ditolak: Anda tidak memiliki izin untuk menghapus pesanan yang dibuat oleh Owner.");
        }

        // Izinkan hapus semua status jika punya akses
        if ($order['status'] == 'processing' || $order['status'] == 'Proses' || $order['status'] == 'completed') {
            // --- LOGIKA REFUND SALDO ---
            // Kembalikan saldo deposit jika pesanan yang dihapus menggunakan deposit
            if (isset($order['balance_used']) && $order['balance_used'] > 0) {
                $custId = $order['customer_id'];
                $all_custs = $db->read('customers');
                foreach ($all_custs as &$c) {
                    if ($c['id'] == $custId) {
                        $c['balance'] = (int)($c['balance'] ?? 0) + (int)$order['balance_used'];
                        break;
                    }
                }
                $db->write('customers', $all_custs);
            }

            // --- HAPUS PENCATATAN KEUANGAN (BUKU KAS) ---
            $all_tx = $db->read('transactions');
            $filtered_tx = [];
            foreach ($all_tx as $tx) {
                // Cek apakah deskripsi transaksi mencantumkan nomor nota pesanan ini
                if (strpos($tx['description'] ?? '', $order['order_number']) === false) {
                    $filtered_tx[] = $tx; // Simpan transaksi yang BUKAN milik pesanan ini
                }
            }
            if (count($all_tx) !== count($filtered_tx)) {
                $db->write('transactions', $filtered_tx); // Timpa dengan data bersih
            }

            $db->delete('orders', $id);
            redirect('pesanan', 'deleted');
        }
    }
    redirect('pesanan', 'unauthorized');
}

// --- PELANGGAN ---
if ($action == 'add_customer') {
    $db->insert('customers', [
        'name' => $_POST['name'],
        'phone' => $_POST['phone'],
        'address' => $_POST['address'],
        'balance' => 0,
        'created_by' => $_SESSION['user_id'],
        'owner_id' => $owner_id
    ]);
    redirect('pelanggan', 'added');
}

if ($action == 'delete_customer') {
    $id = $_GET['id'];
    $customers = $db->read('customers');
    foreach($customers as $c) {
        if($c['id'] == $id && ($c['owner_id'] == $owner_id || $_SESSION['role'] == 'owner')) {
            $db->delete('customers', $id);
            break;
        }
    }
    redirect('pelanggan', 'deleted');
}

// --- KARYAWAN ---
if ($action == 'add_staff') {
    if($_SESSION['role'] != 'owner') die("Akses Ditolak");
    $db->insert('staff', [
        'name' => $_POST['name'],
        'username' => $_POST['username'],
        'password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
        'role' => 'staff',
        'owner_id' => $owner_id
    ]);
    
    // Tambahkan juga ke tabel users utama agar bisa login
    $db->insert('users', [
        'username' => $_POST['username'],
        'password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
        'role' => 'staff',
        'name' => $_POST['name'],
        'owner_id' => $owner_id
    ]);
    redirect('karyawan', 'added');
}

if ($action == 'delete_staff') {
    if($_SESSION['role'] != 'owner') die("Akses Ditolak");
    $id = $_GET['id'];
    // Hapus dari staff
    $db->delete('staff', $id);
    // Hapus juga dari users (berdasarkan username atau ID jika sinkron)
    redirect('karyawan', 'deleted');
}

// --- LAYANAN ---
if ($action == 'add_service') {
    $db->insert('services', [
        'service_name' => $_POST['service_name'],
        'category' => $_POST['category'] ?? 'kiloan',
        'unit' => $_POST['unit'] ?? 'kg',
        'price_per_unit' => (int)($_POST['price'] ?? $_POST['price_per_unit'] ?? 0),
        'estimated_days' => (int)($_POST['days'] ?? $_POST['estimated_days'] ?? 2),
        'owner_id' => $owner_id
    ]);
    redirect('layanan', 'added');
}

if ($action == 'delete_service') {
    $db->delete('services', $_GET['id']);
    redirect('layanan', 'deleted');
}

// --- PENGATURAN ---
if ($action == 'update_settings') {
    $all_settings = $db->read('settings');
    $found_key = -1;
    
    foreach ($all_settings as $key => $s) {
        if (isset($s['owner_id']) && $s['owner_id'] == $owner_id) {
            $found_key = $key;
            break;
        }
    }

    $newData = [
        'business_name' => $_POST['business_name'],
        'phone' => $_POST['business_phone'] ?? '',
        'address' => $_POST['business_address'] ?? '',
        'receipt_note' => $_POST['receipt_note'] ?? '',
        'wa_template' => $_POST['wa_template'] ?? '',
        'owner_id' => $owner_id
    ];

    if ($found_key !== -1) {
        $all_settings[$found_key] = array_merge($all_settings[$found_key], $newData);
    } else {
        $newData['id'] = 'set_' . time();
        $all_settings[] = $newData;
    }

    $db->write('settings', $all_settings);
    redirect('pengaturan', 'success');
}

// --- OUTLET ---
if ($action == 'add_outlet') {
    $db->insert('outlets', [
        'name' => $_POST['name'],
        'address' => $_POST['address'],
        'owner_id' => $owner_id
    ]);
    redirect('pengaturan', 'outlet_added');
}

// --- PENGELUARAN ---
if ($action == 'add_expense') {
    $db->insert('transactions', [
        'type' => 'expense',
        'category' => $_POST['category'],
        'amount' => (int)$_POST['amount'],
        'description' => $_POST['description'],
        'transaction_date' => date('Y-m-d H:i:s'),
        'owner_id' => $owner_id
    ]);
    redirect('laporan', 'expense_added');
}

if ($action == 'edit_owner') {
    if($_SESSION['role'] != 'superadmin') die("Akses Ditolak");
    $user_id = $_POST['id'];
    $users = $db->read('users');
    foreach($users as &$u) {
        if($u['id'] == $user_id) {
            $u['name'] = $_POST['name'];
            $u['expired_at'] = $_POST['expired_at'];
            
            // Jika password baru diisi, maka update
            if (!empty($_POST['password'])) {
                $u['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
            }
        }
    }
    $db->write('users', $users);
    redirect('superadmin', 'updated');
}



if ($action == 'delete_owner') {
    if($_SESSION['role'] != 'superadmin') die("Akses Ditolak");
    $id = $_GET['id'];

    // List tabel yang harus dibersihkan berdasarkan owner_id
    $tables = ['users', 'orders', 'customers', 'services', 'parfums', 'transactions', 'settings', 'outlets', 'staff'];
    
    foreach ($tables as $table) {
        $data = $db->read($table);
        $filtered = array_filter($data, function($item) use ($id) {
            // Hapus jika owner_id cocok ATAU jika id user itu sendiri (untuk tabel users)
            if (isset($item['owner_id']) && $item['owner_id'] == $id) return false;
            if (isset($item['id']) && $item['id'] == $id) return false;
            return true;
        });
        $db->write($table, array_values($filtered));
    }

    redirect('superadmin', 'deleted');
}





if ($action == 'backup_data') {
    if($_SESSION['role'] != 'superadmin') die("Akses Ditolak");
    
    $tables = ['users', 'orders', 'customers', 'services', 'parfums', 'transactions', 'settings', 'outlets', 'staff'];
    $backupData = [];
    foreach ($tables as $t) {
        $backupData[$t] = $db->read($t);
    }
    
    $json = json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $filename = 'laundry_db_backup_' . date('Y-m-d_His') . '.json';
    
    if (ob_get_length()) ob_end_clean();
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    echo $json;
    exit;
}

if ($action == 'add_owner') {
    if($_SESSION['role'] != 'superadmin') die("Akses Ditolak");
    
    $new_id = 'owner_' . time();
    $db->insert('users', [
        'id' => $new_id,
        'username' => $_POST['username'],
        'password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
        'name' => $_POST['name'],
        'role' => 'owner',
        'owner_id' => $new_id, // Owner ID dirinya sendiri
        'expired_at' => $_POST['expired_at']
    ]);
    
    // Inisialisasi pengaturan default untuk owner baru
    $db->insert('settings', [
        'owner_id' => $new_id,
        'business_name' => $_POST['name'],
        'phone' => '-',
        'address' => '-',
        'receipt_note' => 'Terima kasih telah mencuci di tempat kami.',
        'wa_template' => "Halo *{customer_name}*,\n\nPesanan Anda *{order_number}* telah selesai. Silakan diambil ya!\n\nTerima kasih."
    ]);

    redirect('superadmin', 'added');
}

if ($action == 'change_password') {
    $user_id = $_SESSION['user_id'];
    $new_pass = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    
    $users = $db->read('users');
    foreach ($users as &$u) {
        if ($u['id'] == $user_id) {
            $u['password'] = $new_pass;
            break;
        }
    }
    $db->write('users', $users);
    
    // Redirect balik ke halaman sebelumnya
    $ref = $_SERVER['HTTP_REFERER'];
    $separator = (strpos($ref, '?') !== false ? '&' : '?');
    header("Location: " . $ref . $separator . "status=pw_changed");
    exit;
}
