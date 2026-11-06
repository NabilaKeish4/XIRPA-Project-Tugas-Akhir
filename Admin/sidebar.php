<?php
// File: Admin/includes/sidebar.php
if (!function_exists('punyaAkses')) { exit('Akses ditolak.'); }
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="space-y-1">
    <p class="px-3 text-[11px] font-bold text-stone-400 uppercase tracking-wider mb-3">MAIN MENU</p>
    <?php
    $menu = [
        ['url' => 'dashboard.php',  'icon' => 'layout-grid',    'label' => 'Dashboard',        'akses' => 'dashboard'],
        ['url' => 'pos.php',        'icon' => 'shopping-bag',   'label' => 'Kasir (POS)',      'akses' => 'pos'],
        ['url' => 'restock.php',    'icon' => 'truck',          'label' => 'Pembelian',        'akses' => 'restock'],
        ['url' => 'stok.php',       'icon' => 'box',            'label' => 'Stok & Produk',    'akses' => 'stok'],
        ['url' => 'kategori.php',   'icon' => 'tag',            'label' => 'Kategori',         'akses' => 'kategori'],
        ['url' => 'supplier.php',   'icon' => 'building-2',     'label' => 'Supplier',         'akses' => 'supplier'],
        ['url' => 'pelanggan.php',  'icon' => 'users',          'label' => 'Pelanggan',        'akses' => 'pelanggan'],
        ['url' => 'chat.php',       'icon' => 'message-square', 'label' => 'Konsultasi Chat',  'akses' => 'chat'],
        ['url' => 'transaksi.php',  'icon' => 'receipt',        'label' => 'Riwayat Transaksi','akses' => 'transaksi'],
        ['url' => 'laporan.php',    'icon' => 'bar-chart-2',    'label' => 'Laporan',          'akses' => 'laporan'],
    ];

    foreach ($menu as $m):
        if (!punyaAkses($m['akses'])) continue;
        $is_active = ($current_page === $m['url']);
        $cls = $is_active ? 'text-[#1E7D32] bg-[#E8F5E9]' : 'text-stone-700 hover:bg-stone-100';
    ?>
        <a href="<?= $m['url'] ?>" class="flex items-center justify-between px-3 py-2.5 text-sm font-bold rounded-xl transition-colors <?= $cls ?>">
            <div class="flex items-center gap-3">
                <i data-lucide="<?= $m['icon'] ?>" class="w-5 h-5 <?= $is_active ? 'text-[#1E7D32]' : 'text-stone-500' ?>"></i>
                <span><?= $m['label'] ?></span>
            </div>
            <?php if ($is_active): ?><span class="w-2.5 h-2.5 rounded-full bg-[#1E7D32]"></span><?php endif; ?>
        </a>
    <?php endforeach; ?>

    <hr class="border-stone-100 my-3">
    <p class="px-3 text-[11px] font-bold text-stone-400 uppercase tracking-wider mb-3">PENGATURAN</p>
    <?php
    $menu2 = [
        ['url' => 'pengaturan.php', 'icon' => 'settings',    'label' => 'Pengaturan Toko', 'akses' => 'pengaturan'],
        ['url' => 'bantuan.php',    'icon' => 'help-circle', 'label' => 'Bantuan',         'akses' => 'bantuan'],
    ];
    foreach ($menu2 as $m):
        if (!punyaAkses($m['akses'])) continue;
        $is_active = ($current_page === $m['url']);
        $cls = $is_active ? 'text-[#1E7D32] bg-[#E8F5E9]' : 'text-stone-700 hover:bg-stone-100';
    ?>
        <a href="<?= $m['url'] ?>" class="flex items-center justify-between px-3 py-2.5 text-sm font-bold rounded-xl transition-colors <?= $cls ?>">
            <div class="flex items-center gap-3">
                <i data-lucide="<?= $m['icon'] ?>" class="w-5 h-5 <?= $is_active ? 'text-[#1E7D32]' : 'text-stone-500' ?>"></i>
                <span><?= $m['label'] ?></span>
            </div>
            <?php if ($is_active): ?><span class="w-2.5 h-2.5 rounded-full bg-[#1E7D32]"></span><?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>