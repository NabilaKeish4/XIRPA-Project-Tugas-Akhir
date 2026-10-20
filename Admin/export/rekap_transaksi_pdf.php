<?php
session_start();
require_once '../../Config/database.php';
require_once '../../fpdf/fpdf.php';

// =========================================================
// AUTH CHECK (khusus admin)
// =========================================================
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../../Auth/login.php");
    exit;
}

$admin_nama = $_SESSION['nama_user'] ?? 'Admin';

// =========================================================
// AMBIL FILTER DARI URL (sama persis dengan transaksi.php)
// =========================================================
$search       = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterJenis  = $_GET['jenis']  ?? 'semua';
// Paksa filter jenis sesuai tab yang dikirim
if (isset($_GET['jenis']) && in_array($_GET['jenis'], ['penjualan', 'pembelian'])) {
    $filterJenis = $_GET['jenis'];
}
$filterStatus = $_GET['status'] ?? 'semua';

$range       = $_GET['range'] ?? '';
$tgl_mulai   = $_GET['tgl_mulai']   ?? date('Y-m-01');
$tgl_selesai = $_GET['tgl_selesai'] ?? date('Y-m-d');

if ($range === 'today') {
    $tgl_mulai = $tgl_selesai = date('Y-m-d');
} elseif ($range === '7days') {
    $tgl_mulai   = date('Y-m-d', strtotime('-7 days'));
    $tgl_selesai = date('Y-m-d');
} elseif ($range === '30days') {
    $tgl_mulai   = date('Y-m-d', strtotime('-30 days'));
    $tgl_selesai = date('Y-m-d');
} elseif ($range === 'all') {
    $tgl_mulai = $tgl_selesai = null;
} elseif ($range === 'custom') {
    // pakai input
} else {
    $tgl_mulai   = date('Y-m-01');
    $tgl_selesai = date('Y-m-d');
}

$allowedJenis  = ['semua', 'penjualan', 'pembelian'];
$allowedStatus = ['semua', 'Diproses', 'Dikirim', 'Selesai', 'Batal'];
if (!in_array($filterJenis, $allowedJenis))   $filterJenis  = 'semua';
if (!in_array($filterStatus, $allowedStatus)) $filterStatus = 'semua';

// =========================================================
// BUILD WHERE
// =========================================================
$where = "WHERE 1=1";

if ($filterJenis !== 'semua') {
    $jenisEsc = mysqli_real_escape_string($conn, $filterJenis);
    $where .= " AND t.jenis_transaksi = '$jenisEsc'";
}
if ($filterStatus !== 'semua') {
    $statusEsc = mysqli_real_escape_string($conn, $filterStatus);
    $where .= " AND t.status = '$statusEsc'";
}
if ($search !== '') {
    $searchEsc = mysqli_real_escape_string($conn, $search);
    $where .= " AND (t.kode_transaksi LIKE '%$searchEsc%' OR t.nama_penerima LIKE '%$searchEsc%' OR u.nama_lengkap LIKE '%$searchEsc%')";
}
if ($tgl_mulai !== null && $tgl_selesai !== null) {
    $mulaiEsc   = mysqli_real_escape_string($conn, $tgl_mulai);
    $selesaiEsc = mysqli_real_escape_string($conn, $tgl_selesai);
    $where .= " AND DATE(t.created_at) BETWEEN '$mulaiEsc' AND '$selesaiEsc'";
}

// =========================================================
// QUERY DATA (join supplier biar akurat)
// =========================================================
$qData = mysqli_query($conn, "
    SELECT t.kode_transaksi, 
           COALESCE(u.nama_lengkap, s.nama_supplier, 'Walk-in') AS pelanggan,
           COALESCE(t.nama_penerima, '-') AS penerima,
           t.created_at, t.jenis_transaksi, t.status, 
           (SELECT COUNT(*) FROM transaksi_detail td WHERE td.transaksi_id = t.id) AS jml_item,
           t.metode_pembayaran, t.total_harga
    FROM transaksi t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN supplier s ON t.supplier_id = s.id
    $where
    ORDER BY t.created_at DESC
");

// =========================================================
// AMBIL DATA TOKO
// =========================================================
$data_toko = null;
$qToko = mysqli_query($conn, "SELECT * FROM pengaturan WHERE id = 1 LIMIT 1");
if ($qToko && mysqli_num_rows($qToko) > 0) $data_toko = mysqli_fetch_assoc($qToko);

$nama_toko   = $data_toko['nama_toko']   ?? 'PlantHub';
$nama_cabang = $data_toko['nama_cabang'] ?? 'Cabang Utama';
$no_telepon  = $data_toko['no_telepon']  ?? '-';
$email_toko  = $data_toko['email_toko']  ?? '-';
$alamat_toko = $data_toko['alamat_toko'] ?? '-';
$logo_toko   = $data_toko['logo_toko']   ?? 'default_logo.png';

$logo_path = '../../uploads/' . $logo_toko;
$logo_ada  = (!empty($logo_toko) && $logo_toko !== 'default_logo.png' && file_exists($logo_path));

// =========================================================
// INISIALISASI FPDF (A4 Landscape)
// =========================================================
$pdf = new FPDF('L', 'mm', 'A4');
$pdf->SetAutoPageBreak(true, 18);
$pdf->SetMargins(12, 12, 12);
$pdf->AddPage();

// =========================================================
// HEADER (Logo + Info Toko + Judul)
// =========================================================
$x_awal = 12;
$y_awal = 12;

// --- LOGO (kiri) ---
if ($logo_ada) {
    $pdf->Image($logo_path, $x_awal, $y_awal, 18, 18);
    $text_x = $x_awal + 22;
} else {
    $text_x = $x_awal;
}

// --- INFO TOKO (tengah kiri) ---
$pdf->SetXY($text_x, $y_awal);
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(100, 6, strtoupper($nama_toko), 0, 2, 'L');

$pdf->SetFont('Arial', '', 9);
$pdf->SetX($text_x);
$pdf->Cell(100, 4, $nama_cabang, 0, 2, 'L');
$pdf->SetX($text_x);
$pdf->Cell(100, 4, $alamat_toko, 0, 2, 'L');
$pdf->SetX($text_x);
$pdf->Cell(100, 4, 'Telp: ' . $no_telepon . '  |  Email: ' . $email_toko, 0, 2, 'L');

// --- JUDUL (kanan atas) ---
$pdf->SetXY(-100, $y_awal);
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(88, 8, 'REKAP TRANSAKSI', 0, 2, 'R');

$pdf->SetFont('Arial', '', 9);
$pdf->SetX(-100);
$periode_text = ($tgl_mulai && $tgl_selesai) 
    ? date('d/m/Y', strtotime($tgl_mulai)) . ' s.d. ' . date('d/m/Y', strtotime($tgl_selesai))
    : 'Semua Periode';
$pdf->Cell(88, 4, 'Periode: ' . $periode_text, 0, 2, 'R');
$pdf->SetX(-100);
$pdf->Cell(88, 4, 'Dicetak: ' . date('d/m/Y H:i') . ' WIB', 0, 2, 'R');
$pdf->SetX(-100);
$pdf->Cell(88, 4, 'Oleh: ' . $admin_nama, 0, 2, 'R');

// --- GARIS PEMISAH ---
$pdf->SetY(max($pdf->GetY(), $y_awal + 20) + 4);
$pdf->SetDrawColor(46, 125, 50); // hijau
$pdf->SetLineWidth(0.8);
$pdf->Line(12, $pdf->GetY(), 285, $pdf->GetY());
$pdf->SetLineWidth(0.2);
$pdf->SetDrawColor(0, 0, 0);
$pdf->Ln(4);

// =========================================================
// FILTER AKTIF (info kecil)
// =========================================================
$filterInfo = [];
if ($filterJenis !== 'semua')  $filterInfo[] = 'Jenis: ' . ucfirst($filterJenis);
if ($filterStatus !== 'semua') $filterInfo[] = 'Status: ' . $filterStatus;
if ($search !== '')            $filterInfo[] = 'Pencarian: "' . $search . '"';

if (!empty($filterInfo)) {
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 4, 'Filter aktif — ' . implode('  |  ', $filterInfo), 0, 1, 'L');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(2);
}

// =========================================================
// TABEL HEADER
// =========================================================
$w_no    = 10;
$w_kode  = 38;
$w_pel   = 55;
$w_tgl   = 34;
$w_jenis = 22;
$w_stat  = 22;
$w_item  = 15;
$w_total = 35;
$total_w = $w_no + $w_kode + $w_pel + $w_tgl + $w_jenis + $w_stat + $w_item + $w_total; // 231

function headerTabel($pdf, $w_no, $w_kode, $w_pel, $w_tgl, $w_jenis, $w_stat, $w_item, $w_total) {
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(46, 125, 50);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetDrawColor(46, 125, 50);
    
    $pdf->Cell($w_no,    8, 'No', 1, 0, 'C', true);
    $pdf->Cell($w_kode,  8, 'Kode Transaksi', 1, 0, 'C', true);
    $pdf->Cell($w_pel,   8, 'Pelanggan / Supplier', 1, 0, 'C', true);
    $pdf->Cell($w_tgl,   8, 'Tanggal', 1, 0, 'C', true);
    $pdf->Cell($w_jenis, 8, 'Jenis', 1, 0, 'C', true);
    $pdf->Cell($w_stat,  8, 'Status', 1, 0, 'C', true);
    $pdf->Cell($w_item,  8, 'Item', 1, 0, 'C', true);
    $pdf->Cell($w_total, 8, 'Total (Rp)', 1, 1, 'C', true);
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetDrawColor(0, 0, 0);
}

headerTabel($pdf, $w_no, $w_kode, $w_pel, $w_tgl, $w_jenis, $w_stat, $w_item, $w_total);

// =========================================================
// TABEL BODY
// =========================================================
$pdf->SetFont('Arial', '', 8);
$no = 1;
$grand_total = 0;
$total_trx   = 0;

function warnaStatus($status) {
    switch ($status) {
        case 'Diproses': return [255, 243, 205];
        case 'Dikirim':  return [219, 234, 254];
        case 'Selesai':  return [209, 250, 229];
        case 'Batal':    return [254, 226, 226];
        default:         return [245, 245, 245];
    }
}

if ($qData && mysqli_num_rows($qData) > 0) {
    while ($r = mysqli_fetch_assoc($qData)) {
        $grand_total += (float)$r['total_harga'];
        $total_trx++;
        
        // Cek page break manual
        if ($pdf->GetY() > 180) {
            $pdf->AddPage();
            headerTabel($pdf, $w_no, $w_kode, $w_pel, $w_tgl, $w_jenis, $w_stat, $w_item, $w_total);
            $pdf->SetFont('Arial', '', 8);
        }
        
        $fill = ($no % 2 === 0);
        $pdf->SetFillColor(248, 250, 252);
        
        $pdf->Cell($w_no,    6, $no, 1, 0, 'C', $fill);
        $pdf->Cell($w_kode,  6, $r['kode_transaksi'], 1, 0, 'L', $fill);
        $pdf->Cell($w_pel,   6, substr($r['pelanggan'], 0, 32), 1, 0, 'L', $fill);
        $pdf->Cell($w_tgl,   6, date('d/m/Y H:i', strtotime($r['created_at'])), 1, 0, 'C', $fill);
        $pdf->Cell($w_jenis, 6, ucfirst($r['jenis_transaksi']), 1, 0, 'C', $fill);
        
        // Status dengan warna
        $rgb = warnaStatus($r['status']);
        $pdf->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
        $pdf->Cell($w_stat, 6, $r['status'], 1, 0, 'C', true);
        $pdf->SetFillColor(248, 250, 252);
        
        $pdf->Cell($w_item,  6, (int)$r['jml_item'], 1, 0, 'C', $fill);
        $pdf->Cell($w_total, 6, number_format($r['total_harga'], 0, ',', '.'), 1, 1, 'R', $fill);
        
        $no++;
    }
} else {
    $pdf->Cell($total_w, 10, 'Tidak ada data transaksi pada periode/filter ini.', 1, 1, 'C');
}

// =========================================================
// FOOTER (Grand Total)
// =========================================================
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(232, 245, 233);
$pdf->SetTextColor(46, 125, 50);

$pdf->Cell($w_no + $w_kode + $w_pel + $w_tgl + $w_jenis + $w_stat + $w_item, 8, 
           'GRAND TOTAL (' . $total_trx . ' transaksi)', 1, 0, 'R', true);
$pdf->Cell($w_total, 8, number_format($grand_total, 0, ',', '.'), 1, 1, 'R', true);

$pdf->SetTextColor(0, 0, 0);

// =========================================================
// OUTPUT PDF
// =========================================================
$filename = 'rekap_transaksi_' . date('Ymd_His') . '.pdf';
$pdf->Output('I', $filename); // 'I' = tampil di browser, 'D' = force download
exit;