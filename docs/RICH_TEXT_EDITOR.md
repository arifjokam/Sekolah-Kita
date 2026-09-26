# Rich Text Editor untuk Isi Berita

Dokumen ini menjelaskan editor rich text pada form berita admin: format yang
didukung, alur unggah gambar, sanitasi, batas unggah, dan otorisasinya.

---

## 1. Ringkasan

| Aspek | Nilai |
| --- | --- |
| Penyunting | Tiptap 3 (framework agnostic, `@tiptap/core`) |
| Penyimpanan | Kolom `news_articles.body` (TEXT, maks. 65.535 byte) |
| Sanitasi | `symfony/html-sanitizer` di server, sebelum dan saat pembacaan |
| Unggah gambar | Endpoint JSON khusus, melalui `MediaAssetService` |
| Otorisasi | Administrator aktif dan terverifikasi email saja |
| Batas unggah | 5.242.880 byte (5 MB) per gambar |

Kolom `body` yang sudah ada digunakan apa adanya. Tidak ada kolom baru untuk
menyimpan markup, dan tidak ada migration yang mengubah riwayat migration.

---

## 2. Format yang Didukung

Editor offering dibatasi oleh allowlist di `App\Services\RichTextSanitizer`.
Markup di luar daftar ini dibuang atau diturunkan menjadi teks biasa.

| Kelompok | Elemen | Atribut |
| --- | --- | --- |
| Blok | `p` | `data-align` |
| Judul | `h2`, `h3`, `h4` | `data-align` |
| Teks | `strong`, `em`, `u`, `s` | – |
| Tautan | `a` | `href`, `title`, `rel` (dipaksa) |
| Daftar | `ul`, `ol`, `li` (termasuk bersarang) | – |
| Kutipan | `blockquote` | `data-align` |
| Pemisah | `hr`, `br` | – |
| Gambar | `img` | `src`, `alt`, `title`, `data-align`, `data-width` |

Nilai `data-align` hanya `left`, `center`, `right`, `justify`. Nilai
`data-width` hanya `25`, `50`, `75`, `100`.

Aturan tambahan:

- `class`, `id`, `style`, `target`, dan setiap event handler `on*` dibuang.
- Perataan dan ukuran gambar memakai atribut data, bukan `style`, sehingga
  markup yang tersimpan tidak pernah membawa inline style.
- `href` dibatasi pada `http`, `https`, `mailto`, `tel`. Tautan relatif dan
  `javascript:` ditolak; teks tautan tetap dipertahankan.
- Tautan otomatis diberi `rel="noopener noreferrer nofollow ugc"`.
- Elemen yang bisa mengeksekusi, menyematkan, atau memuat sumber daya lain
  (`script`, `iframe`, `object`, `embed`, `svg`, `form`, `style`, dan
  sejenisnya) dibuang beserta isinya.
- Elemen yang tidak diizinkan tetapi tidak berbahaya (`b`, `div`, `span`,
  `pre`, `code`) diturunkan menjadi teksnya, bukan dihapus, agar konten lama
  tidak hilang.

Markdown tidak tersedia, dan `code block` sengaja dimatikan karena tidak ada
dalam allowlist.

---

## 3. Alur Unggah Gambar

Gambar tidak pernah disandikan sebagai base64 di dalam HTML maupun di database.

1. Admin menekan tombol gambar, menyeret berkas ke area editor, atau menempel
   (paste) gambar.
2. Peramban mengunggah berkas ke endpoint JSON di bawah ini. Unggahan hanya
   terjadi atas tindakan eksplisit pengguna; tidak ada unggahan otomatis saat
   mengetik.
3. `App\Services\NewsArticleBodyImageService::upload()` memvalidasi otoritasi,
   lalu meneruskan berkas ke `MediaAssetService::store()`, yang melakukan:
   - pemeriksaan ukuran berkas,
   - inspeksi MIME dari isi berkas (`getimagesize`) dan allowlist
     `image/jpeg`, `image/png`, `image/webp`,
   - batas dimensi 6.000 x 6.000 piksel dan 20 juta piksel,
   - **decode lalu re-encode** (metadata seperti EXIF dihapus),
   - penyimpanan dengan nama acak `media/<uuid>.<ext>`, nama berkas asli tidak
     pernah dipercaya.
4. Derivatif publik dibuat dan endpoint mengembalikan URL publik yang aman.

Respons JSON (201):

```json
{
  "id": 42,
  "url": "https://sekolah.sch.id/storage/media/6b6b1c34-....jpg",
  "width": 1200,
  "height": 800,
  "mime_type": "image/jpeg",
  "max_bytes": 5242880
}
```

Respons tidak pernah memuat private object key, lokasi disk, atau nama berkas
asli. Kegagalan validasi mengembalikan 422 dengan pesan per-field.

### Endpoint

| Method | URI | Nama route | Kapan dipakai |
| --- | --- | --- | --- |
| POST | `/admin/news/images` | `admin.news.images.store` | Layar buat berita (artikel belum ada) |
| POST | `/admin/news/{newsArticle}/images` | `admin.news.images.store-for-article` | Layar edit berita |

Keduanya berada di dalam middleware stack administrator
(`auth`, `auth.session`, `active`, `verified`, `role:administrator`) dan
ditambahkan `throttle:news-image-upload` (30/menit dan 120/jam per pengguna,
60/menit per IP).

### Batas unggah

- Maksimum 5 MB per gambar (`MediaAssetService::MAX_IMAGE_BYTES`).
- Maksimum 6.000 x 6.000 piksel, maksimum 20 juta piksel.
- Hanya JPG, PNG, WEBP. SVG dan berkas non-gambar ditolak.
- Validasi berlapis: aturan form request (ekstensi, MIME, `image`),
  `getimagesize` di `MediaAssetService`, dan decode/re-encode. Ketiganya harus
  setuju.

---

## 4. Referensi Media di dalam Artikel

Tabel `news_article_media` mencatat gambar yang tertanam di dalam isi artikel.

Alasannya: `body` adalah satu kolom HTML, sehingga gambar di dalamnya tidak
punya relasi referensial ke `media_assets`. Tanpa tabel ini,
`MediaAssetService` tidak tahu bahwa sebuah aset masih dipakai, dan bisa
menarik derivatif publik yang masih ditunjuk artikel terbit.

Konsekuensinya:

- Gambar yang tertanam di isi artikel **tetap publik** selama masih dirujuk,
  pada status artikel apa pun. Ini disengaja: setiap publikasi ulang membuat
  object key acak baru, sehingga URL yang tersimpan di dalam `body` hanya
  stabil selama derivatifnya tidak pernah ditarik.
- Saat gambar dihapus dari isi artikel, baris referensinya dihapus dan hanya
  saat itu derivatif publiknya ditarik. Sumber privat tetap disimpan.
- `news_article_id` boleh `null` hanya untuk satu alasan: layar buat berita
  mengunggah gambar sebelum baris artikel ada. Baris seperti itu "diadopsi"
  artikel saat pertama kali disimpan.

### Gambar terlantar

`news:prune-staged-article-images` (terjadwal setiap jam) membersihkan dua
situasi, dengan jendela retensi
`sekolah-kita.news.staged_image_ttl_hours` (default 6 jam):

1. Gambar yang diunggah dari layar buat berita tetapi tidak pernah masuk artikel
   (baris bertanda staged, tanpa artikel).
2. Gambar yang diunggah dari layar edit tetapi tidak pernah disimpan ke dalam
   isi artikel. Untuk kasus ini perintah memanggil reconciliasi referensi yang
   sama dengan saat penyimpanan, sehingga gambar yang masih tertanam di artikel
   tidak akan tersentuh.

Perintah ini hanya membuang aset yang sudah tidak dirujuk apa pun, sehingga
gambar di dalam artikel yang sudah terbit tidak pernah hilang.

---

## 5. Sanitasi

`App\Services\RichTextSanitizer` memakai `symfony/html-sanitizer` dengan
allowlist ketat. Sanitasi berjalan di dua tempat:

1. **Sebelum penyimpanan** — `PublicContentManager::createNews()` dan
   `updateNews()` melewati `body` melalui sanitizer. Ini adalah recomendarasi
   utama.
2. **Saat pembacaan** — `PublicContentReader` menyanitasi ulang di dalam
   closure cache, sebagai lapisan kedua terhadap baris yang masuk ke database
   melalui jalur lain.

Detail penting:

- `allowRelativeMedias(true)` diaktifkan, tetapi ini aman: untuk `img[src]`,
  `RichTextImageSourceSanitizer` berjalan lebih dulu dan menulis ulang nilai
  menjadi URL kanonik aset yang tersimpan. Nilai yang dipakai sanitizer
  bawaan_symfony tidak pernah berasal dari input.
- `img[src]` hanya diterima jika URL tersebut menunjuk object key milik aset
  yang diizinkan untuk sesi editor tersebut: gambar yang sudah menempel pada
  artikel, atau gambar yang diunggah oleh admin yang sedang menulis. URL dari
  host lain ditulis ulang menjadi host sendiri; `data:` dan URL asing dihapus.
- Tubuh artikel yang bukan rich text (tidak mengandung elemen blok di
  allowlist) diteruskan apa adanya, sehingga artikel lama berbasis teks biasa
  tetap tampil persis seperti sebelumnya.
- Batas 65.535 byte dilaporkan sebagai error validasi, bukan dipotong diam-diam.

---

## 6. Render Publik

`resources/views/public/news/show.blade.php` memilih salah satu dari dua
komponen:

- `x-public.rich-text` untuk bodies rich text. Nilainya sudah disanitasi dan
  dirender sebagai HTML.
- `x-public.prose` untuk bodies teks biasa, seperti sebelumnya.

Styling ada di `resources/css/app.css` pada layer `.rich-text` (judul, paragraf,
daftar, kutipan, garis pemisah, tautan, gambar, perataan, ukuran gambar).

---

## 7. Frontend

- `resources/js/editor/rich-text-editor.js` — modul editor yang dapat dipakai
  ulang. Hanya bergantung pada atribut data pada elemen akarnya, sehingga
  editor yang sama bisa dipakai untuk profil sekolah, informasi PPDB, atau
  halaman statis tanpa perubahan kode JavaScript.
- `resources/js/app.js` — memuat modul tersebut dengan `import()` dinamis.
  Vite memisahkannya menjadi chunk sendiri, sehingga situs publik tidak
  mengunduh bundel editor.
- `resources/views/components/form/rich-text-editor.blade.php` — komponen
  Blade yang merender toolbar, area editor, panel tautan/alt, serta penghitung
  kata dan karakter.

Progressive enhancement: `textarea` tetap ada di dalam form sebagai field yang
dikirim. JavaScript menyembunyinya dan memasang editor di tempatnya, sehingga
form tetap berfungsi tanpa JavaScript.

Aksesibilitas: setiap tombol toolbar memiliki `title`, `aria-label`, dan
`aria-pressed` yang mencerminkan status nyata, bukan sekadar gaya. Toolbar
menggunakan `role="toolbar"` dengan grup `role="group"`. Status unggah
dilaporkan lewat `aria-live`, dan negasi dapat dilakukan lewat keyboard.

Tidak ada Permintaan jaringan saat mengetik. Isi artikel hanya dikirim saat
form disimpan.

---

## 8. Komponen yang Ditambahkan

| File | Peran |
| --- | --- |
| `app/Services/RichTextSanitizer.php` | Sanitasi dengan allowlist |
| `app/Services/RichTextImageSourcePolicy.php` | Allowlist sumber gambar |
| `app/Services/RichTextImageSourceSanitizer.php` | Aturan `img[src]` |
| `app/Services/RichTextAttributeSanitizer.php` | Aturan `data-align`, `data-width` |
| `app/Services/NewsArticleBodyImageService.php` | Unggah dan referensi gambar |
| `app/Models/NewsArticleMedia.php` | Relasi gambar di dalam artikel |
| `app/Http/Controllers/Admin/NewsArticleImageController.php` | Endpoint unggah JSON |
| `app/Http/Requests/Admin/UploadNewsArticleImageRequest.php` | Validasi unggah |
| `app/Console/Commands/PruneStagedArticleImagesCommand.php` | Pembersihan gambar terlantar |
| `resources/views/components/public/rich-text.blade.php` | Render publik |
| `resources/views/components/form/editor-icon.blade.php` | Ikon toolbar |

---

## 9. Batasan yang Diketahui

- Batas `body` 20.000 karakter dihitung dari markup yang tersimpan, bukan dari
  teks. Artikel dengan banyak markup akan mendekati batas lebih cepat daripada
  yang terlihat dari jumlah kata.
- Tautan relatif ditolak; alamat tautan harus absolut.
- `code block` dan tabel tidak tersedia.
- Tidak ada undo/redo lintas sesi, dan tidak ada autosave.
- Gambar tidak diperkecil di sisi server selain batas dimensi; ukuran
  presentasi diatur lewat preset 25/50/75/100 persen.
