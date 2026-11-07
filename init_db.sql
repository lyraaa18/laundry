-- Database Skema MySQL untuk Sistem Laundry SaaS Pro
CREATE DATABASE IF NOT EXISTS laundry_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE laundry_db;

-- 1. Tabel Users
CREATE TABLE IF NOT EXISTS users (
    id VARCHAR(100) PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(150) NOT NULL,
    role VARCHAR(50) DEFAULT 'staff',
    owner_id VARCHAR(100) NULL,
    expired_at DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Tabel Customers
CREATE TABLE IF NOT EXISTS customers (
    id VARCHAR(100) PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(50) NULL,
    address TEXT NULL,
    balance DECIMAL(15,2) DEFAULT 0.00,
    created_by VARCHAR(100) NULL,
    owner_id VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabel Services
CREATE TABLE IF NOT EXISTS services (
    id VARCHAR(100) PRIMARY KEY,
    service_name VARCHAR(150) NOT NULL,
    category VARCHAR(50) DEFAULT 'kiloan',
    price_per_unit DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    unit VARCHAR(20) DEFAULT 'kg',
    estimated_days INT DEFAULT 2,
    owner_id VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Tabel Orders
CREATE TABLE IF NOT EXISTS orders (
    id VARCHAR(100) PRIMARY KEY,
    order_number VARCHAR(50) NOT NULL,
    outlet_id VARCHAR(100) NULL,
    customer_id VARCHAR(100) NULL,
    services LONGTEXT NULL,
    items_detail LONGTEXT NULL,
    parfum VARCHAR(100) NULL,
    total_weight DECIMAL(10,2) DEFAULT 0.00,
    staff_ironing VARCHAR(100) NULL,
    grand_total DECIMAL(15,2) DEFAULT 0.00,
    paid_amount DECIMAL(15,2) DEFAULT 0.00,
    balance_used DECIMAL(15,2) DEFAULT 0.00,
    status VARCHAR(50) DEFAULT 'processing',
    order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    est_finish_date DATETIME NULL,
    created_by_role VARCHAR(50) DEFAULT 'staff',
    payment_method VARCHAR(50) DEFAULT 'Cash',
    owner_id VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Tabel Parfums
CREATE TABLE IF NOT EXISTS parfums (
    id VARCHAR(100) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    owner_id VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Tabel Outlets
CREATE TABLE IF NOT EXISTS outlets (
    id VARCHAR(100) PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    address TEXT NULL,
    phone VARCHAR(50) NULL,
    owner_id VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Tabel Staff
CREATE TABLE IF NOT EXISTS staff (
    id VARCHAR(100) PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    username VARCHAR(100) NULL,
    password VARCHAR(255) NULL,
    role VARCHAR(50) DEFAULT 'staff',
    owner_id VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Tabel Transactions
CREATE TABLE IF NOT EXISTS transactions (
    id VARCHAR(100) PRIMARY KEY,
    type VARCHAR(50) NOT NULL,
    category VARCHAR(100) NULL,
    amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    description TEXT NULL,
    transaction_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    owner_id VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Tabel Settings
CREATE TABLE IF NOT EXISTS settings (
    id VARCHAR(100) PRIMARY KEY,
    business_name VARCHAR(150) NULL,
    address TEXT NULL,
    phone VARCHAR(50) NULL,
    receipt_note TEXT NULL,
    wa_template TEXT NULL,
    owner_id VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default Initial Data: Superadmin (password: admin123)
INSERT INTO users (id, username, password, name, role, owner_id) 
VALUES ('super_root', 'studiojopay', '$2y$12$uOc8r8ClLqCWV5xOL1o6suq4UbiBNhkqqK0nAIQtrU0nlQ9zNDuXq', 'Admin Sistem SaaS', 'superadmin', 'system')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Default Initial Data: Owner Toko Pertama (TKH Laundry)
INSERT INTO users (id, username, password, name, role, owner_id, expired_at)
VALUES ('owner_1', 'owner1', '$2y$10$4DZrUNxIWRA3r3vylmorQeE08ZYBEpF7qmNtsK2Uk25y/0vyOAaYS', 'TKH Laundry', 'owner', 'owner_1', '2030-12-31')
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO settings (id, business_name, address, phone, receipt_note, wa_template, owner_id)
VALUES ('set_owner_1', 'TKH Laundry Pro', 'Jl. Kaliurang KM 5', '081234567890', 'Terima kasih telah mempercayakan laundry Anda kepada kami.', 'Halo *{customer_name}*,\n\nPesanan Anda *{order_number}* telah selesai. Silakan diambil ya!\n\nTerima kasih.', 'owner_1')
ON DUPLICATE KEY UPDATE business_name=VALUES(business_name);

INSERT INTO parfums (id, name, owner_id) VALUES 
('prm_1', 'Akasia', 'owner_1'),
('prm_2', 'Sakura', 'owner_1'),
('prm_3', 'Ocean Fresh', 'owner_1'),
('prm_4', 'Snappy', 'owner_1')
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO services (id, service_name, category, price_per_unit, unit, estimated_days, owner_id) VALUES
('S1', 'Cuci Setrika Regular', 'kiloan', 7000, 'kg', 2, 'owner_1'),
('S2', 'Cuci Saja Regular', 'kiloan', 5000, 'kg', 1, 'owner_1'),
('S3', 'Setrika Saja Regular', 'kiloan', 4000, 'kg', 1, 'owner_1'),
('S4', 'Cuci Setrika Express', 'kiloan', 12000, 'kg', 1, 'owner_1'),
('S5', 'Cuci Bedcover', 'satuan', 15000, 'pcs', 3, 'owner_1')
ON DUPLICATE KEY UPDATE service_name=VALUES(service_name);
