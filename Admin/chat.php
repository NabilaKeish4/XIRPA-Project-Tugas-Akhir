<?php
session_start();
require_once '../Config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../Auth/login.php");
    exit;
}

$admin_id   = (int)$_SESSION['user_id'];
$admin_nama = $_SESSION['nama_user'] ?? 'Admin';

// =========================================================
// AMBIL DAFTAR PELANGGAN YANG PERNAH CHAT
// =========================================================
$customers = [];
$qCust = mysqli_query($conn, "
    SELECT 
        u.id, 
        u.nama_lengkap, 
        u.email, 
        u.username,
        (SELECT message FROM chats c WHERE c.user_id = u.id AND c.is_deleted = 0 ORDER BY c.created_at DESC LIMIT 1) AS last_message,
        (SELECT created_at FROM chats c WHERE c.user_id = u.id AND c.is_deleted = 0 ORDER BY c.created_at DESC LIMIT 1) AS last_time,
        (SELECT COUNT(*) FROM chats c WHERE c.user_id = u.id AND c.sender_type = 'customer' AND c.is_read = 0 AND c.is_deleted = 0) AS unread_count,
        (SELECT COUNT(*) FROM chats c WHERE c.user_id = u.id AND c.is_deleted = 0) AS total_messages
    FROM users u
    WHERE u.role = 'customer'
      AND EXISTS (SELECT 1 FROM chats c WHERE c.user_id = u.id AND c.is_deleted = 0)
    ORDER BY last_time DESC
");
if ($qCust) while ($r = mysqli_fetch_assoc($qCust)) $customers[] = $r;

$filterMode = $_GET['filter'] ?? 'semua';

$displayCustomers = $customers;
if ($filterMode === 'unread') {
    $displayCustomers = array_values(array_filter($customers, fn($c) => (int)$c['unread_count'] > 0));
}

$selected_id = (int)($_GET['user_id'] ?? 0);
if ($selected_id === 0 && !empty($displayCustomers)) {
    $selected_id = (int)$displayCustomers[0]['id'];
}

$selectedCustomer = null;
foreach ($customers as $c) {
    if ((int)$c['id'] === $selected_id) {
        $selectedCustomer = $c;
        break;
    }
}

$pesan_error = '';
$pesan_sukses = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selected_id > 0) {
    $action = $_POST['action'] ?? '';

    if ($action === 'kirim') {
        $msg = trim($_POST['message'] ?? '');
        if ($msg !== '') {
            $clean = mysqli_real_escape_string($conn, $msg);
            $sql = "INSERT INTO chats (user_id, sender_type, message, is_read, created_at) 
                    VALUES ($selected_id, 'admin', '$clean', 1, NOW())";
            if (mysqli_query($conn, $sql)) {
                header("Location: chat.php?user_id=$selected_id&status=sent");
                exit;
            } else {
                $pesan_error = "Gagal mengirim: " . mysqli_error($conn);
            }
        } else {
            $pesan_error = "Pesan tidak boleh kosong.";
        }
    }

    if ($action === 'edit') {
        $id     = (int)($_POST['id'] ?? 0);
        $newMsg = trim($_POST['new_message'] ?? '');
        if ($id > 0 && $newMsg !== '') {
            $clean = mysqli_real_escape_string($conn, $newMsg);
            $sql = "UPDATE chats SET message = '$clean', edited_at = NOW() 
                    WHERE id = $id AND sender_type = 'admin'";
            if (mysqli_query($conn, $sql)) {
                header("Location: chat.php?user_id=$selected_id&status=edited");
                exit;
            } else {
                $pesan_error = "Gagal mengedit: " . mysqli_error($conn);
            }
        }
    }

    if ($action === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $sql = "UPDATE chats SET is_deleted = 1 WHERE id = $id AND sender_type = 'admin'";
            if (mysqli_query($conn, $sql)) {
                header("Location: chat.php?user_id=$selected_id&status=deleted");
                exit;
            } else {
                $pesan_error = "Gagal menghapus: " . mysqli_error($conn);
            }
        }
    }
}

if (isset($_GET['status'])) {
    if ($_GET['status'] === 'sent')    $pesan_sukses = "Balasan berhasil dikirim.";
    if ($_GET['status'] === 'edited')  $pesan_sukses = "Pesan berhasil diperbarui.";
    if ($_GET['status'] === 'deleted') $pesan_sukses = "Pesan berhasil dihapus.";
}

// Tandai dibaca
if ($selected_id > 0) {
    mysqli_query($conn, "UPDATE chats SET is_read = 1 WHERE user_id = $selected_id AND sender_type = 'customer' AND is_read = 0");
}

$chatMessages = [];
if ($selected_id > 0) {
    $qChat = mysqli_query($conn, "
        SELECT * FROM chats 
        WHERE user_id = $selected_id AND is_deleted = 0 
        ORDER BY created_at ASC
    ");
    if ($qChat) while ($r = mysqli_fetch_assoc($qChat)) $chatMessages[] = $r;
}

$editId = (int)($_GET['edit'] ?? 0);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantHub - Konsultasi Pelanggan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F9F8F6; color: #2D3748; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(0,0,0,0.02); }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.1); border-radius: 9999px; }
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
                    <div class="w-9 h-9 rounded-xl bg-[#2E7D32] flex items-center justify-center text-white shadow-sm">
                        <i data-lucide="sprout" class="w-5 h-5"></i>
                    </div>
                    <span class="text-xl font-bold tracking-tight text-stone-800">Plant<span class="text-[#2E7D32]">Hub</span></span>
                </a>
            </div>

            <div class="hidden md:flex flex-1 max-w-md">
                <span class="text-xs text-stone-500 self-center">Konsultasi Pelanggan</span>
            </div>

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
                        ['url' => 'chat.php',       'icon' => 'message-square', 'label' => 'Konsultasi Chat',  'active' => true],
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
                    <p class="text-sm font-bold text-stone-800 truncate leading-tight">PlantHub Admin</p>
                    <p class="text-[11px] font-medium text-stone-400 truncate mt-0.5">Sistem Online</p>
                </div>
            </div>
        </aside>

        <main class="flex-1 p-4 sm:p-6 lg:p-8 w-full">
            <div class="flex flex-col h-[calc(100vh-8rem)] space-y-4">

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 shrink-0">
                    <div>
                        <h1 class="text-2xl font-bold text-stone-800 tracking-tight">Konsultasi Pelanggan</h1>
                        <p class="text-sm text-stone-500 mt-0.5">Balas pertanyaan pelanggan tentang produk & perawatan tanaman.</p>
                    </div>
                    <div class="flex items-center gap-1 bg-stone-100 rounded-xl p-1">
                        <a href="chat.php?filter=semua<?= $selected_id ? '&user_id=' . $selected_id : '' ?>" class="px-3 py-1.5 text-xs font-bold rounded-lg transition <?= $filterMode === 'semua' ? 'bg-white text-stone-800 shadow-sm' : 'text-stone-500 hover:text-stone-800' ?>">Semua</a>
                        <a href="chat.php?filter=unread<?= $selected_id ? '&user_id=' . $selected_id : '' ?>" class="px-3 py-1.5 text-xs font-bold rounded-lg transition <?= $filterMode === 'unread' ? 'bg-white text-stone-800 shadow-sm' : 'text-stone-500 hover:text-stone-800' ?>">Belum Dibaca</a>
                    </div>
                </div>

                <?php if (!empty($pesan_error)): ?>
                    <div class="p-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-xs shrink-0"><?= htmlspecialchars($pesan_error) ?></div>
                <?php endif; ?>
                <?php if (!empty($pesan_sukses)): ?>
                    <div class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs shrink-0"><?= htmlspecialchars($pesan_sukses) ?></div>
                <?php endif; ?>

                <div class="flex-1 grid grid-cols-1 lg:grid-cols-3 gap-4 overflow-hidden">

                    <aside class="lg:col-span-1 bg-white rounded-2xl border border-stone-200/80 shadow-sm flex flex-col overflow-hidden">
                        <div class="px-5 py-4 border-b border-stone-100">
                            <h2 class="text-sm font-bold text-stone-800">Daftar Percakapan</h2>
                            <p class="text-[11px] text-stone-500 mt-0.5"><?= count($displayCustomers) ?> pelanggan</p>
                        </div>
                        <div class="flex-1 overflow-y-auto custom-scrollbar divide-y divide-stone-100">
                            <?php if (empty($displayCustomers)): ?>
                                <div class="p-6 text-center">
                                    <div class="w-12 h-12 bg-stone-100 rounded-full flex items-center justify-center mx-auto mb-2">
                                        <i data-lucide="message-square" class="w-5 h-5 text-stone-400"></i>
                                    </div>
                                    <p class="text-xs font-semibold text-stone-600">Belum ada percakapan</p>
                                    <p class="text-[11px] text-stone-400 mt-0.5">Chat pelanggan akan muncul di sini.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($displayCustomers as $c):
                                    $isActive = ((int)$c['id'] === $selected_id);
                                    $initial = strtoupper(substr($c['nama_lengkap'] ?? 'P', 0, 1));
                                    $unread = (int)$c['unread_count'];
                                ?>
                                    <a href="chat.php?user_id=<?= (int)$c['id'] ?><?= $filterMode !== 'semua' ? '&filter=' . $filterMode : '' ?>"
                                       class="block p-4 transition-colors <?= $isActive ? 'bg-[#E8F5E9]' : 'hover:bg-stone-50' ?>">
                                        <div class="flex items-start gap-3">
                                            <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-xs shrink-0 <?= $isActive ? 'bg-[#2E7D32] text-white' : 'bg-stone-200 text-stone-600' ?>">
                                                <?= htmlspecialchars($initial) ?>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center justify-between gap-2">
                                                    <p class="text-xs font-bold truncate <?= $isActive ? 'text-[#1E7D32]' : 'text-stone-800' ?>">
                                                        <?= htmlspecialchars($c['nama_lengkap'] ?? $c['username'] ?? 'Pelanggan') ?>
                                                    </p>
                                                    <?php if ($unread > 0): ?>
                                                        <span class="bg-[#D97706] text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full shrink-0"><?= $unread ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="text-[11px] text-stone-500 truncate mt-0.5">
                                                    <?= htmlspecialchars($c['last_message'] ?? 'Belum ada pesan') ?>
                                                </p>
                                                <p class="text-[10px] text-stone-400 mt-1">
                                                    <?= !empty($c['last_time']) ? date('d M H:i', strtotime($c['last_time'])) : '' ?>
                                                    &middot; <?= (int)$c['total_messages'] ?> pesan
                                                </p>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </aside>

                    <section class="lg:col-span-2 bg-white rounded-2xl border border-stone-200/80 shadow-sm flex flex-col overflow-hidden">

                        <?php if (!$selectedCustomer): ?>
                            <div class="flex-1 flex flex-col items-center justify-center p-8 text-center">
                                <div class="w-16 h-16 bg-stone-100 rounded-full flex items-center justify-center mb-3">
                                    <i data-lucide="inbox" class="w-7 h-7 text-stone-400"></i>
                                </div>
                                <p class="text-sm font-bold text-stone-700">Pilih percakapan</p>
                                <p class="text-xs text-stone-500 mt-1">Pilih pelanggan dari daftar di sebelah kiri untuk mulai membalas.</p>
                            </div>
                        <?php else: ?>

                            <div class="px-5 py-3.5 border-b border-stone-100 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-[#2E7D32] flex items-center justify-center text-white font-bold text-xs">
                                        <?= htmlspecialchars(strtoupper(substr($selectedCustomer['nama_lengkap'] ?? 'P', 0, 1))) ?>
                                    </div>
                                    <div>
                                        <p class="text-sm font-bold text-stone-800"><?= htmlspecialchars($selectedCustomer['nama_lengkap'] ?? $selectedCustomer['username']) ?></p>
                                        <p class="text-[11px] text-emerald-600 font-medium flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full inline-block"></span> Pelanggan Aktif
                                        </p>
                                    </div>
                                </div>
                                <div class="hidden sm:block text-right">
                                    <p class="text-[10px] text-stone-400 uppercase tracking-wider font-bold">Email</p>
                                    <p class="text-[11px] text-stone-600"><?= htmlspecialchars($selectedCustomer['email'] ?? '-') ?></p>
                                </div>
                            </div>

                            <div id="chat-box" class="flex-1 p-5 overflow-y-auto space-y-4 bg-stone-50/40 custom-scrollbar">

                                <?php if (empty($chatMessages)): ?>
                                    <div class="text-center py-12">
                                        <p class="text-sm font-semibold text-stone-700">Belum ada pesan</p>
                                        <p class="text-xs text-stone-500 mt-1">Kirim balasan pertama Anda.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($chatMessages as $msg):
                                        $isAdmin = ($msg['sender_type'] === 'admin');
                                        $msgId   = (int)$msg['id'];
                                        $isEdit  = ($editId === $msgId && $isAdmin);
                                    ?>
                                        <div class="flex <?= $isAdmin ? 'justify-end' : 'justify-start' ?>" id="msg-<?= $msgId ?>">
                                            <div class="max-w-xs sm:max-w-md md:max-w-lg">
                                                <?php if ($isEdit): ?>
                                                    <form method="POST" action="chat.php?user_id=<?= $selected_id ?>" class="bg-white border-2 border-[#2E7D32] rounded-2xl p-3 shadow-sm space-y-2 w-full sm:w-96">
                                                        <input type="hidden" name="action" value="edit">
                                                        <input type="hidden" name="id" value="<?= $msgId ?>">
                                                        <textarea name="new_message" rows="3" required class="w-full text-xs bg-stone-50 border border-stone-200 rounded-lg p-2.5 focus:outline-none focus:border-[#2E7D32] resize-none"><?= htmlspecialchars($msg['message']) ?></textarea>
                                                        <div class="flex items-center justify-end gap-2">
                                                            <a href="chat.php?user_id=<?= $selected_id ?>" class="px-3 py-1.5 text-[11px] font-semibold text-stone-600 hover:bg-stone-100 rounded-lg">Batal</a>
                                                            <button type="submit" class="px-3 py-1.5 text-[11px] font-bold bg-[#2E7D32] text-white rounded-lg hover:bg-emerald-800">Simpan</button>
                                                        </div>
                                                    </form>
                                                <?php else: ?>
                                                    <div class="p-3 rounded-2xl text-xs relative <?= $isAdmin 
                                                        ? 'bg-[#2E7D32] text-white rounded-tr-none shadow-sm' 
                                                        : 'bg-white text-stone-800 rounded-tl-none border border-stone-200/80 shadow-sm' ?>">
                                                        <p class="leading-relaxed whitespace-pre-wrap"><?= htmlspecialchars($msg['message']) ?></p>
                                                        <div class="flex items-center justify-end gap-2 mt-1.5">
                                                            <?php if (!empty($msg['edited_at'])): ?>
                                                                <span class="text-[9px] italic <?= $isAdmin ? 'text-emerald-100' : 'text-stone-400' ?>">(diedit)</span>
                                                            <?php endif; ?>
                                                            <span class="text-[9px] <?= $isAdmin ? 'text-emerald-100' : 'text-stone-400' ?>">
                                                                <?= date('d/m H:i', strtotime($msg['created_at'])) ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <?php if ($isAdmin): ?>
                                                        <div class="flex items-center justify-end gap-1 mt-1.5">
                                                            <a href="chat.php?user_id=<?= $selected_id ?>&edit=<?= $msgId ?>" class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-semibold text-stone-600 hover:text-stone-900 hover:bg-stone-100 rounded-md border border-stone-200">
                                                                <i data-lucide="edit-3" class="w-3 h-3"></i> Edit
                                                            </a>
                                                            <form method="POST" action="chat.php?user_id=<?= $selected_id ?>" onsubmit="return confirm('Hapus pesan ini?');" class="inline">
                                                                <input type="hidden" name="action" value="hapus">
                                                                <input type="hidden" name="id" value="<?= $msgId ?>">
                                                                <button type="submit" class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-semibold text-rose-600 hover:text-white hover:bg-rose-500 rounded-md border border-rose-200">
                                                                    <i data-lucide="trash-2" class="w-3 h-3"></i> Hapus
                                                                </button>
                                                            </form>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                            </div>

                            <form method="POST" action="chat.php?user_id=<?= $selected_id ?>" class="p-3 bg-white border-t border-stone-100 flex gap-2 shrink-0" autocomplete="off">
                                <input type="hidden" name="action" value="kirim">
                                <input type="text" name="message" placeholder="Ketik balasan untuk pelanggan..." required 
                                       class="flex-1 px-4 py-2.5 text-xs bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32] transition">
                                <button type="submit" class="bg-[#2E7D32] hover:bg-emerald-800 text-white px-5 py-2.5 rounded-xl text-xs font-bold transition inline-flex items-center gap-2">
                                    <span>Kirim</span>
                                    <i data-lucide="send" class="w-3.5 h-3.5"></i>
                                </button>
                            </form>

                        <?php endif; ?>
                    </section>

                </div>

            </div>
        </main>
    </div>

    <script>
        lucide.createIcons();
        const chatBox = document.getElementById('chat-box');
        if (chatBox) chatBox.scrollTop = chatBox.scrollHeight;
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