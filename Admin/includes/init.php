<?php
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
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function is_ajax_request(): bool {
    return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
        || isset($_GET['ajax']) || isset($_POST['ajax']);
}

function json_out(array $data, int $status = 200): void {
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

// ---------------------------------------------------------
// GUARD STAF (Bukan Pelanggan)
// ---------------------------------------------------------
// Cek apakah user login & rolenya bukan 'customer'
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') === 'customer' || ($_SESSION['role'] ?? '') === '') {
    if (is_ajax_request()) {
        json_out(['success' => false, 'status' => 'error', 'message' => 'Sesi berakhir atau akses ditolak.'], 401);
    }
    $naik = (basename(dirname($_SERVER['SCRIPT_NAME'])) === 'export') ? '../../' : '../';
    
    // Jika pelanggan nyasar ke Admin, kembalikan ke dashboard pelanggan
    if (($_SESSION['role'] ?? '') === 'customer') {
        header('Location: ' . $naik . 'Pelanggan/dashboard.php');
    } else {
        header('Location: ' . $naik . 'Auth/login.php');
    }
    exit;
}

$admin_id     = (int)$_SESSION['user_id'];
$admin_nama   = $_SESSION['nama_user'] ?? 'Staf';
$current_page = basename($_SERVER['PHP_SELF']);

// ---------------------------------------------------------
// LOAD ROLE HELPER
// ---------------------------------------------------------
require_once __DIR__ . '/auth.php';

// ---------------------------------------------------------
// CSRF
// ---------------------------------------------------------
if (empty($_SESSION['csrf_admin'])) {
    $_SESSION['csrf_admin'] = bin2hex(random_bytes(16));
}
function csrf_token(): string { return $_SESSION['csrf_admin']; }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_ok(): bool {
    $t = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($t) && hash_equals($_SESSION['csrf_admin'], $t);
}

// ---------------------------------------------------------
// PENGATURAN TOKO
// ---------------------------------------------------------
$data_toko = [];
try {
    $qToko = mysqli_query($conn, "SELECT * FROM pengaturan WHERE id = 1 LIMIT 1");
    if ($qToko) $data_toko = mysqli_fetch_assoc($qToko) ?: [];
} catch (Throwable $t) { $data_toko = []; }
$nama_toko    = trim($data_toko['nama_toko']   ?? '') ?: 'PlantHub';
$nama_cabang  = $data_toko['nama_cabang']      ?? 'Cabang Utama';
$no_telepon   = $data_toko['no_telepon']       ?? '';
$email_toko   = $data_toko['email_toko']       ?? '';
$alamat_toko  = $data_toko['alamat_toko']      ?? '';
$catatan_nota = $data_toko['catatan_nota']     ?? '';
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
function js_attr($v): string { return htmlspecialchars(json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8'); }
function valid_date($s, string $default): string { $d = DateTime::createFromFormat('Y-m-d', (string)$s); return ($d && $d->format('Y-m-d') === $s) ? $s : $default; }
function esc_sql($v): string { global $conn; return mysqli_real_escape_string($conn, (string)$v); }
function table_exists(string $t): bool { global $conn; $t = mysqli_real_escape_string($conn, $t); $r = mysqli_query($conn, "SHOW TABLES LIKE '$t'"); return $r && mysqli_num_rows($r) > 0; }
function column_exists(string $t, string $c): bool { global $conn; $t = preg_replace('/[^a-zA-Z0-9_]/', '', $t); $c = mysqli_real_escape_string($conn, $c); $r = mysqli_query($conn, "SHOW COLUMNS FROM `$t` LIKE '$c'"); return $r && mysqli_num_rows($r) > 0; }

function brand_html(): string { global $nama_toko; if (strcasecmp($nama_toko, 'PlantHub') === 0) { return 'Plant<span class="text-[#2E7D32]">Hub</span>'; } return e($nama_toko); }
function brand_icon_html(): string {
    global $logo_url;
    if ($logo_url !== '') { return '<div class="w-9 h-9 rounded-xl bg-white flex items-center justify-center shadow-sm overflow-hidden border border-stone-100"><img src="' . e($logo_url) . '" alt="Logo" class="w-full h-full object-cover"></div>'; }
    return '<div class="w-9 h-9 rounded-xl bg-[#2E7D32] flex items-center justify-center text-white shadow-sm"><i data-lucide="sprout" class="w-5 h-5"></i></div>';
}
function logout_btn_html(): string { return '<a href="../Auth/logout.php" onclick="return confirm(\'Keluar dari sistem?\');" title="Keluar" class="p-2 text-stone-500 hover:text-rose-600 hover:bg-rose-50 rounded-full"><i data-lucide="log-out" class="w-5 h-5"></i></a>'; }
function badge_status(string $status): string {
    switch ($status) {
        case 'Diproses': return 'bg-amber-50 text-amber-700 border-amber-200';
        case 'Dikirim':  return 'bg-blue-50 text-blue-700 border-blue-200';
        case 'Selesai':  return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'Batal':    return 'bg-rose-50 text-rose-700 border-rose-200';
        default:         return 'bg-stone-100 text-stone-600 border-stone-200';
    }
}
const SQL_NOT_BATAL = "(status IS NULL OR status <> 'Batal')";