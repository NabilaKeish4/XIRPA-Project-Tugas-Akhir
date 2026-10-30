<?php
/**
 * PlantHub - Admin/includes/init.php
 * Dipanggil di baris pertama SEMUA halaman Admin:
 *     require_once __DIR__ . '/includes/init.php';
 *
 * Isi:
 *  1. session + koneksi database ($conn)
 *  2. guard khusus role admin
 *  3. variabel global: $admin_id, $admin_nama, $current_page
 *  4. pengaturan toko (tabel `pengaturan` id = 1) -> $nama_toko, $logo_toko, dst
 *  5. helper kecil (e, rupiah, tanggal, csrf, json_out, dst)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../Config/database.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    exit('Koneksi database ($conn) tidak ditemukan. Periksa Config/database.php.');
}

mysqli_set_charset($conn, 'utf8mb4');
date_default_timezone_set('Asia/Jakarta');

// PHP 8.1+ melempar exception pada error SQL; kita tangkap di handler global di bawah.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ---------------------------------------------------------
// HELPER AJAX / JSON
// ---------------------------------------------------------
function is_ajax_request(): bool
{
    return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
        || isset($_GET['ajax']) || isset($_POST['ajax']);
}

function json_out(array $data, int $status = 200): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

// Error SQL / PHP yang tidak tertangkap -> pesan rapi (bukan layar putih)
set_exception_handler(function (Throwable $ex) {
    error_log('[PlantHub Admin] ' . $ex->getMessage() . ' @ ' . $ex->getFile() . ':' . $ex->getLine());
    if (is_ajax_request()) {
        json_out(['success' => false, 'status' => 'error', 'message' => 'Terjadi kesalahan pada server. Periksa log PHP.'], 500);
    }
    http_response_code(500);
    echo '<!DOCTYPE html><meta charset="utf-8"><body style="font-family:sans-serif;background:#F9F8F6;padding:40px">'
       . '<div style="max-width:520px;margin:auto;background:#fff;border:1px solid #e7e5e4;border-radius:16px;padding:24px">'
       . '<h2 style="color:#2E7D32;margin:0 0 8px">Terjadi kesalahan</h2>'
       . '<p style="color:#57534e;font-size:14px">Halaman tidak dapat dimuat. Pastikan struktur database sesuai (lihat cek_integrasi.php) lalu coba lagi.</p>'
       . '<p style="color:#a8a29e;font-size:12px">' . htmlspecialchars($ex->getMessage()) . '</p>'
       . '<a href="dashboard.php" style="color:#2E7D32;font-weight:bold;font-size:14px">&larr; Kembali ke Dashboard</a></div>';
    exit;
});

// ---------------------------------------------------------
// GUARD ADMIN
// ---------------------------------------------------------
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    if (is_ajax_request()) {
        json_out(['success' => false, 'status' => 'error', 'message' => 'Sesi berakhir, silakan login ulang.'], 401);
    }
    // halaman di Admin/ -> ../Auth ; halaman di Admin/export/ -> ../../Auth
    $naik = (basename(dirname($_SERVER['SCRIPT_NAME'])) === 'export') ? '../../' : '../';
    header('Location: ' . $naik . 'Auth/login.php');
    exit;
}

$admin_id     = (int)$_SESSION['user_id'];
$admin_nama   = $_SESSION['nama_user'] ?? 'Admin';
$current_page = basename($_SERVER['PHP_SELF']);

// ---------------------------------------------------------
// CSRF (token per sesi) - dipakai lewat csrf_field() / csrf_check()
// ---------------------------------------------------------
if (empty($_SESSION['csrf_admin'])) {
    $_SESSION['csrf_admin'] = bin2hex(random_bytes(16));
}
function csrf_token(): string { return $_SESSION['csrf_admin']; }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_ok(): bool
{
    $t = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($t) && hash_equals($_SESSION['csrf_admin'], $t);
}

// ---------------------------------------------------------
// PENGATURAN TOKO (dipakai topbar, sidebar, struk, PDF)
// ---------------------------------------------------------
$data_toko = [];
try {
    $qToko = mysqli_query($conn, "SELECT * FROM pengaturan WHERE id = 1 LIMIT 1");
    if ($qToko) $data_toko = mysqli_fetch_assoc($qToko) ?: [];
} catch (Throwable $t) {
    $data_toko = [];
}
$nama_toko    = trim($data_toko['nama_toko']   ?? '') ?: 'PlantHub';
$cabang_toko  = $data_toko['cabang']           ?? '';
$kontak_toko  = $data_toko['kontak']           ?? ($data_toko['no_telp'] ?? '');
$alamat_toko  = $data_toko['alamat']           ?? '';
$logo_toko    = $data_toko['logo_toko']        ?? '';
$foto_profil  = $data_toko['foto_profil']      ?? '';
$logo_url     = (!empty($logo_toko) && $logo_toko !== 'default_logo.png' && is_file(__DIR__ . '/../../uploads/' . $logo_toko))
              ? '../uploads/' . rawurlencode($logo_toko) : '';
$avatar_url   = (!empty($foto_profil) && is_file(__DIR__ . '/../../uploads/' . $foto_profil))
              ? '../uploads/' . rawurlencode($foto_profil)
              : 'https://ui-avatars.com/api/?name=' . urlencode($admin_nama) . '&background=2E7D32&color=fff';

// ---------------------------------------------------------
// HELPER TAMPILAN
// ---------------------------------------------------------
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function rupiah($n): string { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }

/** Aman dipakai di atribut HTML onclick="fn(<?= js_attr($arr) ?>)" - apostrof / tanda kutip tidak merusak. */
function js_attr($v): string
{
    return htmlspecialchars(json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
}

/** Validasi tanggal Y-m-d; kembalikan $default bila tidak valid. */
function valid_date($s, string $default): string
{
    $d = DateTime::createFromFormat('Y-m-d', (string)$s);
    return ($d && $d->format('Y-m-d') === $s) ? $s : $default;
}

function esc_sql($v): string
{
    global $conn;
    return mysqli_real_escape_string($conn, (string)$v);
}

/** Cek apakah tabel / kolom ada (agar fitur baru tidak membuat halaman error di DB lama). */
function table_exists(string $t): bool
{
    global $conn;
    $t = mysqli_real_escape_string($conn, $t);
    $r = mysqli_query($conn, "SHOW TABLES LIKE '$t'");
    return $r && mysqli_num_rows($r) > 0;
}
function column_exists(string $t, string $c): bool
{
    global $conn;
    $t = preg_replace('/[^a-zA-Z0-9_]/', '', $t);
    $c = mysqli_real_escape_string($conn, $c);
    $r = mysqli_query($conn, "SHOW COLUMNS FROM `$t` LIKE '$c'");
    return $r && mysqli_num_rows($r) > 0;
}

/** Nama toko untuk topbar: "PlantHub" tetap tampil 2 warna seperti desain asli. */
function brand_html(): string
{
    global $nama_toko;
    if (strcasecmp($nama_toko, 'PlantHub') === 0) {
        return 'Plant<span class="text-[#2E7D32]">Hub</span>';
    }
    return e($nama_toko);
}

/** Ikon brand topbar: logo upload bila ada, jika tidak ikon sprout bawaan. */
function brand_icon_html(): string
{
    global $logo_url;
    if ($logo_url !== '') {
        return '<div class="w-9 h-9 rounded-xl bg-white flex items-center justify-center shadow-sm overflow-hidden border border-stone-100">'
             . '<img src="' . e($logo_url) . '" alt="Logo" class="w-full h-full object-cover"></div>';
    }
    return '<div class="w-9 h-9 rounded-xl bg-[#2E7D32] flex items-center justify-center text-white shadow-sm">'
         . '<i data-lucide="sprout" class="w-5 h-5"></i></div>';
}

/** Tombol keluar kecil di topbar halaman selain dashboard. */
function logout_btn_html(): string
{
    return '<a href="../Auth/logout.php" onclick="return confirm(\'Keluar dari sistem?\');" title="Keluar" '
         . 'class="p-2 text-stone-500 hover:text-rose-600 hover:bg-rose-50 rounded-full">'
         . '<i data-lucide="log-out" class="w-5 h-5"></i></a>';
}

/** Badge status transaksi (dipakai dashboard, transaksi, detail). */
function badge_status(string $status): string
{
    switch ($status) {
        case 'Diproses': return 'bg-amber-50 text-amber-700 border-amber-200';
        case 'Dikirim':  return 'bg-blue-50 text-blue-700 border-blue-200';
        case 'Selesai':  return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'Batal':    return 'bg-rose-50 text-rose-700 border-rose-200';
        default:         return 'bg-stone-100 text-stone-600 border-stone-200';
    }
}

/** Status yang dihitung sebagai pendapatan (transaksi Batal TIDAK dihitung). */
const SQL_NOT_BATAL = "(status IS NULL OR status <> 'Batal')";
