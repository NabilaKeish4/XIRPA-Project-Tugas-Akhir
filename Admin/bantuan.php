<?php
session_start();
require_once '../Config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../Auth/login.php");
    exit;
}

$admin_nama = $_SESSION['nama_user'] ?? 'Admin';

// Ambil data toko
$data_toko = null;
$qToko = mysqli_query($conn, "SELECT * FROM pengaturan WHERE id = 1 LIMIT 1");
if ($qToko && mysqli_num_rows($qToko) > 0) $data_toko = mysqli_fetch_assoc($qToko);

$nama_toko   = $data_toko['nama_toko']   ?? 'PlantHub';
$no_telepon  = $data_toko['no_telepon']  ?? '081234567890';
$email_toko  = $data_toko['email_toko']  ?? 'admin@planthub.com';

// FAQ Data
$faqs = [
    [
        'q' => 'Bagaimana cara menambah produk baru?',
        'a' => 'Buka menu <b>Stok & Produk</b> dari sidebar, klik tombol <b>Tambah Produk</b> di kanan atas. Isi form lengkap (nama, kategori, harga, stok, gambar), lalu klik <b>Simpan</b>. Produk akan otomatis muncul di katalog pelanggan dan di kasir POS.'
    ],
    [
        'q' => 'Bagaimana cara memproses transaksi penjualan langsung (walk-in)?',
        'a' => 'Buka menu <b>Kasir (POS)</b>. Klik produk untuk menambah ke keranjang, atur qty, pilih metode pembayaran (Tunai/QRIS/Debit), masukkan jumlah bayar, lalu klik <b>Proses Bayar</b>. Struk akan muncul dan bisa langsung dicetak.'
    ],
    [
        'q' => 'Bagaimana cara melakukan restock dari supplier?',
        'a' => 'Buka menu <b>Pembelian (Restock)</b>. Pilih supplier, tambahkan item yang dibeli beserta qty dan harga beli, lalu klik <b>Simpan Pembelian</b>. Stok produk akan otomatis bertambah dan tercatat di laporan pembelian.'
    ],
    [
        'q' => 'Bagaimana cara melihat laporan penjualan?',
        'a' => 'Buka menu <b>Laporan</b>. Anda bisa memfilter rentang tanggal (hari ini, 7 hari, 30 hari, atau custom). Laporan menampilkan total omset, pengeluaran restock, estimasi laba, chart tren omzet, dan 5 produk terlaris.'
    ],
    [
        'q' => 'Bagaimana cara membalas chat pelanggan?',
        'a' => 'Buka menu <b>Konsultasi Chat</b>. Daftar pelanggan yang pernah chat muncul di kolom kiri. Klik salah satu pelanggan, lalu ketik balasan di form bawah dan klik <b>Kirim</b>. Anda juga bisa edit atau hapus pesan Anda sendiri.'
    ],
    [
        'q' => 'Bagaimana cara mengubah status pesanan pelanggan?',
        'a' => 'Buka menu <b>Riwayat Transaksi</b>, klik tombol <b>Detail</b> pada transaksi penjualan. Di bagian bawah, ubah status (Diproses → Dikirim → Selesai), lalu klik <b>Simpan Status</b>. Pelanggan akan melihat update status ini di halaman riwayat mereka.'
    ],
    [
        'q' => 'Bagaimana jika ada produk yang stoknya habis?',
        'a' => 'Sistem akan otomatis menandai produk sebagai <b>Stok Habis</b> (badge merah). Produk dengan stok 0 tidak akan bisa dibeli pelanggan di katalog. Segera lakukan restock via menu <b>Pembelian</b> untuk mengisi ulang stok.'
    ],
    [
        'q' => 'Bagaimana cara mengubah logo atau nama toko?',
        'a' => 'Buka menu <b>Pengaturan Toko</b> di bagian PENGATURAN. Ubah nama toko, cabang, kontak, alamat, dan upload logo/foto profil. Klik <b>Simpan Pengaturan</b>. Perubahan akan otomatis tampil di seluruh halaman termasuk struk nota pelanggan.'
    ],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($nama_toko) ?> - Pusat Bantuan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F9F8F6; color: #2D3748; } </style>
</head>
<body class="antialiased min-h-screen flex flex-col">

    <header class="sticky top-0 z-30 bg-white border-b border-stone-200/80 shadow-sm">
        <div class="px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <button onclick="toggleMobileSidebar()" class="lg:hidden p-2 rounded-lg text-stone-600 hover:bg-stone-100">
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>
                <a href="dashboard.php" class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-[#2E7D32] flex items-center justify-center text-white shadow-sm">
                        <i data-lucide="sprout" class="w-5 h-5"></i>
                    </div>
                    <span class="text-xl font-bold tracking-tight text-stone-800"><?= htmlspecialchars($nama_toko) ?></span>
                </a>
            </div>

            <form method="GET" action="stok.php" class="hidden md:flex flex-1 max-w-md relative">
                <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400"></i>
                <input type="text" name="search" placeholder="Cari tanaman, pot, media tanam..." class="w-full pl-10 pr-4 py-2 text-sm bg-stone-100/70 border border-transparent rounded-full focus:outline-none focus:bg-white focus:border-[#2E7D32] placeholder:text-stone-400">
            </form>

            <a href="dashboard.php" class="flex items-center gap-3 pl-1">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($admin_nama) ?>&background=2E7D32&color=fff" class="w-9 h-9 rounded-full ring-2 ring-[#2E7D32]/20">
                <div class="hidden sm:block text-left">
                    <p class="text-sm font-semibold text-stone-800 leading-tight"><?= htmlspecialchars($admin_nama) ?></p>
                    <p class="text-xs text-stone-500">Administrator</p>
                </div>
            </a>
        </div>
    </header>

    <div class="flex flex-1">
        <aside id="sidebar" class="w-64 bg-white border-r border-stone-200/80 hidden lg:flex flex-col justify-between shrink-0 p-4">
            <div class="space-y-6">
                <nav class="space-y-1">
                    <p class="px-3 text-[11px] font-bold text-stone-400 uppercase tracking-wider mb-3">MAIN MENU</p>
                    <?php
                    $menu = [
                        ['url' => 'dashboard.php',  'icon' => 'layout-grid',    'label' => 'Dashboard',        'active' => false],
                        ['url' => 'pos.php',        'icon' => 'shopping-bag',   'label' => 'Kasir (POS)',      'active' => false],
                        ['url' => 'restock.php',    'icon' => 'truck',          'label' => 'Pembelian',        'active' => false],
                        ['url' => 'stok.php',       'icon' => 'box',            'label' => 'Stok & Produk',    'active' => false],
                        ['url' => 'pelanggan.php',  'icon' => 'users',          'label' => 'Pelanggan',        'active' => false],
                        ['url' => 'chat.php',       'icon' => 'message-square', 'label' => 'Konsultasi Chat',  'active' => false],
                        ['url' => 'transaksi.php',  'icon' => 'receipt',        'label' => 'Riwayat Transaksi','active' => false],
                        ['url' => 'laporan.php',    'icon' => 'bar-chart-2',    'label' => 'Laporan',          'active' => false],
                    ];
                    foreach ($menu as $m):
                        $cls = $m['active'] ? 'text-[#1E7D32] bg-[#E8F5E9]' : 'text-stone-700 hover:bg-stone-100';
                    ?>
                        <a href="<?= $m['url'] ?>" class="flex items-center justify-between px-3 py-2.5 text-sm font-bold rounded-xl transition-colors <?= $cls ?>">
                            <div class="flex items-center gap-3">
                                <i data-lucide="<?= $m['icon'] ?>" class="w-5 h-5 <?= $m['active'] ? 'text-[#1E7D32]' : 'text-stone-500' ?>"></i>
                                <span><?= $m['label'] ?></span>
                            </div>
                            <?php if ($m['active']): ?><span class="w-2.5 h-2.5 rounded-full bg-[#1E7D32]"></span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                    <hr class="border-stone-100 my-3">
                    <p class="px-3 text-[11px] font-bold text-stone-400 uppercase tracking-wider mb-3">PENGATURAN</p>
                    <a href="pengaturan.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-bold rounded-xl text-stone-700 hover:bg-stone-100">
                        <i data-lucide="settings" class="w-5 h-5 text-stone-500"></i> Pengaturan Toko
                    </a>
                    <a href="bantuan.php" class="flex items-center justify-between px-3 py-2.5 text-sm font-bold rounded-xl text-[#1E7D32] bg-[#E8F5E9]">
                        <div class="flex items-center gap-3">
                            <i data-lucide="help-circle" class="w-5 h-5 text-[#1E7D32]"></i> Bantuan
                        </div>
                        <span class="w-2.5 h-2.5 rounded-full bg-[#1E7D32]"></span>
                    </a>
                </nav>
            </div>
            <div class="p-3 bg-stone-50/80 border border-stone-200/60 rounded-2xl flex items-center gap-3 mt-auto">
                <div class="w-10 h-10 rounded-xl bg-emerald-100/70 flex items-center justify-center text-[#2E7D32] shrink-0">
                    <i data-lucide="store" class="w-5 h-5"></i>
                </div>
                <div class="overflow-hidden">
                    <p class="text-sm font-bold text-stone-800 truncate leading-tight"><?= htmlspecialchars($nama_toko) ?> Admin</p>
                    <p class="text-[11px] font-medium text-stone-400 truncate mt-0.5">Sistem Online</p>
                </div>
            </div>
        </aside>

        <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-4xl mx-auto w-full space-y-6">

            <div>
                <h1 class="text-2xl font-bold text-stone-800 tracking-tight">Pusat Bantuan</h1>
                <p class="text-sm text-stone-500 mt-0.5">Panduan penggunaan sistem <?= htmlspecialchars($nama_toko) ?> dan solusi masalah umum.</p>
            </div>

            <!-- QUICK GUIDE CARDS -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <a href="pos.php" class="bg-white p-5 rounded-2xl border border-stone-200/80 shadow-sm hover:shadow-md hover:border-[#2E7D32]/30 transition group">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-[#2E7D32] flex items-center justify-center mb-3 group-hover:bg-[#2E7D32] group-hover:text-white transition">
                        <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                    </div>
                    <h3 class="text-sm font-bold text-stone-800">Transaksi Kasir</h3>
                    <p class="text-xs text-stone-500 mt-1 leading-relaxed">Proses penjualan langsung ke pelanggan walk-in.</p>
                </a>

                <a href="stok.php" class="bg-white p-5 rounded-2xl border border-stone-200/80 shadow-sm hover:shadow-md hover:border-[#2E7D32]/30 transition group">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-[#2E7D32] flex items-center justify-center mb-3 group-hover:bg-[#2E7D32] group-hover:text-white transition">
                        <i data-lucide="box" class="w-5 h-5"></i>
                    </div>
                    <h3 class="text-sm font-bold text-stone-800">Kelola Stok</h3>
                    <p class="text-xs text-stone-500 mt-1 leading-relaxed">Tambah, edit, dan atur stok produk tanaman.</p>
                </a>

                <a href="laporan.php" class="bg-white p-5 rounded-2xl border border-stone-200/80 shadow-sm hover:shadow-md hover:border-[#2E7D32]/30 transition group">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-[#2E7D32] flex items-center justify-center mb-3 group-hover:bg-[#2E7D32] group-hover:text-white transition">
                        <i data-lucide="bar-chart-2" class="w-5 h-5"></i>
                    </div>
                    <h3 class="text-sm font-bold text-stone-800">Laporan Penjualan</h3>
                    <p class="text-xs text-stone-500 mt-1 leading-relaxed">Pantau omset, laba, dan performa produk.</p>
                </a>
            </div>

            <!-- FAQ -->
            <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-6">
                <div class="flex items-center justify-between border-b border-stone-100 pb-3 mb-4">
                    <h2 class="text-base font-bold text-stone-800">Pertanyaan Sering Diajukan</h2>
                    <div class="relative">
                        <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-stone-400"></i>
                        <input type="text" id="faqSearch" onkeyup="filterFAQ()" placeholder="Cari pertanyaan..." class="pl-9 pr-3 py-1.5 text-xs bg-stone-50 border border-stone-200 rounded-lg focus:outline-none focus:border-[#2E7D32] w-44">
                    </div>
                </div>

                <div id="faqList" class="space-y-2">
                    <?php foreach ($faqs as $i => $faq): ?>
                        <div class="faq-item border border-stone-200/80 rounded-xl overflow-hidden">
                            <button onclick="toggleFAQ(this)" class="w-full flex items-center justify-between p-4 text-left hover:bg-stone-50 transition">
                                <span class="faq-question text-sm font-semibold text-stone-800 pr-4"><?= htmlspecialchars($faq['q']) ?></span>
                                <i data-lucide="chevron-down" class="w-4 h-4 text-stone-400 shrink-0 transition-transform duration-200"></i>
                            </button>
                            <div class="faq-answer hidden p-4 pt-0 text-xs text-stone-600 leading-relaxed bg-stone-50/50 border-t border-stone-100">
                                <?= $faq['a'] ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- KONTAK SUPPORT -->
            <div class="bg-[#2E7D32] text-white rounded-2xl p-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="text-center sm:text-left">
                    <h3 class="text-base font-bold">Butuh bantuan tambahan?</h3>
                    <p class="text-xs text-emerald-100 mt-1">Hubungi tim teknis PlantHub untuk kendala sistem.</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a href="mailto:<?= htmlspecialchars($email_toko) ?>" class="inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 border border-white/20 text-white px-4 py-2.5 rounded-xl text-xs font-semibold transition">
                        <i data-lucide="mail" class="w-4 h-4"></i> Email
                    </a>
                    <a href="https://wa.me/<?= htmlspecialchars(preg_replace('/[^0-9]/', '', $no_telepon)) ?>" target="_blank" class="inline-flex items-center gap-2 bg-white text-[#2E7D32] hover:bg-stone-100 px-4 py-2.5 rounded-xl text-xs font-bold transition">
                        <i data-lucide="message-square" class="w-4 h-4"></i> WhatsApp
                    </a>
                </div>
            </div>

        </main>
    </div>

    <script>
        lucide.createIcons();

        function toggleMobileSidebar() {
            const s = document.getElementById('sidebar');
            s?.classList.toggle('hidden');
            s?.classList.toggle('fixed');
            s?.classList.toggle('inset-y-0');
            s?.classList.toggle('left-0');
            s?.classList.toggle('z-40');
        }

        function toggleFAQ(btn) {
            const item = btn.parentElement;
            const answer = item.querySelector('.faq-answer');
            const icon = btn.querySelector('i');

            // Tutup yang lain (opsional — accordion exclusive)
            document.querySelectorAll('.faq-item').forEach(el => {
                if (el !== item) {
                    el.querySelector('.faq-answer')?.classList.add('hidden');
                    el.querySelector('button i')?.classList.remove('rotate-180');
                }
            });

            answer.classList.toggle('hidden');
            icon.classList.toggle('rotate-180');
        }

        function filterFAQ() {
            const input = document.getElementById('faqSearch').value.toLowerCase();
            document.querySelectorAll('.faq-item').forEach(item => {
                const text = item.innerText.toLowerCase();
                item.style.display = text.includes(input) ? '' : 'none';
            });
        }
    </script>
</body>
</html>