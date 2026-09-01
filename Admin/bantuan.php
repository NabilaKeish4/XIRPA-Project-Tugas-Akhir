<?php
session_start();
require_once '../Config/database.php'; // Koneksi DB plant_hub

// Ambil data pengaturan untuk nama toko & profil di header
$res_pengaturan = mysqli_query($conn, "SELECT * FROM pengaturan WHERE id = 1");
$data_toko     = mysqli_fetch_assoc($res_pengaturan);

$avatar_src = (!empty($data_toko['foto_profil']) && file_exists('../uploads/' . $data_toko['foto_profil'])) 
    ? '../uploads/' . $data_toko['foto_profil'] 
    : 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=120';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pusat Bantuan - <?= htmlspecialchars($data_toko['nama_toko'] ?? 'PlantShop') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F9F8F6; color: #2D3748; }
    </style>
</head>
<body class="antialiased min-h-screen flex flex-col">

    <!-- TOP NAVBAR -->
    <header class="relative z-50 bg-white border-b border-stone-200/80 shadow-sm">
        <div class="px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
            
            <div class="flex items-center gap-3">
                <button id="mobile-menu-btn" type="button" class="lg:hidden p-2 rounded-lg text-stone-600 hover:bg-stone-100 cursor-pointer">
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>
                <a href="dashboard.php" class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-[#2E7D32] flex items-center justify-center text-white shadow-sm shadow-emerald-900/20">
                        <i data-lucide="sprout" class="w-5 h-5"></i>
                    </div>
                    <span class="text-xl font-bold tracking-tight text-stone-800">Plant<span class="text-[#2E7D32]">Shop</span></span>
                </a>
            </div>

            <div class="hidden md:flex flex-1 max-w-md relative">
                <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400"></i>
                <input type="text" id="faqSearchInput" onkeyup="searchFAQ()" placeholder="Cari pertanyaan atau bantuan..." class="w-full pl-10 pr-4 py-2 text-sm bg-stone-100/70 border border-transparent rounded-full focus:outline-none focus:bg-white focus:border-[#2E7D32] transition-all">
            </div>

            <!-- DROPDOWN NAVBAR -->
            <div class="flex items-center gap-3">
                
                <!-- NOTIFIKASI -->
                <div class="relative">
                    <button type="button" onclick="toggleDropdown('notifBox')" class="p-2 text-stone-600 hover:text-stone-900 hover:bg-stone-100 rounded-full transition-colors cursor-pointer relative">
                        <i data-lucide="bell" class="w-5 h-5"></i>
                        <span class="absolute top-1.5 right-1.5 w-2.5 h-2.5 bg-[#D97706] rounded-full ring-2 ring-white"></span>
                    </button>
                    <div id="notifBox" class="hidden absolute right-0 mt-2 w-64 bg-white border border-stone-200 rounded-2xl shadow-xl py-2 z-50">
                        <div class="px-4 py-1.5 border-b border-stone-100 text-xs font-bold text-stone-800">Notifikasi</div>
                        <p class="px-4 py-3 text-xs text-stone-500">Tidak ada notifikasi baru hari ini.</p>
                    </div>
                </div>

                <div class="h-6 w-px bg-stone-200 hidden sm:block"></div>

                <!-- PROFIL DROPDOWN -->
                <div class="relative">
                    <button type="button" onclick="toggleDropdown('profileBox')" class="flex items-center gap-3 p-1 rounded-xl hover:bg-stone-50 cursor-pointer focus:outline-none">
                        <img id="navAvatar" src="<?= $avatar_src ?>" alt="Nabila" class="w-9 h-9 rounded-full object-cover ring-2 ring-[#2E7D32]/20">
                        <div class="hidden sm:block text-left">
                            <p class="text-sm font-semibold text-stone-800 leading-tight">Nabila</p>
                            <p class="text-xs text-stone-500">Administrator</p>
                        </div>
                        <i data-lucide="chevron-down" class="w-4 h-4 text-stone-400 hidden sm:block"></i>
                    </button>

                    <div id="profileBox" class="hidden absolute right-0 mt-2 w-48 bg-white border border-stone-200 rounded-2xl shadow-xl py-1 z-50">
                        <a href="pengaturan.php" class="flex items-center gap-2 px-4 py-2 text-xs font-semibold text-stone-700 hover:bg-stone-100">
                            <i data-lucide="settings" class="w-4 h-4"></i> Pengaturan
                        </a>
                        <hr class="my-1 border-stone-100">
                        <a href="logout.php" onclick="return confirm('Keluar dari sistem?')" class="flex items-center gap-2 px-4 py-2 text-xs font-semibold text-red-600 hover:bg-red-50">
                            <i data-lucide="log-out" class="w-4 h-4"></i> Logout
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </header>

    <div class="flex flex-1 relative z-10">
        <!-- SIDEBAR -->
        <aside id="sidebar" class="w-64 bg-white border-r border-stone-200/80 hidden lg:flex flex-col justify-between shrink-0">
            <div class="p-4 space-y-6">
                <nav class="space-y-1">
                    <p class="px-3 text-[11px] font-bold text-stone-400 uppercase tracking-wider mb-2">Main Menu</p>
                    <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100">
                        <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard
                    </a>
                    <a href="pos.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100">
                        <i data-lucide="shopping-bag" class="w-4 h-4"></i> Penjualan (POS)
                    </a>
                    <a href="restock.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100">
                        <i data-lucide="truck" class="w-4 h-4"></i> Pembelian (Restock)
                    </a>
                    <a href="stok.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100">
                        <i data-lucide="package" class="w-4 h-4"></i> Stok & Produk
                    </a>
                    <a href="pelanggan.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100">
                        <i data-lucide="users" class="w-4 h-4"></i> Pelanggan
                    </a>
                    <a href="laporan.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100">
                        <i data-lucide="bar-chart-3" class="w-4 h-4"></i> Laporan
                    </a>
                </nav>

                <hr class="border-stone-100">

                <nav class="space-y-1">
                    <p class="px-3 text-[11px] font-bold text-stone-400 uppercase tracking-wider mb-2">Pengaturan</p>
                    <a href="pengaturan.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100">
                        <i data-lucide="settings" class="w-4 h-4"></i> Pengaturan Toko
                    </a>
                    <a href="bantuan.php" class="flex items-center justify-between px-3 py-2.5 text-sm font-semibold text-[#2E7D32] bg-[#2E7D32]/10 rounded-xl">
                        <div class="flex items-center gap-3">
                            <i data-lucide="help-circle" class="w-4 h-4"></i> Bantuan
                        </div>
                        <span class="w-1.5 h-1.5 rounded-full bg-[#2E7D32]"></span>
                    </a>
                </nav>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-5xl mx-auto w-full space-y-6">
            
            <div>
                <h1 class="text-2xl font-bold text-stone-800 tracking-tight">Pusat Bantuan & Panduan</h1>
                <p class="text-sm text-stone-500 mt-0.5">Temukan solusi cepat dan panduan penggunaan sistem kasir PlantShop.</p>
            </div>

            <!-- PANDUAN CEPAT (CARDS) -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white p-5 rounded-2xl border border-stone-200/80 shadow-sm space-y-2">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-[#2E7D32] flex items-center justify-center font-bold">
                        <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                    </div>
                    <h3 class="text-sm font-bold text-stone-800">Transaksi Kasir (POS)</h3>
                    <p class="text-xs text-stone-500 leading-relaxed">Pilih tanaman, tentukan kuantitas, pilih pelanggan, lalu cetak nota transaksi pembayaran.</p>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-stone-200/80 shadow-sm space-y-2">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center font-bold">
                        <i data-lucide="box" class="w-5 h-5"></i>
                    </div>
                    <h3 class="text-sm font-bold text-stone-800">Manajemen Stok</h3>
                    <p class="text-xs text-stone-500 leading-relaxed">Tambah tanaman baru, atur harga jual, serta perbarui kuantitas persediaan di halaman Stok.</p>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-stone-200/80 shadow-sm space-y-2">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-700 flex items-center justify-center font-bold">
                        <i data-lucide="file-spreadsheet" class="w-5 h-5"></i>
                    </div>
                    <h3 class="text-sm font-bold text-stone-800">Laporan Penjualan</h3>
                    <p class="text-xs text-stone-500 leading-relaxed">Pantau pendapatan harian, bulanan, dan produk terlaris di menu Laporan Penjualan.</p>
                </div>
            </div>

            <!-- FAQ SECTION -->
            <div class="bg-white rounded-2xl border border-stone-200/80 p-6 space-y-4">
                <h2 class="text-base font-bold text-stone-800 border-b border-stone-100 pb-3">Pertanyaan Sering Diajukan (FAQ)</h2>

                <div id="faqList" class="space-y-3">
                    
                    <!-- FAQ 1 -->
                    <div class="faq-item border border-stone-200/70 rounded-xl overflow-hidden">
                        <button onclick="toggleFAQ(this)" class="w-full flex items-center justify-between p-4 text-left font-semibold text-xs sm:text-sm text-stone-800 bg-stone-50/50 hover:bg-stone-50 transition-colors">
                            <span class="faq-question">Bagaimana cara mengganti Foto Profil Admin?</span>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-stone-400 transition-transform duration-200"></i>
                        </button>
                        <div class="faq-answer hidden p-4 text-xs text-stone-600 bg-white border-t border-stone-100 leading-relaxed">
                            Buka menu <b>Pengaturan Toko</b> dari sidebar atau klik profil di pojok kanan atas. Pada bagian Foto Profil, klik tombol <i>Pilih Foto Baru</i>, pilih gambar dari perangkat Anda, lalu klik <b>Simpan Perubahan</b>.
                        </div>
                    </div>

                    <!-- FAQ 2 -->
                    <div class="faq-item border border-stone-200/70 rounded-xl overflow-hidden">
                        <button onclick="toggleFAQ(this)" class="w-full flex items-center justify-between p-4 text-left font-semibold text-xs sm:text-sm text-stone-800 bg-stone-50/50 hover:bg-stone-50 transition-colors">
                            <span class="faq-question">Bagaimana jika transaksi tidak masuk ke laporan?</span>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-stone-400 transition-transform duration-200"></i>
                        </button>
                        <div class="faq-answer hidden p-4 text-xs text-stone-600 bg-white border-t border-stone-100 leading-relaxed">
                            Pastikan proses pembayaran pada menu Penjualan (POS) telah selesai hingga tahap konfirmasi. Periksa juga filter tanggal di menu Laporan agar sesuai dengan rentang tanggal transaksi dilakukan.
                        </div>
                    </div>

                    <!-- FAQ 3 -->
                    <div class="faq-item border border-stone-200/70 rounded-xl overflow-hidden">
                        <button onclick="toggleFAQ(this)" class="w-full flex items-center justify-between p-4 text-left font-semibold text-xs sm:text-sm text-stone-800 bg-stone-50/50 hover:bg-stone-50 transition-colors">
                            <span class="faq-question">Apakah teks nota/struk bisa diubah?</span>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-stone-400 transition-transform duration-200"></i>
                        </button>
                        <div class="faq-answer hidden p-4 text-xs text-stone-600 bg-white border-t border-stone-100 leading-relaxed">
                            Bisa. Anda dapat mengubah ucapan terima kasih atau catatan kaki pada nota melalui menu <b>Pengaturan Toko</b> pada bagian <i>Preferensi Struk POS</i>.
                        </div>
                    </div>

                    <!-- FAQ 4 -->
                    <div class="faq-item border border-stone-200/70 rounded-xl overflow-hidden">
                        <button onclick="toggleFAQ(this)" class="w-full flex items-center justify-between p-4 text-left font-semibold text-xs sm:text-sm text-stone-800 bg-stone-50/50 hover:bg-stone-50 transition-colors">
                            <span class="faq-question">Bagaimana cara menambah pasokan tanaman (Restock)?</span>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-stone-400 transition-transform duration-200"></i>
                        </button>
                        <div class="faq-answer hidden p-4 text-xs text-stone-600 bg-white border-t border-stone-100 leading-relaxed">
                            Pilih menu <b>Pembelian (Restock)</b> pada sidebar, masukkan data pemasok/supplier, pilih item tanaman yang ditambahkan, lalu simpan untuk memperbarui kuantitas stok secara otomatis.
                        </div>
                    </div>

                </div>
            </div>

            <!-- KONTAK SUPPORT -->
            <div class="bg-stone-800 text-white rounded-2xl p-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="space-y-1 text-center sm:text-left">
                    <h3 class="text-base font-bold">Masih butuh bantuan tambahan?</h3>
                    <p class="text-xs text-stone-300">Tim teknis siap membantu kendala sistem aplikasi kasir Anda.</p>
                </div>
                <a href="https://wa.me/6281234567890" target="_blank" class="inline-flex items-center gap-2 bg-[#2E7D32] hover:bg-emerald-700 text-white px-5 py-2.5 rounded-xl text-xs font-semibold transition-colors shrink-0">
                    <i data-lucide="message-square" class="w-4 h-4"></i> Hubungi Tim Support
                </a>
            </div>

        </main>
    </div>

    <!-- JAVASCRIPT HANDLER -->
    <script>
        lucide.createIcons();

        // Toggle Navbar Dropdowns
        function toggleDropdown(id) {
            const notif = document.getElementById('notifBox');
            const profile = document.getElementById('profileBox');

            if (id === 'notifBox') {
                notif.classList.toggle('hidden');
                profile.classList.add('hidden');
            } else if (id === 'profileBox') {
                profile.classList.toggle('hidden');
                notif.classList.add('hidden');
            }
        }

        // Toggle Accordion FAQ
        function toggleFAQ(button) {
            const faqItem = button.parentElement;
            const answer = faqItem.querySelector('.faq-answer');
            const icon = button.querySelector('i');

            answer.classList.toggle('hidden');
            icon.classList.toggle('rotate-180');
        }

        // Search FAQ Realtime
        function searchFAQ() {
            const input = document.getElementById('faqSearchInput').value.toLowerCase();
            const faqItems = document.querySelectorAll('.faq-item');

            faqItems.forEach(item => {
                const text = item.innerText.toLowerCase();
                if (text.includes(input)) {
                    item.style.display = "";
                } else {
                    item.style.display = "none";
                }
            });
        }

        // Sidebar Mobile Toggle
        document.getElementById('mobile-menu-btn')?.addEventListener('click', () => {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('hidden');
            sidebar.classList.toggle('fixed');
            sidebar.classList.toggle('inset-y-0');
            sidebar.classList.toggle('left-0');
            sidebar.classList.toggle('z-40');
        });
    </script>
</body>
</html>