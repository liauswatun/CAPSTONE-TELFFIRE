# Presentasi Web TELF — Pemantauan Kebakaran Hutan Sumatera

> Penjelasan logis, langkah demi langkah. Siap dibacakan / disalin ke slide.
> Setiap slide = satu poin besar. Ikuti urutannya dari S1 sampai S9.

---

## S1 — Apa Proyek Ini?

**TELF** adalah website untuk **memantau titik kebakaran hutan di Sumatera**.

- Menunjukkan **di mana** terjadi kebakaran lewat peta digital.
- Disegalah database, fakta yang ditampilkan (jumlah titik api, area terbakar) lebih bermakna.
- Ditujukan agar orang awam maupun peneliti bisa **melihat + memahami** sebaran kebakaran.

> **Logika dasarnya:** data kebakaran ada → kita buat aplikasi agar data itu mudah dilihat & dijelaskan.

---

## S2 — Kenapa Pilih Tema Ini?

Tema "kebakaran hutan Sumatera" dipilih karena **relevan dan jelas konteksnya**:

1. **Isu nyata** — Sumatera sering mengalami kebakaran lahan gambut; ini masalah lingkungan yang serius.
2. **Cerita desain yang kuat** — "ember di atas hutan":
   - **bara/ember oranye** = api/kebakaran (warna aksen).
   - **jelaga gelap** = malam/langit terbakar.
   - **hijau lumut** = hutan yang masih hidup.
   - Warna-warna ini konsisten di seluruh website.
3. **Alat yang terasa hidup** — peta interaktif, statistik, dan bahasa Indonesia/Inggris membuat topik berat jadi mudah dimengerti.

> **Bisik penting:** warna tidak dipilih sembarangan. Tiap warna melambangkan bagian dari cerita "hutan vs api".

---

## S3 — Bahasa Apa yang Dipakai?

Aplikasi web penuh ("full-stack") = **bagian server + bagian browser**.

| Bahasa | Dipakai untuk apa |
|---|---|
| **PHP** | Logika di server (backend). Dengan framework Laravel. |
| **JavaScript** | Interaksi di browser: peta, animasi, editor teks. |
| **Blade** | Template halaman web milik Laravel. |
| **HTML + CSS/Tailwind** | Membuat tampilan halaman. |
| **SQL (MySQL)** | Menyimpan & mengambil data. |
| **GeoJSON** | Format data peta (lokasi titik api & wilayah). |

**Satu kalimat:** *PHP mengatur data & aturan, JavaScript dan Blade menyajikannya, SQL dan GeoJSON menyediakan datanya.*

---

## S4 — Bagaimana Semua Tersusun? (Struktur)

Kode disusun mengikuti pola **MVC** (Model-View-Controller):

```
URL masuk
   │
   ▼
routes/web.php   →  "alamat ini diarahkan ke mana"
   │
   ▼
Middleware       →  "bolehkah diproses?" (sudah login? admin? bahasa?)
   │
   ▼
Controller       →  "ambil & atur data, lalu panggil tampilan"
   │              ▲
   ▼              │
   Model          →  memetakan tabel database (ORM)
   │
   ▼
View (Blade)     →  "tampilkan ke user"
```

> **MVC** memisahkan: **data** (Model), **logika** (Controller), **tampilan** (View). Ini membuat kode rapi dan mudah dirawat.

---

## S5 — Alur Kerja yang Penting

### a. Routing (peta alamat)
Setiap URL punya tujuan. Contoh:
- `/` → halaman utama.
- `/map` → halaman peta.
- `/admin/...` → halaman admin (hanya untuk admin).

### b. Keamanan (middleware & role)
- **Tidak semua orang boleh masuk semua halaman.**
- Ada peran: **admin**, **editor**, **viewer**.
- `role:admin` di routing = "halaman ini khusus admin".
- Di kode controller ada cek ulang: `authorizeAdminOrEditor()` — kalau bukan admin/editor diarahkan ke error 403.

> **Teori yang dipakai = RBAC** (Role-Based Access Control): tiap pengguna punya peran yang menentukan apa yang boleh diakses.

---

## S6 — Menyimpan Data dengan Model (ORM)

Setiap tabel database diwakili satu **model** (class PHP). Kita tidak menulis SQL mentah, cukup panggil method PHP.

Contoh model `Article` (artikel):

```php
Article::where('status', 'published')->get();  // ambil artikel yang terbit
```

Penjelasan bagian penting:
- `$fillable` → kolom mana yang boleh diisi (lindungi dari isi berbahaya).
- `$casts` → ubah teks tanggal jadi objek tanggal otomatis.
- `author()` → relasi "artikel milik user".
- `scopePublished()` → aturan "artikel yang tampil" dibuat sekali, dipakai berkali-kali.
- `getStatusLabelAttribute()` → ubah kode `draft` menjadi label rapi "Draft".

> **Teori = ORM (Object-Relational Mapping):** tabel = objek, baris = data objek, relasi antar tabel = method relasi.

---

## S7 — Halaman Interaktif tanpa Menulis JavaScript (Livewire)

CMS admin (kelola konten "Tentang", "Tim", "FAQ") dibuat dengan **Livewire**.

Cara kerjanya mudah: kode PHP di server menyimpan "keadaan" halaman, lalu tombol di halaman memanggil method PHP itu tanpa reload penuh.

```php
class AboutManager extends Component
{
    public ?string $hero_title = '';   // input dari form (state)

    public function mount(): void      // saat halaman dibuka
    {
        // isi state dari database
    }

    public function save(): void       // saat tombol "Simpan" diklik
    {
        // validasi lalu simpan ke database
    }
}
```

> **Teori = Reaktivitas Server-Side.** Beda dari aplikasi biasa (React) yang pakai JavaScript, di sini semua logika tetap di server → lebih aman, tanpa perlu menulis JavaScript untuk form/admin.

---

## S8 — Peta Kebakaran Itu Cara Kerjanya?

Peta di halaman `/map` memakai **Leaflet** (library peta) + data **GeoJSON**.

Alur:
1. Browser memuat data titik api (`fires.json`) & batas wilayah (`*.geojson`).
2. Titik api digambar di peta sebagai **kotak berwarna** sesuai intensitas (rendah/sedang/tinggi).
3. Peta bisa di-klik turun: **provinsi → kabupaten → kecamatan → desa** (drill-down).
4. Ada **timeline tahun** untuk melihat perubahan dari tahun ke tahun.
5. Statistik (jumlah titik, tren tahunan) dihitung langsung di browser.

Hal menarik: **semua dijalankan di browser (client-side)**, jadi server tidak terbebani query peta setiap kali halaman dibuka.

> **Teori = Client-Side Rendering + Canvas rendering.** Ribuan titik digambar di "kanvas" agar tetap halus, bukan sebagai elemen HTML satu per satu.

---

## S9 — Kesimpulan

| Pertanyaan | Jawaban |
|---|---|
| **Tema?** | Pemantauan kebakaran hutan Sumatera — isu lingkungan nyata + desain "ember di atas hutan". |
| **Bahasa?** | PHP (Laravel), JavaScript, Blade, HTML/CSS (Tailwind), SQL (MySQL), GeoJSON. |
| **Arsitektur?** | MVC (Model – View – Controller) + middleware + role. |
| **Data?** | MySQL (artikel, wilayah) + GeoJSON (peta). |
| **Interaktif?** | Livewire untuk admin; Leaflet untuk peta; JavaScript untuk animasi. |

**Garis besar:**
```
PHP + Laravel  →  mengatur logika & data     (backend)
Livewire       →  admin interaktif tanpa JS  (backend→UI)
Blade+Tailwind →  tampilan yang rapi         (frontend)
JavaScript     →  peta & interaksi           (frontend)
MySQL+GeoJSON  →  data teks & data peta      (data)
```

> **Pesan penutup:** TELF bukan sekadar website — ini "jembatan" antara *data kebakaran* dan *pemahaman manusia*, dibuat dengan teknologi web modern yang aman, rapi, dan mudah dikembangkan.
