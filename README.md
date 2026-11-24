# SkripsiKu — Portal Bimbingan Skripsi

Aplikasi web untuk mengelola proses bimbingan skripsi dari awal sampai akhir: pengajuan judul, penetapan dosen pembimbing dan SK, jadwal bimbingan, unggah dokumen dan revisi, sampai seminar dan sidang. Dibuat dengan **PHP native** dan **MySQL/MariaDB**, tanpa framework dan tanpa Composer/npm, sehingga cukup dijalankan di XAMPP.

Ada tiga peran pengguna:

| Peran | Yang bisa dilakukan |
|---|---|
| **Mahasiswa** | Mengajukan judul, mengusulkan pembimbing, mengunduh SK, memesan jadwal bimbingan, mengunggah dokumen, berdiskusi dengan dosen, mendaftar seminar/sidang |
| **Dosen** | Melihat mahasiswa bimbingan, mengelola jadwal dan slot konsultasi, memberi catatan dan revisi dokumen, menilai seminar/sidang |
| **Admin / Kaprodi** | Mengelola akun, memvalidasi judul, membuat relasi bimbingan dan SK, menjadwalkan ujian, mengelola pengumuman, laporan, dan pengaturan |

---

## Daftar isi

1. [Fitur](#fitur)
2. [Teknologi](#teknologi)
3. [Instalasi di XAMPP](#instalasi-di-xampp)
4. [Akun contoh](#akun-contoh)
5. [Alur penggunaan](#alur-penggunaan)
6. [Struktur folder](#struktur-folder)
7. [Konfigurasi](#konfigurasi)
8. [Catatan keamanan](#catatan-keamanan)
9. [Masalah umum](#masalah-umum)

---

## Fitur

**Akun dan login**
- Login terpisah untuk mahasiswa (`index.php`) dan staf (`login-staff.php`, pilih Admin atau Dosen)
- Admin menambah akun satu per satu di **Kelola Pengguna** atau sekaligus lewat **Impor CSV**
- Kata sandi awal otomatis `uab#` + 6 digit terakhir NIM/NIP (contoh NIM `231011400099` → `uab#400099`), dan wajib diganti saat login pertama
- Lupa kata sandi: pengguna mengirim permintaan, admin meresetnya ke kata sandi awal
- Akun bisa dinonaktifkan; sesi yang sedang aktif langsung berakhir

**Judul dan pembimbing**
- Mahasiswa mengajukan 2–3 pilihan judul beserta transkrip (PDF), dengan peringatan otomatis bila judul mirip judul lain
- Kaprodi (akun admin) memilih salah satu judul untuk disetujui atau menolak dengan catatan
- Mahasiswa mengusulkan dosen pembimbing; admin menetapkan relasi bimbingan
- **SK bimbingan terbit otomatis** dalam bentuk PDF dengan nomor `UOB/SCDT/SK-BIMBINGAN/<tahun>/<urut>` dan kode verifikasi. Keaslian SK bisa dicek publik di `verifikasi-sk.php`

**Bimbingan**
- Dosen membuka slot konsultasi, mahasiswa memesan jadwal, dosen mengonfirmasi dan mencatat hasil sesi
- Unggah dokumen per bab, revisi dari dosen, perbandingan dua versi dokumen, dan diskusi per dokumen
- Progres skripsi, kalender bulanan, riwayat, dan kartu bimbingan (logbook) siap cetak
- Pendaftaran seminar proposal dan sidang, penunjukan penguji, dan penilaian

**Lainnya**
- Notifikasi di aplikasi untuk setiap perubahan penting, plus pengingat otomatis (jadwal dekat, mahasiswa tidak aktif)
- Pengumuman, panduan, FAQ, direktori dosen, dan unduhan template dokumen
- Laporan statistik dan pengaturan sistem (kuota dosen, tahun akademik, kop dan penandatangan SK)

---

## Teknologi

| Bagian | Teknologi |
|---|---|
| Backend | PHP 8.0+ (native, PDO) |
| Database | MySQL 8 / MariaDB 10.4+ (bawaan XAMPP), dengan trigger untuk kuota dosen |
| Frontend | HTML, CSS, JavaScript tanpa library |
| PDF SK | Generator PDF sederhana bawaan (`config/pdf.php`), tanpa library tambahan |
| Server lokal | XAMPP (Apache + MySQL) |

---

## Instalasi di XAMPP

**Prasyarat:** XAMPP dengan PHP 8.0 atau lebih baru.

1. **Salin project ke `htdocs`.**
   ```bash
   cd C:\xampp\htdocs
   git clone https://github.com/codename24kai/bimbingan-skripsi.git
   ```
   Atau unduh ZIP dari GitHub lalu ekstrak ke `C:\xampp\htdocs\bimbingan-skripsi`.

2. **Jalankan Apache dan MySQL** dari XAMPP Control Panel.

3. **Impor database.** Buka http://localhost/phpmyadmin → tab **Import** → pilih file `database/db_bimbingan_skripsi.sql` → **Kirim**.
   File ini otomatis membuat database `db_bimbingan_skripsi` lengkap dengan data contoh, jadi tidak perlu membuat database dulu.

4. **Buka aplikasinya** di http://localhost/bimbingan-skripsi/

   Alamat aplikasi dideteksi otomatis dari nama folder, jadi folder boleh diganti namanya atau ditaruh di subfolder `htdocs`.

> Jika MySQL kamu memakai password untuk user `root`, ubah `DB_PASS` di `config/db.php`.

---

## Akun contoh

| Peran | Email | Kata sandi |
|---|---|---|
| Admin / Kaprodi | `admin@univ.ac.id` | `admin123` |
| Dosen | `maha@univ.ac.id` | `dosen123` |
| Dosen | `michaelc@univ.ac.id` | `dosen123` |
| Mahasiswa | `kaisanabhan@univ.ac.id` (sudah punya judul disetujui) | `mhs123` |
| Mahasiswa | `danangw@univ.ac.id`, `marfinn@univ.ac.id`, `fajarf@univ.ac.id`, `rizkyjaya@univ.ac.id` | `mhs123` |

Semua kata sandi contoh di atas **wajib diganti saat login pertama** (minimal 8 karakter). Mahasiswa masuk lewat halaman utama (`index.php`), sedangkan admin dan dosen lewat tombol **Masuk sebagai Admin / Dosen** di bawah form tersebut (`login-staff.php`).

---

## Alur penggunaan

```
Admin membuat akun mahasiswa & dosen
        │
Mahasiswa login → ganti kata sandi awal → ajukan 2–3 pilihan judul + transkrip
        │
Kaprodi (admin) memvalidasi: setujui salah satu judul / tolak dengan catatan
        │                                   └─ ditolak → mahasiswa mengajukan ulang
Mahasiswa mengusulkan dosen pembimbing
        │
Admin membuat relasi bimbingan → SK PDF terbit otomatis + notifikasi
        │
Bimbingan: pesan jadwal → sesi → unggah dokumen → revisi → diskusi
        │
Seminar proposal → sidang akhir → bimbingan ditandai selesai
```

---

## Struktur folder

```
bimbingan-skripsi/
├── index.php, login-staff.php   Login mahasiswa / staf
├── beranda.php                  Halaman publik sebelum login
├── dashboard.php                Dashboard sesuai peran
├── profil.php, notifikasi.php, diskusi.php, kalender.php, bandingkan.php
├── lupa-sandi.php               Permintaan reset kata sandi
├── verifikasi-sk.php            Cek keaslian SK (publik)
├── download.php                 Satu-satunya jalan membuka file upload (cek hak akses)
│
├── admin/        Kelola pengguna, impor CSV, validasi judul, relasi & SK, ujian, laporan, pengaturan
├── dosen/        Mahasiswa bimbingan, jadwal, slot, catatan, revisi, validasi judul, ujian
├── mahasiswa/    Pengajuan judul, usulan pembimbing, bimbingan, dokumen, riwayat, ujian
├── info/         Panduan, FAQ, pengumuman, direktori dosen, template
├── cetak/        Kartu bimbingan siap cetak
├── api/          Endpoint cek kemiripan judul
├── cron/         Skrip pengingat otomatis (opsional, lewat Task Scheduler)
│
├── config/
│   ├── app.php      Nama aplikasi, BASE_URL, folder upload
│   ├── db.php       Koneksi database + helper query
│   ├── auth.php     Login, sesi, hak akses, CSRF, kata sandi
│   ├── fitur.php    Notifikasi, upload, pengaturan, judul mirip, dll.
│   ├── format.php   Format tanggal dan label status
│   ├── ui.php       Komponen tampilan (ikon, pesan, upload)
│   ├── sk.php       Penomoran dan penerbitan SK
│   └── pdf.php      Pembuat file PDF
├── includes/     Header, sidebar, topbar, footer
├── assets/       CSS, JavaScript, logo SK, template dokumen
├── database/
│   └── db_bimbingan_skripsi.sql   Struktur + data contoh (satu-satunya file SQL)
└── uploads/      File unggahan pengguna (tidak ikut ke Git, diblokir dari akses langsung)
```

---

## Konfigurasi

| Pengaturan | Lokasi |
|---|---|
| Koneksi database (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`) | `config/db.php` |
| Alamat aplikasi (`BASE_URL`, otomatis) | `config/app.php` |
| Kuota dosen, tahun akademik, kontak prodi, kop & penandatangan SK | Menu **Pengaturan** (login admin) |

**Pengingat otomatis (opsional).** Pengingat sudah berjalan sendiri saat ada pengguna membuka dashboard. Agar tetap terkirim setiap hari, jadwalkan lewat Windows Task Scheduler:
- Program: `C:\xampp\php\php.exe`
- Argumen: `C:\xampp\htdocs\bimbingan-skripsi\cron\pengingat.php`

---

## Catatan keamanan

- Kata sandi disimpan dengan `password_hash` (bcrypt); kata sandi bawaan dan kata sandi awal wajib diganti
- Semua form memakai token CSRF
- File upload hanya bisa dibuka lewat `download.php` oleh pemilik, dosen pembimbingnya, atau admin. Folder `uploads/` diblokir lewat `uploads/.htaccess` (butuh Apache dengan `AllowOverride All`, bawaan XAMPP)
- Project ini ditujukan untuk lingkungan lokal / pembelajaran. Sebelum dipasang di server publik, ganti semua kata sandi contoh dan aktifkan HTTPS

---

## Masalah umum

| Gejala | Penyebab dan solusi |
|---|---|
| Halaman tampil tanpa CSS atau tautan mengarah ke alamat salah | Folder diakses lewat alias/virtual host. Isi `BASE_URL` manual di `config/app.php` (contoh sudah ada di komentar) |
| `SQLSTATE[HY000] [1049] Unknown database` | Database belum diimpor. Ulangi langkah 3 instalasi |
| `SQLSTATE[HY000] [1045] Access denied` | Password MySQL berbeda. Sesuaikan `DB_PASS` di `config/db.php` |
| Pesan "Database belum diperbarui" | Database dari versi lama. Impor ulang `database/db_bimbingan_skripsi.sql` (data lama di database itu akan terhapus) |
| Gagal unggah file besar | Naikkan `upload_max_filesize` dan `post_max_size` di `php.ini` XAMPP (minimal 10M), lalu restart Apache |
