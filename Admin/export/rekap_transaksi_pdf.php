<?php
/**
 * Admin/export/rekap_transaksi_pdf.php
 * Dipanggil dari transaksi.php (tombol "Export PDF") dengan parameter filter yang sama:
 *   ?jenis=penjualan|pembelian &status= &search= &range= &tgl_mulai= &tgl_selesai=
 */
ob_start(); // tahan output liar (warning/notice) agar file PDF tidak rusak
require_once __DIR__ . '/../includes/init.php';

$fpdfPath = __DIR__ . '/../../fpdf/fpdf.php';
if (!is_file($fpdfPath)) {
    http_response_code(500);
    exit('Library FPDF tidak ditemukan di ../fpdf/fpdf.php');
}
require_once $fpdfPath;

$T = function ($v) { return mb_convert_encoding((string)$v, 'ISO-8859-1', 'UTF-8'); };

// ---------------- FILTER (sama dengan transaksi.php) ----------------
$jenis = $_GET['jenis'] ?? ($_GET['tab'] ?? 'penjualan');
if (!in_array($jenis, ['penjualan', 'pembelian'], true)) $jenis = 'penjualan';

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? 'semua';
if (!in_array($status, ['semua', 'Diproses', 'Dikirim', 'Selesai', 'Batal'], true)) $status = 'semua';

$range       = $_GET['range'] ?? '';
$tgl_mulai   = valid_date($_GET['tgl_mulai']   ?? '', date('Y-m-01'));
$tgl_selesai = valid_date($_GET['tgl_selesai'] ?? '', date('Y-m-d'));
if ($range === 'today')      { $tgl_mulai = $tgl_selesai = date('Y-m-d'); }
elseif ($range === '7days')  { $tgl_mulai = date('Y-m-d', strtotime('-6 days'));  $tgl_selesai = date('Y-m-d'); }
elseif ($range === '30days') { $tgl_mulai = date('Y-m-d', strtotime('-29 days')); $tgl_selesai = date('Y-m-d'); }
elseif ($range === 'all')    { $tgl_mulai = $tgl_selesai = null; }
elseif ($range !== 'custom') { $tgl_mulai = date('Y-m-01'); $tgl_selesai = date('Y-m-d'); }
if ($tgl_mulai !== null && $tgl_mulai > $tgl_selesai) { $x = $tgl_mulai; $tgl_mulai = $tgl_selesai; $tgl_selesai = $x; }

$where = "WHERE t.jenis_transaksi = '$jenis'";
if ($status !== 'semua') $where .= " AND t.status = '" . esc_sql($status) . "'";
if ($search !== '') {
    $like = esc_sql(str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search));
    $where .= ($jenis === 'penjualan')
        ? " AND (t.kode_transaksi LIKE '%$like%' OR t.nama_penerima LIKE '%$like%' OR u.nama_lengkap LIKE '%$like%')"
        : " AND (t.kode_transaksi LIKE '%$like%' OR s.nama_supplier LIKE '%$like%')";
}
if ($tgl_mulai !== null) {
    $where .= " AND DATE(t.created_at) BETWEEN '" . esc_sql($tgl_mulai) . "' AND '" . esc_sql($tgl_selesai) . "'";
}

$rows = [];
$q = mysqli_query($conn, "
    SELECT t.*, 
           COALESCE(u.nama_lengkap, t.nama_penerima, 'Pelanggan Walk-in') AS nama_pelanggan,
           COALESCE(s.nama_supplier, 'Supplier Tidak Diketahui') AS nama_supplier
    FROM transaksi t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN supplier s ON t.supplier_id = s.id
    $where
    ORDER BY t.created_at DESC
");
if ($q) while ($r = mysqli_fetch_assoc($q)) $rows[] = $r;

// ---------------- PDF ----------------
$pdf = new FPDF('P', 'mm', 'A4');
$pdf->SetAutoPageBreak(true, 20);
$pdf->SetMargins(15, 15, 15);
$pdf->AddPage();

$logoPath = $logo_url !== '' ? __DIR__ . '/../../uploads/' . $logo_toko : '';
$textX = 15;
if ($logoPath !== '' && is_file($logoPath) && preg_match('/\.(png|jpe?g)$/i', $logoPath)
    && (preg_match('/\.jpe?g$/i', $logoPath) || extension_loaded('gd'))) {
    $pdf->Image($logoPath, 15, 15, 18, 18);
    $textX = 37;
}

$pdf->SetXY($textX, 15);
$pdf->SetFont('Arial', 'B', 16);
$pdf->SetTextColor(46, 125, 50);
$pdf->Cell(100, 7, $T(strtoupper($nama_toko)), 0, 2, 'L');
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(100, 100, 100);
$pdf->SetX($textX);
$pdf->Cell(100, 4, $T($nama_cabang . ' | ' . ($alamat_toko ?: '-')), 0, 2, 'L');

$pdf->SetXY(-85, 15);
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(70, 7, 'REKAP ' . strtoupper($jenis), 0, 2, 'R');
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(100, 100, 100);
$pdf->SetX(-85);
$periode = $tgl_mulai === null ? 'Semua periode' : date('d/m/Y', strtotime($tgl_mulai)) . ' - ' . date('d/m/Y', strtotime($tgl_selesai));
$pdf->Cell(70, 4, 'Periode: ' . $periode, 0, 2, 'R');
$pdf->SetX(-85);
$pdf->Cell(70, 4, 'Dicetak: ' . date('d/m/Y H:i') . ' WIB', 0, 2, 'R');

$pdf->SetY(35);
$pdf->SetDrawColor(46, 125, 50);
$pdf->SetLineWidth(1);
$pdf->Line(15, 35, 195, 35);
$pdf->Ln(8);

function rekapHeader($pdf, $jenis)
{
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(46, 125, 50);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetDrawColor(46, 125, 50);
    $pdf->Cell(8, 8, 'No', 1, 0, 'C', true);
    $pdf->Cell(38, 8, 'Kode Transaksi', 1, 0, 'C', true);
    $pdf->Cell(28, 8, 'Tanggal', 1, 0, 'C', true);
    $pdf->Cell(52, 8, $jenis === 'penjualan' ? 'Pelanggan' : 'Supplier', 1, 0, 'C', true);
    $pdf->Cell(22, 8, 'Status', 1, 0, 'C', true);
    $pdf->Cell(32, 8, 'Total (Rp)', 1, 1, 'C', true);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetDrawColor(220, 220, 220);
}

rekapHeader($pdf, $jenis);
$pdf->SetFont('Arial', '', 8);

$no = 1;
$total = 0;
$batal = 0;
if (empty($rows)) {
    $pdf->Cell(180, 10, 'Tidak ada transaksi pada filter ini.', 1, 1, 'C');
} else {
    foreach ($rows as $r) {
        if ($pdf->GetY() > 265) {
            $pdf->AddPage();
            rekapHeader($pdf, $jenis);
            $pdf->SetFont('Arial', '', 8);
        }
        $fill = ($no % 2 === 0);
        $pdf->SetFillColor(248, 250, 252);
        $nama = $jenis === 'penjualan' ? $r['nama_pelanggan'] : $r['nama_supplier'];

        if ($r['status'] === 'Batal') $batal++; else $total += (float)$r['total_harga'];

        $pdf->Cell(8, 6, $no++, 1, 0, 'C', $fill);
        $pdf->Cell(38, 6, $T($r['kode_transaksi']), 1, 0, 'L', $fill);
        $pdf->Cell(28, 6, date('d/m/Y H:i', strtotime($r['created_at'])), 1, 0, 'C', $fill);
        $pdf->Cell(52, 6, $T(mb_substr($nama, 0, 30)), 1, 0, 'L', $fill);
        $pdf->Cell(22, 6, $T($r['status']), 1, 0, 'C', $fill);
        $pdf->Cell(32, 6, number_format($r['total_harga'], 0, ',', '.'), 1, 1, 'R', $fill);
    }

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(240, 248, 240);
    $pdf->SetTextColor(46, 125, 50);
    $pdf->Cell(148, 8, 'TOTAL (tanpa transaksi Batal)', 1, 0, 'R', true);
    $pdf->Cell(32, 8, number_format($total, 0, ',', '.'), 1, 1, 'R', true);
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(180, 6, count($rows) . ' transaksi' . ($batal ? " ($batal dibatalkan, tidak dihitung)" : ''), 0, 1, 'L');
}

$pdf->Ln(12);
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(130);
$pdf->Cell(50, 5, 'Dicetak Oleh,', 0, 1, 'C');
$pdf->Ln(12);
$pdf->Cell(130);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(50, 5, $T($admin_nama), 0, 1, 'C');

while (ob_get_level() > 0) ob_end_clean();
$pdf->Output('I', 'Rekap_' . ucfirst($jenis) . '_' . date('Ymd_His') . '.pdf');
