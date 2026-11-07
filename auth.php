<?php
// auth.php - Authentication Logic (Sistem JSON)
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $db = new JsonDB();
    $users = $db->read('users');

    $foundUser = null;
    foreach ($users as $u) {
        if ($u['username'] == $username) {
            $foundUser = $u;
            break;
        }
    }

    if ($foundUser && password_verify($password, $foundUser['password'])) {
        // --- CEK MASA BERLAKU (EXPIRED) ---
        if ($foundUser['role'] != 'superadmin') {
            $owner_id_to_check = ($foundUser['role'] == 'owner') ? $foundUser['id'] : $foundUser['owner_id'];
            
            // Cari data Owner untuk cek expired_at
            $owner_record = null;
            foreach ($users as $u) {
                if ($u['id'] == $owner_id_to_check && $u['role'] == 'owner') {
                    $owner_record = $u;
                    break;
                }
            }

            if ($owner_record && isset($owner_record['expired_at'])) {
                if (date('Y-m-d') > $owner_record['expired_at']) {
                    header("Location: login.php?error=expired");
                    exit;
                }
            }
        }

        // Jika "Ingat Saya" dicentang, perpanjang umur cookie sesi menjadi 30 hari
        if (isset($_POST['remember'])) {
            $lifetime = 3600 * 24 * 30; // 30 Hari
            session_set_cookie_params($lifetime);
        }

        // KEAMANAN KRITIS: Bersihkan semua sesi lama secara total
        $_SESSION = array(); 
        session_destroy();
        session_start();
        session_regenerate_id(true);

        $_SESSION['user_id'] = $foundUser['id'];
        $_SESSION['username'] = $foundUser['username'];
        $_SESSION['name'] = $foundUser['name'] ?? $foundUser['username'];
        $_SESSION['role'] = $foundUser['role'];
        
        // Logika Owner ID yang sangat ketat
        if ($foundUser['role'] == 'superadmin') {
            $_SESSION['owner_id'] = null;
        } else {
            $_SESSION['owner_id'] = $foundUser['owner_id'] ?? $foundUser['id'];
        }
        $_SESSION['last_activity'] = time();
        
        session_write_close(); 
        header("Location: index.php?page=dashboard&login=success&login_success=true");
        exit;
    } else {
        header("Location: login.php?error=invalid");
        exit;
    }
} else {
    header("Location: login.php");
    exit;
}
