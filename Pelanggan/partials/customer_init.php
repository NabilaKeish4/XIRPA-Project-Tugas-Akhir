<?php
// Dipanggil setelah session_start() dan require database.php
if (!isset($conn)) {
    require_once __DIR__ . '/../Config/database.php';
}

// PROTEKSI LOGIN PELANGGAN
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header("Location: ../Auth/login.php");
    exit;
}

$user_id   = (int)$_SESSION['user_id'];
$nama_user = $_SESSION['nama_user'] ?? 'Pelanggan';

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
$cart_count = array_sum($_SESSION['cart']);

// DATA USER + FOTO PROFIL (dipakai semua halaman)
$foto_db = '';
$qAv = mysqli_query($conn, "SELECT nama_lengkap, foto_profil FROM users WHERE id = $user_id LIMIT 1");
if ($qAv && ($uAv = mysqli_fetch_assoc($qAv))) {
    if (!empty($uAv['nama_lengkap'])) {
        $nama_user = $uAv['nama_lengkap'];
        $_SESSION['nama_user'] = $nama_user;
    }
    $foto_db = basename($uAv['foto_profil'] ?? '');
}

$avatarSrc = 'https://ui-avatars.com/api/?name=' . urlencode($nama_user) . '&background=2E7D32&color=fff';
if ($foto_db !== '' && $foto_db !== 'default_avatar.jpg') {
    $pathAv = __DIR__ . '/../uploads/avatars/' . $foto_db;
    if (is_file($pathAv)) {
        $avatarSrc = '../uploads/avatars/' . $foto_db . '?v=' . filemtime($pathAv);
    }
}

// CHAT ADMIN BELUM DIBACA
$unreadChat = 0;
$qUn = mysqli_query($conn, "
    SELECT COUNT(*) AS total FROM chats
    WHERE user_id = $user_id AND sender_type = 'admin' AND is_read = 0
      AND (is_deleted = 0 OR is_deleted IS NULL)
");
if ($qUn) $unreadChat = (int)mysqli_fetch_assoc($qUn)['total'];