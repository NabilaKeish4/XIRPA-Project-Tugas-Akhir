<?php
session_start();
require_once '../Config/database.php';

// =========================================================
// AUTO-REDIRECT KALAU SUDAH LOGIN
// =========================================================
if (isset($_SESSION['user_id']) && !empty($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: ../Admin/dashboard.php");
        exit;
    } elseif ($_SESSION['role'] === 'customer') {
        header("Location: ../Pelanggan/dashboard.php");
        exit;
    }
}

// =========================================================
// AMBIL DATA TOKO
// =========================================================
$data_toko = null;
$qToko = mysqli_query($conn, "SELECT * FROM pengaturan WHERE id = 1 LIMIT 1");
if ($qToko && mysqli_num_rows($qToko) > 0) $data_toko = mysqli_fetch_assoc($qToko);

$nama_toko   = $data_toko['nama_toko']   ?? 'PlantHub';
$nama_cabang = $data_toko['nama_cabang'] ?? 'Cabang Utama';
$no_telepon  = $data_toko['no_telepon']  ?? '081234567890';
$email_toko  = $data_toko['email_toko']  ?? 'admin@planthub.com';
$alamat_toko = $data_toko['alamat_toko'] ?? 'Jl. Contoh No. 123';
$logo_toko   = $data_toko['logo_toko']   ?? 'default_logo.png';

$logo_src = (!empty($logo_toko) && $logo_toko !== 'default_logo.png' && file_exists('../uploads/' . $logo_toko))
    ? '../uploads/' . $logo_toko
    : null;

// =========================================================
// STATISTIK
// =========================================================
$statProduk = 0;
$statKategori = 0;
$statPelanggan = 0;
$statTransaksi = 0;

$qStat = mysqli_query($conn, "
    SELECT 
        (SELECT COUNT(*) FROM produk WHERE stok > 0) AS total_produk,
        (SELECT COUNT(*) FROM kategori) AS total_kategori,
        (SELECT COUNT(*) FROM users WHERE role = 'customer') AS total_pelanggan,
        (SELECT COUNT(*) FROM transaksi WHERE jenis_transaksi = 'penjualan' AND status = 'Selesai') AS total_transaksi
");
if ($qStat) {
    $s = mysqli_fetch_assoc($qStat);
    $statProduk     = (int)$s['total_produk'];
    $statKategori   = (int)$s['total_kategori'];
    $statPelanggan  = (int)$s['total_pelanggan'];
    $statTransaksi  = (int)$s['total_transaksi'];
}

// =========================================================
// KATEGORI
// =========================================================
$kategoriList = [];
$qKat = mysqli_query($conn, "
    SELECT k.*, 
           (SELECT COUNT(*) FROM produk p WHERE p.kategori_id = k.id AND p.stok > 0) AS jml_produk,
           (SELECT p.gambar FROM produk p WHERE p.kategori_id = k.id AND p.stok > 0 AND p.gambar IS NOT NULL AND p.gambar != 'default.jpg' LIMIT 1) AS gambar_sample
    FROM kategori k
    ORDER BY jml_produk DESC, k.id ASC
    LIMIT 4
");
if ($qKat) while ($r = mysqli_fetch_assoc($qKat)) $kategoriList[] = $r;

// =========================================================
// PRODUK TERLARIS
// =========================================================
$produkTerlaris = [];
$qProduk = mysqli_query($conn, "
    SELECT p.*, k.nama_kategori,
           COALESCE((SELECT SUM(td.jumlah) FROM transaksi_detail td 
                     JOIN transaksi t ON td.transaksi_id = t.id 
                     WHERE td.produk_id = p.id AND t.status = 'Selesai' AND t.jenis_transaksi = 'penjualan'), 0) AS total_terjual
    FROM produk p
    LEFT JOIN kategori k ON p.kategori_id = k.id
    WHERE p.stok > 0
    ORDER BY total_terjual DESC, p.id DESC
    LIMIT 3
");
if ($qProduk) while ($r = mysqli_fetch_assoc($qProduk)) $produkTerlaris[] = $r;

if (empty($produkTerlaris)) {
    $qProduk = mysqli_query($conn, "
        SELECT p.*, k.nama_kategori, 0 AS total_terjual
        FROM produk p
        LEFT JOIN kategori k ON p.kategori_id = k.id
        WHERE p.stok > 0
        ORDER BY p.id DESC
        LIMIT 3
    ");
    if ($qProduk) while ($r = mysqli_fetch_assoc($qProduk)) $produkTerlaris[] = $r;
}

function gambarLanding($nama) {
    if (!$nama || $nama === 'default.jpg') {
        return "https://images.unsplash.com/photo-1614594975525-e45190c55d0b?auto=format&fit=crop&q=80&w=800";
    }
    if (file_exists("../assets/img/" . $nama)) return "../assets/img/" . $nama;
    return "https://images.unsplash.com/photo-1614594975525-e45190c55d0b?auto=format&fit=crop&q=80&w=800";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($nama_toko) ?> — Koleksi Tanaman Hias Pilihan</title>
    <meta name="description" content="<?= htmlspecialchars($nama_toko) ?> — Kurasi tanaman hias premium untuk rumah Anda.">

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400;1,500&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --olive-deep: #2D5016;
            --olive-fresh: #4A8B2C;
            --lime-pop: #B8D96C;
            --cream: #FAF6EE;
            --earth: #5C4033;
            --terracotta: #D97D54;
        }

        * { -webkit-font-smoothing: antialiased; }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--cream);
            color: var(--earth);
        }

        .font-serif { font-family: 'Playfair Display', serif; }

        .grain::before {
            content: '';
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 1;
            opacity: 0.035;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E");
        }

        .reveal {
            opacity: 0;
            transform: translateY(24px);
            transition: opacity 0.85s cubic-bezier(0.16, 1, 0.3, 1), transform 0.85s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .reveal.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .link-underline { position: relative; display: inline-block; }
        .link-underline::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -2px;
            width: 0;
            height: 1px;
            background: currentColor;
            transition: width 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .link-underline:hover::after { width: 100%; }

        .img-zoom { overflow: hidden; }
        .img-zoom img { transition: transform 1.2s cubic-bezier(0.16, 1, 0.3, 1); }
        .img-zoom:hover img { transform: scale(1.04); }

        @keyframes marquee {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        .marquee-track { animation: marquee 50s linear infinite; }

        @keyframes gentle-rotate {
            0%, 100% { transform: rotate(0deg); }
            50% { transform: rotate(8deg); }
        }
        .gentle-rotate { animation: gentle-rotate 8s ease-in-out infinite; }

        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: var(--cream); }
        ::-webkit-scrollbar-thumb { background: #d6cfbf; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--olive-fresh); }
    </style>
</head>
<body class="grain antialiased">

    <!-- ========================================================= -->
    <!-- NAVBAR                                                    -->
    <!-- ========================================================= -->
    <nav class="sticky top-0 z-50 bg-[#FAF6EE]/85 backdrop-blur-md border-b border-[#2D5016]/8">
        <div class="max-w-7xl mx-auto px-5 lg:px-8">
            <div class="h-16 flex items-center justify-between gap-4">
                <a href="index.php" class="flex items-center gap-2.5 shrink-0">
                    <?php if ($logo_src): ?>
                        <img src="<?= htmlspecialchars($logo_src) ?>" class="w-8 h-8 rounded-full object-cover ring-1 ring-[#2D5016]/15">
                    <?php else: ?>
                        <div class="w-8 h-8 rounded-full bg-[#2D5016] flex items-center justify-center text-[#FAF6EE]">
                            <i data-lucide="sprout" class="w-4 h-4" stroke-width="2"></i>
                        </div>
                    <?php endif; ?>
                    <span class="font-serif text-lg font-semibold tracking-tight text-[#2D5016]">
                        <?= htmlspecialchars($nama_toko) ?>
                    </span>
                </a>

                <div class="hidden md:flex items-center gap-7">
                    <a href="#cerita" class="text-sm font-medium text-[#5C4033]/70 hover:text-[#2D5016] transition link-underline">Cerita</a>
                    <a href="#koleksi" class="text-sm font-medium text-[#5C4033]/70 hover:text-[#2D5016] transition link-underline">Koleksi</a>
                    <a href="#pilihan" class="text-sm font-medium text-[#5C4033]/70 hover:text-[#2D5016] transition link-underline">Pilihan</a>
                    <a href="#pesan" class="text-sm font-medium text-[#5C4033]/70 hover:text-[#2D5016] transition link-underline">Cara Pesan</a>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <a href="login.php" class="hidden sm:inline-flex items-center gap-2 px-3.5 py-2 text-sm font-medium text-[#2D5016] hover:bg-[#2D5016]/5 rounded-full transition">
                        Masuk
                    </a>
                    <a href="register.php" class="inline-flex items-center gap-2 bg-[#2D5016] hover:bg-[#1F3A0F] text-[#FAF6EE] px-4 py-2 rounded-full text-sm font-medium transition">
                        <span>Mulai</span>
                        <i data-lucide="arrow-up-right" class="w-3.5 h-3.5" stroke-width="2.5"></i>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- ========================================================= -->
    <!-- HERO                                                      -->
    <!-- ========================================================= -->
    <section class="relative overflow-hidden">
        <svg class="absolute -top-16 -left-16 w-56 h-56 text-[#4A8B2C]/8 pointer-events-none gentle-rotate" viewBox="0 0 200 200" fill="currentColor">
            <path d="M100 20c-20 30-50 50-80 60 20 20 50 30 80 20 30 10 60 0 80-20-30-10-60-30-80-60z"/>
        </svg>

        <div class="max-w-7xl mx-auto px-5 lg:px-8 pt-10 pb-16 lg:pt-16 lg:pb-24">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10 items-center">

                <!-- Left -->
                <div class="lg:col-span-6 xl:col-span-6 reveal">
                    <div class="flex items-center gap-3 mb-6">
                        <span class="w-10 h-px bg-[#2D5016]/40"></span>
                        <span class="text-[10px] tracking-[0.3em] font-medium text-[#2D5016] uppercase">Est. 2024 — Kurasi Musiman</span>
                    </div>

                    <h1 class="font-serif text-4xl sm:text-5xl lg:text-6xl leading-[1.05] font-medium text-[#2D5016] tracking-tight">
                        Rumah yang <em class="italic font-normal" style="color:#4A8B2C;">hidup</em>,<br>
                        dimulai dari<br>
                        sehelai daun.
                    </h1>

                    <p class="mt-6 text-sm lg:text-base text-[#5C4033]/75 leading-relaxed max-w-md font-light">
                        Kami mengkurasi tanaman hias yang layak tumbuh di ruang Anda. Bukan sekadar jual-beli, tapi merawat keindahan yang akan Anda tinggali setiap hari.
                    </p>

                    <div class="mt-8 flex flex-wrap items-center gap-4">
                        <a href="register.php" class="group inline-flex items-center gap-2.5 bg-[#2D5016] hover:bg-[#1F3A0F] text-[#FAF6EE] pl-5 pr-4 py-3 rounded-full text-sm font-medium transition">
                            <span>Jelajahi Koleksi</span>
                            <span class="w-5 h-5 rounded-full bg-[#B8D96C] text-[#2D5016] flex items-center justify-center transition-transform group-hover:translate-x-0.5">
                                <i data-lucide="arrow-right" class="w-3 h-3" stroke-width="2.5"></i>
                            </span>
                        </a>
                        <a href="#cerita" class="text-sm font-medium text-[#2D5016] link-underline">
                            Kenali Kami
                        </a>
                    </div>

                    <div class="mt-10 pt-6 border-t border-[#2D5016]/10 grid grid-cols-3 gap-4">
                        <div>
                            <p class="font-serif text-2xl lg:text-3xl text-[#2D5016]"><?= $statProduk ?><span class="text-[#4A8B2C] text-lg align-top">+</span></p>
                            <p class="text-[10px] text-[#5C4033]/60 mt-1 uppercase tracking-wider">Varian tanaman</p>
                        </div>
                        <div>
                            <p class="font-serif text-2xl lg:text-3xl text-[#2D5016]"><?= $statKategori ?></p>
                            <p class="text-[10px] text-[#5C4033]/60 mt-1 uppercase tracking-wider">Kategori kurasi</p>
                        </div>
                        <div>
                            <p class="font-serif text-2xl lg:text-3xl text-[#2D5016]"><?= $statPelanggan ?></p>
                            <p class="text-[10px] text-[#5C4033]/60 mt-1 uppercase tracking-wider">Pecinta daun</p>
                        </div>
                    </div>
                </div>

                <!-- Right: image SQUARE -->
                <div class="lg:col-span-6 xl:col-span-6 relative reveal">
                    <div class="absolute -top-3 -right-2 w-20 h-20 rounded-full border border-[#2D5016]/15 pointer-events-none hidden lg:block"></div>
                    <div class="absolute -bottom-5 -left-5 w-28 h-28 rounded-full border border-[#4A8B2C]/15 pointer-events-none hidden lg:block"></div>

                    <div class="relative img-zoom rounded-sm shadow-[0_25px_60px_-25px_rgba(45,80,22,0.4)] max-w-[560px] mx-auto lg:mx-0 lg:ml-auto">
                        <img src="https://images.unsplash.com/photo-1485955900006-10f4d324d411?auto=format&fit=crop&q=85&w=1200"
                             alt="Koleksi tanaman hias"
                             class="w-full aspect-square object-cover">

                        <div class="absolute bottom-4 left-4 right-4 flex items-end justify-between text-[#FAF6EE] z-10">
                            <div>
                                <p class="text-[9px] tracking-[0.3em] uppercase opacity-70">Volume 01</p>
                                <p class="font-serif text-base lg:text-lg mt-1 italic">Musim Kemarau</p>
                            </div>
                            <p class="text-[9px] tracking-[0.3em] uppercase opacity-70">PH — 001</p>
                        </div>
                        <div class="absolute inset-0 bg-gradient-to-t from-[#2D5016]/45 via-transparent to-transparent"></div>
                    </div>

                    <div class="absolute bottom-4 -right-3 lg:-right-6 bg-[#FAF6EE] border border-[#2D5016]/10 rounded-2xl p-3 shadow-xl hidden sm:block">
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 rounded-full bg-[#B8D96C]/40 flex items-center justify-center">
                                <i data-lucide="leaf" class="w-4 h-4 text-[#2D5016]" stroke-width="1.8"></i>
                            </div>
                            <div>
                                <p class="text-[9px] tracking-[0.2em] uppercase text-[#5C4033]/60">Dikirim</p>
                                <p class="text-xs font-medium text-[#2D5016]">Aman & Segar</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================================= -->
    <!-- MARQUEE                                                   -->
    <!-- ========================================================= -->
    <section class="border-y border-[#2D5016]/10 bg-[#FAF6EE] overflow-hidden py-4">
        <div class="flex whitespace-nowrap">
            <div class="marquee-track flex items-center gap-10 pr-10">
                <?php for ($i = 0; $i < 6; $i++): ?>
                    <span class="font-serif text-2xl lg:text-3xl italic text-[#2D5016]/80 flex items-center gap-10">
                        Tumbuh Bersama
                        <span class="w-1.5 h-1.5 rounded-full bg-[#D97D54] inline-block"></span>
                        Pilihan Kurator
                        <span class="w-1.5 h-1.5 rounded-full bg-[#4A8B2C] inline-block"></span>
                        Dikirim dengan Cinta
                        <span class="w-1.5 h-1.5 rounded-full bg-[#B8D96C] inline-block"></span>
                    </span>
                <?php endfor; ?>
            </div>
        </div>
    </section>

    <!-- ========================================================= -->
    <!-- CERITA KAMI                                               -->
    <!-- ========================================================= -->
    <section id="cerita" class="relative py-16 lg:py-24">
        <div class="max-w-7xl mx-auto px-5 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12">

                <div class="lg:col-span-3 reveal">
                    <p class="font-serif text-[#4A8B2C]/40 text-5xl lg:text-6xl leading-none">01</p>
                    <p class="text-[10px] tracking-[0.3em] font-medium text-[#2D5016] uppercase mt-4">Cerita Kami</p>
                    <span class="block w-10 h-px bg-[#2D5016]/30 mt-3"></span>
                </div>

                <div class="lg:col-span-9 reveal">
                    <h2 class="font-serif text-2xl sm:text-3xl lg:text-4xl leading-[1.2] text-[#2D5016] font-medium max-w-2xl">
                        Kami percaya bahwa setiap rumah berhak memiliki <em class="italic" style="color:#4A8B2C;">ruang hijau</em> yang bercerita.
                    </h2>

                    <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6 lg:gap-10 max-w-3xl">
                        <p class="text-sm text-[#5C4033]/75 leading-relaxed font-light">
                            <span class="font-serif text-4xl float-left mr-2 mt-1 leading-none text-[#2D5016]">B</span>erawal dari sebuah balkon kecil dan tiga pot tanaman, <?= htmlspecialchars($nama_toko) ?> lahir dari kecintaan sederhana pada tanaman. Kami menyadari bahwa mencari tanaman yang tepat bukan hal mudah.
                        </p>
                        <p class="text-sm text-[#5C4033]/75 leading-relaxed font-light">
                            Sejak itu, kami berkomitmen untuk mengurasi setiap tanaman dengan teliti. Bekerja sama dengan pembudidaya lokal terpercaya, memastikan setiap daun yang sampai ke tangan Anda adalah yang terbaik.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================================= -->
    <!-- KOLEKSI / KATEGORI                                        -->
    <!-- ========================================================= -->
    <?php if (!empty($kategoriList)): ?>
    <section id="koleksi" class="relative py-16 lg:py-24 bg-[#2D5016]/[0.03] border-y border-[#2D5016]/8">
        <div class="max-w-7xl mx-auto px-5 lg:px-8">

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-10">
                <div class="lg:col-span-3">
                    <p class="font-serif text-[#4A8B2C]/40 text-5xl lg:text-6xl leading-none">02</p>
                    <p class="text-[10px] tracking-[0.3em] font-medium text-[#2D5016] uppercase mt-4">Koleksi</p>
                    <span class="block w-10 h-px bg-[#2D5016]/30 mt-3"></span>
                </div>
                <div class="lg:col-span-9">
                    <h2 class="font-serif text-2xl sm:text-3xl lg:text-4xl leading-[1.2] text-[#2D5016] font-medium max-w-xl">
                        Dikurasi berdasarkan <em class="italic" style="color:#4A8B2C;">cahaya</em> dan karakter ruang Anda.
                    </h2>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <?php foreach ($kategoriList as $idx => $k):
                    $imgKat = null;
                    if (!empty($k['gambar_sample']) && file_exists('../assets/img/' . $k['gambar_sample'])) {
                        $imgKat = '../assets/img/' . $k['gambar_sample'];
                    }
                ?>
                    <a href="register.php" class="group relative block overflow-hidden rounded-sm bg-[#FAF6EE] border border-[#2D5016]/10 hover:border-[#4A8B2C]/40 transition-all duration-500">
                        <div class="aspect-square img-zoom bg-[#2D5016]/5">
                            <?php if ($imgKat): ?>
                                <img src="<?= htmlspecialchars($imgKat) ?>" 
                                     alt="<?= htmlspecialchars($k['nama_kategori']) ?>"
                                     class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-[#4A8B2C]/15 to-[#B8D96C]/15">
                                    <i data-lucide="leaf" class="w-12 h-12 text-[#2D5016]/30" stroke-width="1.2"></i>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="p-4 flex items-end justify-between gap-3">
                            <div>
                                <p class="text-[9px] tracking-[0.25em] uppercase text-[#4A8B2C] mb-1">
                                    <?= sprintf('%02d', $idx + 1) ?> — <?= (int)$k['jml_produk'] ?> item
                                </p>
                                <h3 class="font-serif text-lg text-[#2D5016] leading-tight">
                                    <?= htmlspecialchars($k['nama_kategori']) ?>
                                </h3>
                            </div>
                            <span class="w-7 h-7 rounded-full border border-[#2D5016]/20 group-hover:bg-[#2D5016] group-hover:border-[#2D5016] group-hover:text-[#FAF6EE] text-[#2D5016] flex items-center justify-center transition-all duration-300 shrink-0">
                                <i data-lucide="arrow-up-right" class="w-3 h-3" stroke-width="2.2"></i>
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ========================================================= -->
    <!-- PILIHAN KURATOR                                           -->
    <!-- ========================================================= -->
    <?php if (!empty($produkTerlaris)): ?>
    <section id="pilihan" class="relative py-16 lg:py-24">
        <div class="max-w-7xl mx-auto px-5 lg:px-8">

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-10">
                <div class="lg:col-span-3">
                    <p class="font-serif text-[#4A8B2C]/40 text-5xl lg:text-6xl leading-none">03</p>
                    <p class="text-[10px] tracking-[0.3em] font-medium text-[#2D5016] uppercase mt-4">Pilihan Kurator</p>
                    <span class="block w-10 h-px bg-[#2D5016]/30 mt-3"></span>
                </div>
                <div class="lg:col-span-9 flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                    <h2 class="font-serif text-2xl sm:text-3xl lg:text-4xl leading-[1.2] text-[#2D5016] font-medium max-w-lg">
                        Sedang <em class="italic" style="color:#4A8B2C;">dicari</em> banyak orang musim ini.
                    </h2>
                    <a href="register.php" class="inline-flex items-center gap-2 text-sm font-medium text-[#2D5016] link-underline shrink-0 self-start md:self-end">
                        Lihat semua koleksi
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5" stroke-width="2.2"></i>
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

                <?php $first = $produkTerlaris[0] ?? null; ?>
                <?php if ($first): $imgFirst = gambarLanding($first['gambar']); ?>
                    <div class="lg:col-span-7 group cursor-pointer reveal">
                        <div class="relative overflow-hidden rounded-sm bg-[#2D5016]/5 img-zoom">
                            <img src="<?= htmlspecialchars($imgFirst) ?>" 
                                 alt="<?= htmlspecialchars($first['nama_tanaman']) ?>"
                                 class="w-full aspect-square object-cover">
                            <?php if ((int)$first['total_terjual'] > 0): ?>
                                <span class="absolute top-4 left-4 bg-[#FAF6EE] text-[#2D5016] text-[9px] tracking-[0.25em] font-medium px-2.5 py-1 rounded-full uppercase">
                                    Terlaris
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="mt-5 flex items-start justify-between gap-4">
                            <div>
                                <p class="text-[9px] tracking-[0.25em] uppercase text-[#4A8B2C] mb-1.5">
                                    <?= htmlspecialchars($first['nama_kategori'] ?? 'Tanaman') ?>
                                </p>
                                <h3 class="font-serif text-xl lg:text-2xl text-[#2D5016] leading-tight">
                                    <?= htmlspecialchars($first['nama_tanaman']) ?>
                                </h3>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-[9px] tracking-[0.25em] uppercase text-[#5C4033]/50 mb-1">Mulai dari</p>
                                <p class="font-serif text-lg text-[#2D5016]">
                                    Rp <?= number_format($first['harga_jual'], 0, ',', '.') ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="lg:col-span-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-1 gap-4">
                    <?php for ($i = 1; $i < 3; $i++): 
                        if (!isset($produkTerlaris[$i])) continue;
                        $p = $produkTerlaris[$i];
                        $imgP = gambarLanding($p['gambar']);
                    ?>
                        <div class="group cursor-pointer reveal">
                            <div class="overflow-hidden rounded-sm bg-[#2D5016]/5 img-zoom">
                                <img src="<?= htmlspecialchars($imgP) ?>" 
                                     alt="<?= htmlspecialchars($p['nama_tanaman']) ?>"
                                     class="w-full aspect-square object-cover">
                            </div>
                            <div class="mt-3.5 flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-[9px] tracking-[0.25em] uppercase text-[#4A8B2C] mb-1">
                                        <?= htmlspecialchars($p['nama_kategori'] ?? 'Tanaman') ?>
                                    </p>
                                    <h3 class="font-serif text-base text-[#2D5016] leading-tight">
                                        <?= htmlspecialchars($p['nama_tanaman']) ?>
                                    </h3>
                                </div>
                                <p class="font-serif text-sm text-[#2D5016] shrink-0">
                                    Rp <?= number_format($p['harga_jual'], 0, ',', '.') ?>
                                </p>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ========================================================= -->
    <!-- CARA PESAN                                                -->
    <!-- ========================================================= -->
    <section id="pesan" class="relative py-16 lg:py-24 bg-[#2D5016] text-[#FAF6EE] overflow-hidden">
        <svg class="absolute -bottom-24 -right-24 w-72 h-72 text-[#B8D96C]/10 pointer-events-none" viewBox="0 0 200 200" fill="currentColor">
            <path d="M100 20c-20 30-50 50-80 60 20 20 50 30 80 20 30 10 60 0 80-20-30-10-60-30-80-60z"/>
        </svg>

        <div class="max-w-7xl mx-auto px-5 lg:px-8 relative">

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-10">
                <div class="lg:col-span-3">
                    <p class="font-serif text-[#B8D96C]/40 text-5xl lg:text-6xl leading-none">04</p>
                    <p class="text-[10px] tracking-[0.3em] font-medium text-[#B8D96C] uppercase mt-4">Cara Pesan</p>
                    <span class="block w-10 h-px bg-[#B8D96C]/30 mt-3"></span>
                </div>
                <div class="lg:col-span-9">
                    <h2 class="font-serif text-2xl sm:text-3xl lg:text-4xl leading-[1.2] font-medium max-w-xl">
                        Tiga langkah untuk memiliki <em class="italic text-[#B8D96C]">ruang hijau</em> Anda sendiri.
                    </h2>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-px bg-[#FAF6EE]/10 rounded-sm overflow-hidden">

                <div class="bg-[#2D5016] p-6 lg:p-8 reveal">
                    <div class="flex items-baseline gap-3 mb-5">
                        <span class="font-serif text-4xl text-[#B8D96C]">01</span>
                        <span class="text-[9px] tracking-[0.25em] uppercase text-[#FAF6EE]/50">Langkah Pertama</span>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-[#FAF6EE]/10 border border-[#FAF6EE]/20 flex items-center justify-center mb-5">
                        <i data-lucide="user-plus" class="w-4 h-4 text-[#B8D96C]" stroke-width="1.8"></i>
                    </div>
                    <h3 class="font-serif text-xl mb-2.5">Buat Akun</h3>
                    <p class="text-xs text-[#FAF6EE]/65 leading-relaxed font-light">
                        Daftar gratis dalam satu menit. Cukup nama, email, dan kata sandi — kami tidak akan mengganggu dengan spam.
                    </p>
                </div>

                <div class="bg-[#2D5016] p-6 lg:p-8 reveal">
                    <div class="flex items-baseline gap-3 mb-5">
                        <span class="font-serif text-4xl text-[#B8D96C]">02</span>
                        <span class="text-[9px] tracking-[0.25em] uppercase text-[#FAF6EE]/50">Langkah Kedua</span>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-[#FAF6EE]/10 border border-[#FAF6EE]/20 flex items-center justify-center mb-5">
                        <i data-lucide="shopping-bag" class="w-4 h-4 text-[#B8D96C]" stroke-width="1.8"></i>
                    </div>
                    <h3 class="font-serif text-xl mb-2.5">Pilih & Pesan</h3>
                    <p class="text-xs text-[#FAF6EE]/65 leading-relaxed font-light">
                        Telusuri koleksi kurasi kami, tambahkan ke keranjang, lalu selesaikan pesanan dengan mudah dan aman.
                    </p>
                </div>

                <div class="bg-[#2D5016] p-6 lg:p-8 reveal">
                    <div class="flex items-baseline gap-3 mb-5">
                        <span class="font-serif text-4xl text-[#B8D96C]">03</span>
                        <span class="text-[9px] tracking-[0.25em] uppercase text-[#FAF6EE]/50">Langkah Ketiga</span>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-[#FAF6EE]/10 border border-[#FAF6EE]/20 flex items-center justify-center mb-5">
                        <i data-lucide="package-check" class="w-4 h-4 text-[#B8D96C]" stroke-width="1.8"></i>
                    </div>
                    <h3 class="font-serif text-xl mb-2.5">Terima & Rawat</h3>
                    <p class="text-xs text-[#FAF6EE]/65 leading-relaxed font-light">
                        Kami kemas dengan hati-hati dan kirim cepat. Tanaman Anda siap memulai cerita baru di rumah.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================================= -->
    <!-- CTA FINAL                                                 -->
    <!-- ========================================================= -->
    <section class="py-16 lg:py-24">
        <div class="max-w-4xl mx-auto px-5 lg:px-8 text-center reveal">
            <p class="text-[10px] tracking-[0.3em] font-medium text-[#4A8B2C] uppercase mb-5">Mari Mulai</p>

            <h2 class="font-serif text-3xl sm:text-4xl lg:text-5xl leading-[1.1] text-[#2D5016] font-medium">
                Rumah Anda <em class="italic" style="color:#4A8B2C;">menunggu</em><br>
                sesuatu yang hijau.
            </h2>

            <p class="mt-5 text-sm text-[#5C4033]/70 max-w-lg mx-auto font-light leading-relaxed">
                Bergabung dengan <?= number_format($statPelanggan, 0, ',', '.') ?> pecinta tanaman yang telah menemukan tanaman impian mereka di <?= htmlspecialchars($nama_toko) ?>.
            </p>

            <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
                <a href="register.php" class="group inline-flex items-center gap-2.5 bg-[#2D5016] hover:bg-[#1F3A0F] text-[#FAF6EE] pl-5 pr-4 py-3.5 rounded-full text-sm font-medium transition">
                    <span>Mulai Belanja Sekarang</span>
                    <span class="w-5 h-5 rounded-full bg-[#B8D96C] text-[#2D5016] flex items-center justify-center transition-transform group-hover:translate-x-0.5">
                        <i data-lucide="arrow-right" class="w-3 h-3" stroke-width="2.5"></i>
                    </span>
                </a>
                <a href="login.php" class="text-sm font-medium text-[#2D5016] link-underline">
                    Sudah punya akun? Masuk
                </a>
            </div>
        </div>
    </section>

    <!-- ========================================================= -->
    <!-- FOOTER                                                    -->
    <!-- ========================================================= -->
    <footer class="border-t border-[#2D5016]/10 bg-[#FAF6EE] pt-14 pb-8">
        <div class="max-w-7xl mx-auto px-5 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-10 pb-12">

                <div class="md:col-span-5 space-y-5">
                    <div class="flex items-center gap-2.5">
                        <?php if ($logo_src): ?>
                            <img src="<?= htmlspecialchars($logo_src) ?>" class="w-9 h-9 rounded-full object-cover ring-1 ring-[#2D5016]/15">
                        <?php else: ?>
                            <div class="w-9 h-9 rounded-full bg-[#2D5016] flex items-center justify-center text-[#FAF6EE]">
                                <i data-lucide="sprout" class="w-4 h-4" stroke-width="2"></i>
                            </div>
                        <?php endif; ?>
                        <span class="font-serif text-xl font-semibold text-[#2D5016]"><?= htmlspecialchars($nama_toko) ?></span>
                    </div>
                    <p class="text-sm text-[#5C4033]/70 leading-relaxed font-light max-w-md">
                        Kurasi tanaman hias premium untuk ruang yang ingin Anda tinggali lebih lama. Setiap daun, cerita.
                    </p>

                    <div class="flex items-center gap-2 pt-1">
                        <a href="#" class="w-8 h-8 rounded-full border border-[#2D5016]/15 hover:bg-[#2D5016] hover:border-[#2D5016] hover:text-[#FAF6EE] text-[#2D5016] flex items-center justify-center transition">
                            <i data-lucide="instagram" class="w-3.5 h-3.5" stroke-width="1.8"></i>
                        </a>
                        <a href="#" class="w-8 h-8 rounded-full border border-[#2D5016]/15 hover:bg-[#2D5016] hover:border-[#2D5016] hover:text-[#FAF6EE] text-[#2D5016] flex items-center justify-center transition">
                            <i data-lucide="facebook" class="w-3.5 h-3.5" stroke-width="1.8"></i>
                        </a>
                        <a href="#" class="w-8 h-8 rounded-full border border-[#2D5016]/15 hover:bg-[#2D5016] hover:border-[#2D5016] hover:text-[#FAF6EE] text-[#2D5016] flex items-center justify-center transition">
                            <i data-lucide="twitter" class="w-3.5 h-3.5" stroke-width="1.8"></i>
                        </a>
                    </div>
                </div>

                <div class="md:col-span-2">
                    <h4 class="text-[10px] tracking-[0.25em] font-medium text-[#2D5016] uppercase mb-4">Jelajahi</h4>
                    <ul class="space-y-2.5 text-sm">
                        <li><a href="#cerita" class="text-[#5C4033]/70 hover:text-[#2D5016] transition">Cerita</a></li>
                        <li><a href="#koleksi" class="text-[#5C4033]/70 hover:text-[#2D5016] transition">Koleksi</a></li>
                        <li><a href="#pilihan" class="text-[#5C4033]/70 hover:text-[#2D5016] transition">Pilihan</a></li>
                        <li><a href="#pesan" class="text-[#5C4033]/70 hover:text-[#2D5016] transition">Cara Pesan</a></li>
                    </ul>
                </div>

                <div class="md:col-span-2">
                    <h4 class="text-[10px] tracking-[0.25em] font-medium text-[#2D5016] uppercase mb-4">Akun</h4>
                    <ul class="space-y-2.5 text-sm">
                        <li><a href="register.php" class="text-[#5C4033]/70 hover:text-[#2D5016] transition">Daftar</a></li>
                        <li><a href="login.php" class="text-[#5C4033]/70 hover:text-[#2D5016] transition">Masuk</a></li>
                    </ul>
                </div>

                <div class="md:col-span-3">
                    <h4 class="text-[10px] tracking-[0.25em] font-medium text-[#2D5016] uppercase mb-4">Kontak</h4>
                    <ul class="space-y-3.5 text-sm">
                        <li class="flex items-start gap-2.5 text-[#5C4033]/70 font-light leading-relaxed">
                            <i data-lucide="map-pin" class="w-3.5 h-3.5 text-[#4A8B2C] shrink-0 mt-0.5" stroke-width="1.8"></i>
                            <span><?= htmlspecialchars($alamat_toko) ?></span>
                        </li>
                        <li class="flex items-center gap-2.5 text-[#5C4033]/70 font-light">
                            <i data-lucide="phone" class="w-3.5 h-3.5 text-[#4A8B2C] shrink-0" stroke-width="1.8"></i>
                            <span><?= htmlspecialchars($no_telepon) ?></span>
                        </li>
                        <li class="flex items-center gap-2.5 text-[#5C4033]/70 font-light">
                            <i data-lucide="mail" class="w-3.5 h-3.5 text-[#4A8B2C] shrink-0" stroke-width="1.8"></i>
                            <span><?= htmlspecialchars($email_toko) ?></span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="pt-6 border-t border-[#2D5016]/10 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-[#5C4033]/50 font-light">
                <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($nama_toko) ?>. Seluruh hak cipta dilindungi.</p>
                <p>Dirancang dengan perhatian pada setiap detail.</p>
            </div>
        </div>
    </footer>

    <script>
        lucide.createIcons();

        const reveals = document.querySelectorAll('.reveal');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry, i) => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        entry.target.classList.add('visible');
                    }, i * 80);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -50px 0px' });

        reveals.forEach(el => observer.observe(el));

        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    </script>
</body>
</html>