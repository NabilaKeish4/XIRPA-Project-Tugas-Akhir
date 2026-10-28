# PlantHub — Bagian Pelanggan (versi rombak)

Timpa file-file di folder **`Pelanggan/`** proyek Anda dengan isi folder ini (nama folder harus tepat `Pelanggan`,
karena `Auth/login.php` mengarah ke `../Pelanggan/dashboard.php`).

**Satu-satunya file pendukung adalah `customer_init.php`** — wajib terisi (jangan dibiarkan kosong). Folder `includes/` dan
`partials/` milik Anda tidak dipakai dan tidak disentuh. Folder `includes/` dari versi zip sebelumnya tidak diperlukan lagi.

```
PlantHub/
├── Auth/  Config/  assets/img/  uploads/(chats, avatars → dibuat otomatis)
└── Pelanggan/   ← 9 halaman asli + customer_init.php + wishlist.php
```

## Halaman
| File | Fungsi |
|---|---|
| `dashboard.php` | Beranda: ringkasan, pesanan terakhir + pelacak, kategori, terlaris, terbaru, tips |
| `katalog.php` | Katalog bergambar, filter kategori/harga/stok, urutan, pagination, wishlist |
| `detail.php` | Detail produk, jumlah, Tambah Keranjang, **Beli Sekarang** (tidak menghapus keranjang), produk serupa |
| `cart.php` | Keranjang (+/−/hapus, validasi stok real-time, rekomendasi) |
| `checkout.php` | Alamat, metode bayar (kartu pilihan), konfirmasi, transaksi aman + kunci stok |
| `riwayat.php` | Riwayat pesanan, tab status, filter, pagination, **batalkan (stok kembali)**, beli lagi |
| `note.php` | Nota digital: QR, cetak/PDF, salin link, bagikan WhatsApp |
| `chat.php` | Konsultasi dengan admin: kirim gambar, edit/hapus, auto-refresh, pertanyaan cepat, konteks produk |
| `profil.php` | Edit profil, foto, ganti password (indikator kekuatan), bantuan |
| `wishlist.php` | **Baru** — daftar tanaman favorit |
| `customer_init.php` | Satu file pendukung: koneksi, helper DB, CSRF, keranjang, komponen UI, header & sidebar |
| `sql/wishlist.sql` | Opsional — tabel wishlist dibuat otomatis oleh aplikasi |

## Perbaikan dari versi lama
- Semua query memakai **prepared statement** (rentan SQL injection sebelumnya).
- Semua aksi mengubah data memakai **POST + token CSRF** (sebelumnya hapus/tambah keranjang lewat link GET).
- Checkout: pesan error teknis ("DEBUG CHECKOUT") tidak lagi tampil ke pelanggan; stok dikunci (`FOR UPDATE`).
- "Beli Sekarang" tidak lagi menimpa isi keranjang.
- Hasil "Beli Lagi" kini ditampilkan; total belanja tidak menghitung pesanan batal.
- Upload gambar divalidasi isi file (`getimagesize`), bukan hanya ekstensi.
- Status "Admin Online" palsu diganti info jam layanan & waktu balasan terakhir.
- Badge chat belum dibaca kini menangani `is_deleted` NULL.

## Kebutuhan database
Memakai tabel yang sudah ada: `users`, `produk`, `kategori`, `transaksi`, `transaksi_detail`, `chats`, `pengaturan`.
Kolom yang dipakai: `users.foto_profil`, `users.last_login`, `chats.is_deleted`, `chats.edited_at`, `chats.attachment`.
Tabel baru `wishlist` dibuat otomatis saat pertama dipakai.

Total di `transaksi.total_harga` = subtotal + PPN 11% (sama seperti sebelumnya).
