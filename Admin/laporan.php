<?php
require_once __DIR__ . '/includes/init.php';

// =========================================================
// FILTER TANGGAL
// =========================================================
$range       = $_GET['range'] ?? '';
$tgl_mulai   = valid_date($_GET['tgl_mulai']   ?? '', date('Y-m-01'));
$tgl_selesai = valid_date($_GET['tgl_selesai'] ?? '', date('Y-m-d'));

if ($range === 'today') {
    $tgl_mulai = $tgl_selesai = date('Y-m-d');
} elseif ($range === '7days') {
    $tgl_mulai   = date('Y-m-d', strtotime('-6 days'));
    $tgl_selesai = date('Y-m-d');
} elseif ($range === '30days') {
    $tgl_mulai   = date('Y-m-d', strtotime('-29 days'));
    $tgl_selesai = date('Y-m-d');
}

if ($tgl_mulai > $tgl_selesai) { $tmp = $tgl_mulai; $tgl_mulai = $tgl_selesai; $tgl_selesai = $tmp; }

$tgl_mulai_esc   = mysqli_real_escape_string($conn, $tgl_mulai);
$tgl_selesai_esc = mysqli_real_escape_string($conn, $tgl_selesai);

// =========================================================
// 1. RINGKASAN
// =========================================================
$summary = [
    'total_penjualan'  => 0,
    'jumlah_penjualan' => 0,
    'total_pembelian'  => 0,
    'jumlah_pembelian' => 0,
];
$qSummary = mysqli_query($conn, "
    SELECT 
        COALESCE(SUM(CASE WHEN jenis_transaksi = 'penjualan' THEN total_harga ELSE 0 END), 0) AS total_penjualan,
        COALESCE(SUM(CASE WHEN jenis_transaksi = 'penjualan' THEN 1 ELSE 0 END), 0) AS jumlah_penjualan,
        COALESCE(SUM(CASE WHEN jenis_transaksi = 'pembelian' THEN total_harga ELSE 0 END), 0) AS total_pembelian,
        COALESCE(SUM(CASE WHEN jenis_transaksi = 'pembelian' THEN 1 ELSE 0 END), 0) AS jumlah_pembelian
    FROM transaksi
    WHERE DATE(created_at) BETWEEN '$tgl_mulai_esc' AND '$tgl_selesai_esc'
      AND (status IS NULL OR status <> 'Batal')
");
if ($qSummary) $summary = mysqli_fetch_assoc($qSummary);

// =========================================================
// 2. ESTIMASI LABA
// =========================================================
$estimasiLaba = 0;
$qLaba = mysqli_query($conn, "
    SELECT COALESCE(SUM(td.jumlah * (td.harga_satuan - COALESCE(p.harga_beli, 0))), 0) AS estimasi
    FROM transaksi_detail td
    JOIN transaksi t ON td.transaksi_id = t.id
    LEFT JOIN produk p ON td.produk_id = p.id
    WHERE t.jenis_transaksi = 'penjualan'
      AND (t.status IS NULL OR t.status <> 'Batal')
      AND DATE(t.created_at) BETWEEN '$tgl_mulai_esc' AND '$tgl_selesai_esc'
");
if ($qLaba) $estimasiLaba = (float)mysqli_fetch_assoc($qLaba)['estimasi'];

$omset       = (float)$summary['total_penjualan'];
$pengeluaran = (float)$summary['total_pembelian'];

// =========================================================
// 3. TOP 5 PRODUK TERLARIS
// =========================================================
$topProduk = [];
$qTop = mysqli_query($conn, "
    SELECT 
        p.nama_tanaman,
        SUM(td.jumlah) AS total_terjual,
        SUM(td.subtotal) AS total_pendapatan
    FROM transaksi_detail td
    JOIN transaksi t ON td.transaksi_id = t.id
    LEFT JOIN produk p ON td.produk_id = p.id
    WHERE t.jenis_transaksi = 'penjualan'
      AND (t.status IS NULL OR t.status <> 'Batal')
      AND DATE(t.created_at) BETWEEN '$tgl_mulai_esc' AND '$tgl_selesai_esc'
    GROUP BY td.produk_id, p.nama_tanaman
    ORDER BY total_terjual DESC
    LIMIT 5
");
if ($qTop) while ($r = mysqli_fetch_assoc($qTop)) $topProduk[] = $r;

// =========================================================
// 4. RIWAYAT TRANSAKSI PADA PERIODE
// =========================================================
$trxList = [];
$qTrx = mysqli_query($conn, "
    SELECT t.*, 
           COALESCE(u.nama_lengkap, s.nama_supplier, t.nama_penerima, 'Walk-in') AS pelanggan
    FROM transaksi t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN supplier s ON t.supplier_id = s.id
    WHERE DATE(t.created_at) BETWEEN '$tgl_mulai_esc' AND '$tgl_selesai_esc'
    ORDER BY t.created_at DESC
    LIMIT 50
");
if ($qTrx) while ($r = mysqli_fetch_assoc($qTrx)) $trxList[] = $r;

// =========================================================
// 5. CHART OMZET HARIAN
// =========================================================
$chartRaw = [];
$qChart = mysqli_query($conn, "
    SELECT DATE(created_at) AS tgl, SUM(total_harga) AS total
    FROM transaksi
    WHERE jenis_transaksi = 'penjualan'
      AND (status IS NULL OR status <> 'Batal')
      AND DATE(created_at) BETWEEN '$tgl_mulai_esc' AND '$tgl_selesai_esc'
    GROUP BY DATE(created_at)
    ORDER BY DATE(created_at) ASC
");
if ($qChart) while ($r = mysqli_fetch_assoc($qChart)) $chartRaw[$r['tgl']] = (float)$r['total'];

// Isi tanggal kosong
$chart_labels = [];
$chart_data   = [];
$start = strtotime($tgl_mulai);
$end   = strtotime($tgl_selesai);
for ($t = $start; $t <= $end; $t += 86400) {
    $key = date('Y-m-d', $t);
    $chart_labels[] = date('d M', $t);
    $chart_data[]   = $chartRaw[$key] ?? 0;
}

function badgeStatusLaporan($status) {
    switch ($status) {
        case 'Diproses': return 'bg-amber-50 text-amber-700 border-amber-200';
        case 'Dikirim':  return 'bg-blue-50 text-blue-700 border-blue-200';
        case 'Selesai':  return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'Batal':    return 'bg-rose-50 text-rose-700 border-rose-200';
        default:         return 'bg-stone-100 text-stone-600 border-stone-200';
    }
}

// =========================================================
// LOGIKA EXPORT PDF (Jika tombol Export PDF diklik)
// =========================================================
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $fpdfPath = __DIR__ . '/../fpdf/fpdf.php';
    if (!is_file($fpdfPath)) {
        http_response_code(500);
        exit('Library FPDF tidak ditemukan di ../fpdf/fpdf.php');
    }
    require_once $fpdfPath;

    // Info toko ($nama_toko, $nama_cabang, $alamat_toko) sudah dimuat dari includes/init.php
    $alamat_pdf = $alamat_toko ?: '-';
    $logo_path  = $logo_url !== '' ? __DIR__ . '/../uploads/' . $logo_toko : '';
    $logo_ada   = ($logo_path !== '' && is_file($logo_path) && preg_match('/\.(png|jpe?g)$/i', $logo_path)
                   && (preg_match('/\.jpe?g$/i', $logo_path) || extension_loaded('gd')));

    // Teks FPDF harus ISO-8859-1 (agar karakter non-ASCII tidak rusak)
    ob_start(); // tahan semua output liar (warning/notice) agar file PDF tidak rusak
    $T = function ($v) { return mb_convert_encoding((string)$v, 'ISO-8859-1', 'UTF-8'); };

    // Untuk PDF: SEMUA transaksi periode (bukan hanya 50 teratas)
    $trxList = [];
    $qAll = mysqli_query($conn, "
        SELECT t.*, COALESCE(u.nama_lengkap, s.nama_supplier, t.nama_penerima, 'Walk-in') AS pelanggan
        FROM transaksi t
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN supplier s ON t.supplier_id = s.id
        WHERE DATE(t.created_at) BETWEEN '$tgl_mulai_esc' AND '$tgl_selesai_esc'
        ORDER BY t.created_at DESC
    ");
    if ($qAll) while ($r = mysqli_fetch_assoc($qAll)) $trxList[] = $r;

    // Mulai FPDF
    $pdf = new FPDF('P', 'mm', 'A4'); // Pakai Portrait biar lebih rapi
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->SetMargins(15, 15, 15);
    $pdf->AddPage();

    // -----------------------------------------
    // HEADER PDF
    // -----------------------------------------
    if ($logo_ada) {
        $pdf->Image($logo_path, 15, 15, 18, 18);
        $text_x = 37;
    } else {
        $text_x = 15;
    }

    $pdf->SetXY($text_x, 15);
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetTextColor(46, 125, 50); // Hijau
    $pdf->Cell(100, 7, $T(strtoupper($nama_toko)), 0, 2, 'L');
    
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->SetX($text_x);
    $pdf->Cell(100, 4, $T($nama_cabang . ' | ' . $alamat_pdf), 0, 2, 'L');

    // Judul Kanan
    $pdf->SetXY(-85, 15);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(70, 7, 'LAPORAN KEUANGAN', 0, 2, 'R');
    
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->SetX(-85);
    $pdf->Cell(70, 4, 'Periode: ' . date('d/m/Y', strtotime($tgl_mulai)) . ' - ' . date('d/m/Y', strtotime($tgl_selesai)), 0, 2, 'R');
    $pdf->SetX(-85);
    $pdf->Cell(70, 4, 'Dicetak: ' . date('d/m/Y H:i') . ' WIB', 0, 2, 'R');

    // Garis Hijau
    $pdf->SetY(35);
    $pdf->SetDrawColor(46, 125, 50);
    $pdf->SetLineWidth(1);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(8);

    // -----------------------------------------
    // RINGKASAN KEUANGAN
    // -----------------------------------------
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 6, 'RINGKASAN KEUANGAN', 0, 1, 'L');
    $pdf->Ln(2);

    // Kotak Omset
    $pdf->SetFillColor(240, 248, 240); // Hijau sangat muda
    $pdf->SetDrawColor(200, 220, 200);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(55, 8, 'TOTAL PENDAPATAN', 1, 0, 'C', true);
    $pdf->Cell(55, 8, 'TOTAL PENGELUARAN', 1, 0, 'C', true);
    $pdf->Cell(55, 8, 'ESTIMASI LABA KOTOR', 1, 1, 'C', true);
    
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(46, 125, 50); // Teks hijau
    $pdf->Cell(55, 10, 'Rp ' . number_format($omset, 0, ',', '.'), 1, 0, 'C', true);
    $pdf->SetTextColor(217, 119, 6); // Teks oranye (pengeluaran)
    $pdf->Cell(55, 10, 'Rp ' . number_format($pengeluaran, 0, ',', '.'), 1, 0, 'C', true);
    $pdf->SetTextColor(46, 125, 50); // Teks hijau
    $pdf->Cell(55, 10, 'Rp ' . number_format($estimasiLaba, 0, ',', '.'), 1, 1, 'C', true);
    
    $pdf->SetTextColor(100, 100, 100);
    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell(55, 6, '(' . (int)$summary['jumlah_penjualan'] . ' Transaksi Penjualan)', 1, 0, 'C', true);
    $pdf->Cell(55, 6, '(' . (int)$summary['jumlah_pembelian'] . ' Transaksi Pembelian)', 1, 0, 'C', true);
    $pdf->Cell(55, 6, '(Berdasarkan Harga Beli saat ini)', 1, 1, 'C', true);

    $pdf->Ln(10);

    // -----------------------------------------
    // TOP PRODUK
    // -----------------------------------------
    if (!empty($topProduk)) {
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 6, '5 PRODUK TERLARIS', 0, 1, 'L');
        $pdf->Ln(2);

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(46, 125, 50);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(46, 125, 50);
        
        $pdf->Cell(10, 8, 'No', 1, 0, 'C', true);
        $pdf->Cell(85, 8, 'Nama Produk', 1, 0, 'C', true);
        $pdf->Cell(25, 8, 'Terjual', 1, 0, 'C', true);
        $pdf->Cell(45, 8, 'Total Pendapatan', 1, 1, 'C', true);
        
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->SetFont('Arial', '', 9);
        
        $no = 1;
        foreach ($topProduk as $tp) {
            $pdf->Cell(10, 7, $no++, 1, 0, 'C');
            $pdf->Cell(85, 7, $T(mb_substr($tp['nama_tanaman'] ?? 'Produk', 0, 45)), 1, 0, 'L');
            $pdf->Cell(25, 7, (int)$tp['total_terjual'] . ' pcs', 1, 0, 'C');
            $pdf->Cell(45, 7, 'Rp ' . number_format($tp['total_pendapatan'], 0, ',', '.'), 1, 1, 'R');
        }
        $pdf->Ln(10);
    }

    // -----------------------------------------
    // TABEL TRANSAKSI
    // -----------------------------------------
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 6, 'RINCIAN TRANSAKSI', 0, 1, 'L');
    $pdf->Ln(2);

    function headerTabelLaporan($pdf) {
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetFillColor(46, 125, 50);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(46, 125, 50);
        
        $pdf->Cell(8, 8, 'No', 1, 0, 'C', true);
        $pdf->Cell(35, 8, 'Kode Trx', 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'Tanggal', 1, 0, 'C', true);
        $pdf->Cell(45, 8, 'Pelanggan / Supplier', 1, 0, 'C', true);
        $pdf->Cell(20, 8, 'Jenis', 1, 0, 'C', true);
        $pdf->Cell(20, 8, 'Status', 1, 0, 'C', true);
        $pdf->Cell(22, 8, 'Total (Rp)', 1, 1, 'C', true);
        
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetDrawColor(220, 220, 220);
    }

    headerTabelLaporan($pdf);
    $pdf->SetFont('Arial', '', 8);

    $no = 1;
    $totJual = 0; $totBeli = 0;
    if (empty($trxList)) {
        $pdf->Cell(180, 10, 'Tidak ada transaksi pada periode ini.', 1, 1, 'C');
    } else {
        foreach ($trxList as $t) {
            if ($pdf->GetY() > 250) {
                $pdf->AddPage();
                headerTabelLaporan($pdf);
                $pdf->SetFont('Arial', '', 8);
            }
            
            $fill = ($no % 2 === 0);
            $pdf->SetFillColor(248, 250, 252);
            if ($t['status'] !== 'Batal') {
                if ($t['jenis_transaksi'] === 'penjualan') $totJual += (float)$t['total_harga'];
                else $totBeli += (float)$t['total_harga'];
            }
            
            $pdf->Cell(8, 6, $no++, 1, 0, 'C', $fill);
            $pdf->Cell(35, 6, $t['kode_transaksi'], 1, 0, 'L', $fill);
            $pdf->Cell(30, 6, date('d/m/Y', strtotime($t['created_at'])), 1, 0, 'C', $fill);
            $pdf->Cell(45, 6, $T(mb_substr($t['pelanggan'], 0, 25)), 1, 0, 'L', $fill);
            $pdf->Cell(20, 6, ucfirst($t['jenis_transaksi']), 1, 0, 'C', $fill);
            $pdf->Cell(20, 6, $t['status'], 1, 0, 'C', $fill);
            $pdf->Cell(22, 6, number_format($t['total_harga'], 0, ',', '.'), 1, 1, 'R', $fill);
        }
        
        // Footer total (transaksi Batal tidak dihitung; penjualan & pembelian dipisah)
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(240, 248, 240);
        $pdf->SetTextColor(46, 125, 50);
        $pdf->Cell(158, 8, 'TOTAL PENJUALAN (tanpa Batal)', 1, 0, 'R', true);
        $pdf->Cell(22, 8, number_format($totJual, 0, ',', '.'), 1, 1, 'R', true);
        $pdf->SetTextColor(217, 119, 6);
        $pdf->Cell(158, 8, 'TOTAL PEMBELIAN (tanpa Batal)', 1, 0, 'R', true);
        $pdf->Cell(22, 8, number_format($totBeli, 0, ',', '.'), 1, 1, 'R', true);
    }

    // Tanda Tangan (Opsional, biar keren)
    $pdf->Ln(15);
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(130);
    $pdf->Cell(50, 5, 'Dicetak Oleh,', 0, 1, 'C');
    $pdf->Ln(12);
    $pdf->Cell(130);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(50, 5, $T($admin_nama), 0, 1, 'C');

    $filename = 'Laporan_Keuangan_' . date('Ymd_His') . '.pdf';
    while (ob_get_level() > 0) ob_end_clean();
    $pdf->Output('I', $filename);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($nama_toko) ?> - Laporan Keuangan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F9F8F6; color: #2D3748; }
    </style>
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

            <div class="hidden md:flex flex-1 max-w-md">
                <span class="text-xs text-stone-500 self-center">Laporan Keuangan</span>
            </div>

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
                        ['url' => 'laporan.php',    'icon' => 'bar-chart-2',    'label' => 'Laporan',          'active' => true],
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
                    <a href="bantuan.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-bold rounded-xl text-stone-700 hover:bg-stone-100">
                        <i data-lucide="help-circle" class="w-5 h-5 text-stone-500"></i> Bantuan
                    </a>
                </nav>
            </div>
            <div class="p-3 bg-stone-50/80 border border-stone-200/60 rounded-2xl flex items-center gap-3 mt-auto">
                <div class="w-10 h-10 rounded-xl bg-emerald-100/70 flex items-center justify-center text-[#2E7D32] shrink-0">
                    <i data-lucide="store" class="w-5 h-5"></i>
                </div>
                <div class="overflow-hidden">
                    <p class="text-sm font-bold text-stone-800 truncate leading-tight"><?= e($nama_toko) ?> Admin</p>
                    <p class="text-[11px] font-medium text-stone-400 truncate mt-0.5">Sistem Online</p>
                </div>
            </div>
        </aside>

        <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full space-y-6">

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-stone-800 tracking-tight">Laporan Keuangan Toko</h1>
                    <p class="text-sm text-stone-500 mt-0.5">Analisis arus kas, omzet, dan performa penjualan.</p>
                </div>
                <div class="flex items-center gap-2">
                    <!-- TOMBOL EXPORT PDF (TANPA TOMBOL CETAK) -->
                    <a href="laporan.php?export=pdf&<?= http_build_query($_GET) ?>" target="_blank" class="inline-flex items-center gap-2 bg-[#2E7D32] text-white px-4 py-2.5 rounded-xl text-sm font-semibold hover:bg-emerald-800 shadow-sm transition">
                        <i data-lucide="download" class="w-4 h-4"></i> Export PDF
                    </a>
                    <a href="pos.php" class="inline-flex items-center gap-2 bg-white border border-stone-300 text-stone-700 px-4 py-2.5 rounded-xl text-sm font-semibold hover:bg-stone-50 shadow-sm">
                        <i data-lucide="plus" class="w-4 h-4"></i> Transaksi Baru
                    </a>
                </div>
            </div>

            <!-- FILTER TANGGAL -->
            <div class="bg-white p-5 rounded-2xl border border-stone-200/80 shadow-sm space-y-4">
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <p class="text-xs font-bold text-stone-400 uppercase tracking-wider">Filter Cepat</p>
                    <div class="flex items-center gap-2">
                        <a href="laporan.php?range=today" class="px-3 py-1.5 text-xs font-semibold rounded-lg border <?= $range==='today' ? 'bg-[#2E7D32] text-white border-[#2E7D32]' : 'bg-stone-50 text-stone-600 hover:bg-stone-100 border-stone-200' ?>">Hari Ini</a>
                        <a href="laporan.php?range=7days" class="px-3 py-1.5 text-xs font-semibold rounded-lg border <?= $range==='7days' ? 'bg-[#2E7D32] text-white border-[#2E7D32]' : 'bg-stone-50 text-stone-600 hover:bg-stone-100 border-stone-200' ?>">7 Hari</a>
                        <a href="laporan.php?range=30days" class="px-3 py-1.5 text-xs font-semibold rounded-lg border <?= $range==='30days' ? 'bg-[#2E7D32] text-white border-[#2E7D32]' : 'bg-stone-50 text-stone-600 hover:bg-stone-100 border-stone-200' ?>">30 Hari</a>
                    </div>
                </div>

                <form method="GET" action="laporan.php" class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2 border-t border-stone-100">
                    <div>
                        <label class="block text-xs font-semibold text-stone-500 mb-1">Mulai Dari</label>
                        <input type="date" name="tgl_mulai" value="<?= htmlspecialchars($tgl_mulai) ?>" class="w-full px-3 py-2 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:border-[#2E7D32]">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-stone-500 mb-1">Sampai Dengan</label>
                        <input type="date" name="tgl_selesai" value="<?= htmlspecialchars($tgl_selesai) ?>" class="w-full px-3 py-2 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:border-[#2E7D32]">
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="flex-1 bg-[#2E7D32] text-white py-2 rounded-xl text-sm font-semibold hover:bg-emerald-800">Terapkan</button>
                        <a href="laporan.php" class="px-4 py-2 bg-stone-100 text-stone-600 rounded-xl text-sm font-medium hover:bg-stone-200">Reset</a>
                    </div>
                </form>
            </div>

            <!-- METRIC CARDS -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-[#2E7D32]">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Total Omset</p>
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-[#2E7D32] flex items-center justify-center">
                            <i data-lucide="wallet" class="w-5 h-5"></i>
                        </div>
                    </div>
                    <p class="text-2xl font-bold text-stone-800 tracking-tight mb-2">Rp <?= number_format($omset, 0, ',', '.') ?></p>
                    <span class="inline-flex items-center gap-1 font-medium text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-100 text-xs">
                        <i data-lucide="check-circle-2" class="w-3 h-3"></i> <?= (int)$summary['jumlah_penjualan'] ?> transaksi
                    </span>
                </div>

                <div class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-[#D97706]">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Pengeluaran Restock</p>
                        <div class="w-10 h-10 rounded-xl bg-amber-50 text-[#D97706] flex items-center justify-center">
                            <i data-lucide="truck" class="w-5 h-5"></i>
                        </div>
                    </div>
                    <p class="text-2xl font-bold text-stone-800 tracking-tight mb-2">Rp <?= number_format($pengeluaran, 0, ',', '.') ?></p>
                    <span class="inline-flex items-center gap-1 font-medium text-amber-700 bg-amber-50 px-2 py-0.5 rounded-md border border-amber-100 text-xs">
                        <i data-lucide="check-circle-2" class="w-3 h-3"></i> <?= (int)$summary['jumlah_pembelian'] ?> restock
                    </span>
                </div>

                <div class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-[#2E7D32]">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Estimasi Laba</p>
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-[#2E7D32] flex items-center justify-center">
                            <i data-lucide="trending-up" class="w-5 h-5"></i>
                        </div>
                    </div>
                    <p class="text-2xl font-bold text-stone-800 tracking-tight mb-2">Rp <?= number_format($estimasiLaba, 0, ',', '.') ?></p>
                    <span class="inline-flex items-center gap-1 font-medium text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-100 text-xs">
                        <i data-lucide="check-circle-2" class="w-3 h-3"></i> Margin Keuntungan
                    </span>
                </div>

                <div class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-blue-500">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Periode Laporan</p>
                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                            <i data-lucide="calendar" class="w-5 h-5"></i>
                        </div>
                    </div>
                    <p class="text-lg font-bold text-stone-800 tracking-tight mb-2"><?= date('d/m/Y', strtotime($tgl_mulai)) ?></p>
                    <span class="inline-flex items-center gap-1 font-medium text-blue-700 bg-blue-50 px-2 py-0.5 rounded-md border border-blue-100 text-xs">
                        <i data-lucide="arrow-right" class="w-3 h-3"></i> <?= date('d/m/Y', strtotime($tgl_selesai)) ?>
                    </span>
                </div>
            </div>

            <!-- CHART + TOP PRODUK -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <div class="lg:col-span-2 bg-white p-6 rounded-2xl border border-stone-200/80 shadow-sm flex flex-col">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-lg font-bold text-stone-800">Grafik Omzet Penjualan</h2>
                            <p class="text-xs text-stone-500">Visualisasi omzet harian pada periode terpilih</p>
                        </div>
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-stone-600 bg-stone-100 px-3 py-1.5 rounded-lg">
                            <span class="w-2 h-2 rounded-full bg-[#2E7D32]"></span>
                            Omzet
                        </span>
                    </div>
                    <div class="relative w-full h-72 sm:h-80 flex-1">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl border border-stone-200/80 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-lg font-bold text-stone-800">5 Produk Terlaris</h2>
                            <p class="text-xs text-stone-500">Berdasarkan kuantitas</p>
                        </div>
                        <span class="p-1.5 bg-emerald-50 text-[#2E7D32] rounded-lg">
                            <i data-lucide="trophy" class="w-4 h-4"></i>
                        </span>
                    </div>

                    <?php if (empty($topProduk)): ?>
                        <p class="text-center text-xs text-stone-400 py-8">Belum ada penjualan di periode ini.</p>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php $rank = 1; foreach ($topProduk as $tp): ?>
                                <div class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-stone-50 transition">
                                    <div class="w-8 h-8 rounded-lg flex items-center justify-center font-bold text-xs shrink-0
                                        <?= $rank === 1 ? 'bg-amber-100 text-amber-700' : ($rank === 2 ? 'bg-stone-200 text-stone-700' : ($rank === 3 ? 'bg-orange-100 text-orange-700' : 'bg-stone-100 text-stone-500')) ?>">
                                        <?= $rank++ ?>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-bold text-stone-800 truncate"><?= htmlspecialchars($tp['nama_tanaman'] ?? 'Produk') ?></p>
                                        <p class="text-[11px] text-stone-500">Rp <?= number_format($tp['total_pendapatan'], 0, ',', '.') ?></p>
                                    </div>
                                    <span class="text-xs font-bold text-[#2E7D32] bg-emerald-50 px-2 py-1 rounded-lg shrink-0">
                                        <?= (int)$tp['total_terjual'] ?>x
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

            <!-- TABEL TRANSAKSI -->
            <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-stone-100">
                    <h2 class="text-base font-bold text-stone-800">Rincian Transaksi</h2>
                    <p class="text-xs text-stone-500 mt-0.5">Transaksi pada periode <?= date('d M Y', strtotime($tgl_mulai)) ?> — <?= date('d M Y', strtotime($tgl_selesai)) ?></p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-stone-200 bg-stone-50/50 text-[11px] font-bold text-stone-500 uppercase tracking-wider">
                                <th class="py-3.5 px-6">Kode</th>
                                <th class="py-3.5 px-4">Tanggal</th>
                                <th class="py-3.5 px-4">Pelanggan</th>
                                <th class="py-3.5 px-4">Jenis</th>
                                <th class="py-3.5 px-4">Status</th>
                                <th class="py-3.5 px-6 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100 text-xs">
                            <?php if (empty($trxList)): ?>
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-stone-400">Tidak ada transaksi pada periode ini.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($trxList as $t):
                                    $isJual = ($t['jenis_transaksi'] === 'penjualan');
                                ?>
                                    <tr class="hover:bg-stone-50/60 transition">
                                        <td class="py-3.5 px-6 font-mono text-[11px] font-semibold text-stone-700"><?= htmlspecialchars($t['kode_transaksi']) ?></td>
                                        <td class="py-3.5 px-4 text-stone-500"><?= date('d M Y, H:i', strtotime($t['created_at'])) ?></td>
                                        <td class="py-3.5 px-4 font-semibold text-stone-800"><?= htmlspecialchars($t['pelanggan']) ?></td>
                                        <td class="py-3.5 px-4">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase <?= $isJual ? 'bg-emerald-50 text-[#2E7D32] border border-emerald-100' : 'bg-amber-50 text-[#D97706] border border-amber-100' ?>">
                                                <?= htmlspecialchars($t['jenis_transaksi']) ?>
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold uppercase border <?= badgeStatusLaporan($t['status']) ?>">
                                                <?= htmlspecialchars($t['status']) ?>
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-6 text-right font-bold text-stone-800">Rp <?= number_format($t['total_harga'], 0, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
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

        const ctx = document.getElementById('salesChart').getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(46, 125, 50, 0.35)');
        gradient.addColorStop(1, 'rgba(46, 125, 50, 0.0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chart_labels) ?>,
                datasets: [{
                    label: 'Omzet (Rp)',
                    data: <?= json_encode($chart_data) ?>,
                    borderColor: '#2E7D32',
                    borderWidth: 3,
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#2E7D32',
                    pointBorderColor: '#FFFFFF',
                    pointBorderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#2D3748',
                        titleFont: { family: 'Plus Jakarta Sans', size: 12 },
                        bodyFont: { family: 'Plus Jakarta Sans', size: 13, weight: 'bold' },
                        padding: 10,
                        cornerRadius: 10,
                        callbacks: {
                            label: function(context) {
                                return 'Rp ' + context.parsed.y.toLocaleString('id-ID');
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Plus Jakarta Sans', size: 10 }, color: '#9CA3AF', maxRotation: 0, autoSkip: true, maxTicksLimit: 12 }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#F3F4F6' },
                        ticks: {
                            font: { family: 'Plus Jakarta Sans', size: 11 },
                            color: '#9CA3AF',
                            callback: function(value) {
                                if (value >= 1000000) return 'Rp ' + (value / 1000000).toFixed(1) + ' Jt';
                                if (value >= 1000) return 'Rp ' + (value / 1000).toFixed(0) + ' Rb';
                                return 'Rp ' + value;
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html> 