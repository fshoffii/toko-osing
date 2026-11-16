# SPEC: Toko Osing — Katalog Produk Desa

Etalase digital sederhana untuk produk UMKM desa wisata Osing (batik, kopi, gula aren, dan kerajinan), untuk latihan satu hari.

Versi 1.1 (menggantikan konsep Popchikens). Tanggal 16 November 2026. Estimasi pengerjaan sekitar 8,5 jam.

## 1. Tujuan

### 1.1 Tujuan produk
- Mengangkat produk desa wisata Osing ke etalase digital yang sederhana, mudah dibuka di ponsel, dan tidak membutuhkan server.
- Membantu calon pembeli menemukan produk per desa (Kemiren, Tamansari, Osing) dan per kategori (Batik, Kopi, Gula Aren, Kerajinan), termasuk penanda “Oleh-oleh khas Banyuwangi”.
- Memperkenalkan usaha pembuatnya lewat profil singkat perajin yang hanya memuat nama usaha dan desa.
- Memudahkan pemesanan lewat WhatsApp dengan pesan yang sudah terisi otomatis.
- Memberi pengelola dashboard demo untuk menambah, mengubah, dan menghapus produk serta melihat ringkasan stok.
- Semua data tersimpan di peramban (localStorage) pada satu perangkat.

### 1.2 Tujuan belajar (latihan 1 hari)
- Menyiapkan proyek Vite dengan JavaScript vanilla dan memecahnya menjadi modul yang rapi.
- Berlatih CRUD, validasi input, relasi sederhana antardata (produk ke usaha), render DOM aman, dan localStorage.
- Menulis kriteria penerimaan yang bisa diperiksa manual, lalu benar-benar memeriksanya.

### 1.3 Indikator berhasil
- Seluruh acceptance criteria di bagian 8 lolos pada Chrome atau Firefox terbaru.
- `npm run build` selesai tanpa error dan hasil build berjalan lewat `npm run preview`.
- Tidak ada API key, data pribadi, nama usaha nyata, atau aset berhak cipta di repositori.

### 1.4 Asumsi
- “Osing” diperlakukan sebagai salah satu pilihan wilayah/desa sesuai permintaan. Daftar desa berada di `src/config.js` sehingga mudah diganti.
- Nama usaha pada data contoh seluruhnya fiktif. Pemilik toko mengganti dengan data asli (dan izin pemiliknya) di luar lingkup latihan ini.
- Penanda “Oleh-oleh khas Banyuwangi” adalah kurasi pengelola, bukan sertifikasi atau indikasi geografis resmi.
- Satu nomor WhatsApp toko dipakai untuk semua pesanan. Nomor per usaha belum dibuat.

## 2. Pengguna

| Pengguna | Kebutuhan | Cara mengakses |
|---|---|---|
| Calon pembeli (wisatawan atau pembeli daring) | Menjelajah produk per desa dan kategori, mencari oleh-oleh, mengenal usaha pembuat, lalu memesan lewat WhatsApp | Halaman Katalog (`#/`) dan Perajin (`#/perajin`) |
| Pengelola toko desa wisata | Mengelola data produk dan memantau stok | Halaman Dashboard (`#/dashboard`) |

Catatan penting:
- Kedua peran memakai satu perangkat dan satu peramban. Tidak ada login, akun, atau peran palsu.
- Dashboard hanya dipisahkan sebagai halaman terpisah. Itu bukan mekanisme keamanan: siapa pun yang memegang perangkat dapat membukanya.
- Navigasi memakai hash routing (`#/`, `#/perajin`, `#/dashboard`) agar berjalan tanpa konfigurasi server.

## 3. Daftar fitur MVP

### 3.1 Katalog dan profil perajin (pembeli)

| ID | Fitur | Perilaku |
|---|---|---|
| F-01 | Kartu produk | Menampilkan gambar, nama, kategori, harga format Rupiah, nama usaha beserta desanya, status stok, badge Unggulan jika `unggulan` true, dan badge “Oleh-oleh khas Banyuwangi” jika `olehOleh` true. |
| F-02 | Placeholder gambar lokal | Gambar berasal dari SVG buatan sendiri di `public/img/` (satu per kategori ditambah satu umum). Jika `imageUrl` kosong atau gagal dimuat, tampilkan `public/img/placeholder.svg`. |
| F-03 | Pencarian | Satu kolom teks. Mencocokkan nama produk, kategori, deskripsi, nama usaha, dan desa tanpa membedakan huruf besar/kecil. Spasi di awal/akhir diabaikan. |
| F-04 | Filter desa | Pilihan “Semua desa” ditambah Kemiren, Tamansari, dan Osing. Desa suatu produk mengikuti desa usahanya. |
| F-05 | Filter kategori | Pilihan “Semua kategori” ditambah Batik, Kopi, Gula Aren, dan Kerajinan. |
| F-06 | Filter “Oleh-oleh khas Banyuwangi” | Tombol sakelar. Saat aktif, hanya produk dengan `olehOleh` true yang tampil. |
| F-07 | Sorting | Unggulan dulu (default), Harga termurah, Harga termahal, Nama A–Z, Stok terbanyak. Unggulan dulu memakai nama A–Z sebagai pembeda. |
| F-08 | Tombol Pesan via WhatsApp | Membuka tautan `wa.me` di tab baru dengan pesan terisi otomatis (template di bagian 4.3). Jika stok 0, tombol nonaktif dan berlabel “Habis”. |
| F-09 | Penanda stok | Stok 0 menampilkan “Habis”. Stok 1 sampai 5 menampilkan “Sisa N”. |
| F-10 | Penghitung dan keadaan kosong | Teks “Menampilkan X dari Y produk”, penanda filter aktif, dan tombol “Reset filter”. Jika hasil kosong, tampilkan pesan dengan tombol yang sama. |
| F-11 | Halaman Perajin | Daftar kartu usaha (read-only) berisi nama usaha, desa, kalimat profil singkat, dan jumlah produk. Tombol “Lihat produk” membuka Katalog yang disaring pada usaha itu. |

Filter pada F-03 sampai F-06 dan penyaringan usaha pada F-11 bekerja bersamaan (AND).

### 3.2 Dashboard demo (pengelola)

| ID | Fitur | Perilaku |
|---|---|---|
| F-12 | Ringkasan stok | Kartu: total produk, total stok (jumlah seluruh stok), jumlah kategori yang dipakai, jumlah produk oleh-oleh, dan jumlah produk habis. Ditambah dua tabel kecil: stok per kategori dan stok per desa. Selalu diperbarui setelah tambah, ubah, hapus, atau reset. |
| F-13 | Tabel produk | Kolom: nama, kategori, usaha dan desa, harga, stok, unggulan, oleh-oleh, aksi (Ubah, Hapus). |
| F-14 | Tambah produk | Formulir semua field model. Produk baru muncul di tabel dan katalog tanpa muat ulang halaman. |
| F-15 | Ubah produk | Formulir yang sama, terisi data produk terpilih. `id` tidak berubah. |
| F-16 | Hapus produk | Meminta konfirmasi sebelum menghapus. |
| F-17 | Validasi formulir | Pesan galat tampil di samping field. Data tidak disimpan selama masih ada galat. |
| F-18 | Reset data contoh | Tombol “Kembalikan data contoh” dengan konfirmasi, mengganti seluruh data produk dengan 10 produk awal. |
| F-19 | Penyimpanan localStorage | Setiap perubahan langsung disimpan. Data bertahan setelah muat ulang dan tutup peramban. |

Data perajin pada MVP bersifat tetap (dari berkas seed) dan tidak dapat diubah lewat dashboard.

### 3.3 Data contoh: 6 usaha perajin (fiktif, nonpribadi)

| ID | Nama usaha | Desa | Jenis usaha |
|---|---|---|---|
| pr-01 | Sanggar Batik Kemiren Asri | Kemiren | Batik |
| pr-02 | Griya Kopi Kemiren | Kemiren | Kopi |
| pr-03 | Kopi Rakyat Tamansari | Tamansari | Kopi |
| pr-04 | Gula Aren Sumber Lestari | Tamansari | Gula aren |
| pr-05 | Anyaman Bambu Osing Karya | Osing | Kerajinan |
| pr-06 | Kriya Kayu Osing Jaya | Osing | Kerajinan |

Profil setiap usaha berupa satu kalimat tentang jenis produknya. Tidak ada nama orang, nomor telepon, alamat rumah, atau foto orang.

### 3.4 Data contoh: 10 produk

| Nama | Kategori | Usaha | Harga (Rp) | Stok | Unggulan | Oleh-oleh |
|---|---|---|---|---|---|---|
| Batik Tulis Motif Gajah Oling | Batik | pr-01 | 350000 | 6 | Ya | Ya |
| Selendang Batik Cap Motif Kopi Pecah | Batik | pr-01 | 120000 | 14 | Tidak | Ya |
| Kopi Osing Bubuk 200 g | Kopi | pr-02 | 45000 | 40 | Ya | Ya |
| Kopi Biji Sangrai Robusta 250 g | Kopi | pr-03 | 55000 | 22 | Tidak | Ya |
| Gula Aren Cetak 500 g | Gula Aren | pr-04 | 30000 | 50 | Ya | Ya |
| Gula Aren Semut 250 g | Gula Aren | pr-04 | 22000 | 0 | Tidak | Ya |
| Anyaman Bambu Tempat Tisu | Kerajinan | pr-05 | 35000 | 18 | Tidak | Tidak |
| Tas Anyaman Pandan | Kerajinan | pr-05 | 85000 | 9 | Tidak | Tidak |
| Gantungan Kunci Kayu Ukir | Kerajinan | pr-06 | 15000 | 60 | Tidak | Ya |
| Topeng Kayu Hias Mini | Kerajinan | pr-06 | 90000 | 5 | Tidak | Ya |

Nilai turunan untuk pemeriksaan:
- Total produk 10, total stok 224, kategori terpakai 4, produk unggulan 3, produk oleh-oleh 8, produk habis 1 (Gula Aren Semut 250 g).
- Produk per desa: Kemiren 3, Tamansari 3, Osing 4. Produk per kategori: Batik 2, Kopi 2, Gula Aren 2, Kerajinan 4.
- Stok per kategori: Batik 20, Kopi 62, Gula Aren 50, Kerajinan 92. Stok per desa: Kemiren 60, Tamansari 72, Osing 92.
- Nama motif (Gajah Oling, Kopi Pecah) hanya dipakai sebagai nama jenis produk. Deskripsi ditulis sendiri, satu kalimat, tanpa menyalin teks dari situs lain.

## 4. Data model

### 4.1 Entitas Product (disimpan di localStorage)

| Field | Tipe | Aturan |
|---|---|---|
| `id` | string | Wajib, unik, dibuat otomatis (`crypto.randomUUID()`, cadangan: stempel waktu + angka acak). Tidak dapat diubah. |
| `nama` | string | Wajib, setelah trim 2 sampai 60 karakter. |
| `kategori` | string | Wajib, salah satu dari: Batik, Kopi, Gula Aren, Kerajinan. |
| `perajinId` | string | Wajib, harus cocok dengan `id` pada daftar perajin. Desa produk diturunkan dari usaha ini dan tidak disimpan di produk. |
| `harga` | number | Angka hingga terbatas (bukan NaN/Infinity), nilai >= 0. Dianjurkan bilangan bulat Rupiah. |
| `stok` | number | Bilangan bulat, nilai >= 0. |
| `deskripsi` | string | Opsional, maksimal 200 karakter. |
| `imageUrl` | string | Opsional. Kosong atau path lokal berawalan `/img/` dan berakhiran `.svg`, `.png`, `.jpg`, atau `.webp`. URL eksternal ditolak pada MVP. |
| `unggulan` | boolean | Wajib, default false. |
| `olehOleh` | boolean | Wajib, default false. Menandai produk sebagai “Oleh-oleh khas Banyuwangi”. |

Contoh objek:

```json
{
  "id": "p-005",
  "nama": "Gula Aren Cetak 500 g",
  "kategori": "Gula Aren",
  "perajinId": "pr-04",
  "harga": 30000,
  "stok": 50,
  "deskripsi": "Gula aren cetak dari nira aren, cocok untuk minuman dan kue tradisional.",
  "imageUrl": "/img/placeholder-gula-aren.svg",
  "unggulan": true,
  "olehOleh": true
}
```

### 4.2 Entitas Perajin (tetap, dari seed, tidak disimpan)

| Field | Tipe | Aturan |
|---|---|---|
| `id` | string | Unik, contoh `pr-01`. |
| `namaUsaha` | string | Nama usaha fiktif, bukan nama orang. |
| `desa` | string | Salah satu dari: Kemiren, Tamansari, Osing. |
| `profil` | string | Satu kalimat, maksimal 160 karakter, tanpa data pribadi. |

### 4.3 Penyimpanan dan konfigurasi

Penyimpanan:
- Kunci localStorage: `tokoosing:v1:products`.
- Format nilai: `{ "version": 1, "products": [ ...Product ] }`.
- Pertama kali dibuka (kunci belum ada): isi dengan 10 data contoh.
- Jika JSON rusak atau tidak sesuai skema: abaikan data tersebut, muat ulang data contoh, dan tampilkan pesan singkat bahwa data direset.
- Setiap item hasil baca disaring ulang dengan aturan validasi yang sama (termasuk `perajinId` harus valid). Item tidak valid dibuang.
- Jika semua produk dihapus pengguna, keadaan kosong dipertahankan (tidak diisi ulang otomatis). Pengguna memakai F-18 untuk memulihkan.
- Penulisan dibungkus `try/catch`. Jika gagal (kuota penuh atau penyimpanan diblokir), tampilkan pesan galat dan aplikasi tetap berjalan dengan data di memori.

Konfigurasi di `src/config.js`:
- `WHATSAPP_NUMBER`: nomor format internasional tanpa tanda plus, misalnya `6280000000000`. Nilai bawaan adalah nomor contoh palsu dan harus diganti pemilik toko sebelum dipakai sungguhan.
- `STORAGE_KEY`: `tokoosing:v1:products`.
- `LOW_STOCK_THRESHOLD`: 5.
- `KATEGORI`: Batik, Kopi, Gula Aren, Kerajinan.
- `DESA`: Kemiren, Tamansari, Osing.

Template pesan WhatsApp (teks dienkode dengan `encodeURIComponent`):

```text
Halo Toko Osing, saya ingin memesan {nama} dari {namaUsaha} (Desa {desa}), harga {harga}. Jumlah: 1. Mohon info ketersediaan dan cara pembayaran.
```

## 5. Nonfunctional requirements

| Area | Persyaratan |
|---|---|
| Tech stack | Vite + JavaScript vanilla (ES modules). Tanpa framework, tanpa backend, tanpa dependensi runtime. Hanya `vite` sebagai devDependency. |
| Kompatibilitas | Dua versi terakhir Chrome, Firefox, Edge, dan Safari. |
| Responsif | Terbaca dan dapat dipakai tanpa gulir horizontal pada lebar 360 px sampai desktop. Tabel dashboard boleh bergulir di dalam wadahnya sendiri. |
| Performa | Render ulang daftar kurang dari 100 ms untuk sampai 200 produk. Pencarian langsung (tanpa tombol), boleh memakai debounce maksimal 200 ms. |
| Aksesibilitas | Semua input punya label. Tombol dan sakelar dapat dijangkau keyboard dengan fokus terlihat. Gambar punya `alt` berisi nama produk. Kontras teks minimal 4,5:1. Dialog konfirmasi dapat ditutup dengan Esc. |
| Keamanan | Data pengguna dirender dengan `textContent` atau `createElement`, tidak lewat `innerHTML`. Tidak memakai `eval`. Tautan eksternal memakai `rel="noopener noreferrer"`. Tidak ada API key, token, atau rahasia di kode maupun repositori. |
| Privasi dan konten lokal | Seluruh data contoh fiktif dan nonpribadi. Profil perajin hanya memuat nama usaha dan desa. Tidak ada pelacakan, analitik, atau permintaan jaringan selain membuka tautan WhatsApp oleh pengguna. |
| Bahasa | Antarmuka berbahasa Indonesia sederhana. Nama produk dan istilah lokal ditulis apa adanya. |
| Offline | Setelah halaman termuat, katalog, profil perajin, dan dashboard berfungsi tanpa internet. Hanya tombol WhatsApp yang butuh koneksi. |
| Lisensi aset | Gambar placeholder berupa SVG sederhana buatan sendiri. Tanpa foto stok, logo usaha atau merek, foto orang, dan font/ikon berlisensi yang tidak jelas. |
| Pemeliharaan | Logika dipisah dari tampilan: fungsi murni untuk filter, sort, validasi, dan format. Tidak ada variabel global. Nama berkas dan fungsi konsisten (bahasa Inggris untuk kode, Indonesia untuk teks antarmuka). |
| Keamanan perintah | Hanya perintah standar proyek: `npm create vite@latest`, `npm install`, `npm run dev`, `npm run build`, `npm run preview`. Dilarang: `sudo`, `rm -rf`, mengunduh lalu langsung menjalankan skrip dari internet, mengubah izin sistem, atau memakai skrip dari sumber tak dikenal. |

### 5.1 Struktur proyek yang disarankan

```text
toko-osing/
  index.html
  package.json
  public/img/            placeholder.svg + placeholder-batik, -kopi, -gula-aren, -kerajinan (SVG buatan sendiri)
  src/
    main.js              inisialisasi dan router hash
    config.js            nomor WhatsApp, kunci storage, ambang stok, daftar kategori dan desa
    data/seed.js         10 produk contoh
    data/perajin.js      6 usaha perajin (tetap)
    state/store.js       baca/tulis localStorage, CRUD, reset
    utils/format.js      format Rupiah, tautan WhatsApp
    utils/validate.js    validasi dan sanitasi Product
    utils/query.js       fungsi murni: cari, filter, sort, ringkasan
    ui/catalog.js        tampilan katalog
    ui/perajin.js        tampilan profil perajin
    ui/dashboard.js      tampilan dashboard
    styles/main.css
```

## 6. Risiko

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Data hilang saat pengguna membersihkan data peramban | Produk hasil input hilang | Tampilkan catatan di dashboard bahwa data hanya ada di peramban ini. Sediakan reset data contoh. |
| JSON localStorage rusak atau diubah manual | Aplikasi gagal dimuat | `try/catch` saat parse, validasi ulang tiap item, fallback ke data contoh. |
| Injeksi HTML lewat nama atau deskripsi | Skrip berbahaya berjalan (XSS) | Render hanya dengan `textContent`. Uji dengan nama `<img src=x onerror=alert(1)>`. |
| Klaim “Oleh-oleh khas Banyuwangi” dianggap sertifikasi resmi | Informasi menyesatkan | Penanda diatur pengelola, dijelaskan sebagai kurasi toko, dan teks aplikasi tidak menyebut sertifikasi. |
| Nama usaha atau motif contoh mirip usaha nyata, atau nama orang ikut tercantum | Masalah privasi atau pencatutan | Semua nama usaha fiktif. Tidak ada nama orang, kontak, atau alamat di data. Ganti dengan data asli hanya atas izin pemilik. |
| Nomor WhatsApp salah atau masih contoh | Pesanan tidak sampai | Konstanta di satu tempat, ditandai jelas, dicek di checklist rilis. |
| Usaha dihapus atau diubah sehingga `perajinId` tidak cocok | Produk tampil tanpa usaha | Data perajin tetap pada MVP. Validasi membuang atau menolak `perajinId` yang tidak ada. |
| Dashboard terbuka untuk siapa saja di perangkat | Data dapat diubah pihak lain | Dinyatakan sebagai batasan demo. Tidak ada login palsu agar tidak menimbulkan rasa aman semu. |
| Harga desimal tampil dibulatkan | Selisih tampilan | Input memakai `step="1"`. Format tampilan tanpa pecahan. |
| Kuota localStorage (sekitar 5 MB) | Gagal menyimpan | Tidak menyimpan gambar sebagai data URL. Tangani galat penyimpanan. |
| Perluasan lingkup | Tidak selesai dalam sehari | Patuhi bagian Out-of-scope. Fitur tambahan hanya jika semua acceptance criteria sudah lolos. |
| Aset berhak cipta | Masalah lisensi | Hanya SVG buatan sendiri. Tolak `imageUrl` eksternal. |

## 7. Out-of-scope

- Backend, database, API, dan sinkronisasi antarperangkat.
- Login, registrasi, peran pengguna, dan proteksi dashboard.
- CRUD data perajin, nomor WhatsApp per usaha, dan foto atau kontak pribadi perajin.
- Keranjang belanja, checkout, pembayaran, ongkos kirim, dan pelacakan pesanan.
- Unggah gambar, kamera, dan penyimpanan gambar di localStorage.
- Peta lokasi desa, informasi paket wisata, dan reservasi homestay.
- Multi-bahasa (misalnya bahasa Osing atau Inggris), multi-mata uang, dan multi-toko.
- Ekspor/impor data, laporan penjualan, dan grafik.
- PWA, notifikasi, dan SEO lanjutan.
- Pengujian otomatis (opsional bila waktu tersisa).
- Integrasi WhatsApp Business API (hanya tautan `wa.me`).

## 8. Acceptance criteria

Semua butir diperiksa manual pada peramban dengan localStorage kosong, kecuali disebut lain. Centang saat lolos.

### 8.1 Katalog

- [ ] **AC-01** Buka aplikasi pertama kali. Katalog menampilkan tepat 10 kartu produk sesuai tabel data contoh, masing-masing dengan gambar, nama, kategori, harga, nama usaha, desa, dan status stok.
- [ ] **AC-02** Harga tampil dalam format Rupiah dengan pemisah ribuan titik (contoh: Rp350.000 untuk Batik Tulis Motif Gajah Oling).
- [ ] **AC-03** Tepat 3 kartu memiliki badge Unggulan (Batik Tulis Motif Gajah Oling, Kopi Osing Bubuk 200 g, Gula Aren Cetak 500 g) dan tepat 8 kartu memiliki badge “Oleh-oleh khas Banyuwangi”.
- [ ] **AC-04** Gambar produk berasal dari berkas lokal. Pada tab Network DevTools tidak ada permintaan gambar ke domain lain.
- [ ] **AC-05** Filter desa: Kemiren menampilkan 3 produk, Tamansari 3, Osing 4. “Semua desa” menampilkan 10.
- [ ] **AC-06** Filter kategori: Batik 2, Kopi 2, Gula Aren 2, Kerajinan 4.
- [ ] **AC-07** Aktifkan sakelar “Oleh-oleh khas Banyuwangi”. Tampil 8 produk dan dua produk Anyaman (Tempat Tisu dan Tas Pandan) tidak ada. Dengan desa Osing ikut dipilih, tampil 2 produk (Gantungan Kunci Kayu Ukir dan Topeng Kayu Hias Mini).
- [ ] **AC-08** Desa Tamansari digabung kategori Gula Aren menampilkan 2 produk. Menekan “Reset filter” mengembalikan 10 produk dan mematikan semua filter.
- [ ] **AC-09** Ketik “kopi” pada pencarian. Semua kartu yang tampil memuat kata itu pada nama, kategori, deskripsi, nama usaha, atau desa, dan penghitung sama dengan jumlah kartu. Ketik “KOPI” menghasilkan daftar yang sama.
- [ ] **AC-10** Ketik “zzzz”. Muncul pesan hasil kosong dan tombol “Reset filter” yang berfungsi.
- [ ] **AC-11** Sorting “Harga termurah”: Gantungan Kunci Kayu Ukir (Rp15.000) pertama dan Batik Tulis Motif Gajah Oling (Rp350.000) terakhir. “Harga termahal” kebalikannya. “Nama A–Z” terurut abjad. “Stok terbanyak” menempatkan Gantungan Kunci Kayu Ukir (60) pertama.
- [ ] **AC-12** Urutan bawaan (Unggulan dulu) menampilkan 3 produk unggulan di posisi teratas dengan urutan Batik Tulis Motif Gajah Oling, Gula Aren Cetak 500 g, Kopi Osing Bubuk 200 g.
- [ ] **AC-13** Tombol “Pesan via WhatsApp” membuka tab baru ke `https://wa.me/{nomor}?text=...`. Setelah didekode, teks berisi nama produk, nama usaha, desa, dan harga produk yang diklik, sesuai template.
- [ ] **AC-14** Gula Aren Semut 250 g (stok 0) menampilkan “Habis” dan tombol pesannya nonaktif serta tidak membuka apa pun saat diklik. Topeng Kayu Hias Mini menampilkan “Sisa 5”.

### 8.2 Profil perajin

- [ ] **AC-15** Buka `#/perajin`. Tampil 6 kartu usaha sesuai tabel perajin, masing-masing dengan nama usaha, desa, profil satu kalimat, dan jumlah produk (2, 1, 1, 2, 2, 2).
- [ ] **AC-16** Tidak ada nama orang, nomor telepon, alamat rumah, atau foto orang pada halaman Perajin maupun di data seed.
- [ ] **AC-17** Tombol “Lihat produk” pada Gula Aren Sumber Lestari membuka Katalog dengan tepat 2 produk dan penanda filter usaha yang dapat dihapus lewat “Reset filter”.

### 8.3 Dashboard

- [ ] **AC-18** Buka `#/dashboard`. Ringkasan menunjukkan Total produk 10, Total stok 224, Kategori 4, Oleh-oleh 8, Habis 1. Tabel stok per kategori: Batik 20, Kopi 62, Gula Aren 50, Kerajinan 92. Tabel stok per desa: Kemiren 60, Tamansari 72, Osing 92.
- [ ] **AC-19** Tambah produk valid (kategori Kopi, usaha Kopi Rakyat Tamansari, stok 10, oleh-oleh dicentang). Ringkasan menjadi 11, 234, 4, 9, 1. Stok Kopi menjadi 72 dan stok Tamansari menjadi 82. Produk muncul di tabel dan katalog.
- [ ] **AC-20** Ubah harga produk yang baru dibuat. Perubahan tampil di tabel dan katalog, `id` tidak berubah, dan jumlah produk tetap. Ubah usaha ke usaha di desa lain: filter desa di katalog mengikuti desa baru.
- [ ] **AC-21** Hapus produk. Muncul konfirmasi. Pilih batal: data tetap. Pilih setuju: produk hilang dan ringkasan diperbarui.
- [ ] **AC-22** Validasi menolak dan menampilkan pesan galat untuk: nama kosong atau 1 huruf, kategori atau usaha belum dipilih, harga negatif atau bukan angka, stok negatif, stok desimal (misalnya 2,5), dan deskripsi lebih dari 200 karakter. Tidak ada data yang tersimpan.
- [ ] **AC-23** Harga 0 dan stok 0 diterima sebagai nilai valid.
- [ ] **AC-24** `imageUrl` berisi alamat `https://...` ditolak. Nilai kosong diterima dan kartu memakai placeholder.

### 8.4 Penyimpanan dan ketahanan

- [ ] **AC-25** Setelah menambah, mengubah, atau menghapus, muat ulang halaman (F5). Data tetap sama. Nilai tersimpan terlihat di DevTools, Application, Local Storage, kunci `tokoosing:v1:products`.
- [ ] **AC-26** Ubah nilai kunci tersebut menjadi `{rusak` lalu muat ulang. Aplikasi tidak error, menampilkan pesan data direset, dan memuat 10 produk contoh.
- [ ] **AC-27** Ubah satu `perajinId` di localStorage menjadi nilai yang tidak ada lalu muat ulang. Produk itu tidak tampil dan aplikasi tetap berjalan tanpa error di konsol.
- [ ] **AC-28** Tombol “Kembalikan data contoh” (dengan konfirmasi) memulihkan tepat 10 produk awal.
- [ ] **AC-29** Tambah produk bernama `<img src=x onerror=alert(1)>`. Teks tampil apa adanya, tidak ada alert, dan tidak ada elemen gambar baru yang terbentuk dari nama itu.

### 8.5 Nonfungsional dan proyek

- [ ] **AC-30** Pada lebar 360 px (mode perangkat di DevTools) tidak ada gulir horizontal pada halaman, kartu tersusun satu kolom, dan semua filter serta kontrol tetap dapat dipakai.
- [ ] **AC-31** Seluruh alur katalog, perajin, dan dashboard dapat dijalankan hanya dengan keyboard (Tab, Enter, Spasi, Esc) dengan fokus terlihat.
- [ ] **AC-32** Matikan jaringan (DevTools, Offline) setelah halaman termuat. Pencarian, filter, sorting, halaman Perajin, dan CRUD tetap berjalan.
- [ ] **AC-33** `npm run build` selesai tanpa error dan `npm run preview` menampilkan aplikasi yang sama.
- [ ] **AC-34** Pencarian teks pada seluruh repositori (misalnya kata “key”, “token”, “secret”) tidak menemukan kredensial. Folder `public/img` hanya berisi berkas buatan sendiri.

## 9. Implementation plan per iterasi

Urutan sengaja dibuat agar setiap iterasi menghasilkan sesuatu yang dapat dicoba. Estimasi total sekitar 8,5 jam.

### Iterasi 0: Setup (30 menit)
- Buat proyek: `npm create vite@latest toko-osing -- --template vanilla`, lalu `npm install`.
- Hapus berkas contoh bawaan, buat struktur folder di bagian 5.1, siapkan `index.html` dengan kerangka header (navigasi Katalog, Perajin, Dashboard) dan satu elemen `main`.
- Pasang router hash sederhana yang berpindah antara tiga tampilan kosong.
- Selesai bila: `npm run dev` berjalan dan klik navigasi mengganti isi halaman.

### Iterasi 1: Data dan penyimpanan (1 jam)
- Tulis `config.js`, `seed.js` (10 produk), `perajin.js` (6 usaha), dan 5 SVG placeholder sederhana buatan sendiri.
- Tulis `validate.js` (validasi dan sanitasi Product, termasuk pemeriksaan `perajinId`) dan `store.js` (muat, simpan, tambah, ubah, hapus, reset, dengan `try/catch`).
- Tulis `format.js` (Rupiah, tautan WhatsApp dari template).
- Selesai bila: AC-25 sampai AC-28 dapat diuji lewat konsol atau tombol sementara, dan pemuatan pertama mengisi 10 produk.

### Iterasi 2: Katalog (2 jam)
- Tulis `query.js` (cari, filter desa/kategori/oleh-oleh/usaha, sort) sebagai fungsi murni.
- Bangun `catalog.js`: kartu produk, badge, penanda stok, kontrol pencarian, tiga filter, sorting, penghitung, penanda filter aktif, dan keadaan kosong.
- Hubungkan tombol WhatsApp dan fallback gambar (`onerror`).
- Gaya dasar responsif (mobile dulu).
- Selesai bila: AC-01 sampai AC-14 lolos.

### Iterasi 3: Profil perajin (45 menit)
- Bangun `perajin.js`: kartu usaha dengan jumlah produk (dihitung dari data produk saat ini).
- Hubungkan tombol “Lihat produk” ke filter usaha di katalog.
- Selesai bila: AC-15 sampai AC-17 lolos.

### Iterasi 4: Dashboard CRUD (2 jam)
- Bangun `dashboard.js`: tabel produk, formulir tambah/ubah (pilihan kategori dan usaha dari data tetap), konfirmasi hapus, tombol reset.
- Hubungkan ke `store.js` dan `validate.js`, tampilkan galat per field.
- Pastikan katalog dan halaman Perajin dirender ulang saat data berubah.
- Selesai bila: AC-19 sampai AC-24 lolos.

### Iterasi 5: Ringkasan stok, keamanan, dan polish (1 jam)
- Tambahkan kartu ringkasan dan tabel stok per kategori dan per desa dari fungsi di `query.js`.
- Periksa semua render memakai `textContent`, tambahkan catatan “data hanya tersimpan di peramban ini”.
- Rapikan fokus keyboard, label formulir, kontras, dan tampilan 360 px.
- Selesai bila: AC-18, AC-29, AC-30, dan AC-31 lolos.

### Iterasi 6: QA dan dokumentasi (1 jam 15 menit)
- Jalankan seluruh acceptance criteria dari localStorage kosong, centang satu per satu, dan catat temuan.
- Jalankan `npm run build` dan `npm run preview` (AC-33), uji mode offline (AC-32), periksa repositori (AC-34).
- Tulis `README.md` singkat: cara menjalankan, cara mengganti nomor WhatsApp, desa, dan kategori, serta batasan yang diketahui.
- Selesai bila: semua AC tercentang atau tiap kegagalan tercatat bersama rencana perbaikan.

### Definition of done
- Seluruh 34 acceptance criteria lolos atau pengecualiannya tercatat.
- Tidak ada error di konsol peramban saat alur utama dijalankan.
- Tidak ada rahasia, data pribadi, nama usaha nyata, atau aset berhak cipta di repositori.
- Nomor WhatsApp contoh sudah ditandai di README agar diganti sebelum dipakai nyata.
