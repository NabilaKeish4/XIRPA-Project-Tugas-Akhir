<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantShop - Point of Sale (POS)</title>
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
                            sage: '#81C784',
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
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.02);
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 9999px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body class="antialiased h-screen flex flex-col overflow-hidden">

    <?php
    $categories = [
        ['id' => 'all', 'name' => 'Semua'],
        ['id' => 'Indoor', 'name' => 'Indoor'],
        ['id' => 'Outdoor', 'name' => 'Outdoor'],
        ['id' => 'Pot', 'name' => 'Pot'],
        ['id' => 'Media Tanam', 'name' => 'Media Tanam'],
    ];

    $products = [
        ['id' => 1, 'name' => 'Monstera Deliciosa', 'category' => 'Indoor', 'price' => 125000, 'stock' => 8, 'image' => 'https://images.unsplash.com/photo-1614594975525-e45190c55d0b?auto=format&fit=crop&q=80&w=400', 'out_of_stock' => false],
        ['id' => 2, 'name' => 'Snake Plant (Sansevieria)', 'category' => 'Indoor', 'price' => 45000, 'stock' => 15, 'image' => 'https://images.unsplash.com/photo-1509423350716-97f9360b4e09?auto=format&fit=crop&q=80&w=400', 'out_of_stock' => false],
        ['id' => 3, 'name' => 'Fiddle Leaf Fig', 'category' => 'Indoor', 'price' => 210000, 'stock' => 3, 'image' => 'https://images.unsplash.com/photo-1545241047-6083a3684587?auto=format&fit=crop&q=80&w=400', 'out_of_stock' => false],
        ['id' => 4, 'name' => 'Calathea Orbifolia', 'category' => 'Indoor', 'price' => 85000, 'stock' => 0, 'image' => 'https://images.unsplash.com/photo-1599598425947-320f323c683b?auto=format&fit=crop&q=80&w=400', 'out_of_stock' => true],
        ['id' => 5, 'name' => 'Pot Terakota Minimalis 20cm', 'category' => 'Pot', 'price' => 35000, 'stock' => 24, 'image' => 'https://images.unsplash.com/photo-1485955900006-10f4d324d411?auto=format&fit=crop&q=80&w=400', 'out_of_stock' => false],
        ['id' => 6, 'name' => 'Media Tanam Organik Premium 5kg', 'category' => 'Media Tanam', 'price' => 28000, 'stock' => 40, 'image' => 'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?auto=format&fit=crop&q=80&w=400', 'out_of_stock' => false],
    ];
    ?>

    <!-- Top Compact Header -->
    <header class="bg-white border-b border-stone-200 px-6 py-3 flex items-center justify-between shrink-0">
        <div class="flex items-center gap-4">
            <a href="dashboard.php" class="p-2 rounded-xl text-stone-500 hover:bg-stone-100 transition-colors" title="Kembali ke Dashboard">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div class="flex items-center gap-2.5">
                <div class="bg-brand-primary p-2 rounded-xl text-white shadow-sm shadow-emerald-900/20">
                    <i data-lucide="shopping-bag" class="w-5 h-5"></i>
                </div>
                <div>
                    <h1 class="text-base font-bold text-stone-800 leading-tight">Kasir (POS)</h1>
                    <p class="text-xs text-stone-400">Kasir: Nabila | Sesi #042</p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-brand-primary border border-emerald-200">
                <span class="w-2 h-2 rounded-full bg-brand-primary animate-pulse"></span>
                Sistem Terhubung
            </span>
            <div class="h-6 w-[1px] bg-stone-200"></div>
            <button onclick="clearCart()" class="flex items-center gap-2 bg-stone-100 hover:bg-stone-200/80 px-3 py-1.5 rounded-xl text-xs font-semibold text-stone-700 transition-colors">
                <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                <span>Reset Sesi</span>
            </button>
        </div>
    </header>

    <!-- Main POS Layout -->
    <div class="flex flex-1 overflow-hidden">

        <!-- Left Column: Product Catalog -->
        <section class="w-full lg:w-[60%] flex flex-col border-r border-stone-200 bg-brand-bg">
            <!-- Search & Filter Controls -->
            <div class="p-6 bg-white border-b border-stone-200/80 space-y-4 shrink-0 shadow-sm">
                <div class="relative w-full">
                    <i data-lucide="search" class="w-5 h-5 absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400"></i>
                    <input type="text" id="searchInput" oninput="filterProducts()" placeholder="Cari nama tanaman atau kategori..." class="w-full pl-11 pr-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary transition-all">
                </div>

                <div class="flex items-center gap-2 overflow-x-auto pb-1 custom-scrollbar">
                    <?php foreach ($categories as $index => $cat): ?>
                        <button onclick="setCategory('<?= $cat['id'] ?>', this)" class="category-btn px-4 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-all duration-150 <?= $index === 0 ? 'bg-brand-primary text-white shadow-sm' : 'bg-stone-100 text-stone-600 hover:bg-stone-200/70' ?>">
                            <?= $cat['name'] ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Product Grid Area -->
            <div class="flex-1 overflow-y-auto p-6 custom-scrollbar">
                <div id="productGrid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    <!-- Products rendered via JS -->
                </div>
            </div>
        </section>

        <!-- Right Column: Cart / Checkout Panel -->
        <section class="hidden lg:flex w-[40%] flex-col bg-white border-l border-stone-200 shadow-xl z-10">
            <div class="p-4 border-b border-stone-200/80 bg-stone-50/60 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-stone-200/70 text-stone-600 rounded-xl">
                        <i data-lucide="user" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <p class="text-xs text-stone-400 font-medium">Pelanggan</p>
                        <p class="text-sm font-semibold text-stone-800">Pelanggan Umum (Walk-in)</p>
                    </div>
                </div>
                <a href="pelanggan.php" class="text-xs font-semibold text-brand-primary hover:underline flex items-center gap-1">
                    <span>Kelola</span>
                    <i data-lucide="chevron-right" class="w-3 h-3"></i>
                </a>
            </div>

            <!-- Cart Header -->
            <div class="px-6 py-3 border-b border-stone-100 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-bold text-stone-800 uppercase tracking-wider">Keranjang Belanja</h2>
                    <span id="cartCountBadge" class="bg-brand-primary-light text-brand-primary text-xs font-bold px-2 py-0.5 rounded-full">
                        0 Item
                    </span>
                </div>
                <button onclick="clearCart()" class="text-xs text-stone-400 hover:text-brand-danger flex items-center gap-1 transition-colors">
                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    <span>Kosongkan</span>
                </button>
            </div>

            <!-- Cart Items List -->
            <div id="cartList" class="flex-1 overflow-y-auto px-6 divide-y divide-stone-100 custom-scrollbar">
                <!-- Cart items rendered via JS -->
            </div>

            <!-- Payment & Summary Footer -->
            <div class="p-6 bg-stone-50/80 border-t border-stone-200 space-y-4 shrink-0">
                <div class="space-y-1.5 text-xs">
                    <div class="flex justify-between text-stone-500">
                        <span>Subtotal</span>
                        <span id="subtotalText" class="font-medium text-stone-700">Rp 0</span>
                    </div>
                    <div class="flex justify-between text-stone-500">
                        <span>PPN (11%)</span>
                        <span id="taxText" class="font-medium text-stone-700">Rp 0</span>
                    </div>
                    <div class="pt-2 border-t border-stone-200 flex justify-between items-baseline">
                        <span class="text-sm font-bold text-stone-800">Total Pembayaran</span>
                        <span id="totalText" class="text-lg font-extrabold text-brand-primary">
                            Rp 0
                        </span>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-stone-400 uppercase tracking-wider mb-2">Metode Pembayaran</label>
                    <div class="grid grid-cols-3 gap-2">
                        <button type="button" onclick="selectPayment(this)" class="pay-method flex flex-col items-center justify-center p-2.5 rounded-xl border-2 border-brand-primary bg-brand-primary-light/40 text-brand-primary font-bold text-xs transition-all shadow-sm">
                            <i data-lucide="banknote" class="w-4 h-4 mb-1"></i>
                            <span>Tunai</span>
                        </button>
                        <button type="button" onclick="selectPayment(this)" class="pay-method flex flex-col items-center justify-center p-2.5 rounded-xl border border-stone-200 bg-white hover:bg-stone-100 text-stone-600 font-semibold text-xs transition-all">
                            <i data-lucide="qr-code" class="w-4 h-4 mb-1"></i>
                            <span>QRIS</span>
                        </button>
                        <button type="button" onclick="selectPayment(this)" class="pay-method flex flex-col items-center justify-center p-2.5 rounded-xl border border-stone-200 bg-white hover:bg-stone-100 text-stone-600 font-semibold text-xs transition-all">
                            <i data-lucide="credit-card" class="w-4 h-4 mb-1"></i>
                            <span>Debit</span>
                        </button>
                    </div>
                </div>

                <button onclick="processCheckout()" class="w-full py-3.5 bg-brand-primary hover:bg-brand-primary-hover active:scale-[0.99] text-white rounded-xl font-bold text-sm shadow-md shadow-emerald-900/20 flex items-center justify-center gap-2 transition-all">
                    <i data-lucide="check-circle-2" class="w-5 h-5"></i>
                    <span id="payBtnText">Proses Bayar (Rp 0)</span>
                </button>
            </div>
        </section>

    </div>

    <script>
        const productsData = <?= json_encode($products) ?>;
        let cart = [
            { id: 1, name: 'Monstera Deliciosa', price: 125000, qty: 1, image: 'https://images.unsplash.com/photo-1614594975525-e45190c55d0b?auto=format&fit=crop&q=80&w=200' },
            { id: 2, name: 'Snake Plant (Sansevieria)', price: 45000, qty: 2, image: 'https://images.unsplash.com/photo-1509423350716-97f9360b4e09?auto=format&fit=crop&q=80&w=200' },
            { id: 5, name: 'Pot Terakota Minimalis 20cm', price: 35000, qty: 1, image: 'https://images.unsplash.com/photo-1485955900006-10f4d324d411?auto=format&fit=crop&q=80&w=200' }
        ];
        let currentCategory = 'all';

        function formatRp(val) {
            return 'Rp ' + val.toLocaleString('id-ID');
        }

        function renderProducts() {
            const grid = document.getElementById('productGrid');
            const searchVal = document.getElementById('searchInput').value.toLowerCase();
            grid.innerHTML = '';

            const filtered = productsData.filter(p => {
                const matchCat = currentCategory === 'all' || p.category === currentCategory;
                const matchSearch = p.name.toLowerCase().includes(searchVal) || p.category.toLowerCase().includes(searchVal);
                return matchCat && matchSearch;
            });

            if(filtered.length === 0) {
                grid.innerHTML = `<div class="col-span-full text-center py-12 text-stone-400 font-medium">Tanaman tidak ditemukan</div>`;
                return;
            }

            filtered.forEach(p => {
                const card = document.createElement('div');
                card.className = `bg-white rounded-xl border border-stone-200/80 p-3 flex flex-col justify-between shadow-sm hover:shadow-md transition-all relative group ${p.out_of_stock ? 'opacity-65' : ''}`;
                card.innerHTML = `
                    <div>
                        <div class="relative w-full h-36 rounded-lg overflow-hidden bg-stone-100 mb-3">
                            <img src="${p.image}" alt="${p.name}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                            <div class="absolute top-2 left-2">
                                ${p.out_of_stock ? 
                                    '<span class="bg-brand-danger text-white text-[10px] font-bold px-2 py-0.5 rounded-md shadow-sm">Stok Habis</span>' : 
                                    `<span class="bg-white/90 backdrop-blur-md text-stone-700 text-[10px] font-semibold px-2 py-0.5 rounded-md shadow-sm border border-stone-200">Stok: ${p.stock}</span>`}
                            </div>
                        </div>
                        <h3 class="font-bold text-stone-800 text-sm leading-snug line-clamp-1 mb-1">${p.name}</h3>
                        <p class="text-[11px] text-stone-400 font-medium mb-2">${p.category}</p>
                    </div>
                    <div class="pt-2 border-t border-stone-100 flex items-center justify-between mt-auto">
                        <div>
                            <span class="text-[10px] uppercase font-semibold text-stone-400 block leading-none">Harga</span>
                            <span class="text-sm font-bold text-brand-primary">${formatRp(p.price)}</span>
                        </div>
                        <button ${p.out_of_stock ? 'disabled' : `onclick="addToCart(${p.id})"`} class="${p.out_of_stock ? 'bg-stone-200 text-stone-400 cursor-not-allowed' : 'bg-brand-primary hover:bg-brand-primary-hover active:scale-95 text-white shadow-sm'} inline-flex items-center justify-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all">
                            <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                            <span>Beli</span>
                        </button>
                    </div>
                `;
                grid.appendChild(card);
            });
            lucide.createIcons();
        }

        function setCategory(cat, btn) {
            currentCategory = cat;
            document.querySelectorAll('.category-btn').forEach(b => {
                b.className = 'category-btn px-4 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-all duration-150 bg-stone-100 text-stone-600 hover:bg-stone-200/70';
            });
            btn.className = 'category-btn px-4 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-all duration-150 bg-brand-primary text-white shadow-sm';
            renderProducts();
        }

        function filterProducts() {
            renderProducts();
        }

        function addToCart(id) {
            const product = productsData.find(p => p.id === id);
            if (!product || product.out_of_stock) return;

            const existing = cart.find(item => item.id === id);
            if (existing) {
                if (existing.qty < product.stock) {
                    existing.qty++;
                } else {
                    alert('Stok maksimal telah tercapai!');
                }
            } else {
                cart.push({
                    id: product.id,
                    name: product.name,
                    price: product.price,
                    qty: 1,
                    image: product.image
                });
            }
            renderCart();
        }

        function updateQty(id, delta) {
            const item = cart.find(i => i.id === id);
            const product = productsData.find(p => p.id === id);
            if (item) {
                item.qty += delta;
                if (item.qty > product.stock) {
                    item.qty = product.stock;
                    alert('Stok tidak mencukupi');
                }
                if (item.qty <= 0) {
                    removeFromCart(id);
                    return;
                }
            }
            renderCart();
        }

        function removeFromCart(id) {
            cart = cart.filter(i => i.id !== id);
            renderCart();
        }

        function clearCart() {
            cart = [];
            renderCart();
        }

        function renderCart() {
            const cartList = document.getElementById('cartList');
            cartList.innerHTML = '';

            let subtotal = 0;
            let totalItems = 0;

            if (cart.length === 0) {
                cartList.innerHTML = `<div class="text-center py-12 text-stone-400 text-xs">Keranjang masih kosong</div>`;
            } else {
                cart.forEach(item => {
                    subtotal += item.price * item.qty;
                    totalItems += item.qty;

                    const row = document.createElement('div');
                    row.className = 'py-3.5 flex items-center gap-3 group';
                    row.innerHTML = `
                        <img src="${item.image}" alt="${item.name}" class="w-12 h-12 rounded-lg object-cover bg-stone-100 shrink-0">
                        <div class="flex-1 min-w-0">
                            <h4 class="text-xs font-bold text-stone-800 truncate leading-snug">${item.name}</h4>
                            <p class="text-xs font-semibold text-stone-500 mt-0.5">${formatRp(item.price)}</p>
                        </div>
                        <div class="flex items-center border border-stone-200 rounded-lg overflow-hidden bg-stone-50 shrink-0">
                            <button onclick="updateQty(${item.id}, -1)" class="p-1 text-stone-500 hover:bg-stone-200 hover:text-stone-800 transition-colors">
                                <i data-lucide="minus" class="w-3.5 h-3.5"></i>
                            </button>
                            <span class="w-7 text-center text-xs font-bold text-stone-800">${item.qty}</span>
                            <button onclick="updateQty(${item.id}, 1)" class="p-1 text-stone-500 hover:bg-stone-200 hover:text-stone-800 transition-colors">
                                <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>
                        <div class="text-right shrink-0 min-w-[70px]">
                            <span class="text-xs font-bold text-stone-800 block">${formatRp(item.price * item.qty)}</span>
                            <button onclick="removeFromCart(${item.id})" class="text-[10px] text-stone-400 hover:text-brand-danger transition-colors mt-0.5">Hapus</button>
                        </div>
                    `;
                    cartList.appendChild(row);
                });
            }

            const tax = subtotal * 0.11;
            const total = subtotal + tax;

            document.getElementById('cartCountBadge').innerText = `${totalItems} Item`;
            document.getElementById('subtotalText').innerText = formatRp(subtotal);
            document.getElementById('taxText').innerText = formatRp(tax);
            document.getElementById('totalText').innerText = formatRp(total);
            document.getElementById('payBtnText').innerText = `Proses Bayar (${formatRp(total)})`;

            lucide.createIcons();
        }

        function selectPayment(btn) {
            document.querySelectorAll('.pay-method').forEach(b => {
                b.className = 'pay-method flex flex-col items-center justify-center p-2.5 rounded-xl border border-stone-200 bg-white hover:bg-stone-100 text-stone-600 font-semibold text-xs transition-all';
            });
            btn.className = 'pay-method flex flex-col items-center justify-center p-2.5 rounded-xl border-2 border-brand-primary bg-brand-primary-light/40 text-brand-primary font-bold text-xs transition-all shadow-sm';
        }

        function processCheckout() {
            if (cart.length === 0) {
                alert('Keranjang belanja kosong!');
                return;
            }
            alert('Transaksi Berhasil Diproses!');
            clearCart();
        }

        // Initialize
        renderProducts();
        renderCart();
    </script>
</body>
</html>