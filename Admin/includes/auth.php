<?php
// includes/auth.php

// DAFTAR ROLE YANG DIIZINKAN
$AKSES_ROLE = [
    'admin'      => ['*'], // Bintang artinya bebas akses semua halaman
    'kasir'      => ['dashboard', 'pos', 'transaksi', 'pelanggan'],
    'gudang'     => ['dashboard', 'stok', 'kategori', 'supplier', 'restock'],
    'purchasing' => ['dashboard', 'restock', 'supplier', 'stok'],
    'penjualan'  => ['dashboard', 'transaksi', 'laporan', 'pelanggan', 'chat'],
    'manajer'    => ['dashboard', 'laporan', 'transaksi', 'stok', 'supplier', 'restock', 'pelanggan', 'chat', 'pos'],
];

// FUNGSI MENAMPILKAN PILIHAN DI FORM TAMBAH STAF
function daftarRole(): array {
    return ['admin', 'kasir', 'gudang', 'purchasing', 'penjualan', 'manajer'];
}

// FUNGSI MENERJEMAHKAN DATABASE KE TEKS (Untuk Tampilan Tabel)
function labelRole(string $role): string {
    return [
        'admin'      => 'Administrator',
        'kasir'      => 'Kasir Toko',
        'gudang'     => 'Staff Gudang',
        'purchasing' => 'Bagian Pengadaan',
        'penjualan'  => 'Staff Penjualan',
        'manajer'    => 'Manajer Toko',
        'customer'   => 'Pelanggan',
    ][$role] ?? ucfirst($role);
}

// FUNGSI CEK IZIN
function bolehAkses(string $role, string $halaman): bool {
    global $AKSES_ROLE;
    if (!isset($AKSES_ROLE[$role])) return false;
    $daftar = $AKSES_ROLE[$role];
    if (in_array('*', $daftar, true)) return true;
    return in_array($halaman, $daftar, true);
}

function punyaAkses(string $halaman): bool {
    $role = $_SESSION['role'] ?? '';
    return bolehAkses($role, $halaman);
}

// FUNGSI BLOKIR HALAMAN
function wajibAkses(string $halaman): void {
    $role = $_SESSION['role'] ?? '';
    if (!bolehAkses($role, $halaman)) {
        http_response_code(403);
        $roleLabel = htmlspecialchars(labelRole($role));
        $hal = htmlspecialchars($halaman);
        echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">
              <title>Akses Ditolak</title>
              <script src="https://cdn.tailwindcss.com"></script>
              <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
              <style>body{font-family:\'Plus Jakarta Sans\',sans-serif;background:#F9F8F6;}</style>
              </head><body class="flex items-center justify-center h-screen">
              <div class="bg-white p-10 rounded-2xl shadow-lg text-center max-w-md border border-stone-200">
                <div class="w-16 h-16 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center mx-auto mb-4">
                  <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
                <h1 class="text-xl font-bold text-stone-800 mb-2">Akses Ditolak</h1>
                <p class="text-sm text-stone-500 mb-6">Role <b>' . $roleLabel . '</b> tidak punya izin membuka halaman <b>' . $hal . '</b>. Hubungi Admin jika Anda butuh akses.</p>
                <a href="dashboard.php" class="inline-block bg-[#2E7D32] hover:bg-emerald-800 text-white px-6 py-2.5 rounded-xl font-bold text-sm transition">Kembali ke Dashboard</a>
              </div></body></html>';
        exit;
    }
}