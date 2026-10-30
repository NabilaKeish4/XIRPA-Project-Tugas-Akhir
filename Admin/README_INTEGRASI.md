# Admin PlantHub — Panduan Integrasi

Letakkan folder `Admin/` sejajar dengan `Config/`, `Auth/`, `Pelanggan/`, `assets/`, `uploads/`, `fpdf/`:

```
planthub/
├── Admin/        <- folder ini
├── Auth/         login.php, logout.php
├── Config/       database.php  (harus membuat variabel $conn = mysqli)
├── Pelanggan/
├── assets/img/   gambar produk
├── uploads/      logo & foto profil (+ uploads/chats/ untuk lampiran chat)
└── fpdf/         fpdf.php  (unduh dari fpdf.org)
```

Setelah login sebagai admin, buka `Admin/cek_integrasi.php` — semua centang wajib harus hijau.

Session yang harus diisi oleh Auth/login.php:
`$_SESSION['user_id']`, `$_SESSION['role'] = 'admin'`, `$_SESSION['nama_user']`.
