# Dokumentasi Web TELF — Pemantauan Kebakaran Hutan Sumatera

Dokumen ini berisi:
1. **Bahasa pemrograman** yang digunakan dalam project.
2. **Dokumentasi kode inti** (struktur & cara kerja).
3. **Teori** di balik kode.

---

## 1. Bahasa Pemrograman yang Digunakan

Project ini adalah aplikasi web **full-stack** yang memakai **3 bahasa utama** plus beberapa bahasa pelengkap.

| Bahasa | Jenis | Keterangan |
|---|---|---|
| **PHP 8.4/8.5** | Backend | Bahasa utama untuk logika server lewat framework Laravel |
| **JavaScript** | Frontend | Interaktivitas peta (Leaflet), editor TinyMCE, animasi |
| **Blade** | Templating | Bahasa template milik Laravel (di dalam file `.blade.php`) |
| **HTML** | Markup | Struktur halaman yang dirender oleh Blade |
| **CSS** | Styling | Gaya tampilan; di project ini ditulis utility-first lewat Tailwind |
| **SQL (MySQL)** | Database | Query untuk menyimpan/mengambil data |
| **GeoJSON** | Data spasial | Format data titik api & batas wilayah di peta |

### Detail per bahasa

**a. PHP (Backend)**
- Menjalankan seluruh logika: routing, autentikasi & role, CRUD, validasi.
- Ditemukan di folder `app/` — misalnya controller (`app/Http/Controllers`), model (ORM `app/Models`), dan komponen Livewire (`app/Livewire`).
- Basis: framework **Laravel 13** + **Livewire 4**.

**b. JavaScript (Frontend)**
- Menangani peta interaktif dengan **Leaflet** (library open-source).
- Memuat data GeoJSON (`fires.json`, `sumatera_provinces.geojson`) lalu menggambar titik api & batas wilayah.
- Editor teks kaya **TinyMCE** dijelaskan di `resources/js/tinymce.js`.
- Contoh pemakaian: `resources/views/platform.blade.php` berisi ribuan baris JS untuk peta (drill-down provinsi → kabupaten → kecamatan, timeline animasi tahun, filter intensitas).

**c. Blade (Templating)**
- File `.blade.php` = HTML + direktif Blade (contoh `@extends`, `@section`, `@yield`, `{{ $var }}`).
- Memisahkan tampilan dari logika; controller menyuplai data ke view.
- Contoh: `resources/views/landing.blade.php` (halaman depan), `routes/web.php` memetakan URL ke view.

**d. CSS / Tailwind**
- `resources/css/app.css` memakai `@import 'tailwindcss'` dan mendefinisikan token warna tema "ember di atas hutan".
- Semua utility class (`flex`, `grid`, `px-6`) ditulis langsung di template.

**e. SQL (MySQL)**
- Database default: **MySQL** (dari `database.engine`).
- Migrasi di `database/migrations` mendefinisikan tabel (regions, articles, land_cover_types, deforestation_records, map_layers, dll).

---

## 2. Dokumentasi Kode Inti

### 2.1 Struktur Project

```
telmaps/
├── app/
│   ├── Http/
│   │   ├── Controllers/   → Controller MVC untuk route HTTP
│   │   │   ├── ArticleController.php
│   │   │   ├── MapController.php
│   │   │   ├── RegionController.php
│   │   │   └── ...
│   │   ├── Middleware/    → Filter request (auth, role, locale)
│   │   └── Requests/      → Form Request untuk validasi
│   ├── Livewire/          → Komponen interaktif (CMS admin, login/register)
│   ├── Models/            → Eloquent model / ORM (Article, User, Region, ...)
│   └── Providers/         → Service provider
├── config/                → Konfigurasi Laravel
├── database/              → Migrasi, seeder, factory
├── public/data/           → File GeoJSON & fires.json (data peta)
├── resources/             → View (blade), CSS, JS
│   ├── css/app.css        → Tailwind + token warna tema
│   ├── js/                → app.js, tinymce.js
│   └── views/             → Template blade (landing, admin, platform, ...)
├── routes/web.php         → Definisi semua URL
└── tests/                 → Tes otomatis (Pest)
```

### 2.2 Routing (routes/web.php)

Routing = "peta alamat URL". Tiga kelompok utama.

> **Sumber kode:** `routes/web.php` — definisi semua URL aplikasi.

```php
// 1) Publik (tanpa login)
Route::middleware('locale')->group(function () {
    Route::view('/', 'landing')->name('home');       // halaman utama
    Route::view('/map', 'map')->name('map');          // (lihat MapController)
});

// 2) Admin (wajib login + role admin)
Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/about', AboutManager::class);         // Livewire CMS
    Route::resource('articles', ArticleController::class);
});

// 3) Editor (wajib login + role editor)
Route::middleware(['auth', 'role:editor'])->prefix('editor')->group(function () {
    Route::get('/articles', [ArticleController::class, 'index']);
});
```

**Teori routing (MVC):** setiap URL masuk → middleware memeriksa izin → controller menjalankan aksi → mengembalikan view/data. `middleware('role:admin')` adalah **filter keamanan** yang memblokir akses non-admin.

### 2.3 Kontroller (App\Http\Controllers\ArticleController)

Controller menghubungkan **routing → data → view**.

> **Sumber kode:** `app/Http/Controllers/ArticleController.php` (class `ArticleController`). Kode ini *disarikan/disederhanakan* dari file aslinya agar mudah dibaca — metode `index()` dan `store()` di file asli tetap sama logikanya.

```php
class ArticleController extends Controller
{
    public function index(): View
    {
        $this->authorizeAdminOrEditor();   // cek izin

        // Editor hanya melihat artikel miliknya; admin melihat semua
        $scoped = Article::when(! auth()->user()->isAdmin(),
            fn ($q) => $q->where('author_id', auth()->id()));

        $articles = $scoped->clone()->with('author')->latest('published_at')->paginate(15);

        return view('admin.articles', ['articles' => $articles]);
    }

    public function store(StoreArticleRequest $request): RedirectResponse
    {
        $data = $request->validated();                 // validasi
        $data['slug'] = $data['slug'] ?? Str::slug($data['title']);
        if ($request->hasFile('featured_image')) {
            $data['featured_image'] = $request->file('featured_image')->store('article-images', 'public');
        }
        Article::create($data);                        // simpan ke DB
        return redirect()->route('admin.articles.index')->with('success', 'Artikel dibuat.');
    }
}
```

**Inti logika:**
- `authorizeAdminOrEditor()` → cek role user, kalau bukan admin/editor → `abort(403)` (sistem **keamanan/otorisasi**).
- `Article::when(...)` → Eloquent **conditional query**; menyesuaikan data per role (sistem **scoping**).
- `->paginate(15)` → **paginasi** (dipecah per 15 data agar ringan).
- `Str::slug()` → membuat **slug URL** yang aman dari judul.
- `->store('article-images', 'public')` → upload/gambar disimpan di storage publik.

### 2.4 Model/ORM (App\Models\Article)

Model = representasi **tabel database** di PHP (pola **Active Record ORM**).

> **Sumber kode:** `app/Models/Article.php` (class `Article`).

```php
class Article extends Model
{
    protected $fillable = ['title', 'slug', 'content', 'status', 'published_at'];

    protected $casts = ['published_at' => 'datetime'];   // konversi tipe otomatis

    public function author(): BelongsTo                     // relasi ke User
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopePublished($query)                  // query scope 'published'
    {
        return $query->where('status', 'published')
            ->where('published_at', '<=', now());
    }

    public function getStatusLabelAttribute(): string       // accessor label
    {
        return match ($this->status) {
            'draft'      => 'Draft',
            'published'  => 'Dipublikasikan',
            'archived'   => 'Diarsipkan',
            default      => $this->status,
        };
    }
}
```

**Inti logika:**
- `$fillable` → daftar kolom yang boleh diisi (sistem **proteksi mass-assignment**).
- `$casts` → otomatis mengubah `published_at` menjadi objek Carbon (tanggal).
- `author()` → relasi *belongs to* (artikel milik satu user).
- `scopePublished()` → **query scope** reusable: `Article::published()->get()`.
- `getStatusLabelAttribute()` → **accessor**; bisa dipanggil sebagai `$article->status_label`.

### 2.5 Model User & Role (App\Models\User)

> **Sumber kode:** `app/Models/User.php` (class `User`). Snippet di bawah *disederhanakan* — file aslinya juga memakai atribut PHP `#[Fillable(...)]` & `#[Hidden(...)]`, konstanta `ROLE_VIEWER`, dan helper lain.

```php
class User extends Authenticatable
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_EDITOR = 'editor';

    public function hasRole(string $role): bool { return $this->role === $role; }
    public function isAdmin(): bool           { return $this->hasRole(self::ROLE_ADMIN); }
    public function isEditor(): bool          { return $this->hasRole(self::ROLE_EDITOR); }
}
```

**Teori role:** sistem menggunakan kolom `role` di tabel users sebagai **role-based access control (RBAC)** sederhana — setiap user punya satu peran yang menentukan fitur yang bisa diakses.

### 2.6 Komponen Livewire (App\Livewire\Admin\AboutManager)

Livewire membuat halaman **reaktif tanpa JavaScript manual**. Komponen adalah class PHP dengan properti publik sebagai state.

> **Sumber kode:** `app/Livewire/Admin/AboutManager.php` (class `AboutManager`, namespace `App\Livewire\Admin`). View-nya di `resources/views/livewire/admin/about-manager.blade.php`. Contoh ini *disarikan* untuk menjelaskan pola `mount()/save()/render()`.

```php
class AboutManager extends Component
{
    use WithFileUploads;

    public ?string $hero_title = '';
    public $hero_image_upload = null;   // file upload sementara
    public ?string $successMessage = null;

    public function mount(): void          // dijalankan saat halaman dibuka
    {
        $about = About::latest('id')->first();
        if ($about) $this->fill($about->only(['hero_title', ...]));
    }

    public function save(): void           // dipicu tombol (wire:click)
    {
        $validated = $this->validate([...]);   // validasi server-side
        About::create($validated);
        $this->successMessage = 'Konten Tentang berhasil disimpan.';
    }

    public function render()
    {
        return view('livewire.admin.about-manager')->layout('layouts.admin');
    }
}
```

**Teori Livewire:**
- `mount()` = **lifecycle hook** saat komponen pertama kali dirender.
- `save()` dipanggil dari tombol `wire:click="save"` tanpa reload halaman penuh.
- Properti publik (`hero_title`, dll.) otomatis tersedia di view sebagai `$hero_title`.
- Metode = **aksi** yang menerima input dari frontend lalu kembali ke server (state di server).

### 2.7 Peta Interaktif (Leaflet + GeoJSON)

Peta di `resources/views/platform.blade.php` menggunakan **Leaflet**.

> **Sumber kode:** `resources/views/platform.blade.php` (skrip `<script>` di dalam template), didukung data di `public/data/sumatera_provinces.geojson` dan `public/data/fires.json`. Snippet di bawah *ringkasan* — file aslinya berisi ribuan baris JS (drill-down, timeline, filter).

```js
// Inisialisasi peta
map = L.map('map').setView([-0.5, 101.5], 6);

// Load data titik api & batas wilayah (GeoJSON)
Promise.all([
    fetch('/data/sumatera_provinces.geojson').then(r => r.json()),
    fetch('/data/fires.json').then(r => r.json()),
]).then(([provs, fires]) => {
    // render batas + titik api
});
```

**Teori spatial:**
- **GeoJSON** = format standar untuk data geografis (titik, garis, poligon).
- `fires.json` berisi titik api `[lat, lng, dn, year, kecCode]` (lintang, bujur, intensitas DN, tahun, kode kecamatan).
- **Drill-down**: klik provinsi → kabupaten → kecamatan → desa, masing-masing memfilter poligon & titik api.
- **Canvas cell layer**: titik api digambar sebagai kotak di canvas agar cepat walaupun ribuan titik.

### 2.8 Localization (ID/EN) — App\Http\Middleware\SetLocale

> **Sumber kode:** `app/Http/Middleware/SetLocale.php` (class `SetLocale`, terdaftar di `bootstrap/app.php` dan dipakai di `routes/web.php` lewat `middleware('locale')`).

```php
$lang = $request->query('lang') === 'en' ? 'en' : 'id';
app()->setLocale($lang);
View::share('lang', $lang);
```

**Teori:** Middleware membaca `?lang=` dari URL, menetapkan bahasa aplikasi, lalu membagikan variabel `$lang` ke semua view. Template memakai `$lang === 'en' ? ... : ...` untuk menampilkan teks bilingual. Model menyimpan teks ganda (`title` & `title_en`, dsb.).

---

## 3. Teori Inti (Konsep di Balik Kode)

### 3.1 Arsitektur MVC (Model-View-Controller)
- **Model** (`App\Models`) → mengelola data & relasi database.
- **View** (`resources/views`) → menampilkan data (Blade).
- **Controller** (`App\Http\Controllers`) → menerima request, memproses, mengembalikan view.

### 3.2 Eloquent ORM (Object-Relational Mapping)
- Setiap tabel DB diwakili class PHP. Query ditulis dengan method PHP, bukan SQL mentah.
- `Article::where('status','published')->get()` → terjemahan query `SELECT ... WHERE status = 'published'`.
- Relasi (hasMany, belongsTo) menggabungkan tabel secara otomatis.

### 3.3 Routing & Middleware
- **Route** memetakan URL ke kode.
- **Middleware** = lapisan yang menjalankan cek *sebelum* controller, mis. `auth` (sudah login?), `role:admin` (admin?), `locale` (bahasa?).
- Ini pola **pipeline HTTP** — setiap request melewati beberapa middleware berurutan.

### 3.4 Role-Based Access Control (RBAC)
- Kolom `role` di users (`admin`, `editor`, `viewer`).
- Middleware `role:admin` / `role:editor` memblokir akses sesuai peran.
- Di controller, helper `isAdmin()` / `authorizeOwnership()` memberikan kontrol lebih halus (mis. editor hanya boleh ubah artikel miliknya).

### 3.5 Livewire (Reaktivitas Server-Side)
- Berbeda dengan SPA (React/Vue) yang memakai JavaScript, Livewire menyimpan semua state di **server PHP**.
- `wire:click`, `wire:model` mengirim request AJAX kecil ke server; server merender ulang komponen; balasan dikirim sebagai DOM yang diperbarui.
- Keuntungan: logika & keamanan server-side, tanpa perlu menulis JavaScript untuk interaktivitas.
- Kekurangan: setiap interaksi butuh round-trip ke server.

### 3.6 Client-Side Rendering Peta (Leaflet + Canvas)
- Peta berjalan **sepenuhnya di browser** (client-side) memakai data statis GeoJSON dari `public/data`.
- Ini membebaskan server: tidak perlu query DB untuk menampilkan peta (lihat komentar `MapController`).
- Titik api digambar ke **canvas** (bukan DOM) agar mampu merender ribuan titik dengan lancar — ini penerapan **kanvas rendering / batching** untuk performa.

### 3.7 Tailwind CSS (Utility-First)
- Styling langsung dari utility class (`flex`, `grid`, `px-6`) di HTML, tanpa menulis file CSS terpisah per komponen.
- Token desain didefinisikan sekali di `resources/css/app.css` melalui `@theme`, sehingga seluruh tema "ember di atas hutan" konsisten.

### 3.8 Testing (Pest)
- Menggunakan **Pest 5** (framework testing PHP berbasis Laravel).
- Test memastikan perilaku (mis. role, validasi, upload) tidak rusak saat pengembangan lanjut. Jalankan: `php artisan test`.

---

## 4. Cara Menjalankan Project

```bash
composer install          # pasang dependency PHP
npm install               # pasang dependency JS
cp .env.example .env      # (Windows: copy .env.example .env)
php artisan key:generate  # buat app key
php artisan migrate       # buat tabel database
npm run dev               # (atau npm run build) jalankan Vite
php artisan serve         # jalankan server → http://localhost:8000
```

---

## Glosarium Singkat

| Istilah | Arti |
|---|---|
| **Backend** | Bagian server: logika, database, keamanan |
| **Frontend** | Bagian yang dilihat user di browser |
| **Endpoint** | Alamat URL yang menerima request |
| **ORM** | Teknik memetakan tabel DB ke object PHP |
| **Middleware** | Filter request sebelum masuk controller |
| **View/Blade** | Template rendering HTML |
| **GeoJSON** | Format data geografis (titik/poligon) |
| **RBAC** | Sistem kontrol akses berbasis peran |
