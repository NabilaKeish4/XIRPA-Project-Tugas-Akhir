<?php
session_start();
require_once '../Config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header("Location: ../Auth/login.php");
    exit;
}

$user_id    = (int)$_SESSION['user_id'];
$nama_user  = $_SESSION['nama_user'] ?? 'Pelanggan';
$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;

$pesan_error  = '';
$pesan_sukses = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'kirim') {
        $msg = trim($_POST['message'] ?? '');
        if ($msg !== '') {
            $clean = mysqli_real_escape_string($conn, $msg);
            $sql = "INSERT INTO chats (user_id, sender_type, message, is_read, created_at) 
                    VALUES ($user_id, 'customer', '$clean', 0, NOW())";
            if (mysqli_query($conn, $sql)) {
                header("Location: chat.php?status=sent");
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
            $sql = "UPDATE chats 
                    SET message = '$clean', edited_at = NOW() 
                    WHERE id = $id AND user_id = $user_id AND sender_type = 'customer'";
            if (mysqli_query($conn, $sql)) {
                header("Location: chat.php?status=edited");
                exit;
            } else {
                $pesan_error = "Gagal mengedit: " . mysqli_error($conn);
            }
        }
    }

    if ($action === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $sql = "UPDATE chats SET is_deleted = 1 
                    WHERE id = $id AND user_id = $user_id AND sender_type = 'customer'";
            if (mysqli_query($conn, $sql)) {
                header("Location: chat.php?status=deleted");
                exit;
            } else {
                $pesan_error = "Gagal menghapus: " . mysqli_error($conn);
            }
        }
    }
}

if (isset($_GET['status'])) {
    if ($_GET['status'] === 'sent')    $pesan_sukses = "Pesan berhasil dikirim.";
    if ($_GET['status'] === 'edited')  $pesan_sukses = "Pesan berhasil diperbarui.";
    if ($_GET['status'] === 'deleted') $pesan_sukses = "Pesan berhasil dihapus.";
}

$chatMessages = [];
$qChat = mysqli_query($conn, "
    SELECT * FROM chats 
    WHERE user_id = $user_id AND (is_deleted = 0 OR is_deleted IS NULL)
    ORDER BY created_at ASC
");
if ($qChat) while ($row = mysqli_fetch_assoc($qChat)) $chatMessages[] = $row;

$editId = (int)($_GET['edit'] ?? 0);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantHub - Konsultasi</title>
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
                    <div class="w-9 h-9 rounded-xl bg-[#2E7D32] flex items-center justify-center text-white">
                        <i data-lucide="sprout" class="w-5 h-5"></i>
                    </div>
                    <span class="text-xl font-bold tracking-tight text-stone-800">Plant<span class="text-[#2E7D32]">Hub</span></span>
                </a>
            </div>

            <div class="hidden md:flex flex-1 max-w-md">
                <span class="text-xs text-stone-500 self-center">Konsultasi dengan admin PlantHub</span>
            </div>

            <div class="flex items-center gap-3">
                <a href="cart.php" class="relative p-2 text-stone-600 hover:bg-stone-100 rounded-full">
                    <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                    <?php if ($cart_count > 0): ?>
                        <span class="absolute top-1 right-1 w-4 h-4 bg-[#2E7D32] text-white text-[9px] font-bold rounded-full flex items-center justify-center ring-2 ring-white"><?= $cart_count ?></span>
                    <?php endif; ?>
                </a>
                <div class="h-6 w-px bg-stone-200 hidden sm:block"></div>
                <a href="profil.php" class="flex items-center gap-3 pl-1">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($nama_user) ?>&background=2E7D32&color=fff" class="w-9 h-9 rounded-full object-cover ring-2 ring-[#2E7D32]/20">
                    <div class="hidden sm:block text-left">
                        <p class="text-sm font-semibold text-stone-800 leading-tight"><?= htmlspecialchars($nama_user) ?></p>
                        <p class="text-xs text-stone-500">Pelanggan</p>
                    </div>
                </a>
            </div>
        </div>
    </header>

    <div class="flex flex-1">
        <aside id="sidebar" class="w-64 bg-white border-r border-stone-200/80 hidden lg:flex flex-col justify-between shrink-0 p-4">
            <div class="space-y-6">
                <nav class="space-y-1">
                    <p class="px-3 text-[11px] font-bold text-stone-400 uppercase tracking-wider mb-3">MENU PELANGGAN</p>
                    <?php
                    $menu = [
                        ['url' => 'dashboard.php', 'icon' => 'layout-grid',    'label' => 'Beranda',        'active' => false],
                        ['url' => 'katalog.php',   'icon' => 'store',          'label' => 'Katalog Shop',   'active' => false],
                        ['url' => 'cart.php',      'icon' => 'shopping-bag',   'label' => 'Keranjang',      'active' => false],
                        ['url' => 'riwayat.php',   'icon' => 'history',        'label' => 'Riwayat Order',  'active' => false],
                        ['url' => 'chat.php',      'icon' => 'message-square', 'label' => 'Konsultasi',     'active' => true],
                        ['url' => 'profil.php',    'icon' => 'user',           'label' => 'Profil Saya',    'active' => false],
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
                </nav>
            </div>
            <div class="p-3 bg-stone-50/80 border border-stone-200/60 rounded-2xl flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-100/70 flex items-center justify-center text-[#2E7D32] shrink-0">
                    <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                </div>
                <div class="overflow-hidden">
                    <p class="text-sm font-bold text-stone-800 leading-tight">Troli Belanja</p>
                    <p class="text-[11px] text-stone-400 mt-0.5"><?= $cart_count ?> item</p>
                </div>
            </div>
        </aside>

        <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full">
            <div class="flex flex-col h-[calc(100vh-8rem)] space-y-4">

                <div>
                    <h1 class="text-2xl font-bold text-stone-800 tracking-tight">Konsultasi</h1>
                    <p class="text-sm text-stone-500 mt-0.5">Tanyakan masalah perawatan tanaman atau produk ke admin.</p>
                </div>

                <?php if (!empty($pesan_error)): ?>
                    <div class="p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-sm flex items-center gap-2">
                        <i data-lucide="alert-circle" class="w-5 h-5 shrink-0"></i>
                        <span><?= htmlspecialchars($pesan_error) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($pesan_sukses)): ?>
                    <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm flex items-center gap-2">
                        <i data-lucide="check-circle" class="w-5 h-5 text-[#2E7D32] shrink-0"></i>
                        <span><?= htmlspecialchars($pesan_sukses) ?></span>
                    </div>
                <?php endif; ?>

                <div class="flex-1 bg-white rounded-2xl border border-stone-200/80 shadow-sm flex flex-col overflow-hidden">

                    <div class="px-5 py-3.5 border-b border-stone-100 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center text-[#2E7D32]">
                            <i data-lucide="headphones" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-stone-800">Customer Care PlantHub</p>
                            <p class="text-[11px] text-emerald-600 font-medium flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full inline-block"></span>
                                Admin Online
                            </p>
                        </div>
                    </div>

                    <div id="chat-box" class="flex-1 p-5 overflow-y-auto space-y-4 bg-stone-50/40 custom-scrollbar">

                        <?php if (empty($chatMessages)): ?>
                            <div class="text-center py-12">
                                <div class="w-14 h-14 bg-white border border-stone-200 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <i data-lucide="message-square" class="w-6 h-6 text-stone-400"></i>
                                </div>
                                <p class="text-sm font-semibold text-stone-700">Belum ada percakapan</p>
                                <p class="text-xs text-stone-500 mt-1">Kirim pesan pertama Anda ke admin.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($chatMessages as $msg): 
                                $isAdmin = ($msg['sender_type'] === 'admin');
                                $msgId   = (int)$msg['id'];
                                $isEdit  = ($editId === $msgId && !$isAdmin);
                            ?>
                                <div class="flex <?= $isAdmin ? 'justify-start' : 'justify-end' ?>" id="msg-<?= $msgId ?>">
                                    <div class="max-w-xs sm:max-w-md md:max-w-lg">
                                        <?php if ($isEdit): ?>
                                            <form method="POST" action="chat.php" class="bg-white border-2 border-[#2E7D32] rounded-2xl p-3 shadow-sm space-y-2 w-full sm:w-96">
                                                <input type="hidden" name="action" value="edit">
                                                <input type="hidden" name="id" value="<?= $msgId ?>">
                                                <textarea name="new_message" rows="3" required class="w-full text-xs bg-stone-50 border border-stone-200 rounded-lg p-2.5 focus:outline-none focus:border-[#2E7D32] resize-none"><?= htmlspecialchars($msg['message']) ?></textarea>
                                                <div class="flex items-center justify-end gap-2">
                                                    <a href="chat.php" class="px-3 py-1.5 text-[11px] font-semibold text-stone-600 hover:bg-stone-100 rounded-lg">Batal</a>
                                                    <button type="submit" class="px-3 py-1.5 text-[11px] font-bold bg-[#2E7D32] text-white rounded-lg hover:bg-emerald-800">Simpan</button>
                                                </div>
                                            </form>
                                        <?php else: ?>
                                            <div class="p-3 rounded-2xl text-xs relative <?= $isAdmin 
                                                ? 'bg-white text-stone-800 rounded-tl-none border border-stone-200/80 shadow-sm' 
                                                : 'bg-[#2E7D32] text-white rounded-tr-none shadow-sm' ?>">
                                                <p class="leading-relaxed whitespace-pre-wrap"><?= htmlspecialchars($msg['message']) ?></p>
                                                <div class="flex items-center justify-end gap-2 mt-1.5">
                                                    <?php if (!empty($msg['edited_at'])): ?>
                                                        <span class="text-[9px] italic <?= $isAdmin ? 'text-stone-400' : 'text-emerald-100' ?>">(diedit)</span>
                                                    <?php endif; ?>
                                                    <span class="text-[9px] <?= $isAdmin ? 'text-stone-400' : 'text-emerald-100' ?>">
                                                        <?= date('H:i', strtotime($msg['created_at'])) ?>
                                                    </span>
                                                </div>
                                            </div>

                                            <?php if (!$isAdmin): ?>
                                                <div class="flex items-center justify-end gap-1 mt-1.5">
                                                    <a href="chat.php?edit=<?= $msgId ?>" class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-semibold text-stone-600 hover:text-stone-900 hover:bg-stone-100 rounded-md border border-stone-200">
                                                        <i data-lucide="edit-3" class="w-3 h-3"></i> Edit
                                                    </a>
                                                    <form method="POST" action="chat.php" onsubmit="return confirm('Hapus pesan ini?');" class="inline">
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

                    <form method="POST" action="chat.php" class="p-3 bg-white border-t border-stone-100 flex gap-2" autocomplete="off">
                        <input type="hidden" name="action" value="kirim">
                        <input type="text" name="message" placeholder="Ketik pesan..." required 
                               class="flex-1 px-4 py-2.5 text-xs bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32] transition">
                        <button type="submit" class="bg-[#2E7D32] hover:bg-emerald-800 text-white px-5 py-2.5 rounded-xl text-xs font-bold transition inline-flex items-center gap-2">
                            <span>Kirim</span>
                            <i data-lucide="send" class="w-3.5 h-3.5"></i>
                        </button>
                    </form>

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