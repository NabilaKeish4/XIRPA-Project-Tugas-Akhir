<?php
$activePage = $activePage ?? '';
$isKatalog  = ($activePage === 'katalog');
?>
<header class="sticky top-0 z-30 bg-white border-b border-stone-200/80 shadow-sm no-print">
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

        <?php if (isset($headerCenter)): ?>
            <?= $headerCenter ?>
        <?php else: ?>
            <form action="katalog.php" method="GET" class="hidden md:flex flex-1 max-w-md relative">
                <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400"></i>
                <input type="text" name="search" value="<?= $isKatalog ? htmlspecialchars($_GET['search'] ?? '') : '' ?>" placeholder="Cari tanaman, pot, media tanam..." class="w-full pl-10 pr-4 py-2 text-sm bg-stone-100/70 border border-transparent rounded-full focus:outline-none focus:bg-white focus:border-[#2E7D32] placeholder:text-stone-400">
                <?php if ($isKatalog && (int)($_GET['kategori'] ?? 0) > 0): ?>
                    <input type="hidden" name="kategori" value="<?= (int)$_GET['kategori'] ?>">
                <?php endif; ?>
                <?php if ($isKatalog && !empty($_GET['sort'])): ?>
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($_GET['sort']) ?>">
                <?php endif; ?>
            </form>
        <?php endif; ?>

        <div class="flex items-center gap-3">
            <a href="cart.php" class="relative p-2 text-stone-600 hover:bg-stone-100 rounded-full">
                <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                <span id="cart-badge-header" class="absolute top-1 right-1 w-4 h-4 bg-[#2E7D32] text-white text-[9px] font-bold rounded-full flex items-center justify-center ring-2 ring-white <?= $cart_count > 0 ? '' : 'hidden' ?>"><?= (int)$cart_count ?></span>
            </a>
            <div class="h-6 w-px bg-stone-200 hidden sm:block"></div>
            <div class="relative">
                <button onclick="toggleProfileMenu()" class="flex items-center gap-3 pl-1 focus:outline-none">
                    <img src="<?= htmlspecialchars($avatarSrc) ?>" class="w-9 h-9 rounded-full object-cover ring-2 ring-[#2E7D32]/20">
                    <div class="hidden sm:block text-left">
                        <p class="text-sm font-semibold text-stone-800 leading-tight"><?= htmlspecialchars($nama_user) ?></p>
                        <p class="text-xs text-stone-500">Pelanggan</p>
                    </div>
                    <i data-lucide="chevron-down" class="w-4 h-4 text-stone-400 hidden sm:block"></i>
                </button>
                <div id="profileDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-2xl shadow-xl border border-stone-100 p-2 space-y-1 z-50">
                    <a href="profil.php" class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-stone-700 hover:bg-stone-100 rounded-xl">
                        <i data-lucide="user" class="w-4 h-4 text-stone-500"></i> Profil Saya
                    </a>
                    <a href="riwayat.php" class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-stone-700 hover:bg-stone-100 rounded-xl">
                        <i data-lucide="history" class="w-4 h-4 text-stone-500"></i> Riwayat Saya
                    </a>
                    <a href="chat.php" class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-stone-700 hover:bg-stone-100 rounded-xl">
                        <i data-lucide="message-square" class="w-4 h-4 text-stone-500"></i> Konsultasi
                    </a>
                    <hr class="border-stone-100 my-1">
                    <a href="../Auth/logout.php" onclick="return confirm('Keluar dari akun?');" class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-rose-600 hover:bg-rose-50 rounded-xl">
                        <i data-lucide="log-out" class="w-4 h-4 text-rose-500"></i> Keluar
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
    function toggleProfileMenu() {
        document.getElementById('profileDropdown')?.classList.toggle('hidden');
    }
    function toggleMobileSidebar() {
        const s = document.getElementById('sidebar');
        s?.classList.toggle('hidden');
        s?.classList.toggle('fixed');
        s?.classList.toggle('inset-y-0');
        s?.classList.toggle('left-0');
        s?.classList.toggle('z-40');
    }
    document.addEventListener('click', (e) => {
        if (!e.target.closest('#profileDropdown') && !e.target.closest('button[onclick="toggleProfileMenu()"]')) {
            document.getElementById('profileDropdown')?.classList.add('hidden');
        }
    });
</script>