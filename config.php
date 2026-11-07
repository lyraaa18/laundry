<?php
// config.php - MySQL Database System (Production Multi-Tenant Edition)

class MysqlDB {
    private static $pdo = null;
    private $db;

    public function __construct() {
        if (self::$pdo === null) {
            $host   = getenv('DB_HOST') ?: 'localhost';
            $port   = getenv('DB_PORT') ?: '3306';
            $dbname = getenv('DB_NAME') ?: 'laundry_db';
            $user   = getenv('DB_USER') ?: 'root';
            $pass   = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            try {
                self::$pdo = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                die("Koneksi Database Gagal: " . $e->getMessage());
            }
        }
        $this->db = self::$pdo;
    }

    public function getConnection() {
        return $this->db;
    }

    /**
     * Membaca semua data dari tabel tertentu
     */
    public function read($table) {
        $allowed = ['users', 'orders', 'customers', 'services', 'parfums', 'transactions', 'settings', 'outlets', 'staff'];
        if (!in_array($table, $allowed)) return [];

        try {
            $stmt = $this->db->query("SELECT * FROM `{$table}`");
            $rows = $stmt->fetchAll();
            return array_map([$this, 'decodeJsonFields'], $rows);
        } catch (PDOException $e) {
            error_log("Database read error on table {$table}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Membaca data yang terfilter berdasarkan owner_id (Multi-Tenancy)
     */
    public function readFiltered($table) {
        $allowed = ['users', 'orders', 'customers', 'services', 'parfums', 'transactions', 'settings', 'outlets', 'staff'];
        if (!in_array($table, $allowed)) return [];

        $role = $_SESSION['role'] ?? 'staff';
        $owner_id = $_SESSION['owner_id'] ?? null;

        try {
            if ($role === 'superadmin') {
                $stmt = $this->db->query("SELECT * FROM `{$table}`");
                $rows = $stmt->fetchAll();
            } else {
                if ($table === 'users') {
                    $stmt = $this->db->prepare("SELECT * FROM `users` WHERE `owner_id` = :owner_id OR `id` = :owner_id");
                    $stmt->execute(['owner_id' => $owner_id]);
                } else {
                    $stmt = $this->db->prepare("SELECT * FROM `{$table}` WHERE `owner_id` = :owner_id");
                    $stmt->execute(['owner_id' => $owner_id]);
                }
                $rows = $stmt->fetchAll();
            }
            return array_map([$this, 'decodeJsonFields'], $rows);
        } catch (PDOException $e) {
            error_log("Database readFiltered error on table {$table}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Insert satu data baru ke tabel
     */
    public function insert($table, $newData) {
        $allowed = ['users', 'orders', 'customers', 'services', 'parfums', 'transactions', 'settings', 'outlets', 'staff'];
        if (!in_array($table, $allowed)) return false;

        if (!isset($newData["id"])) {
            $newData["id"] = (string)(time() . rand(1000, 9999));
        }

        $prepared = $this->prepareDataForDb($table, $newData);
        $columns = array_keys($prepared);
        $colSql = implode(', ', array_map(function($c) { return "`$c`"; }, $columns));
        $placeholders = implode(', ', array_map(function($c) { return ":$c"; }, $columns));

        $sql = "INSERT INTO `{$table}` ({$colSql}) VALUES ({$placeholders})";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($prepared);
            return $newData['id'];
        } catch (PDOException $e) {
            error_log("Database insert error on table {$table}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update data berdasarkan ID
     */
    public function update($table, $id, $updateData) {
        $allowed = ['users', 'orders', 'customers', 'services', 'parfums', 'transactions', 'settings', 'outlets', 'staff'];
        if (!in_array($table, $allowed)) return false;

        $prepared = $this->prepareDataForDb($table, $updateData);
        $setClauses = [];
        foreach ($prepared as $col => $val) {
            if ($col === 'id') continue;
            $setClauses[] = "`{$col}` = :{$col}";
        }

        if (empty($setClauses)) return true;

        $prepared['__target_id'] = $id;
        $sql = "UPDATE `{$table}` SET " . implode(', ', $setClauses) . " WHERE `id` = :__target_id";
        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($prepared);
        } catch (PDOException $e) {
            error_log("Database update error on table {$table}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Hapus data berdasarkan ID
     */
    public function delete($table, $id) {
        $allowed = ['users', 'orders', 'customers', 'services', 'parfums', 'transactions', 'settings', 'outlets', 'staff'];
        if (!in_array($table, $allowed)) return false;

        try {
            $stmt = $this->db->prepare("DELETE FROM `{$table}` WHERE `id` = :id");
            return $stmt->execute(['id' => $id]);
        } catch (PDOException $e) {
            error_log("Database delete error on table {$table}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Bulk write / sync untuk kompabilitas penuh dengan kode yang memakai $db->write($table, $data)
     */
    public function write($table, $data) {
        $allowed = ['users', 'orders', 'customers', 'services', 'parfums', 'transactions', 'settings', 'outlets', 'staff'];
        if (!in_array($table, $allowed)) return false;

        if (!is_array($data)) return false;

        $activeIds = [];
        foreach ($data as $row) {
            if (isset($row['id'])) {
                $activeIds[] = (string)$row['id'];
                $this->upsert($table, $row);
            }
        }

        $role = $_SESSION['role'] ?? 'staff';
        $owner_id = $_SESSION['owner_id'] ?? null;

        if (!empty($activeIds)) {
            $inClause = implode(',', array_fill(0, count($activeIds), '?'));
            if ($role === 'superadmin') {
                $stmt = $this->db->prepare("DELETE FROM `{$table}` WHERE `id` NOT IN ({$inClause})");
                $stmt->execute($activeIds);
            } else if ($owner_id) {
                $stmt = $this->db->prepare("DELETE FROM `{$table}` WHERE `owner_id` = ? AND `id` NOT IN ({$inClause})");
                $stmt->execute(array_merge([$owner_id], $activeIds));
            }
        } else {
            if ($role === 'superadmin') {
                $this->db->exec("DELETE FROM `{$table}`");
            } else if ($owner_id) {
                $stmt = $this->db->prepare("DELETE FROM `{$table}` WHERE `owner_id` = ?");
                $stmt->execute([$owner_id]);
            }
        }

        return true;
    }

    /**
     * Upsert (Insert or Update on Duplicate Key)
     */
    private function upsert($table, $row) {
        $prepared = $this->prepareDataForDb($table, $row);
        if (empty($prepared)) return false;

        $columns = array_keys($prepared);
        $colSql = implode(', ', array_map(function($c) { return "`$c`"; }, $columns));
        $placeholders = implode(', ', array_map(function($c) { return ":$c"; }, $columns));
        
        $updateParts = [];
        foreach ($columns as $c) {
            if ($c === 'id') continue;
            $updateParts[] = "`$c` = VALUES(`$c`)";
        }
        $updateSql = !empty($updateParts) ? "ON DUPLICATE KEY UPDATE " . implode(', ', $updateParts) : "ON DUPLICATE KEY UPDATE `id`=`id`";

        $sql = "INSERT INTO `{$table}` ({$colSql}) VALUES ({$placeholders}) {$updateSql}";
        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($prepared);
        } catch (PDOException $e) {
            error_log("Database upsert error on table {$table}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Helper: membersihkan dan menyesuaikan data sebelum disimpan ke MySQL
     */
    private function prepareDataForDb($table, $data) {
        $clean = [];
        
        static $tableColumnsCache = [];
        if (!isset($tableColumnsCache[$table])) {
            try {
                $stmt = $this->db->query("SHOW COLUMNS FROM `{$table}`");
                $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $tableColumnsCache[$table] = $cols;
            } catch (Exception $e) {
                $tableColumnsCache[$table] = array_keys($data);
            }
        }
        $validColumns = $tableColumnsCache[$table];

        foreach ($data as $key => $val) {
            if (!in_array($key, $validColumns)) continue;

            if (is_array($val) || is_object($val)) {
                $clean[$key] = json_encode($val);
            } else {
                $clean[$key] = $val;
            }
        }
        return $clean;
    }

    /**
     * Helper: mengembalikan field JSON string menjadi array saat dibaca
     */
    private function decodeJsonFields($row) {
        if (!is_array($row)) return $row;

        $jsonFields = ['services', 'items_detail'];
        foreach ($jsonFields as $field) {
            if (isset($row[$field]) && is_string($row[$field])) {
                $trimmed = trim($row[$field]);
                if (str_starts_with($trimmed, '[') || str_starts_with($trimmed, '{')) {
                    $decoded = json_decode($trimmed, true);
                    if ($decoded !== null) {
                        $row[$field] = $decoded;
                    }
                }
            }
        }
        return $row;
    }
}

// Alias untuk menjaga backward compatibility penuh
class JsonDB extends MysqlDB {}
class Database extends MysqlDB {}
?>