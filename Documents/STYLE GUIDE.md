# STYLE GUIDE
## Website Company Profile & Portfolio

| | |
|---|---|
| **Versi** | 0.1 (Draft) |
| **Acuan** | SRS v1.1, Bagian 3.1 dan 4.1 |
| **Status** | Nilai bertanda **[USULAN]** belum diputuskan dan dapat diubah. |

Dokumen ini adalah sumber tunggal untuk keputusan visual. Jika sebuah nilai (warna, jarak, font) dibutuhkan di kode, nilai itu harus berasal dari **token** di sini, bukan ditulis langsung.

---

## 1. Prinsip Visual

1. **Foto adalah konten utama.** UI tidak boleh bersaing dengan foto proyek.
2. **Minimal dan editorial.** Sedikit elemen, banyak ruang kosong, tipografi yang jelas.
3. **Asimetris tapi teratur.** Layout tidak seragam, namun mengikuti grid dan pola yang konsisten (bukan acak).
4. **Tanpa dekorasi.** Tidak ada gradient, bayangan tebal, ikon hias, atau animasi yang tidak punya fungsi.
5. **Satu bahasa visual.** Frontend publik dan dashboard memakai token yang sama; dashboard hanya lebih padat.

---

## 2. Warna

Palet netral. Warna aksen **tidak dipakai** di frontend publik; hanya status fungsional di dashboard.

### 2.1 Token Netral **[USULAN]**

| Token | Nilai | Pemakaian |
|---|---|---|
| `--color-bg` | `#FFFFFF` | Latar utama |
| `--color-bg-soft` | `#F7F6F3` | Latar off-white, section selang-seling |
| `--color-ink` | `#111111` | Teks utama, tombol utama |
| `--color-ink-muted` | `#6B6B6B` | Teks sekunder, caption, metadata |
| `--color-line` | `#E4E2DD` | Garis pemisah tipis |
| `--color-overlay` | `#0B0B0B` | Latar lightbox dan menu fullscreen |

### 2.2 Warna Status (dashboard saja) **[USULAN]**

| Token | Nilai | Pemakaian |
|---|---|---|
| `--status-success` | `#2E7D4F` | Published, Accepted |
| `--status-warning` | `#B7791F` | Scheduled, Unpublished Changes |
| `--status-danger` | `#B3261E` | Failed, Rejected, hapus |
| `--status-neutral` | `#6B6B6B` | Draft, Archived, Closed |

**Aturan:** status tidak boleh hanya dibedakan lewat warna; selalu sertakan label teks. Rasio kontras teks minimal **4,5:1** (WCAG AA).

---

## 3. Tipografi

- **Keluarga:** sans-serif grotesk. **[USULAN]** Inter atau "Helvetica Neue / system-ui" sebagai cadangan. Satu keluarga untuk seluruh sistem.
- **Pemuatan font:** self-host, subset Latin, `font-display: swap`, maksimal 2 bobot (400 dan 600).
- **Huruf:** tidak memakai all-caps panjang; all-caps hanya untuk label kecil (kategori, nav) dengan letter-spacing sedikit.

### Skala **[USULAN]**

| Token | Ukuran (desktop / mobile) | Bobot | Pemakaian |
|---|---|---|---|
| `--text-display` | 56 / 36 px | 600 | Judul halaman besar, featured article |
| `--text-h1` | 40 / 28 px | 600 | Judul halaman |
| `--text-h2` | 28 / 22 px | 600 | Judul section |
| `--text-h3` | 20 / 18 px | 600 | Subjudul, judul kartu |
| `--text-body` | 17 / 16 px | 400 | Isi teks |
| `--text-small` | 14 px | 400 | Metadata, caption |
| `--text-label` | 12 px | 600 | Label kategori, tombol kecil |

- Tinggi baris: 1,15 (display) sampai 1,6 (body).
- Lebar baris artikel: 60–75 karakter.

---

## 4. Jarak dan Grid

- **Satuan dasar:** 4 px. Skala jarak: 4, 8, 12, 16, 24, 32, 48, 64, 96, 128.
- **Container:** lebar maks 1440 px (publik), padding samping 24 px (mobile) / 48 px (desktop).
- **Grid publik:** 12 kolom (desktop), 8 (tablet), 4 (mobile); gutter 24 px.
- **Breakpoint** **[USULAN]**: mobile < 768, tablet 768–1199, desktop ≥ 1200 px.
- **Radius:** 0 px untuk gambar dan kartu; maksimal 4 px untuk input dan tombol. Tidak ada sudut membulat besar.
- **Bayangan:** tidak dipakai. Pemisah memakai garis 1 px `--color-line`.

---

## 5. Komponen

### 5.1 Navigasi (publik)
- Navbar sticky: logo kiri; search dan hamburger kanan; tinggi 64 px; latar `--color-bg`; garis bawah tipis saat halaman digulir.
- Menu hamburger: fullscreen, latar `--color-overlay`, teks putih, ukuran `--text-display`.
- Bar kategori: sticky di bawah navbar, teks `--text-label`, kategori aktif bergaris bawah 2 px. Tanpa latar tombol.

### 5.2 Kartu Proyek dan Artikel
- Gambar penuh tanpa border dan radius; judul kecil di bawah atau di atas gambar; metadata `--text-small` warna muted.
- Rasio gambar di grid bervariasi mengikuti pola yang sudah ditetapkan (lihat 6.1), bukan acak.
- Hover: perubahan halus (opacity atau zoom ≤ 3%), durasi 200 ms.

### 5.3 Tombol
| Jenis | Tampilan |
|---|---|
| Primary | Latar `--color-ink`, teks putih, radius 2 px |
| Secondary | Garis 1 px `--color-ink`, latar transparan |
| Text | Teks bergaris bawah, tanpa kotak |

Satu tombol primary per area. Fokus keyboard wajib terlihat (outline 2 px).

### 5.4 Form
- Label di atas input; input bergaris bawah atau kotak 1 px `--color-line`; pesan error di bawah field dengan teks dan warna `--status-danger`.
- Semua input memiliki label terhubung (bukan hanya placeholder).

### 5.5 Lightbox
- Latar `--color-overlay`; kontrol minimal; indikator posisi `03/24`; kontrol dapat diakses keyboard.

### 5.6 Skeleton dan Loading
- Skeleton berwarna `--color-bg-soft` dengan pulsa lembut; ukuran sama dengan konten asli agar tidak terjadi layout shift.

---

## 6. Gambar

### 6.1 Pola Grid Editorial **[USULAN, perlu dirancang]**
Pola ditetapkan sebagai urutan tetap yang berulang (contoh: besar–kecil–kecil–lebar), dihitung dari posisi item. Pola ini dipakai seragam di Projects, News, dan galeri proyek.

### 6.2 Aturan Tampilan
- Selalu set `width` dan `height` (atau `aspect-ratio`) untuk mencegah layout shift.
- Cover: 16:9 (desktop), 4:5 (mobile), mengikuti focal point.
- Foto galeri tampil dengan rasio aslinya.
- `loading="lazy"` kecuali gambar paling atas halaman.
- Selalu gunakan `srcset` dan `sizes` dari varian yang dihasilkan sistem.
- Alt text wajib untuk gambar informatif.

---

## 7. Dashboard

- Latar `--color-bg`; sidebar `--color-bg-soft`; pemisah garis 1 px.
- Hindari card berlapis; gunakan tabel dan garis pemisah.
- Tabel: baris 48 px, header `--text-label` muted, aksi di kolom paling kanan.
- Badge status: teks + warna status (lihat 2.2), tanpa ikon dekoratif.
- Sidebar: expanded 240 px, collapsed 64 px.
- Penyimpanan otomatis menampilkan indikator kecil: `Saving…`, `Saved`, `Failed — Retry`.
- Implementasi memakai tema Filament yang dikustomisasi agar token di atas berlaku; tidak membuat komponen sendiri bila sudah ada di Filament.

---

## 8. Gerak (Motion)

- Durasi 150–250 ms, easing `ease-out`.
- Transisi hanya untuk memberi umpan balik (hover, buka/tutup menu, lightbox).
- Hormati `prefers-reduced-motion`: matikan animasi non-esensial.

---

## 9. Aksesibilitas

- Target WCAG 2.1 AA.
- Seluruh elemen interaktif dapat dijangkau dan dioperasikan lewat keyboard.
- Fokus terlihat; urutan tab logis.
- Teks tidak bergantung pada warna saja untuk menyampaikan makna.
- Ukuran target sentuh minimal 44 × 44 px di mobile.

---

## 10. Implementasi di Kode

- **CSS:** Tailwind CSS, dengan token di atas didefinisikan sebagai CSS variable dan dipetakan di konfigurasi Tailwind. Tidak menulis nilai warna atau ukuran langsung di komponen.
- **Penamaan:** komponen Blade dalam `resources/views/components/` dengan nama yang menjelaskan fungsi (`project-card`, `category-bar`, `lightbox`).
- **Satu sumber kebenaran:** perubahan nilai visual dilakukan pertama di dokumen ini, lalu di token.
- **Pemeriksaan sebelum merge:** kontras warna, fokus keyboard, dan tampilan pada tiga breakpoint.

---

## 11. Yang Perlu Diputuskan

| # | Keputusan |
|---|---|
| 1 | Font final (Inter atau pilihan lain) dan lisensinya. |
| 2 | Warna final, termasuk apakah logo klien menuntut warna aksen. |
| 3 | Pola grid editorial: jumlah dan urutan variasi ukuran. |
| 4 | Apakah mode gelap diperlukan (saat ini tidak). |
| 5 | Logo, favicon, dan tagline resmi dari klien. |
| 6 | Referensi visual tambahan selain BIG.dk. |
