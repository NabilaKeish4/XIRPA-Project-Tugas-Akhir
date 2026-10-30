<?php
require_once __DIR__ . '/includes/init.php';

// =========================================================
// FEEDBACK FAQ (disimpan di database, per admin)
// =========================================================
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS bantuan_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    faq_id VARCHAR(100) NOT NULL,
    vote ENUM('up','down') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_faq (user_id, faq_id)
)");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'feedback') {
    header('Content-Type: application/json');
    if (!csrf_ok()) { echo json_encode(['success' => false, 'message' => 'Sesi tidak valid.']); exit; }
    $faq  = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['faq_id'] ?? '');
    $vote = $_POST['vote'] ?? '';
    if ($faq === '') { echo json_encode(['success' => false, 'message' => 'FAQ tidak valid.']); exit; }
    if ($vote === 'none') {
        mysqli_query($conn, "DELETE FROM bantuan_feedback WHERE user_id = $admin_id AND faq_id = '$faq'");
    } elseif ($vote === 'up' || $vote === 'down') {
        mysqli_query($conn, "INSERT INTO bantuan_feedback (user_id, faq_id, vote) VALUES ($admin_id, '$faq', '$vote')
                             ON DUPLICATE KEY UPDATE vote = '$vote'");
    } else {
        echo json_encode(['success' => false, 'message' => 'Vote tidak valid.']); exit;
    }
    echo json_encode(['success' => true]);
    exit;
}

$votesMine = [];
$qV = mysqli_query($conn, "SELECT faq_id, vote FROM bantuan_feedback WHERE user_id = $admin_id");
if ($qV) while ($r = mysqli_fetch_assoc($qV)) $votesMine[$r['faq_id']] = $r['vote'];
$totUp = $totDown = 0;
$qT = mysqli_query($conn, "SELECT vote, COUNT(*) AS n FROM bantuan_feedback GROUP BY vote");
if ($qT) while ($r = mysqli_fetch_assoc($qT)) { if ($r['vote'] === 'up') $totUp = (int)$r['n']; else $totDown = (int)$r['n']; }

// Data toko ($nama_toko, $no_telepon, $email_toko) sudah dimuat oleh includes/init.php
$no_telepon = $no_telepon ?: '081234567890';
$email_toko = $email_toko ?: 'admin@planthub.com';

// FAQ Data (dengan ID unik untuk feedback)
$faqs = [
    [
        'id' => 'faq_tambah_produk',
        'q' => 'Bagaimana cara menambah produk baru?',
        'a' => 'Buka menu <b>Stok & Produk</b> dari sidebar, klik tombol <b>Tambah Produk</b> di kanan atas. Isi form lengkap (nama, kategori, harga, stok, gambar), lalu klik <b>Simpan</b>. Produk akan otomatis muncul di katalog pelanggan dan di kasir POS.'
    ],
    [
        'id' => 'faq_pos',
        'q' => 'Bagaimana cara memproses transaksi penjualan langsung (walk-in)?',
        'a' => 'Buka menu <b>Kasir (POS)</b>. Klik produk untuk menambah ke keranjang, atur qty, pilih metode pembayaran (Tunai/QRIS/Debit), masukkan jumlah bayar, lalu klik <b>Proses Bayar</b>. Struk akan muncul dan bisa langsung dicetak.'
    ],
    [
        'id' => 'faq_restock',
        'q' => 'Bagaimana cara melakukan restock dari supplier?',
        'a' => 'Buka menu <b>Pembelian (Restock)</b>. Pilih supplier, tambahkan item yang dibeli beserta qty dan harga beli, lalu klik <b>Simpan Pembelian</b>. Stok produk akan otomatis bertambah dan tercatat di laporan pembelian.'
    ],
    [
        'id' => 'faq_laporan',
        'q' => 'Bagaimana cara melihat laporan penjualan?',
        'a' => 'Buka menu <b>Laporan</b>. Anda bisa memfilter rentang tanggal (hari ini, 7 hari, 30 hari, atau custom). Laporan menampilkan total omset, pengeluaran restock, estimasi laba, chart tren omzet, dan 5 produk terlaris.'
    ],
    [
        'id' => 'faq_chat',
        'q' => 'Bagaimana cara membalas chat pelanggan?',
        'a' => 'Buka menu <b>Konsultasi Chat</b>. Daftar pelanggan yang pernah chat muncul di kolom kiri. Klik salah satu pelanggan, lalu ketik balasan di form bawah dan klik <b>Kirim</b>. Anda juga bisa edit atau hapus pesan Anda sendiri.'
    ],
    [
        'id' => 'faq_status_pesanan',
        'q' => 'Bagaimana cara mengubah status pesanan pelanggan?',
        'a' => 'Buka menu <b>Riwayat Transaksi</b>, klik tombol <b>Detail</b> pada transaksi penjualan. Di bagian bawah, ubah status (Diproses → Dikirim → Selesai), lalu klik <b>Simpan Status</b>. Pelanggan akan melihat update status ini di halaman riwayat mereka.'
    ],
    [
        'id' => 'faq_stok_habis',
        'q' => 'Bagaimana jika ada produk yang stoknya habis?',
        'a' => 'Sistem akan otomatis menandai produk sebagai <b>Stok Habis</b> (badge merah). Produk dengan stok 0 tidak akan bisa dibeli pelanggan di katalog. Segera lakukan restock via menu <b>Pembelian</b> untuk mengisi ulang stok.'
    ],
    [
        'id' => 'faq_batal',
        'q' => 'Apa yang terjadi saat transaksi dibatalkan?',
        'a' => 'Di <b>Detail Transaksi</b>, ubah status ke <b>Batal</b>. Stok produk otomatis dikembalikan dan transaksi tersebut <b>tidak dihitung</b> di omzet, laba, maupun total laporan. Status Batal tidak bisa diubah lagi.'
    ],
    [
        'id' => 'faq_pdf',
        'q' => 'Bagaimana cara mengunduh laporan atau rekap transaksi dalam PDF?',
        'a' => 'Di menu <b>Laporan</b> atau <b>Riwayat Transaksi</b>, atur filter tanggal lalu klik <b>Export PDF</b>. Kop PDF memakai nama toko, alamat, dan logo dari Pengaturan Toko.'
    ],
    [
        'id' => 'faq_reset_pw',
        'q' => 'Pelanggan lupa password, apa yang harus dilakukan?',
        'a' => 'Buka menu <b>Pelanggan</b>, klik ikon kunci pada pelanggan tersebut, lalu <b>Ya, Reset</b>. Sistem membuat password sementara yang bisa Anda salin dan berikan ke pelanggan. Untuk password akun Anda sendiri, gunakan <b>Ganti Password Saya</b> di Pengaturan Toko.'
    ],
    [
        'id' => 'faq_ubah_toko',
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
                    <?= brand_icon_html() ?>
                    <span class="text-xl font-bold tracking-tight text-stone-800"><?= brand_html() ?></span>
                </a>
            </div>

            <form onsubmit="event.preventDefault(); document.getElementById('faqSearch').value = this.search.value; filterFAQ(); document.getElementById('faqList').scrollIntoView({behavior:'smooth'});" class="hidden md:flex flex-1 max-w-md relative">
                <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400"></i>
                <input type="text" name="search" placeholder="Cari panduan bantuan..." class="w-full pl-10 pr-4 py-2 text-sm bg-stone-100/70 border border-transparent rounded-full focus:outline-none focus:bg-white focus:border-[#2E7D32] placeholder:text-stone-400">
            </form>

            <div class="flex items-center gap-1">
                <a href="dashboard.php" class="flex items-center gap-3 pl-1">
                <img src="<?= e($avatar_url) ?>" class="w-9 h-9 rounded-full object-cover ring-2 ring-[#2E7D32]/20">
                <div class="hidden sm:block text-left">
                    <p class="text-sm font-semibold text-stone-800 leading-tight"><?= htmlspecialchars($admin_nama) ?></p>
                    <p class="text-xs text-stone-500">Administrator</p>
                </div>
            </a>
                <?= logout_btn_html() ?>
            </div>
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
                        ['url' => 'kategori.php',   'icon' => 'tag',            'label' => 'Kategori',         'active' => false],
                        ['url' => 'supplier.php',   'icon' => 'building-2',     'label' => 'Supplier',         'active' => false],
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

            <!-- PERLU PERHATIAN (data langsung dari sistem) -->
            <?php
            $q1 = mysqli_query($conn, "SELECT COUNT(*) AS n FROM transaksi WHERE jenis_transaksi='penjualan' AND status IN ('Diproses','Dikirim')");
            $pesananAktif = $q1 ? (int)mysqli_fetch_assoc($q1)['n'] : 0;
            $q2 = mysqli_query($conn, "SELECT COUNT(*) AS n FROM produk WHERE stok <= stok_minimal");
            $stokPerhatian = $q2 ? (int)mysqli_fetch_assoc($q2)['n'] : 0;
            $q3 = mysqli_query($conn, "SELECT COUNT(*) AS n FROM chats WHERE sender_type='customer' AND is_read=0 AND is_deleted=0");
            $chatBaru = $q3 ? (int)mysqli_fetch_assoc($q3)['n'] : 0;
            $q4 = mysqli_query($conn, "SELECT COUNT(*) AS n, COALESCE(SUM(total_harga),0) AS t FROM transaksi WHERE jenis_transaksi='penjualan' AND status <> 'Batal' AND DATE(created_at)=CURDATE()");
            $hariIni = $q4 ? mysqli_fetch_assoc($q4) : ['n' => 0, 't' => 0];
            $tugas = [
                ['url' => 'transaksi.php?status=Diproses', 'icon' => 'clock',          'angka' => $pesananAktif, 'judul' => 'Pesanan perlu diproses', 'ket' => $pesananAktif > 0 ? 'Buka riwayat, ubah status ke Dikirim / Selesai.' : 'Tidak ada pesanan yang menunggu.', 'warn' => $pesananAktif > 0],
                ['url' => 'stok.php?status_filter=critical', 'icon' => 'alert-triangle', 'angka' => $stokPerhatian, 'judul' => 'Produk stok menipis / habis', 'ket' => $stokPerhatian > 0 ? 'Segera restock lewat menu Pembelian.' : 'Semua stok aman.', 'warn' => $stokPerhatian > 0],
                ['url' => 'chat.php?filter=unread', 'icon' => 'message-square', 'angka' => $chatBaru, 'judul' => 'Chat belum dibalas', 'ket' => $chatBaru > 0 ? 'Pelanggan menunggu balasan konsultasi.' : 'Tidak ada chat baru.', 'warn' => $chatBaru > 0],
                ['url' => 'laporan.php?range=today', 'icon' => 'wallet', 'angka' => (int)$hariIni['n'], 'judul' => 'Transaksi hari ini', 'ket' => 'Omzet ' . rupiah($hariIni['t']) . ' (tanpa Batal).', 'warn' => false],
            ];
            ?>
            <div>
                <h2 class="text-sm font-bold text-stone-800 mb-3 flex items-center gap-2">
                    <i data-lucide="list-checks" class="w-4 h-4 text-[#2E7D32]"></i> Perlu perhatian hari ini
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <?php foreach ($tugas as $t): ?>
                        <a href="<?= e($t['url']) ?>" class="bg-white p-4 rounded-2xl border <?= $t['warn'] ? 'border-amber-200' : 'border-stone-200/80' ?> shadow-sm hover:shadow-md hover:border-[#2E7D32]/30 transition group flex items-start gap-3">
                            <div class="w-10 h-10 rounded-xl <?= $t['warn'] ? 'bg-amber-50 text-amber-600' : 'bg-emerald-50 text-[#2E7D32]' ?> flex items-center justify-center shrink-0">
                                <i data-lucide="<?= e($t['icon']) ?>" class="w-5 h-5"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xl font-bold text-stone-800 leading-none"><?= (int)$t['angka'] ?></p>
                                <h3 class="text-sm font-bold text-stone-800 mt-1"><?= e($t['judul']) ?></h3>
                                <p class="text-xs text-stone-500 mt-0.5 leading-relaxed"><?= e($t['ket']) ?></p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ALUR KERJA HARIAN -->
            <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-6">
                <h2 class="text-base font-bold text-stone-800 mb-4">Alur kerja harian yang disarankan</h2>
                <ol class="space-y-3 text-xs text-stone-600">
                    <li class="flex gap-3"><span class="w-6 h-6 rounded-full bg-[#E8F5E9] text-[#2E7D32] font-bold flex items-center justify-center shrink-0">1</span><span><b class="text-stone-800">Pagi:</b> cek <a href="stok.php?status_filter=critical" class="text-[#2E7D32] font-bold hover:underline">stok menipis</a> dan <a href="transaksi.php?status=Diproses" class="text-[#2E7D32] font-bold hover:underline">pesanan yang diproses</a>.</span></li>
                    <li class="flex gap-3"><span class="w-6 h-6 rounded-full bg-[#E8F5E9] text-[#2E7D32] font-bold flex items-center justify-center shrink-0">2</span><span><b class="text-stone-800">Siang:</b> layani pembeli di <a href="pos.php" class="text-[#2E7D32] font-bold hover:underline">Kasir (POS)</a> dan balas <a href="chat.php" class="text-[#2E7D32] font-bold hover:underline">chat konsultasi</a>.</span></li>
                    <li class="flex gap-3"><span class="w-6 h-6 rounded-full bg-[#E8F5E9] text-[#2E7D32] font-bold flex items-center justify-center shrink-0">3</span><span><b class="text-stone-800">Barang datang:</b> catat lewat <a href="restock.php" class="text-[#2E7D32] font-bold hover:underline">Pembelian</a> agar stok dan harga beli ikut terbarui.</span></li>
                    <li class="flex gap-3"><span class="w-6 h-6 rounded-full bg-[#E8F5E9] text-[#2E7D32] font-bold flex items-center justify-center shrink-0">4</span><span><b class="text-stone-800">Sore:</b> lihat <a href="laporan.php?range=today" class="text-[#2E7D32] font-bold hover:underline">laporan hari ini</a> dan unduh PDF bila perlu.</span></li>
                </ol>
            </div>

            <!-- FAQ -->
            <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-6">
                <div class="flex items-center justify-between border-b border-stone-100 pb-3 mb-4 flex-wrap gap-3">
                    <div>
                        <h2 class="text-base font-bold text-stone-800">Pertanyaan Sering Diajukan</h2>
                        <p class="text-[11px] text-stone-500 mt-0.5" id="statistikFeedback">Memuat statistik...</p>
                    </div>
                    <div class="relative">
                        <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-stone-400"></i>
                        <input type="text" id="faqSearch" onkeyup="filterFAQ()" placeholder="Cari pertanyaan..." class="pl-9 pr-3 py-1.5 text-xs bg-stone-50 border border-stone-200 rounded-lg focus:outline-none focus:border-[#2E7D32] w-44">
                    </div>
                </div>

                <div id="faqList" class="space-y-2">
                    <?php foreach ($faqs as $i => $faq): ?>
                        <div class="faq-item border border-stone-200/80 rounded-xl overflow-hidden" data-faq-id="<?= htmlspecialchars($faq['id']) ?>">
                            <button onclick="toggleFAQ(this)" class="w-full flex items-center justify-between p-4 text-left hover:bg-stone-50 transition">
                                <span class="faq-question text-sm font-semibold text-stone-800 pr-4"><?= htmlspecialchars($faq['q']) ?></span>
                                <i data-lucide="chevron-down" class="w-4 h-4 text-stone-400 shrink-0 transition-transform duration-200"></i>
                            </button>
                            <div class="faq-answer hidden p-4 pt-0 text-xs text-stone-600 leading-relaxed bg-stone-50/50 border-t border-stone-100">
                                <div class="mb-4"><?= $faq['a'] ?></div>

                                <!-- FEEDBACK -->
                                <div class="pt-3 border-t border-stone-200/60">
                                    <div class="feedback-section" data-faq-id="<?= htmlspecialchars($faq['id']) ?>">
                                        <p class="text-[11px] font-semibold text-stone-500 mb-2">Apakah artikel ini membantu?</p>
                                        <div class="flex items-center gap-2">
                                            <button onclick="kirimFeedback('<?= htmlspecialchars($faq['id']) ?>', 'up', this)" 
                                                    class="fb-btn-up inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-bold rounded-lg border border-stone-200 bg-white text-stone-600 hover:bg-emerald-50 hover:border-emerald-200 hover:text-[#2E7D32] transition">
                                                <i data-lucide="thumbs-up" class="w-3.5 h-3.5"></i> Ya
                                            </button>
                                            <button onclick="kirimFeedback('<?= htmlspecialchars($faq['id']) ?>', 'down', this)" 
                                                    class="fb-btn-down inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-bold rounded-lg border border-stone-200 bg-white text-stone-600 hover:bg-rose-50 hover:border-rose-200 hover:text-rose-600 transition">
                                                <i data-lucide="thumbs-down" class="w-3.5 h-3.5"></i> Tidak
                                            </button>
                                            <span class="fb-result hidden text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-100 ml-2"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div id="faqEmpty" class="hidden text-center py-8">
                    <div class="w-14 h-14 bg-stone-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i data-lucide="search-x" class="w-6 h-6 text-stone-400"></i>
                    </div>
                    <p class="text-sm font-semibold text-stone-700">Tidak ada pertanyaan yang cocok</p>
                    <p class="text-xs text-stone-500 mt-1">Coba kata kunci lain.</p>
                </div>
            </div>

            <!-- KONTAK SUPPORT -->
            <div class="bg-[#2E7D32] text-white rounded-2xl p-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="text-center sm:text-left">
                    <h3 class="text-base font-bold">Butuh bantuan tambahan?</h3>
                    <p class="text-xs text-emerald-100 mt-1">Hubungi tim teknis <?= htmlspecialchars($nama_toko) ?> untuk kendala sistem.</p>
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

        // ========================================================
        // STORAGE: feedback disimpan di localStorage
        // Format: { "faq_tambah_produk": "up", "faq_pos": "down", ... }
        // ========================================================
        // Data feedback berasal dari database (bukan localStorage lagi)
        let votes = <?= json_encode((object)$votesMine) ?>;
        let totalUp = <?= (int)$totUp ?>;
        let totalDown = <?= (int)$totDown ?>;
        const CSRF = <?= json_encode(csrf_token()) ?>;

        function getFeedbackData() { return votes; }

        function kirimKeServer(faqId, vote) {
            const fd = new FormData();
            fd.append('action', 'feedback');
            fd.append('csrf', CSRF);
            fd.append('faq_id', faqId);
            fd.append('vote', vote);
            fetch('bantuan.php', { method: 'POST', body: fd }).catch(() => {});
        }

        // ========================================================
        // KIRIM FEEDBACK
        // ========================================================
        function kirimFeedback(faqId, type, btn) {
            const section = btn.closest('.feedback-section');
            const upBtn = section.querySelector('.fb-btn-up');
            const downBtn = section.querySelector('.fb-btn-down');
            const result = section.querySelector('.fb-result');

            // Cek apakah sudah pernah vote
            const data = getFeedbackData();
            if (data[faqId]) {
                // Update vote (ganti)
                if (data[faqId] === type) {
                    // Klik vote yang sama → hapus vote
                    if (type === 'up') totalUp--; else totalDown--;
                    delete data[faqId];
                    kirimKeServer(faqId, 'none');
                    resetFeedbackUI(section);
                    updateStatistik();
                    return;
                }
            }

            // Simpan vote baru (ganti vote lama bila ada)
            if (data[faqId] === 'up') totalUp--; else if (data[faqId] === 'down') totalDown--;
            if (type === 'up') totalUp++; else totalDown++;
            data[faqId] = type;
            kirimKeServer(faqId, type);

            // Update UI
            resetFeedbackUI(section);

            if (type === 'up') {
                upBtn.className = 'fb-btn-up inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-bold rounded-lg border border-[#2E7D32] bg-[#E8F5E9] text-[#2E7D32] transition';
                result.innerText = 'Terima kasih! 🙏';
                result.className = 'fb-result text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-100 ml-2';
            } else {
                downBtn.className = 'fb-btn-down inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-bold rounded-lg border border-rose-300 bg-rose-50 text-rose-600 transition';
                result.innerText = 'Terima kasih atas masukannya.';
                result.className = 'fb-result text-[11px] font-semibold text-rose-700 bg-rose-50 px-2.5 py-1 rounded-lg border border-rose-100 ml-2';
            }
            result.classList.remove('hidden');

            updateStatistik();
        }

        function resetFeedbackUI(section) {
            const upBtn = section.querySelector('.fb-btn-up');
            const downBtn = section.querySelector('.fb-btn-down');
            const result = section.querySelector('.fb-result');

            upBtn.className = 'fb-btn-up inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-bold rounded-lg border border-stone-200 bg-white text-stone-600 hover:bg-emerald-50 hover:border-emerald-200 hover:text-[#2E7D32] transition';
            downBtn.className = 'fb-btn-down inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-bold rounded-lg border border-stone-200 bg-white text-stone-600 hover:bg-rose-50 hover:border-rose-200 hover:text-rose-600 transition';
            result.classList.add('hidden');
            result.innerText = '';
        }

        // ========================================================
        // RESTORE UI SAAT LOAD (kalau user pernah vote)
        // ========================================================
        function restoreFeedbackUI() {
            const data = getFeedbackData();
            document.querySelectorAll('.feedback-section').forEach(section => {
                const faqId = section.dataset.faqId;
                if (data[faqId]) {
                    const btn = data[faqId] === 'up' 
                        ? section.querySelector('.fb-btn-up') 
                        : section.querySelector('.fb-btn-down');
                    const result = section.querySelector('.fb-result');

                    if (data[faqId] === 'up') {
                        btn.className = 'fb-btn-up inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-bold rounded-lg border border-[#2E7D32] bg-[#E8F5E9] text-[#2E7D32] transition';
                        result.innerText = 'Terima kasih! 🙏';
                    } else {
                        btn.className = 'fb-btn-down inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-bold rounded-lg border border-rose-300 bg-rose-50 text-rose-600 transition';
                        result.innerText = 'Terima kasih atas masukannya.';
                    }
                    result.className = 'fb-result text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-100 ml-2';
                    result.classList.remove('hidden');
                }
            });
        }

        // ========================================================
        // STATISTIK: gabungan feedback seluruh admin (dari database)
        // ========================================================
        function updateStatistik() {
            const totalVote = Math.max(0, totalUp) + Math.max(0, totalDown);
            const persen    = totalVote > 0 ? Math.round((totalUp / totalVote) * 100) : 0;

            const el = document.getElementById('statistikFeedback');
            if (!el) return;

            if (totalVote === 0) {
                el.innerText = 'Belum ada feedback. Bantu kami dengan klik 👍 atau 👎.';
            } else {
                el.innerHTML = `<b class="text-stone-700">${persen}%</b> dari <b class="text-stone-700">${totalVote}</b> feedback merasa terbantu.`;
            }
        }

        // ========================================================
        // FAQ TOGGLE
        // ========================================================
        function toggleFAQ(btn) {
            const item = btn.parentElement;
            const answer = item.querySelector('.faq-answer');
            const icon = btn.querySelector('i');

            document.querySelectorAll('.faq-item').forEach(el => {
                if (el !== item) {
                    el.querySelector('.faq-answer')?.classList.add('hidden');
                    el.querySelector('button i')?.classList.remove('rotate-180');
                }
            });

            answer.classList.toggle('hidden');
            icon.classList.toggle('rotate-180');
        }

        // ========================================================
        // FILTER FAQ
        // ========================================================
        function filterFAQ() {
            const input = document.getElementById('faqSearch').value.toLowerCase();
            let visibleCount = 0;

            document.querySelectorAll('.faq-item').forEach(item => {
                const text = item.textContent.toLowerCase();
                const show = text.includes(input);
                item.style.display = show ? '' : 'none';
                if (show) visibleCount++;
            });

            document.getElementById('faqEmpty').classList.toggle('hidden', visibleCount > 0);
        }

        // ========================================================
        // INIT
        // ========================================================
        restoreFeedbackUI();
        updateStatistik();

        function toggleMobileSidebar() {
            const s = document.getElementById('sidebar');
            s?.classList.toggle('hidden');
            s?.classList.toggle('fixed');
            s?.classList.toggle('inset-y-0');
            s?.classList.toggle('left-0');
            s?.classList.toggle('z-40');
        }
    </script>
</body>
</html>