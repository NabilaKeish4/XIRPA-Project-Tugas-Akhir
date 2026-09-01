<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantShop - Restock Supplier</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            bg: '#F9F8F6',
                            text: '#2D3748',
                            primary: '#2E7D32',
                            'primary-hover': '#236327',
                            'primary-light': '#E8F5E9',
                            danger: '#E53E3E',
                            'danger-light': '#FFF5F5',
                        }
                    },
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background-color: #F9F8F6;
            color: #2D3748;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        input::-webkit-outer-spin-button,
        input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        input[type=number] {
            -moz-appearance: textfield;
        }
    </style>
</head>
<body class="antialiased min-h-screen flex flex-col">

    <?php
    $suppliers = [
        ['id' => 1, 'name' => 'CV. Flora Utama'],
        ['id' => 2, 'name' => 'Tani Makmur Jaya'],
        ['id' => 3, 'name' => 'Nursery Hijau Lestari'],
    ];

    $plants = [
        ['id' => 101, 'name' => 'Monstera Deliciosa', 'default_price' => 75000],
        ['id' => 102, 'name' => 'Aglonema Red', 'default_price' => 45000],
        ['id' => 103, 'name' => 'Calathea Orbifolia', 'default_price' => 50000],
        ['id' => 104, 'name' => 'Sansevieria Trifasciata', 'default_price' => 25000],
    ];

    $invoice_number = "INV-RESTOCK-004";
    $today_date = date('Y-m-d');
    ?>

    <!-- Top Navigation Bar -->
    <header class="bg-white border-b border-stone-200/80 sticky top-0 z-30 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="dashboard.php" class="p-2 rounded-xl text-stone-500 hover:bg-stone-100 transition-colors" title="Kembali ke Dashboard">
                    <i data-lucide="arrow-left" class="w-5 h-5"></i>
                </a>
                <div class="flex items-center gap-2.5">
                    <div class="bg-brand-primary p-2 rounded-xl text-white shadow-sm shadow-emerald-900/20">
                        <i data-lucide="truck" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <span class="text-xs font-semibold text-stone-400 block uppercase tracking-wider">Modul Restock</span>
                        <h1 class="text-base font-bold text-stone-800 leading-none">Pembelian Supplier</h1>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <span class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-brand-primary border border-emerald-200">
                    <span class="w-2 h-2 rounded-full bg-brand-primary animate-pulse"></span>
                    Sesi Restock Aktif
                </span>
                <div class="h-6 w-[1px] bg-stone-200 hidden sm:block"></div>
                <a href="dashboard.php" class="px-3.5 py-1.5 rounded-xl border border-stone-200 text-stone-600 hover:bg-stone-50 text-xs font-semibold transition-colors">
                    Dashboard
                </a>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 lg:p-8 space-y-6">

        <form action="#" method="POST" class="space-y-6" onsubmit="saveRestock(event)">

            <!-- SECTION 1: Header & Supplier Selection -->
            <section class="bg-white rounded-xl border border-stone-200/80 shadow-sm p-6 relative overflow-hidden">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-5 border-b border-stone-100">
                    <div class="flex items-center gap-3">
                        <div class="p-3 bg-brand-primary-light text-brand-primary rounded-xl">
                            <i data-lucide="package-plus" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-stone-800">Form Pembelian Barang (Restock Supplier)</h2>
                            <p class="text-xs text-stone-500">Pilih supplier dan lengkapi detail informasi restock tanaman Anda.</p>
                        </div>
                    </div>

                    <div class="self-start md:self-auto bg-stone-100 border border-stone-200/80 px-3.5 py-1.5 rounded-xl flex items-center gap-2">
                        <i data-lucide="receipt" class="w-4 h-4 text-stone-500"></i>
                        <span class="text-xs text-stone-500 font-medium">No. Invoice:</span>
                        <span class="text-xs font-bold font-mono text-brand-primary"><?= $invoice_number ?></span>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-5">
                    <div>
                        <label for="supplier" class="block text-xs font-bold text-stone-700 uppercase tracking-wider mb-2">
                            Pilih Supplier <span class="text-brand-danger">*</span>
                        </label>
                        <div class="relative">
                            <i data-lucide="building-2" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400"></i>
                            <select id="supplier" required class="w-full pl-10 pr-10 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-sm font-medium text-stone-800 focus:outline-none focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary transition-all appearance-none cursor-pointer">
                                <option value="" disabled>-- Pilih Supplier --</option>
                                <?php foreach ($suppliers as $index => $sup): ?>
                                    <option value="<?= $sup['id'] ?>" <?= $index === 0 ? 'selected' : '' ?>><?= $sup['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                            <i data-lucide="chevron-down" class="w-4 h-4 absolute right-3.5 top-1/2 -translate-y-1/2 text-stone-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div>
                        <label for="tanggal" class="block text-xs font-bold text-stone-700 uppercase tracking-wider mb-2">
                            Tanggal Pembelian <span class="text-brand-danger">*</span>
                        </label>
                        <div class="relative">
                            <i data-lucide="calendar" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400 pointer-events-none"></i>
                            <input type="date" id="tanggal" value="<?= $today_date ?>" required class="w-full pl-10 pr-4 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-sm font-medium text-stone-800 focus:outline-none focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary transition-all cursor-pointer">
                        </div>
                    </div>
                </div>
            </section>

            <!-- SECTION 2: Quick Item Input Bar -->
            <section class="bg-brand-primary-light/80 border border-emerald-200/80 rounded-xl p-5 shadow-sm">
                <div class="flex items-center gap-2 mb-3 text-brand-primary font-bold text-xs uppercase tracking-wider">
                    <i data-lucide="zap" class="w-4 h-4"></i>
                    <span>Tambah Item Restock Cepat</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                    <div class="md:col-span-5">
                        <label for="plant_select" class="block text-xs font-semibold text-stone-600 mb-1">Pilih Tanaman</label>
                        <div class="relative">
                            <i data-lucide="sprout" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-stone-400"></i>
                            <select id="plant_select" class="w-full pl-9 pr-8 py-2 bg-white border border-stone-200 rounded-xl text-sm font-medium text-stone-800 focus:outline-none focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary transition-all appearance-none cursor-pointer">
                                <option value="" disabled selected>-- Cari / Pilih Tanaman --</option>
                                <?php foreach ($plants as $p): ?>
                                    <option value="<?= $p['id'] ?>" data-name="<?= $p['name'] ?>" data-price="<?= $p['default_price'] ?>"><?= $p['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                            <i data-lucide="chevron-down" class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-stone-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="md:col-span-2">
                        <label for="input_qty" class="block text-xs font-semibold text-stone-600 mb-1">Jumlah (Qty)</label>
                        <input type="number" id="input_qty" min="1" value="1" class="w-full px-3 py-2 bg-white border border-stone-200 rounded-xl text-sm font-semibold text-center text-stone-800 focus:outline-none focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary transition-all">
                    </div>

                    <div class="md:col-span-3">
                        <label for="input_price" class="block text-xs font-semibold text-stone-600 mb-1">Harga Beli / Unit (Rp)</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-stone-400">Rp</span>
                            <input type="number" id="input_price" min="0" step="500" placeholder="0" class="w-full pl-9 pr-3 py-2 bg-white border border-stone-200 rounded-xl text-sm font-bold text-stone-800 focus:outline-none focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary transition-all">
                        </div>
                    </div>

                    <div class="md:col-span-2">
                        <button type="button" onclick="addRestockItem()" class="w-full py-2 bg-brand-primary hover:bg-brand-primary-hover active:scale-95 text-white rounded-xl text-sm font-bold shadow-sm shadow-emerald-900/20 flex items-center justify-center gap-1.5 transition-all">
                            <i data-lucide="plus-circle" class="w-4 h-4"></i>
                            <span>+ Tambah Item</span>
                        </button>
                    </div>
                </div>
            </section>

            <!-- SECTION 3: Purchased Items Table -->
            <section class="bg-white rounded-xl border border-stone-200/80 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-stone-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-stone-800">Daftar Barang Dibeli</h3>
                        <p class="text-xs text-stone-500">Rincian item tanaman yang akan masuk ke dalam stok toko.</p>
                    </div>
                    <span id="itemCountBadge" class="bg-stone-100 text-stone-600 text-xs font-semibold px-3 py-1 rounded-full">
                        2 Item Terdaftar
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-stone-50/80 border-b border-stone-200/80 text-[11px] font-bold text-stone-500 uppercase tracking-wider">
                                <th class="py-3 px-4 text-center w-12">No</th>
                                <th class="py-3 px-4">Nama Tanaman</th>
                                <th class="py-3 px-4 text-center w-28">Jumlah (Qty)</th>
                                <th class="py-3 px-4 text-right w-44">Harga Beli / Unit</th>
                                <th class="py-3 px-4 text-right w-48">Subtotal</th>
                                <th class="py-3 px-4 text-center w-20">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="restockTableBody" class="divide-y divide-stone-100 text-sm font-medium text-stone-700">
                            <!-- Items dynamically rendered -->
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- SECTION 4: Footer Summary & Action Controls -->
            <section class="grid grid-cols-1 md:grid-cols-12 gap-6 items-start">
                <div class="md:col-span-6 bg-white p-5 rounded-xl border border-stone-200/80 shadow-sm space-y-2">
                    <label for="catatan" class="block text-xs font-bold text-stone-700 uppercase tracking-wider flex items-center gap-1.5">
                        <i data-lucide="file-text" class="w-4 h-4 text-stone-400"></i>
                        <span>Catatan Pembelian (Opsional)</span>
                    </label>
                    <textarea id="catatan" name="catatan" rows="3" placeholder="Contoh: Pengiriman via kurir toko, pembayaran lunas via transfer..." class="w-full p-3 bg-stone-50 border border-stone-200 rounded-xl text-xs font-medium text-stone-800 focus:outline-none focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary transition-all resize-none">Pengiriman via kurir toko, pembayaran lunas</textarea>
                </div>

                <div class="md:col-span-6 space-y-4">
                    <div class="bg-white p-5 rounded-xl border-2 border-brand-primary/30 shadow-sm flex items-center justify-between">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-stone-400 block">Total Keseluruhan</span>
                            <span class="text-xs text-stone-500">Termasuk pajak & biaya admin</span>
                        </div>
                        <div class="text-right">
                            <span class="text-xs font-extrabold text-brand-primary tracking-wider uppercase block">GRAND TOTAL</span>
                            <span id="grandTotalText" class="text-2xl sm:text-3xl font-black text-brand-primary tracking-tight">
                                Rp 0
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <a href="dashboard.php" class="px-5 py-3 bg-white border border-stone-200 hover:bg-stone-100 text-stone-600 rounded-xl font-bold text-sm transition-all text-center">
                            Batal
                        </a>
                        <button type="submit" class="flex-1 sm:flex-initial px-8 py-3 bg-brand-primary hover:bg-brand-primary-hover active:scale-[0.98] text-white rounded-xl font-bold text-sm shadow-md shadow-emerald-900/20 flex items-center justify-center gap-2 transition-all">
                            <i data-lucide="check-circle-2" class="w-5 h-5"></i>
                            <span>SIMPAN PEMBELIAN</span>
                        </button>
                    </div>
                </div>
            </section>

        </form>

    </main>

    <script>
        let restockItems = [
            { id: 101, name: 'Monstera Deliciosa', qty: 10, price: 75000 },
            { id: 102, name: 'Aglonema Red', qty: 20, price: 55000 }
        ];

        function formatRp(val) {
            return 'Rp ' + val.toLocaleString('id-ID');
        }

        const plantSelect = document.getElementById('plant_select');
        const priceInput = document.getElementById('input_price');

        plantSelect?.addEventListener('change', (e) => {
            const selectedOption = e.target.options[e.target.selectedIndex];
            const price = selectedOption.getAttribute('data-price');
            if (price) {
                priceInput.value = price;
            }
        });

        function renderRestockTable() {
            const tbody = document.getElementById('restockTableBody');
            tbody.innerHTML = '';
            let grandTotal = 0;

            if (restockItems.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" class="py-8 text-center text-stone-400 text-xs">Belum ada barang terdaftar dalam restock</td></tr>`;
            } else {
                restockItems.forEach((item, index) => {
                    const subtotal = item.qty * item.price;
                    grandTotal += subtotal;

                    const tr = document.createElement('tr');
                    tr.className = 'hover:bg-stone-50/50 transition-colors';
                    tr.innerHTML = `
                        <td class="py-3.5 px-4 text-center font-mono text-stone-400 text-xs">${index + 1}</td>
                        <td class="py-3.5 px-4 font-bold text-stone-800">
                            <div class="flex items-center gap-2">
                                <i data-lucide="sprout" class="w-4 h-4 text-brand-primary"></i>
                                <span>${item.name}</span>
                            </div>
                        </td>
                        <td class="py-3.5 px-4 text-center font-semibold">
                            <span class="inline-block bg-stone-100 px-2.5 py-0.5 rounded-lg text-xs font-bold text-stone-700">
                                ${item.qty}
                            </span>
                        </td>
                        <td class="py-3.5 px-4 text-right font-medium">
                            ${formatRp(item.price)}
                        </td>
                        <td class="py-3.5 px-4 text-right font-bold text-stone-900">
                            ${formatRp(subtotal)}
                        </td>
                        <td class="py-3.5 px-4 text-center">
                            <button type="button" onclick="removeRestockItem(${index})" class="p-1.5 text-stone-400 hover:text-brand-danger hover:bg-brand-danger-light rounded-lg transition-colors" title="Hapus Item">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            }

            document.getElementById('itemCountBadge').innerText = `${restockItems.length} Item Terdaftar`;
            document.getElementById('grandTotalText').innerText = formatRp(grandTotal);
            lucide.createIcons();
        }

        function addRestockItem() {
            const plantId = plantSelect.value;
            const selectedOpt = plantSelect.options[plantSelect.selectedIndex];
            const qty = parseInt(document.getElementById('input_qty').value);
            const price = parseInt(priceInput.value);

            if (!plantId) {
                alert('Pilih tanaman terlebih dahulu');
                return;
            }
            if (!qty || qty < 1) {
                alert('Masukkan kuantitas yang valid');
                return;
            }
            if (isNaN(price) || price < 0) {
                alert('Masukkan harga beli yang valid');
                return;
            }

            const name = selectedOpt.getAttribute('data-name');
            restockItems.push({ id: parseInt(plantId), name, qty, price });
            
            // Reset Quick Input
            plantSelect.selectedIndex = 0;
            document.getElementById('input_qty').value = 1;
            priceInput.value = '';

            renderRestockTable();
        }

        function removeRestockItem(index) {
            restockItems.splice(index, 1);
            renderRestockTable();
        }

        function saveRestock(e) {
            e.preventDefault();
            if (restockItems.length === 0) {
                alert('Tambahkan setidaknya satu item barang!');
                return;
            }
            alert('Transaksi restock supplier berhasil disimpan!');
            window.location.href = 'dashboard.php';
        }

        // Initialize
        renderRestockTable();
    </script>
</body>
</html>