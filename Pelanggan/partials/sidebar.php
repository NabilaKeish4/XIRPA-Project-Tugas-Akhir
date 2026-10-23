<?php
$activePage = $activePage ?? '';
$menuPelanggan = [
    ['key' => 'dashboard', 'url' => 'dashboard.php', 'icon' => 'layout-grid',    'label' => 'Beranda'],
    ['key' => 'katalog',   'url' => 'katalog.php',   'icon' => 'store',          'label' => 'Katalog Shop'],
    ['key' => 'cart',      'url' => 'cart.php',      'icon' => 'shopping-bag',   'label' => 'Keranjang'],
    ['key' => 'riwayat',   'url' => 'riwayat.php',   'icon' => 'history',        'label' => 'Riwayat Order'],
    ['key' => 'chat',      'url' => 'chat.php',      'icon' => 'message-square', 'label' => 'Konsultasi'],
    ['key' => 'profil',    'url' => 'profil.php',    'icon' => 'user',           'label' => 'Profil Saya'],
];
?>
<aside id="sidebar" class="w-64 bg-white border-r border-stone-200/80 hidden lg:flex flex-col justify-between shrink-0 p-4 no-print">
    <div class="space-y-6">
        <nav class="space-y-1">
            <p class="px-3 text-[11px] font-bold text-stone-400 uppercase tracking-wider mb-3">MENU PELANGGAN</p>
            <?php foreach ($menuPelanggan as $m):
                $aktif = ($m['key'] === $activePage);
                $cls   = $aktif ? 'text-[#1E7D32] bg-[#E8F5E9]' : 'text-stone-700 hover:bg-stone-100';
            ?>
                <a href="<?= $m['url'] ?>" class="flex items-center justify-between px-3 py-2.5 text-sm font-bold rounded-xl transition-colors <?= $cls ?>">
                    <div class="flex items-center gap-3">
                        <i data-lucide="<?= $m['icon'] ?>" class="w-5 h-5 <?= $aktif ? 'text-[#1E7D32]' : 'text-stone-500' ?>"></i>
                        <span><?= $m['label'] ?></span>
                    </div>
                    <?php if ($aktif): ?><span class="w-2.5 h-2.5 rounded-full bg-[#1E7D32]"></span><?php endif; ?>
                    <?php if ($m['key'] === 'chat' && ($unreadChat ?? 0) > 0): ?>
                        <span class="bg-[#D97706] text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full"><?= (int)$unreadChat ?></span>
                    <?php endif; ?>
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
            <p id="cart-count-sidebar" class="text-[11px] text-stone-400 mt-0.5"><?= (int)$cart_count ?> item</p>
        </div>
    </div>
</aside>