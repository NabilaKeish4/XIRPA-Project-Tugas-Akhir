<?php
/**
 * PlantHub - customer_init.php  (SATU-SATUNYA file pendukung bagian Pelanggan)
 * ---------------------------------------------------------------
 * Dipanggil oleh semua halaman Pelanggan dengan:
 *     require_once __DIR__ . '/customer_init.php';
 * Isi: session, koneksi DB, helper query, CSRF, keranjang, wishlist,
 *      komponen UI, dan kerangka halaman (header + sidebar).
 * Tidak membutuhkan folder includes/ atau partials/.
 * ===============================================================
 */

// ===== BAGIAN 1: CORE =====
/**
 * PlantHub - Bootstrap bagian Pelanggan
 * ---------------------------------------------------------------
 * Dimuat oleh semua halaman Pelanggan (lewat includes/layout.php).
 * Isi: session, koneksi DB, helper query (prepared statement),
 * CSRF, flash message, helper keranjang, wishlist, upload gambar.
 *
 * Semua akses database di halaman Pelanggan lewat fungsi db_*()
 * di file ini, sehingga seluruh query memakai prepared statement.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../Config/database.php';

if (!isset($conn)) {
    die('Koneksi database ($conn) tidak ditemukan. Periksa file Config/database.php.');
}
if ($conn instanceof mysqli) {
    mysqli_set_charset($conn, 'utf8mb4');
}

// ---------------------------------------------------------------
// KONSTANTA
// ---------------------------------------------------------------
if (!defined('PPN_RATE')) define('PPN_RATE', 0.11);
if (!defined('PLACEHOLDER_IMG')) define('PLACEHOLDER_IMG', 'https://images.unsplash.com/photo-1614594975525-e45190c55d0b?auto=format&fit=crop&q=80&w=');

// Path di server (absolut) dan URL relatif dari folder Pelanggan/
define('ROOT_DIR',        dirname(__DIR__) . '/');
define('PATH_PRODUK_IMG', ROOT_DIR . 'assets/img/');
define('URL_PRODUK_IMG',  '../assets/img/');
define('PATH_CHAT',       ROOT_DIR . 'uploads/chats/');
define('URL_CHAT',        '../uploads/chats/');
define('PATH_AVATAR',     ROOT_DIR . 'uploads/avatars/');
define('URL_AVATAR',      '../uploads/avatars/');

/** Error yang aman ditampilkan ke pengguna (pesan ramah). */
class UserError extends RuntimeException {}

// ---------------------------------------------------------------
// DATABASE HELPER (prepared statement)
// ---------------------------------------------------------------
if (!function_exists('db_all')) {

    /** Jalankan statement; lempar exception jika gagal. */
    function db_stmt(string $sql, string $types = '', array $params = []): mysqli_stmt {
        global $conn;
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            throw new RuntimeException('Query gagal disiapkan: ' . mysqli_error($conn));
        }
        if ($types !== '') {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        if (!mysqli_stmt_execute($stmt)) {
            throw new RuntimeException('Query gagal dijalankan: ' . mysqli_stmt_error($stmt));
        }
        return $stmt;
    }

    /** SELECT banyak baris. Return [] bila gagal (error dicatat di log). */
    function db_all(string $sql, string $types = '', array $params = []): array {
        try {
            $stmt = db_stmt($sql, $types, $params);
            $res  = mysqli_stmt_get_result($stmt);
            $rows = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
            mysqli_stmt_close($stmt);
            return $rows;
        } catch (Throwable $e) {
            error_log('[PlantHub db_all] ' . $e->getMessage());
            return [];
        }
    }

    /** SELECT satu baris atau null. */
    function db_one(string $sql, string $types = '', array $params = []): ?array {
        $rows = db_all($sql, $types, $params);
        return $rows[0] ?? null;
    }

    /** SELECT satu nilai (kolom pertama baris pertama). */
    function db_val(string $sql, string $types = '', array $params = [], $default = 0) {
        $row = db_one($sql, $types, $params);
        return $row ? reset($row) : $default;
    }

    /** INSERT/UPDATE/DELETE. Return jumlah baris terpengaruh. Lempar exception bila gagal. */
    function db_exec(string $sql, string $types = '', array $params = []): int {
        $stmt = db_stmt($sql, $types, $params);
        $n = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        return (int)$n;
    }

    /** INSERT dan kembalikan ID baru. */
    function db_insert(string $sql, string $types = '', array $params = []): int {
        global $conn;
        $stmt = db_stmt($sql, $types, $params);
        mysqli_stmt_close($stmt);
        return (int)mysqli_insert_id($conn);
    }

    function db_begin(): void    { global $conn; mysqli_begin_transaction($conn); }
    function db_commit(): void   { global $conn; mysqli_commit($conn); }
    function db_rollback(): void { global $conn; mysqli_rollback($conn); }
}

// ---------------------------------------------------------------
// FORMAT & UMUM
// ---------------------------------------------------------------
function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function rupiah($n): string {
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

function tgl_id($ts, bool $jam = false): string {
    $t = is_numeric($ts) ? (int)$ts : strtotime((string)$ts);
    if (!$t) return '-';
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $s = date('d', $t) . ' ' . $bulan[(int)date('n', $t)] . ' ' . date('Y', $t);
    return $jam ? $s . ', ' . date('H:i', $t) . ' WIB' : $s;
}

function hari_lengkap(?int $t = null): string {
    $t = $t ?? time();
    $hari  = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return $hari[(int)date('w', $t)] . ', ' . date('j', $t) . ' ' . $bulan[(int)date('n', $t)] . ' ' . date('Y', $t);
}

function waktu_lalu($ts): string {
    $t = is_numeric($ts) ? (int)$ts : strtotime((string)$ts);
    if (!$t) return '-';
    $d = time() - $t;
    if ($d < 60)     return 'Baru saja';
    if ($d < 3600)   return floor($d / 60) . ' menit lalu';
    if ($d < 86400)  return floor($d / 3600) . ' jam lalu';
    if ($d < 604800) return floor($d / 86400) . ' hari lalu';
    return tgl_id($t, true);
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function json_out(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function is_post(): bool {
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

function is_ajax(): bool {
    return (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') || (($_POST['ajax'] ?? '') === '1');
}

// ---------------------------------------------------------------
// AUTH
// ---------------------------------------------------------------
function require_customer(): void {
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
        redirect('../Auth/login.php');
    }
}

function user_id(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

function current_user(bool $refresh = false): array {
    static $cache = null;
    if ($cache === null || $refresh) {
        $cache = db_one('SELECT * FROM users WHERE id = ? LIMIT 1', 'i', [user_id()]) ?? [];
    }
    return $cache;
}

function nama_user(): string {
    return $_SESSION['nama_user'] ?? (current_user()['nama_lengkap'] ?? 'Pelanggan');
}

function avatar_url(?array $u = null, int $size = 128): string {
    $u    = $u ?? current_user();
    $foto = $u['foto_profil'] ?? '';
    if ($foto && $foto !== 'default_avatar.jpg' && is_file(PATH_AVATAR . $foto)) {
        return URL_AVATAR . $foto;
    }
    return 'https://ui-avatars.com/api/?name=' . urlencode(nama_user()) . '&background=2E7D32&color=fff&size=' . $size;
}

function avatar_is_custom(?array $u = null): bool {
    $u    = $u ?? current_user();
    $foto = $u['foto_profil'] ?? '';
    return $foto && $foto !== 'default_avatar.jpg' && is_file(PATH_AVATAR . $foto);
}

// ---------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool {
    $t = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($t) && $t !== '' && hash_equals($_SESSION['csrf'] ?? '', $t);
}

/** Hentikan request POST bila token CSRF salah. */
function csrf_require(?string $back = null): void {
    if (csrf_valid()) return;
    if (is_ajax()) {
        json_out(['status' => 'error', 'message' => 'Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.'], 419);
    }
    flash('error', 'Sesi kedaluwarsa. Silakan ulangi aksi Anda.');
    redirect($back ?? basename($_SERVER['PHP_SELF']));
}

// ---------------------------------------------------------------
// FLASH MESSAGE (ditampilkan sebagai toast oleh layout)
// ---------------------------------------------------------------
function flash(string $type, string $msg): void {
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flash_pull(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// ---------------------------------------------------------------
// PRODUK
// ---------------------------------------------------------------
function produk_img(?string $file, int $w = 400): string {
    if ($file && $file !== 'default.jpg' && is_file(PATH_PRODUK_IMG . $file)) {
        return URL_PRODUK_IMG . $file;
    }
    return PLACEHOLDER_IMG . $w;
}

/** Produk terlaris [produk_id => total_terjual] (top 5, minimal terjual 3). */
function best_seller_ids(): array {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $rows = db_all("
            SELECT td.produk_id, SUM(td.jumlah) AS total
            FROM transaksi_detail td
            JOIN transaksi t ON td.transaksi_id = t.id
            WHERE t.status = 'Selesai' AND t.jenis_transaksi = 'penjualan'
            GROUP BY td.produk_id
            HAVING SUM(td.jumlah) >= 3
            ORDER BY total DESC
            LIMIT 5
        ");
        foreach ($rows as $r) $cache[(int)$r['produk_id']] = (int)$r['total'];
    }
    return $cache;
}

// ---------------------------------------------------------------
// KERANJANG
// ---------------------------------------------------------------
function cart_get(): array {
    $raw   = $_SESSION['cart'] ?? [];
    $clean = [];
    if (is_array($raw)) {
        foreach ($raw as $id => $q) {
            $id = (int)$id; $q = (int)$q;
            if ($id > 0 && $q > 0) $clean[$id] = $q;
        }
    }
    $_SESSION['cart'] = $clean;
    return $clean;
}

function cart_count(): int {
    return (int)array_sum(cart_get());
}

/** Tambah ke keranjang. Return: added | overstock | habis | notfound | invalid */
function cart_add(int $id, int $qty = 1): string {
    $qty = max(1, $qty);
    if ($id <= 0) return 'invalid';

    $row = db_one('SELECT stok FROM produk WHERE id = ? LIMIT 1', 'i', [$id]);
    if (!$row) return 'notfound';

    $stok = (int)$row['stok'];
    if ($stok <= 0) return 'habis';

    $cart  = cart_get();
    $total = ($cart[$id] ?? 0) + $qty;
    if ($total > $stok) {
        $_SESSION['cart'][$id] = $stok;
        return 'overstock';
    }
    $_SESSION['cart'][$id] = $total;
    return 'added';
}

/**
 * Bangun rincian item dari array [produk_id => qty] dengan validasi stok real-time.
 * Tidak mengubah session. Return:
 *  items, subtotal, count, adjusted[], empty[], missing[]
 */
function cart_lines(array $cart): array {
    $out = ['items' => [], 'subtotal' => 0.0, 'count' => 0, 'adjusted' => [], 'empty' => [], 'missing' => []];
    if (!$cart) return $out;

    $ids  = array_map('intval', array_keys($cart));
    $ph   = implode(',', array_fill(0, count($ids), '?'));
    $rows = db_all("
        SELECT p.*, k.nama_kategori
        FROM produk p
        LEFT JOIN kategori k ON p.kategori_id = k.id
        WHERE p.id IN ($ph)
    ", str_repeat('i', count($ids)), $ids);

    $found = [];
    foreach ($rows as $r) {
        $pid = (int)$r['id'];
        if (!isset($cart[$pid])) continue;
        $found[$pid] = true;

        $qtyAsal = (int)$cart[$pid];
        $stok    = (int)$r['stok'];
        $harga   = (float)$r['harga_jual'];
        $status  = 'ok';
        $qty     = $qtyAsal;

        if ($stok <= 0) {
            $status = 'empty';
            $qty    = 0;
            $out['empty'][] = $r['nama_tanaman'];
        } elseif ($qtyAsal > $stok) {
            $status = 'adjusted';
            $qty    = $stok;
            $out['adjusted'][] = ['nama' => $r['nama_tanaman'], 'dari' => $qtyAsal, 'jadi' => $stok];
        }

        $sub = $harga * $qty;
        $out['subtotal'] += $sub;
        $out['count']    += $qty;
        $out['items'][] = [
            'id'           => $pid,
            'nama'         => $r['nama_tanaman'],
            'kategori'     => $r['nama_kategori'] ?? 'Tanaman',
            'harga'        => $harga,
            'qty'          => $qty,
            'qty_asal'     => $qtyAsal,
            'subtotal'     => $sub,
            'stok'         => $stok,
            'stok_minimal' => (int)($r['stok_minimal'] ?? 0),
            'gambar'       => produk_img($r['gambar'] ?? '', 400),
            'status'       => $status,
        ];
    }

    foreach ($ids as $pid) {
        if (!isset($found[$pid])) $out['missing'][] = $pid;
    }

    // Pertahankan urutan penambahan di keranjang
    $order = array_flip($ids);
    usort($out['items'], fn($a, $b) => $order[$a['id']] <=> $order[$b['id']]);

    return $out;
}

function hitung_total(float $subtotal): array {
    $ppn = round($subtotal * PPN_RATE, 2);
    return ['subtotal' => $subtotal, 'ppn' => $ppn, 'total' => $subtotal + $ppn];
}

// ---------------------------------------------------------------
// WISHLIST (tabel dibuat otomatis bila belum ada)
// ---------------------------------------------------------------
function ensure_wishlist_table(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db_exec("CREATE TABLE IF NOT EXISTS wishlist (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            produk_id INT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_produk (user_id, produk_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {
        error_log('[PlantHub wishlist] ' . $e->getMessage());
    }
}

/** Return [produk_id => true] */
function wishlist_ids(?int $uid = null): array {
    static $cache = [];
    $uid = $uid ?? user_id();
    if (!isset($cache[$uid])) {
        ensure_wishlist_table();
        $cache[$uid] = [];
        foreach (db_all('SELECT produk_id FROM wishlist WHERE user_id = ?', 'i', [$uid]) as $r) {
            $cache[$uid][(int)$r['produk_id']] = true;
        }
    }
    return $cache[$uid];
}

// ---------------------------------------------------------------
// CHAT
// ---------------------------------------------------------------
function unread_chat_count(?int $uid = null): int {
    $uid = $uid ?? user_id();
    return (int)db_val("SELECT COUNT(*) FROM chats
                        WHERE user_id = ? AND sender_type = 'admin' AND is_read = 0
                          AND (is_deleted = 0 OR is_deleted IS NULL)", 'i', [$uid]);
}

// ---------------------------------------------------------------
// STATUS PESANAN
// ---------------------------------------------------------------
function status_class(string $status): string {
    switch ($status) {
        case 'Diproses': return 'bg-amber-50 text-amber-700 border-amber-200';
        case 'Dikirim':  return 'bg-blue-50 text-blue-700 border-blue-200';
        case 'Selesai':  return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'Batal':    return 'bg-rose-50 text-rose-700 border-rose-200';
        default:         return 'bg-stone-100 text-stone-600 border-stone-200';
    }
}

function status_icon(string $status): string {
    switch ($status) {
        case 'Diproses': return 'clock';
        case 'Dikirim':  return 'truck';
        case 'Selesai':  return 'check-circle-2';
        case 'Batal':    return 'x-circle';
        default:         return 'circle';
    }
}

function metode_label(string $m): string {
    $map = [
        'transfer' => 'Transfer Bank',
        'ewallet'  => 'E-Wallet',
        'qris'     => 'QRIS',
        'cod'      => 'Bayar di Tempat (COD)',
        'tunai'    => 'Tunai',
        'debit'    => 'Kartu Debit',
    ];
    return $map[$m] ?? ucfirst($m);
}

// ---------------------------------------------------------------
// UPLOAD GAMBAR (validasi isi file, bukan hanya ekstensi)
// ---------------------------------------------------------------
/**
 * @return string|null nama file tersimpan; null bila tidak ada file / gagal (lihat $error)
 */
function save_image_upload(?array $file, string $destDir, string $prefix, int $maxBytes, array $allowedExt, ?string &$error = null): ?string {
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Gagal mengunggah gambar (kode ' . $file['error'] . ').';
        return null;
    }
    if ($file['size'] > $maxBytes) {
        $error = 'Ukuran gambar melebihi ' . round($maxBytes / 1048576) . 'MB.';
        return null;
    }
    $info = @getimagesize($file['tmp_name']);
    if (!$info) {
        $error = 'File yang dipilih bukan gambar yang valid.';
        return null;
    }
    $map = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    $ext = $map[$info[2]] ?? null;
    if (!$ext || !in_array($ext, $allowedExt, true)) {
        $error = 'Format gambar tidak didukung. Gunakan ' . strtoupper(implode(', ', $allowedExt)) . '.';
        return null;
    }
    if (!is_dir($destDir) && !@mkdir($destDir, 0755, true)) {
        $error = 'Folder upload tidak dapat dibuat. Periksa izin folder uploads/.';
        return null;
    }
    $name = $prefix . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], rtrim($destDir, '/') . '/' . $name)) {
        $error = 'Gagal menyimpan gambar ke folder uploads.';
        return null;
    }
    return $name;
}

// ===== BAGIAN 2: KOMPONEN UI =====
/**
 * PlantHub - Komponen UI yang dipakai ulang di banyak halaman Pelanggan.
 * Seluruh fungsi mengembalikan string HTML (sudah di-escape).
 */

/** Bangun URL halaman ini dengan parameter GET yang dimodifikasi. */
function url_with(array $over = [], ?string $base = null, bool $resetPage = true): string {
    $params = $_GET;
    if ($resetPage && !array_key_exists('page', $over)) unset($params['page']);
    foreach ($over as $k => $v) {
        if ($v === null || $v === '') unset($params[$k]);
        else $params[$k] = $v;
    }
    $base = $base ?? basename($_SERVER['PHP_SELF']);
    return $base . ($params ? '?' . http_build_query($params) : '');
}

function ui_status_badge(string $status): string {
    return '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase border '
        . e(status_class($status)) . '"><i data-lucide="' . e(status_icon($status)) . '" class="w-3 h-3"></i>'
        . e($status) . '</span>';
}

/** Progress tracker Diproses → Dikirim → Selesai */
function ui_order_tracker(string $status): string {
    if ($status === 'Batal') {
        return '<div class="inline-flex items-center gap-2 text-xs font-semibold text-rose-600 bg-rose-50 border border-rose-100 rounded-xl px-3 py-2">'
             . '<i data-lucide="x-circle" class="w-4 h-4"></i> Pesanan dibatalkan</div>';
    }
    $steps = [
        ['Diproses', 'package-check'],
        ['Dikirim',  'truck'],
        ['Selesai',  'check-circle-2'],
    ];
    $idx = 0;
    foreach ($steps as $i => $s) if ($s[0] === $status) $idx = $i;

    $h = '<div class="flex items-center w-full max-w-md">';
    foreach ($steps as $i => $s) {
        $done   = $i <= $idx;
        $circle = $done ? 'bg-[#2E7D32] text-white shadow-sm shadow-emerald-200' : 'bg-stone-100 text-stone-400';
        $label  = $done ? 'text-[#2E7D32]' : 'text-stone-400';
        $h .= '<div class="flex flex-col items-center gap-1 shrink-0">'
            . '<div class="w-9 h-9 rounded-full flex items-center justify-center ' . $circle . '"><i data-lucide="' . $s[1] . '" class="w-4 h-4"></i></div>'
            . '<span class="text-[10px] font-bold uppercase tracking-wide ' . $label . '">' . $s[0] . '</span></div>';
        if ($i < count($steps) - 1) {
            $line = $i < $idx ? 'bg-[#2E7D32]' : 'bg-stone-200';
            $h .= '<div class="flex-1 h-0.5 mx-2 -mt-5 rounded ' . $line . '"></div>';
        }
    }
    return $h . '</div>';
}

function ui_empty(string $icon, string $title, string $text = '', ?string $href = null, ?string $label = null, string $btnIcon = 'store'): string {
    $h  = '<div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-10 sm:p-14 text-center fade-up">';
    $h .= '<div class="w-16 h-16 bg-emerald-50 rounded-full flex items-center justify-center mx-auto mb-4 text-[#2E7D32]"><i data-lucide="' . e($icon) . '" class="w-8 h-8"></i></div>';
    $h .= '<h3 class="text-base font-bold text-stone-800">' . e($title) . '</h3>';
    if ($text !== '') $h .= '<p class="text-sm text-stone-500 mt-1 max-w-sm mx-auto">' . e($text) . '</p>';
    if ($href && $label) {
        $h .= '<a href="' . e($href) . '" class="inline-flex items-center gap-2 mt-5 bg-[#2E7D32] hover:bg-emerald-800 text-white font-semibold px-5 py-2.5 rounded-xl text-sm transition shadow-sm">'
            . '<i data-lucide="' . e($btnIcon) . '" class="w-4 h-4"></i><span>' . e($label) . '</span></a>';
    }
    return $h . '</div>';
}

function ui_section_head(string $title, string $sub = '', ?string $href = null, string $label = 'Lihat Semua'): string {
    $h  = '<div class="flex items-end justify-between gap-3 border-b border-stone-100 pb-3 mb-4"><div>';
    $h .= '<h2 class="text-base font-bold text-stone-800">' . e($title) . '</h2>';
    if ($sub !== '') $h .= '<p class="text-xs text-stone-500 mt-0.5">' . e($sub) . '</p>';
    $h .= '</div>';
    if ($href) {
        $h .= '<a href="' . e($href) . '" class="text-xs font-semibold text-[#2E7D32] hover:underline inline-flex items-center gap-1 shrink-0">'
            . e($label) . ' <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i></a>';
    }
    return $h . '</div>';
}

/**
 * Kartu produk standar (katalog, dashboard, wishlist, produk serupa).
 * @param array $wish   hasil wishlist_ids()
 * @param array $best   hasil best_seller_ids()
 */
function ui_product_card(array $p, array $wish = [], array $best = [], array $opt = []): string {
    $id      = (int)$p['id'];
    $stok    = (int)$p['stok'];
    $habis   = $stok <= 0;
    $kritis  = $stok > 0 && $stok <= (int)($p['stok_minimal'] ?? 0);
    $isBest  = isset($best[$id]);
    $wished  = isset($wish[$id]);
    $terjual = (int)($p['total_terjual'] ?? 0);
    $img     = produk_img($p['gambar'] ?? '', 400);
    $removeCard = !empty($opt['remove_on_unwish']);

    ob_start(); ?>
    <div data-card class="group bg-white rounded-2xl border border-stone-200/80 shadow-sm overflow-hidden flex flex-col hover:shadow-lg hover:-translate-y-0.5 transition-all duration-300 fade-up">
        <div class="relative bg-stone-100 h-44 overflow-hidden">
            <a href="detail.php?id=<?= $id ?>" class="block h-full">
                <img src="<?= e($img) ?>" alt="<?= e($p['nama_tanaman']) ?>" loading="lazy"
                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500 <?= $habis ? 'grayscale opacity-70' : '' ?>">
            </a>

            <div class="absolute top-2.5 left-2.5 flex flex-col items-start gap-1.5">
                <?php if ($habis): ?>
                    <span class="bg-rose-600 text-white text-[10px] font-bold px-2 py-1 rounded-md uppercase">Stok Habis</span>
                <?php elseif ($kritis): ?>
                    <span class="bg-[#D97706] text-white text-[10px] font-bold px-2 py-1 rounded-md uppercase">Sisa <?= $stok ?></span>
                <?php endif; ?>
                <?php if ($isBest): ?>
                    <span class="bg-gradient-to-r from-amber-500 to-orange-500 text-white text-[10px] font-bold px-2 py-1 rounded-md uppercase inline-flex items-center gap-1 shadow-sm">
                        <i data-lucide="flame" class="w-3 h-3"></i> Terlaris
                    </span>
                <?php endif; ?>
            </div>

            <button type="button" data-wish="<?= $id ?>" <?= $removeCard ? 'data-remove-card' : '' ?>
                    class="wish-btn absolute top-2.5 right-2.5 w-8 h-8 rounded-full bg-white/90 backdrop-blur text-stone-500 hover:text-rose-500 shadow flex items-center justify-center transition <?= $wished ? 'is-wished' : '' ?>"
                    aria-label="Simpan ke wishlist" title="Simpan ke wishlist">
                <i data-lucide="heart" class="w-4 h-4"></i>
            </button>
        </div>

        <div class="p-4 flex-1 flex flex-col">
            <p class="text-[10px] font-semibold uppercase text-stone-400 tracking-wider"><?= e($p['nama_kategori'] ?? 'Tanaman') ?></p>
            <a href="detail.php?id=<?= $id ?>" class="text-sm font-bold text-stone-800 hover:text-[#2E7D32] mt-0.5 leading-snug line-clamp-2 min-h-[2.5rem] transition-colors"><?= e($p['nama_tanaman']) ?></a>
            <p class="text-base font-bold text-[#2E7D32] mt-2"><?= rupiah($p['harga_jual']) ?></p>

            <div class="mt-1 flex items-center justify-between text-[11px]">
                <span class="text-stone-500">Stok: <?= $stok ?> unit</span>
                <?php if ($terjual > 0): ?>
                    <span class="text-stone-400 inline-flex items-center gap-1"><i data-lucide="shopping-bag" class="w-3 h-3"></i><?= $terjual ?> terjual</span>
                <?php endif; ?>
            </div>

            <div class="mt-3 pt-3 border-t border-stone-100 grid grid-cols-2 gap-2 mt-auto">
                <a href="detail.php?id=<?= $id ?>" class="text-center text-xs font-semibold text-stone-700 bg-stone-100 hover:bg-stone-200 py-2 rounded-lg transition">Detail</a>
                <?php if ($habis): ?>
                    <button disabled class="text-center text-xs font-semibold text-stone-400 bg-stone-100 py-2 rounded-lg cursor-not-allowed">Habis</button>
                <?php else: ?>
                    <button type="button" class="btn-add-cart inline-flex items-center justify-center gap-1 text-xs font-semibold text-white bg-[#2E7D32] hover:bg-emerald-800 py-2 rounded-lg transition active:scale-95"
                            data-id="<?= $id ?>" data-nama="<?= e($p['nama_tanaman']) ?>">
                        <i data-lucide="plus" class="w-3.5 h-3.5"></i> Keranjang
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function ui_pagination(int $page, int $pages): string {
    if ($pages <= 1) return '';

    $nums = [];
    for ($i = 1; $i <= $pages; $i++) {
        if ($i === 1 || $i === $pages || abs($i - $page) <= 1) $nums[] = $i;
    }
    $h = '<nav class="flex items-center justify-center gap-1.5 flex-wrap pt-2" aria-label="Halaman">';

    $btn = 'min-w-[2.25rem] h-9 px-3 inline-flex items-center justify-center rounded-lg text-xs font-semibold border transition ';
    $off = $btn . 'bg-white text-stone-600 border-stone-200 hover:bg-stone-50';
    $on  = $btn . 'bg-[#2E7D32] text-white border-[#2E7D32] shadow-sm';

    if ($page > 1) {
        $h .= '<a href="' . e(url_with(['page' => $page - 1], null, false)) . '" class="' . $off . '"><i data-lucide="chevron-left" class="w-4 h-4"></i></a>';
    }
    $prev = 0;
    foreach ($nums as $n) {
        if ($prev && $n - $prev > 1) $h .= '<span class="px-1 text-stone-400">…</span>';
        $h .= '<a href="' . e(url_with(['page' => $n], null, false)) . '" class="' . ($n === $page ? $on : $off) . '">' . $n . '</a>';
        $prev = $n;
    }
    if ($page < $pages) {
        $h .= '<a href="' . e(url_with(['page' => $page + 1], null, false)) . '" class="' . $off . '"><i data-lucide="chevron-right" class="w-4 h-4"></i></a>';
    }
    return $h . '</nav>';
}

/** Ringkasan Subtotal / PPN / Total (dipakai cart & checkout). */
function ui_totals(float $subtotal, int $count, string $idPrefix = ''): string {
    $t = hitung_total($subtotal);
    ob_start(); ?>
    <div class="space-y-2 text-sm">
        <div class="flex justify-between text-stone-600">
            <span>Subtotal (<?= $count ?> item)</span>
            <span class="font-semibold"><?= rupiah($t['subtotal']) ?></span>
        </div>
        <div class="flex justify-between text-stone-600">
            <span>PPN <?= (int)(PPN_RATE * 100) ?>%</span>
            <span class="font-semibold"><?= rupiah($t['ppn']) ?></span>
        </div>
        <div class="pt-3 border-t border-stone-200 flex justify-between items-baseline">
            <span class="text-sm font-bold text-stone-800">Total Bayar</span>
            <span class="text-xl font-bold text-[#2E7D32]"><?= rupiah($t['total']) ?></span>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// ===== BAGIAN 3: KERANGKA HALAMAN =====
/**
 * PlantHub - Kerangka halaman Pelanggan.
 * Pemakaian:
 *   layout_start(['title'=>'Katalog','active'=>'katalog','width'=>'max-w-7xl']);
 *   ... isi halaman ...
 *   layout_end();
 */

function layout_nav_items(): array {
    return [
        'dashboard' => ['Beranda',       'dashboard.php', 'layout-grid'],
        'katalog'   => ['Katalog Shop',  'katalog.php',   'store'],
        'wishlist'  => ['Wishlist',      'wishlist.php',  'heart'],
        'cart'      => ['Keranjang',     'cart.php',      'shopping-bag'],
        'riwayat'   => ['Riwayat Order', 'riwayat.php',   'history'],
        'chat'      => ['Konsultasi',    'chat.php',      'message-square'],
        'profil'    => ['Profil Saya',   'profil.php',    'user'],
    ];
}

function layout_start(array $opt = []): void {
    $title  = $opt['title']  ?? 'PlantHub';
    $active = $opt['active'] ?? '';
    $width  = $opt['width']  ?? 'max-w-6xl';
    $uid    = user_id();
    $user   = current_user();
    $nama   = nama_user();
    $cartN  = cart_count();
    $unread = unread_chat_count($uid);
    $wishN  = count(wishlist_ids($uid));
    $flash  = flash_pull();
    $nav    = layout_nav_items();
    $badge  = ['cart' => $cartN, 'chat' => $unread, 'wishlist' => $wishN];
    ?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title>PlantHub - <?= e($title) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] } } } };
    </script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background:#F9F8F6; }
        .fade-up { animation: fadeUp .45s ease both; }
        @keyframes fadeUp { from { opacity:0; transform:translateY(10px);} to { opacity:1; transform:none; } }
        .pop { animation: pop .35s ease; }
        @keyframes pop { 0%{transform:scale(1)} 50%{transform:scale(1.35)} 100%{transform:scale(1)} }
        .wish-btn.is-wished { color:#e11d48; }
        .wish-btn.is-wished svg { fill:#e11d48; }
        .line-clamp-2{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
        .no-scrollbar::-webkit-scrollbar{display:none}.no-scrollbar{scrollbar-width:none}
        ::-webkit-scrollbar{width:8px;height:8px}::-webkit-scrollbar-thumb{background:#d6d3d1;border-radius:8px}
        @media print { .no-print{display:none!important} body{background:#fff} }
    </style>
</head>
<body class="text-stone-800 antialiased min-h-screen flex flex-col">

<!-- HEADER -->
<header class="no-print sticky top-0 z-30 bg-white border-b border-stone-200/80 shadow-sm">
    <div class="px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <button id="menuBtn" type="button" class="lg:hidden p-2 rounded-lg text-stone-600 hover:bg-stone-100" aria-label="Menu"><i data-lucide="menu" class="w-5 h-5"></i></button>
            <a href="dashboard.php" class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-[#2E7D32] flex items-center justify-center text-white"><i data-lucide="sprout" class="w-5 h-5"></i></div>
                <span class="text-xl font-bold tracking-tight text-stone-800">Plant<span class="text-[#2E7D32]">Hub</span></span>
            </a>
        </div>

        <form action="katalog.php" method="GET" class="hidden md:flex flex-1 max-w-md relative">
            <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400"></i>
            <input type="text" name="search" value="<?= e($_GET['search'] ?? '') ?>" placeholder="Cari tanaman, pot, media tanam..." class="w-full pl-10 pr-4 py-2 text-sm bg-stone-100/70 border border-transparent rounded-full focus:outline-none focus:bg-white focus:border-[#2E7D32] placeholder:text-stone-400">
        </form>

        <div class="flex items-center gap-3">
            <a href="cart.php" class="relative p-2 text-stone-600 hover:text-stone-900 hover:bg-stone-100 rounded-full" aria-label="Keranjang">
                <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                <span data-cart-count class="absolute top-1 right-1 min-w-[16px] h-4 px-1 bg-[#2E7D32] text-white text-[9px] font-bold rounded-full items-center justify-center ring-2 ring-white <?= $cartN > 0 ? 'flex' : 'hidden' ?>"><?= $cartN ?></span>
            </a>
            <div class="h-6 w-px bg-stone-200 hidden sm:block"></div>
            <div class="relative" id="profileWrap">
                <button id="profileBtn" type="button" class="flex items-center gap-3 pl-1 focus:outline-none">
                    <img src="<?= e(avatar_url($user)) ?>" alt="" class="w-9 h-9 rounded-full object-cover ring-2 ring-[#2E7D32]/20">
                    <div class="hidden sm:block text-left">
                        <p class="text-sm font-semibold text-stone-800 leading-tight max-w-[120px] truncate"><?= e($nama) ?></p>
                        <p class="text-xs text-stone-500">Pelanggan</p>
                    </div>
                    <i data-lucide="chevron-down" class="w-4 h-4 text-stone-400 hidden sm:block"></i>
                </button>
                <div id="profileMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-2xl shadow-xl border border-stone-100 p-2 space-y-1 z-50">
                    <a href="profil.php" class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-stone-700 hover:bg-stone-100 rounded-xl"><i data-lucide="user" class="w-4 h-4 text-stone-500"></i> Profil Saya</a>
                    <a href="riwayat.php" class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-stone-700 hover:bg-stone-100 rounded-xl"><i data-lucide="history" class="w-4 h-4 text-stone-500"></i> Riwayat Saya</a>
                    <a href="wishlist.php" class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-stone-700 hover:bg-stone-100 rounded-xl"><i data-lucide="heart" class="w-4 h-4 text-stone-500"></i> Wishlist</a>
                    <a href="chat.php" class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-stone-700 hover:bg-stone-100 rounded-xl"><i data-lucide="message-square" class="w-4 h-4 text-stone-500"></i> Konsultasi</a>
                    <hr class="border-stone-100 my-1">
                    <a href="../Auth/logout.php" onclick="return confirm('Keluar dari akun?');" class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-rose-600 hover:bg-rose-50 rounded-xl"><i data-lucide="log-out" class="w-4 h-4 text-rose-500"></i> Keluar</a>
                </div>
            </div>
        </div>
    </div>
</header>

<div class="flex flex-1">
    <div id="overlay" class="no-print hidden fixed inset-0 bg-black/40 z-40 lg:hidden"></div>

    <!-- SIDEBAR -->
    <aside id="sidebar" class="no-print fixed lg:sticky top-0 lg:top-16 left-0 z-50 lg:z-10 h-screen lg:h-[calc(100vh-4rem)] w-64 bg-white border-r border-stone-200/80 -translate-x-full lg:translate-x-0 transition-transform duration-300 flex flex-col justify-between shrink-0 p-4 overflow-y-auto">
        <div class="space-y-6">
            <div class="lg:hidden flex items-center justify-between -mt-1">
                <span class="text-lg font-bold">Plant<span class="text-[#2E7D32]">Hub</span></span>
                <button id="closeMenu" type="button" class="p-2 rounded-lg hover:bg-stone-100" aria-label="Tutup"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <nav class="space-y-1">
                <p class="px-3 text-[11px] font-bold text-stone-400 uppercase tracking-wider mb-3">MENU PELANGGAN</p>
                <?php foreach ($nav as $key => [$label, $href, $icon]):
                    $on = $active === $key;
                    $cls = $on ? 'text-[#1E7D32] bg-[#E8F5E9]' : 'text-stone-700 hover:bg-stone-100';
                    $b = $badge[$key] ?? 0; ?>
                    <a href="<?= $href ?>" class="flex items-center justify-between px-3 py-2.5 text-sm font-bold rounded-xl transition-colors <?= $cls ?>">
                        <div class="flex items-center gap-3">
                            <i data-lucide="<?= $icon ?>" class="w-5 h-5 <?= $on ? 'text-[#1E7D32]' : 'text-stone-500' ?>"></i>
                            <span><?= $label ?></span>
                        </div>
                        <?php if ($key === 'chat' && $b > 0): ?>
                            <span class="bg-[#D97706] text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full"><?= $b ?></span>
                        <?php elseif ($key === 'wishlist' && $b > 0 && !$on): ?>
                            <span class="bg-rose-50 text-rose-500 text-[10px] font-bold px-1.5 py-0.5 rounded-full"><?= $b ?></span>
                        <?php elseif ($on): ?>
                            <span class="w-2.5 h-2.5 rounded-full bg-[#1E7D32]"></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
        <a href="cart.php" class="p-3 bg-stone-50/80 border border-stone-200/60 rounded-2xl flex items-center gap-3 mt-6 hover:bg-stone-100 transition">
            <div class="w-10 h-10 rounded-xl bg-emerald-100/70 flex items-center justify-center text-[#2E7D32] shrink-0"><i data-lucide="shopping-cart" class="w-5 h-5"></i></div>
            <div class="overflow-hidden">
                <p class="text-sm font-bold text-stone-800 truncate leading-tight">Troli Belanja</p>
                <p class="text-[11px] font-medium text-stone-400 truncate mt-0.5"><span data-cart-count-text><?= $cartN ?></span> item siap checkout</p>
            </div>
        </a>
    </aside>

    <!-- KONTEN -->
    <main class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 pb-24 lg:pb-8">
        <div class="<?= e($width) ?> mx-auto space-y-6">
<?php
    $GLOBALS['__flash'] = $flash;
}

function layout_end(): void {
    $nav    = layout_nav_items();
    $active = $GLOBALS['__active'] ?? '';
    $bottom = ['dashboard', 'katalog', 'cart', 'riwayat', 'profil'];
    $cartN  = cart_count();
    ?>
        </div>
    </main>
</div>

<!-- NAV BAWAH (mobile) -->
<nav class="no-print lg:hidden fixed bottom-0 inset-x-0 z-30 bg-white border-t border-stone-200 grid grid-cols-5">
    <?php foreach ($bottom as $k): [$label, $href, $icon] = $nav[$k];
        $on = basename($_SERVER['PHP_SELF']) === $href; ?>
        <a href="<?= $href ?>" class="relative flex flex-col items-center gap-0.5 py-2 text-[10px] font-semibold <?= $on ? 'text-[#2E7D32]' : 'text-stone-500' ?>">
            <i data-lucide="<?= $icon ?>" class="w-5 h-5"></i><?= $label ?>
            <?php if ($k === 'cart'): ?><span data-cart-count class="absolute top-1 left-1/2 ml-2 min-w-[15px] h-[15px] px-1 bg-[#2E7D32] text-white text-[9px] font-bold rounded-full items-center justify-center <?= $cartN > 0 ? 'flex' : 'hidden' ?>"><?= $cartN ?></span><?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>

<button id="toTop" class="no-print hidden fixed bottom-20 lg:bottom-6 right-4 z-30 w-10 h-10 rounded-full bg-[#2E7D32] text-white shadow-lg items-center justify-center hover:bg-emerald-800 transition" aria-label="Ke atas">
    <i data-lucide="arrow-up" class="w-5 h-5"></i>
</button>

<div id="toasts" class="no-print fixed top-20 right-4 z-[60] space-y-2 w-[calc(100%-2rem)] max-w-sm pointer-events-none"></div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
window.__flash = <?= json_encode($GLOBALS['__flash'] ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;

function toast(msg, type = 'success') {
    const colors = { success:'bg-emerald-600', error:'bg-rose-600', info:'bg-stone-800', warning:'bg-amber-600' };
    const icons  = { success:'check-circle-2', error:'alert-circle', info:'info', warning:'alert-triangle' };
    const el = document.createElement('div');
    el.className = 'pointer-events-auto flex items-start gap-2 text-white text-sm font-medium rounded-xl shadow-xl px-4 py-3 transition-all duration-300 translate-x-8 opacity-0 ' + (colors[type] || colors.info);
    el.innerHTML = '<i data-lucide="' + (icons[type] || 'info') + '" class="w-4 h-4 mt-0.5 shrink-0"></i><span></span>';
    el.querySelector('span').textContent = msg;
    document.getElementById('toasts').appendChild(el);
    lucide.createIcons();
    requestAnimationFrame(() => el.classList.remove('translate-x-8', 'opacity-0'));
    setTimeout(() => { el.classList.add('opacity-0', 'translate-x-8'); setTimeout(() => el.remove(), 300); }, 3500);
}

function setCartCount(n) {
    document.querySelectorAll('[data-cart-count-text]').forEach(el => { el.textContent = n; });
    document.querySelectorAll('[data-cart-count]').forEach(el => {
        el.textContent = n;
        el.classList.toggle('hidden', n <= 0);
        el.classList.toggle('flex', n > 0);
        el.classList.add('pop'); setTimeout(() => el.classList.remove('pop'), 400);
    });
}

async function postJSON(url, data) {
    const fd = new FormData();
    Object.entries(data).forEach(([k, v]) => fd.append(k, v));
    fd.append('ajax', '1'); fd.append('csrf', CSRF);
    const r = await fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': CSRF } });
    try {
        const j = await r.json();
        if (typeof j.ok === 'undefined') j.ok = (j.status === 'success');
        if (!j.msg && j.message) j.msg = j.message;
        if (!j.type && j.status && j.status !== 'success') j.type = j.status;
        if (typeof j.count === 'undefined' && typeof j.cart_count !== 'undefined') j.count = j.cart_count;
        return j;
    } catch (e) { return { ok: false, msg: 'Respons server tidak valid.' }; }
}

// Tambah ke keranjang (AJAX)
document.addEventListener('click', async (ev) => {
    const btn = ev.target.closest('.btn-add-cart');
    if (!btn || btn.disabled) return;
    ev.preventDefault();
    const qtyEl = btn.dataset.qtyInput ? document.querySelector(btn.dataset.qtyInput) : null;
    const qty = qtyEl ? parseInt(qtyEl.value, 10) || 1 : 1;
    btn.disabled = true; btn.classList.add('opacity-70');
    try {
        const res = await postJSON('cart.php', { op: 'add', id: btn.dataset.id, qty });
        if (res.ok) { toast(res.msg || 'Ditambahkan ke keranjang', 'success'); if (typeof res.count !== 'undefined') setCartCount(res.count); }
        else toast(res.msg || 'Gagal menambah ke keranjang', res.type || 'error');
    } catch (e) { toast('Koneksi bermasalah, coba lagi.', 'error'); }
    btn.disabled = false; btn.classList.remove('opacity-70');
});

// Wishlist toggle (AJAX)
document.addEventListener('click', async (ev) => {
    const btn = ev.target.closest('[data-wish]');
    if (!btn) return;
    ev.preventDefault();
    try {
        const res = await postJSON('wishlist.php', { op: 'toggle', id: btn.dataset.wish });
        if (!res.ok) return toast(res.msg || 'Gagal memperbarui wishlist', 'error');
        btn.classList.toggle('is-wished', !!res.wished);
        btn.classList.add('pop'); setTimeout(() => btn.classList.remove('pop'), 400);
        toast(res.wished ? 'Disimpan ke wishlist' : 'Dihapus dari wishlist', res.wished ? 'success' : 'info');
        if (!res.wished && btn.hasAttribute('data-remove-card')) {
            const card = btn.closest('[data-card]');
            if (card) { card.style.transition = 'all .3s'; card.style.opacity = 0; card.style.transform = 'scale(.95)'; setTimeout(() => card.remove(), 300); }
        }
    } catch (e) { toast('Koneksi bermasalah, coba lagi.', 'error'); }
});

// Konfirmasi form
document.addEventListener('submit', (ev) => {
    const f = ev.target.closest('form[data-confirm]');
    if (f && !confirm(f.dataset.confirm)) ev.preventDefault();
});

// Sidebar & menu
const sb = document.getElementById('sidebar'), ov = document.getElementById('overlay');
const openSb  = () => { sb.classList.remove('-translate-x-full'); ov.classList.remove('hidden'); };
const closeSb = () => { sb.classList.add('-translate-x-full'); ov.classList.add('hidden'); };
document.getElementById('menuBtn').addEventListener('click', openSb);
document.getElementById('closeMenu').addEventListener('click', closeSb);
ov.addEventListener('click', closeSb);

const pBtn = document.getElementById('profileBtn'), pMenu = document.getElementById('profileMenu');
pBtn.addEventListener('click', (e) => { e.stopPropagation(); pMenu.classList.toggle('hidden'); });
document.addEventListener('click', (e) => { if (!document.getElementById('profileWrap').contains(e.target)) pMenu.classList.add('hidden'); });

// Back to top
const topBtn = document.getElementById('toTop');
window.addEventListener('scroll', () => {
    const show = window.scrollY > 400;
    topBtn.classList.toggle('hidden', !show); topBtn.classList.toggle('flex', show);
});
topBtn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

// Flash dari server
(window.__flash || []).forEach((f, i) => setTimeout(() => toast(f.msg, f.type || 'info'), i * 250));

lucide.createIcons();
</script>
<?php if (!empty($GLOBALS['__extra_js'])) echo $GLOBALS['__extra_js']; ?>
</body>
</html>
<?php
}

// ---------------------------------------------------------------
// Proteksi login pelanggan (jalan otomatis di setiap halaman)
// ---------------------------------------------------------------
require_customer();
