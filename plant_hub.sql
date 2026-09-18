-- =====================================================
-- DATABASE FINAL: plant_hub
-- Sistem Manajemen Toko Tanaman Hias - PlantHub
-- =====================================================

DROP DATABASE IF EXISTS plant_hub;
CREATE DATABASE plant_hub CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE plant_hub;

-- =====================================================
-- 1. TABEL USERS (Admin & Pelanggan)
-- =====================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_lengkap VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','customer') NOT NULL DEFAULT 'customer',
    no_hp VARCHAR(20) NULL,
    alamat TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- 2. TABEL KATEGORI
-- =====================================================
CREATE TABLE kategori (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_kategori VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- =====================================================
-- 3. TABEL PRODUK
-- =====================================================
CREATE TABLE produk (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kategori_id INT NULL,
    nama_tanaman VARCHAR(100) NOT NULL,
    deskripsi TEXT NULL,
    cara_perawatan TEXT NULL,
    harga_jual DECIMAL(12,2) NOT NULL DEFAULT 0,
    harga_beli DECIMAL(12,2) NOT NULL DEFAULT 0,
    stok INT NOT NULL DEFAULT 0,
    stok_minimal INT NOT NULL DEFAULT 5,
    gambar VARCHAR(255) DEFAULT 'default.jpg',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kategori_id) REFERENCES kategori(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- 4. TABEL SUPPLIER
-- =====================================================
CREATE TABLE supplier (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_supplier VARCHAR(100) NOT NULL,
    kontak VARCHAR(50) NULL,
    alamat TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- 5. TABEL TRANSAKSI (Penjualan & Pembelian)
-- =====================================================
CREATE TABLE transaksi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_transaksi VARCHAR(50) NOT NULL UNIQUE,
    user_id INT NULL,                                -- NULL jika walk-in
    supplier_id INT NULL,                            -- Hanya untuk pembelian
    jenis_transaksi ENUM('penjualan','pembelian') NOT NULL,
    nama_penerima VARCHAR(100) NULL,
    alamat TEXT NULL,
    telepon VARCHAR(20) NULL,
    total_harga DECIMAL(12,2) NOT NULL DEFAULT 0,
    metode_pembayaran ENUM('tunai','qris','debit','transfer','ewallet','cod') DEFAULT 'tunai',
    status ENUM('Diproses','Dikirim','Selesai','Batal') DEFAULT 'Selesai',
    catatan TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (supplier_id) REFERENCES supplier(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- 6. TABEL TRANSAKSI_DETAIL
-- =====================================================
CREATE TABLE transaksi_detail (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaksi_id INT NOT NULL,
    produk_id INT NOT NULL,
    jumlah INT NOT NULL DEFAULT 1,
    harga_satuan DECIMAL(12,2) NOT NULL DEFAULT 0,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (transaksi_id) REFERENCES transaksi(id) ON DELETE CASCADE,
    FOREIGN KEY (produk_id) REFERENCES produk(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 7. TABEL CHATS (Pengganti `messages`)
-- =====================================================
CREATE TABLE chats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,                            -- Pelanggan yang chat
    sender_type ENUM('admin','customer') NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 8. TABEL PENGATURAN (untuk halaman pengaturan admin)
-- =====================================================
CREATE TABLE pengaturan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_toko VARCHAR(100) NOT NULL DEFAULT 'PlantHub',
    nama_cabang VARCHAR(100) NOT NULL DEFAULT 'Cabang Utama',
    no_telepon VARCHAR(20) NULL,
    email_toko VARCHAR(100) NULL,
    alamat_toko TEXT NULL,
    catatan_nota TEXT NULL,
    logo_toko VARCHAR(255) DEFAULT 'default_logo.png',
    foto_profil VARCHAR(255) DEFAULT 'default_avatar.jpg',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- DATA SAMPLE
-- =====================================================

-- Admin default (password: admin123)
INSERT INTO users (nama_lengkap, username, email, password, role) VALUES
('Nabila Admin', 'admin', 'admin@planthub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Pelanggan contoh (password: pelanggan123)
INSERT INTO users (nama_lengkap, username, email, password, role, no_hp, alamat) VALUES
('Budi Santoso', 'budi', 'budi@mail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', '08123456789', 'Jl. Merdeka No. 10, Malang');

-- Kategori
INSERT INTO kategori (nama_kategori) VALUES
('Indoor'), ('Outdoor'), ('Pot'), ('Media Tanam');

-- Produk
INSERT INTO produk (kategori_id, nama_tanaman, deskripsi, cara_perawatan, harga_jual, harga_beli, stok, stok_minimal, gambar) VALUES
(1, 'Monstera Deliciosa', 'Tanaman hias daun tropis yang populer.', 'Siram 2 hari sekali, cahaya tidak langsung.', 125000, 75000, 8, 5, 'monstera.jpg'),
(1, 'Snake Plant', 'Tanaman tahan banting, cocok untuk pemula.', 'Siram seminggu sekali.', 45000, 25000, 15, 5, 'snake.jpg'),
(1, 'Fiddle Leaf Fig', 'Daun besar unik, sangat estetik.', 'Cahaya terang, siram saat tanah kering.', 210000, 150000, 3, 5, 'fiddle.jpg'),
(1, 'Calathea Orbifolia', 'Daun bergaris indah, butuh kelembapan.', 'Semprot air setiap hari.', 85000, 50000, 0, 5, 'calathea.jpg'),
(3, 'Pot Terakota 20cm', 'Pot tanah liat klasik.', '-', 35000, 20000, 24, 10, 'pot.jpg'),
(4, 'Media Tanam Premium 5kg', 'Campuran tanah, sekam, pupuk.', '-', 28000, 15000, 40, 15, 'media.jpg');

-- Supplier
INSERT INTO supplier (nama_supplier, kontak, alamat) VALUES
('CV. Flora Utama', '081234567890', 'Batu, Malang'),
('Tani Makmur Jaya', '082345678901', 'Surabaya');

-- Pengaturan awal
INSERT INTO pengaturan (id, nama_toko, nama_cabang, no_telepon, email_toko, alamat_toko, catatan_nota)
VALUES (1, 'PlantHub', 'Cabang Batu Central', '081234567890', 'admin@planthub.com', 'Jl. Kebon Tanaman No. 123, Batu', 'Terima kasih telah berbelanja di PlantHub!');