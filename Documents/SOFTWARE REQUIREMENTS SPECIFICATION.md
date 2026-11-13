# SOFTWARE REQUIREMENTS SPECIFICATION (SRS)
## Website Company Profile & Portfolio

| | |
|---|---|
| **Versi** | 1.1 |
| **Status** | Draft — Hasil Diskusi Kebutuhan (telah dirapikan) |
| **Jenis Sistem** | Website Company Profile, Portfolio & Recruitment Management |
| **Klien** | Perusahaan kontraktor |

### Riwayat Revisi

| Versi | Perubahan |
|---|---|
| 1.0 | Draft awal hasil diskusi kebutuhan. |
| 1.1 | Penomoran requirement (ID), prioritas dan fase, matriks status konten, glosarium, aktor, perluasan non-functional, perapian inkonsistensi, dan penambahan daftar keputusan terbuka. |

### Konvensi Dokumen

- **ID requirement:** `FR-<modul>-<nomor>` untuk fungsional dan `NFR-<kategori>-<nomor>` untuk non-fungsional.
- **Prioritas (MoSCoW):** **M** = Must, **S** = Should, **C** = Could. Prioritas bersifat **usulan** dan perlu dikonfirmasi klien.
- **Fase:** **F1** = rilis awal (MVP), **F2** = pengembangan lanjutan.
- Item bertanda **[USULAN]** adalah tambahan dari proses perapian dan **belum disetujui klien**.
- Item bertanda **[TBD]** belum memiliki keputusan final (lihat Bagian 15).
- Nilai teknis (misalnya ukuran, durasi, kualitas) adalah **parameter awal** yang dapat berubah setelah evaluasi.

---

# 1. Pendahuluan

## 1.1 Tujuan Sistem

Membangun website company profile untuk perusahaan kontraktor yang berfungsi sebagai:

1. Media informasi dan profil perusahaan.
2. Media publikasi hasil proyek konstruksi.
3. Media publikasi berita dan artikel perusahaan.
4. Media informasi lowongan pekerjaan dan penerimaan lamaran.
5. Media komunikasi antara perusahaan dan pengunjung website.
6. Sistem pengelolaan konten terpusat melalui dashboard administrator.

Website mengadopsi pendekatan desain editorial yang minimalis, modern, dan berorientasi pada fotografi, dengan referensi visual utama BIG.dk.

## 1.2 Aktor

| Aktor | Deskripsi |
|---|---|
| Pengunjung | Calon klien, mitra bisnis, dan masyarakat umum. Hanya mengakses konten published. |
| Pelamar | Individu yang melamar pekerjaan tanpa membuat akun. |
| Administrator | Staf internal yang mengelola website. Semua administrator memiliki hak akses setara. |
| Sistem (terjadwal) | Proses otomatis: scheduled publishing, pengingat deadline lowongan, pembersihan file temporary, dan garbage collection media. |

## 1.3 Prinsip Utama

- Desain minimalis dan editorial.
- Portfolio menjadi fokus utama website.
- Pengelolaan konten dinamis.
- Tidak menggunakan page builder yang terlalu kompleks.
- Pengelolaan gambar terpusat dengan antarmuka sesuai modul.
- Semua administrator memiliki hak akses yang setara.
- Sistem mudah dikembangkan pada tahap berikutnya.

## 1.4 Glosarium

| Istilah | Arti |
|---|---|
| Published | Versi konten yang tampil di frontend publik. |
| Draft | Versi kerja yang belum tampil di publik. |
| Unpublished Changes | Konten published yang memiliki perubahan draft yang belum dipublikasikan. |
| Focal Point | Titik fokus gambar yang menentukan area yang dipertahankan saat crop. |
| Snapshot formulir | Salinan struktur formulir yang tersimpan bersama lamaran, sehingga jawaban lama tetap terbaca walau template berubah. |
| Soft Delete | Penghapusan logis. File fisik baru dihapus setelah masa retensi dan tanpa referensi. |
| Garbage Collection (GC) | Proses otomatis penghapusan permanen file tanpa referensi. |
| Edit Lock | Mekanisme yang membatasi satu editor aktif per konten. |

## 1.5 Asumsi dan Batasan Umum

- Website berbahasa tunggal **[TBD — lihat Bagian 15]**.
- Hanya ada satu peran pengguna (administrator).
- Infrastruktur awal: 2 vCPU, RAM 2 GB, storage 1 TB, trafik rendah. Server belum disediakan (lihat Bagian 14).
- Detail arsitektur teknis, database, deployment, dan konfigurasi server dibahas pada dokumen terpisah.

---

# 2. Prioritas dan Pembagian Fase (Usulan)

Seluruh fitur pada dokumen ini berada dalam scope. Pembagian di bawah adalah **usulan urutan implementasi** agar risiko pada infrastruktur terbatas dapat dikelola.

| Fase | Cakupan |
|---|---|
| **F1 — MVP** | Frontend (Projects, About Us, News, Careers, Contact, Search), dashboard dasar, pengelolaan konten, kategori, rekrutmen (lowongan, form lamaran, daftar pelamar), user management, settings, activity logs, auto-save, manual publish, pipeline gambar inti. |
| **F2 — Lanjutan** | Form Builder drag-and-drop penuh dan conditional fields, Kanban pelamar, Version History (compare dan restore), Edit Lock, notifikasi real-time, Select from Existing Media, deduplikasi, focal point terpisah desktop/mobile. |

Penandaan fase per requirement ada pada kolom **Fase** di setiap tabel. **Pembagian ini perlu disetujui klien.**

---

# 3. Frontend Publik

## 3.1 Desain dan Navigasi

**Referensi visual:** BIG.dk. Layout editorial dan asimetris; dominasi putih, off-white, hitam, dan abu-abu; tipografi sans-serif; ruang kosong cukup; fotografi proyek sebagai elemen visual utama.

| ID | Requirement | Prio | Fase |
|---|---|---|---|
| FR-FE-001 | Navbar terdiri dari logo perusahaan, Global Search, dan Hamburger Menu, serta bersifat sticky. | M | F1 |
| FR-FE-002 | Hamburger menu menampilkan fullscreen navigation: Projects, About Us, News & Articles, Careers, Contact. | M | F1 |
| FR-FE-003 | Footer minimalis berisi nama dan tagline perusahaan, Explore Links, informasi kontak, alamat, social media, dan copyright. | M | F1 |
| FR-FE-004 | Data navbar, footer, dan metadata diambil dari Website Settings (Bagian 8). | M | F1 |

## 3.2 Homepage / Projects Listing

Homepage langsung menampilkan daftar proyek tanpa hero section konvensional.

| ID | Requirement | Prio | Fase |
|---|---|---|---|
| FR-FE-010 | Proyek ditampilkan dalam grid editorial asimetris dengan ukuran gambar bervariasi (bukan masonry acak). | M | F1 |
| FR-FE-011 | Judul proyek ditampilkan secara minimal tanpa elemen dekoratif berlebihan. | M | F1 |
| FR-FE-012 | Kategori ditampilkan dalam bar horizontal, dinamis, tanpa kategori "All Projects". | M | F1 |
| FR-FE-013 | Hanya kategori yang memiliki proyek published yang ditampilkan; urutannya diatur administrator. | M | F1 |
| FR-FE-014 | Bar kategori mendukung horizontal scroll dan swipe mobile, sticky di bawah navbar. | M | F1 |
| FR-FE-015 | Pergantian kategori tidak memerlukan reload halaman. | M | F1 |
| FR-FE-016 | Infinite scroll: memuat 8 proyek pertama, lalu 8 berikutnya ketika pengguna mendekati bagian bawah halaman. | M | F1 |
| FR-FE-017 | Gambar menggunakan lazy loading dan skeleton loading. | M | F1 |
| FR-FE-018 | Request duplikat dicegah dan pemuatan berhenti ketika semua data telah ditampilkan. | M | F1 |
| FR-FE-019 | Posisi scroll dipertahankan ketika pengguna kembali dari detail proyek. | S | F1 |
| FR-FE-020 | Fallback pagination tersedia jika JavaScript tidak aktif. | S | F1 |

## 3.3 Project Detail

| ID | Requirement | Prio | Fase |
|---|---|---|---|
| FR-FE-030 | Halaman detail menampilkan nama, kategori, lokasi, tahun, deskripsi, cover image, dan galeri foto. | M | F1 |
| FR-FE-031 | Galeri menggunakan layout editorial asimetris yang polanya ditentukan sistem berdasarkan urutan foto; administrator tidak mengatur layout per foto. | M | F1 |
| FR-FE-032 | Galeri responsif di desktop, tablet, dan mobile. | M | F1 |
| FR-FE-033 | Navigasi Next Project dan Previous Project. | S | F1 |

### Lightbox

| ID | Requirement | Prio | Fase |
|---|---|---|---|
| FR-FE-040 | Klik foto membuka tampilan fullscreen dengan background gelap, tombol close, dan navigasi sebelumnya/berikutnya. | M | F1 |
| FR-FE-041 | Navigasi keyboard (panah dan Escape) serta swipe pada perangkat mobile. | S | F1 |
| FR-FE-042 | Indikator posisi (contoh: 03/24). | S | F1 |
| FR-FE-043 | Klik area luar foto menutup lightbox; posisi scroll halaman dipertahankan. | S | F1 |
| FR-FE-044 | Gambar resolusi tinggi dimuat ketika foto dibuka. | S | F1 |

## 3.4 Global Search Frontend

| ID | Requirement | Prio | Fase |
|---|---|---|---|
| FR-FE-050 | Pencarian menggunakan fullscreen overlay dengan live search (debounce ± 300 ms). | M | F1 |
| FR-FE-051 | Hasil dikelompokkan: Projects, News & Articles, Careers; setiap hasil dapat diklik. | M | F1 |
| FR-FE-052 | Enter atau "See All Results" membuka halaman hasil lengkap dengan filter jenis konten. | M | F1 |

## 3.5 About Us

Pendekatan corporate profile, bukan storytelling editorial bebas.

| ID | Requirement | Prio | Fase |
|---|---|---|---|
| FR-FE-060 | Struktur section tetap: Company Overview, Company History, Vision & Mission, Company Values, Company Statistics, Our Team (opsional). | M | F1 |
| FR-FE-061 | Statistics dan Team Members dikelola dinamis (lihat FR-CM-050). | M | F1 |

## 3.6 News & Articles

| ID | Requirement | Prio | Fase |
|---|---|---|---|
| FR-FE-070 | Listing menggunakan Editorial Magazine Layout: satu artikel featured besar, sisanya grid editorial asimetris. | M | F1 |
| FR-FE-071 | Navigasi kategori horizontal dan infinite scroll. | M | F1 |
| FR-FE-072 | Hanya satu artikel featured pada satu waktu, dipilih manual. Memilih artikel lain otomatis mencabut status featured sebelumnya. | M | F1 |
| FR-FE-073 | Jika belum ada artikel featured, artikel terbaru ditampilkan sebagai fallback. | M | F1 |
| FR-FE-074 | Detail artikel menampilkan kategori, tanggal publikasi, judul, excerpt, cover, isi, inline images, dan Related Articles. | M | F1 |

**Rich Text Editor** mendukung: heading, bold/italic, alignment, ordered/unordered list, inline image dengan caption, external link, quote, dan table. (Lihat FR-CM-030.)

## 3.7 Careers (Publik)

Sistem rekrutmen sederhana yang terintegrasi dengan website, **bukan** HRIS atau ATS kompleks.

| ID | Requirement | Prio | Fase |
|---|---|---|---|
| FR-FE-080 | Job Listing menampilkan posisi, kategori, departemen, lokasi, employment type, deskripsi, kualifikasi, persyaratan dokumen, dan deadline. | M | F1 |
| FR-FE-081 | Filter berdasarkan kategori dan status lowongan. | S | F1 |
| FR-FE-082 | Job Detail menampilkan informasi lengkap dan formulir lamaran. | M | F1 |
| FR-FE-083 | Pelamar tidak perlu membuat akun; posisi terisi otomatis dari lowongan. | M | F1 |
| FR-FE-084 | Formulir mengikuti konfigurasi lowongan dan mendukung berbagai tipe input (lihat FR-RC-020). | M | F1 |
| FR-FE-085 | Setiap lamaran mendapat nomor referensi otomatis. | M | F1 |
| FR-FE-086 | Lowongan berstatus Closed atau melewati deadline tidak dapat menerima lamaran **[USULAN]**. | M | F1 |

## 3.8 Contact

Pendekatan **Email Only**.

| ID | Requirement | Prio | Fase |
|---|---|---|---|
| FR-FE-090 | Form berisi Full Name, Email, Phone, Subject (dropdown), dan Message. | M | F1 |
| FR-FE-091 | Pesan dikirim langsung ke email perusahaan melalui SMTP atau transactional email service, **tidak disimpan** di database. | M | F1 |
| FR-FE-092 | Validasi input dan perlindungan anti-spam (mekanisme dipilih pada fase desain, mis. honeypot/rate limit/CAPTCHA). | M | F1 |
| FR-FE-093 | Tidak ada Contact Inbox dan Quote Request Form pada tahap ini. | — | — |

---

# 4. Dashboard Administrator

## 4.1 Struktur dan Layout

Pendekatan hybrid: struktur admin tradisional dengan visual minimalis dan editorial (putih/off-white, tipografi hitam, garis pemisah tipis, minim card dan grafik dekoratif).

| ID | Requirement | Prio | Fase |
|---|---|---|---|
| FR-DB-001 | Layout terdiri dari Left Sidebar, Topbar, dan Main Content Area. | M | F1 |
| FR-DB-002 | Sidebar dapat expanded (icon + label) atau collapsed (icon saja dengan tooltip), status collapse dipertahankan, dan menjadi drawer pada mobile. | M | F1 |
| FR-DB-003 | Topbar berisi sidebar toggle, page title dan breadcrumb, Global Search, notification icon dengan unread badge, dan User Profile. Pada mobile, pencarian dibuka melalui icon. | M | F1 |
| FR-DB-004 | User Profile Menu: My Profile, Change Password, Logout. | M | F1 |

## 4.2 Navigasi Sidebar

| Grup | Menu |
|---|---|
| GENERAL | Dashboard |
| CONTENT MANAGEMENT | Projects, News & Articles, Categories, About Us |
| RECRUITMENT | Job Vacancies, Applicants, Form Builder |
| SYSTEM | Website Settings, Users, Activity Logs |

Tidak ada menu Media Library global (lihat Bagian 12).

## 4.3 Dashboard Overview

| ID | Requirement | Prio | Fase |
|---|---|---|---|
| FR-DB-010 | Empat metrik: Total Projects, Published Articles, Active Job Vacancies, Total Applications. | M | F1 |
| FR-DB-011 | Recent Activities: siapa, melakukan apa, objek yang terdampak, dan waktu. | S | F1 |
| FR-DB-012 | Recent Applications: lima pelamar terbaru (nama, posisi, tanggal, status); nama dapat diklik menuju detail lamaran. | S | F1 |

## 4.4 Global Search Dashboard

| ID | Requirement | Prio | Fase |
|---|---|---|---|
| FR-DB-020 | Pencarian terpusat untuk Projects, Articles, Categories, Jobs, Applicants, dan Users. | S | F1 |
| FR-DB-021 | Hasil dikelompokkan per jenis konten dan membuka halaman pengelolaan terkait. | S | F1 |

---

# 5. Content Management

## 5.1 Project Management

**Field:** Name, Slug, Category, Location, Year, Description, Cover Image, Gallery, Publication Status.

| ID | Requirement | Prio | Fase |
|---|---|---|---|
| FR-CM-001 | Administrator dapat Create, Edit, Delete, Preview, dan Publish proyek. | M | F1 |
| FR-CM-002 | Administrator dapat mengelola Cover, Gallery, dan urutan Gallery. | M | F1 |
| FR-CM-003 | Setiap proyek hanya memiliki satu kategori. | M | F1 |
| FR-CM-004 | Kategori yang sedang digunakan tidak dapat dihapus sebelum proyek dipindahkan ke kategori lain. | M | F1 |
| FR-CM-005 | Version History tersedia untuk proyek (lihat Bagian 11.3). | S | F2 |
| FR-CM-006 | Status proyek mengikuti Matriks Status (Bagian 11.2). | M | F1 |

## 5.2 Article Management

**Field:** Title, Slug, Category, Excerpt, Cover, Rich Content, Author, Status, Publish Date/Time, Featured Status.

| ID | Requirement | Prio | Fase |
|---|---|---|---|
| FR-CM-020 | Daftar artikel berbentuk Table View: Thumbnail, Title, Category, Author, Publish Date, Status, Featured Indicator, Action. | M | F1 |
| FR-CM-021 | Daftar mendukung Search, Filter, Sort, Pagination, dan Add Article. | M | F1 |
| FR-CM-022 | Create dan Edit menggunakan Dedicated Page. | M | F1 |
| FR-CM-030 | Editor rich text mendukung elemen yang tercantum di Bagian 3.6. | M | F1 |
| FR-CM-031 | Status artikel mengikuti Matriks Status (Bagian 11.2), termasuk Publish Now dan Scheduled Publishing. | M | F1 |

## 5.3 Category Management

| ID | Requirement | Prio | Fase |
|---|---|---|---|
| FR-CM-040 | Satu menu Categories dengan dua tab: Project Categories dan Article Categories. | M | F1 |
| FR-CM-041 | Setiap tab menyediakan Table View, Create, Edit, Delete, Reorder, dan Search. | M | F1 |
| FR-CM-042 | Kategori yang sedang digunakan tidak dapat dihapus. | M | F1 |

## 5.4 About Us Management

| ID | Requirement | Prio | Fase |
|---|---|---|---|
| FR-CM-050 | Section-based Editor; setiap section diedit terpisah. Section berulang (Statistics, Team Members) mendukung Add, Edit, Delete, Reorder. | M | F1 |
| FR-CM-051 | Tersedia preview sebelum publikasi. | S | F1 |
| FR-CM-052 | Tidak menggunakan free-form page builder. | — | — |

---

# 6. Recruitment Management

## 6.1 Job Vacancies

| ID | Requirement | Prio | Fase |
|---|---|---|---|
| FR-RC-001 | Dashboard Table View: Position, Category, Location, Employment Type, Applicant Count, Deadline, Status, Action. | M | F1 |
| FR-RC-002 | Applicant Count dapat diklik untuk membuka daftar pelamar yang telah difilter berdasarkan lowongan. | S | F1 |
| FR-RC-003 | Status lowongan: Draft, Published, Closed. | M | F1 |

## 6.2 Dynamic Recruitment Form Builder

| ID | Requirement | Prio | Fase |
|---|---|---|---|
| FR-RC-010 | Setiap kategori pekerjaan memiliki template formulir default. | M | F1 |
| FR-RC-011 | Saat lowongan dibuat: admin memilih kategori, sistem mengambil template, formulir diwariskan ke lowongan, dan admin dapat menyesuaikannya per lowongan. | M | F1 |
| FR-RC-012 | Form Builder menggunakan pendekatan visual drag-and-drop. | S | F2 |
| FR-RC-020 | Jenis field: Text, Email, Phone, Number, Textarea, Dropdown, Radio, Checkbox, Date, File Upload, Section, Repeater. | M | F1 |
| FR-RC-021 | Konfigurasi field: Label, Description, Placeholder, Required, Options, File restrictions, Conditional fields, Urutan. | M | F1 |
| FR-RC-022 | Conditional fields dapat ditunda ke F2 bila diperlukan **[USULAN]**. | C | F2 |
| FR-RC-023 | Struktur formulir disimpan sebagai snapshot pada setiap lamaran. | M | F1 |

> Catatan fase: pada F1 builder dapat berupa editor berbasis daftar (tambah/ubah/urut field); drag-and-drop penuh pada F2 **[USULAN]**.

## 6.3 Applicant Management

| ID | Requirement | Prio | Fase |
|---|---|---|---|
| FR-RC-030 | Table View: Applicant Name, Position, Email, Phone, Application Date, Status, Action. | M | F1 |
| FR-RC-031 | Filter: Job, Category, Status, Date. | M | F1 |
| FR-RC-032 | Kanban View dengan tahapan Submitted, Reviewing, Shortlisted, Interview, Accepted, Rejected. | S | F2 |
| FR-RC-033 | Perubahan status manual (drag and drop pada Kanban atau kontrol status pada tabel/detail) dengan Status Change History. | M | F1 |
| FR-RC-034 | Applicant Detail memiliki tab: Profile, Application Answers, Documents, Activity History. | M | F1 |
| FR-RC-035 | Status selalu terlihat di bagian atas detail; tersedia Internal Notes serta preview dan download dokumen. | M | F1 |
| FR-RC-036 | Data pelamar dan dokumen bersifat privat dan hanya dapat diakses administrator. | M | F1 |
| FR-RC-037 | Tidak ada: Applicant Account, Automated Interview Scheduling, Automated Interview Invitation. | — | — |

---

# 7. User Management dan Autentikasi

| ID | Requirement | Prio | Fase |
|---|---|---|---|
| FR-US-001 | Login administrator, Logout, Change Password, dan Profile Management. | M | F1 |
| FR-US-002 | Akun dibuat manual oleh administrator yang sudah ada; tidak ada self-registration dan tidak ada invitation email. | M | F1 |
| FR-US-003 | Setiap pengguna memiliki akun dan password sendiri; semua administrator berhak akses setara. | M | F1 |
| FR-US-004 | User Table: Name, Email, Status, Last Login, Created At, Action. | M | F1 |
| FR-US-005 | Aksi: Create User, Edit User, Reset Password, Deactivate User, Reactivate User. | M | F1 |
| FR-US-006 | Pengguna dapat mengelola nama, email, foto profil, password, serta melihat informasi akun dan tanggal bergabung. | M | F1 |
| FR-US-010 | Tidak boleh menonaktifkan administrator aktif terakhir (termasuk akun sendiri jika berakibat tidak ada admin aktif). | M | F1 |
| FR-US-011 | Perubahan akun tercatat di Activity Logs; password tidak pernah dicatat. | M | F1 |
| FR-US-020 | Kebijakan password minimum (panjang, kompleksitas) **[USULAN]**. | M | F1 |
| FR-US-021 | Pembatasan percobaan login (rate limit/lockout sementara) **[USULAN]**. | M | F1 |
| FR-US-022 | Mekanisme reset password: oleh administrator lain (sesuai FR-US-005); alur *forgot password* mandiri **[TBD]**. | S | F1 |

---

# 8. Website Settings

Satu halaman Settings dengan tab berikut. Nilainya dipakai frontend (navbar, footer, metadata).

| Tab | Field |
|---|---|
| General | Site Name, Logo, Favicon, Tagline |
| Company Information | Company Name, Description, Address |
| Contact | Email, Phone, Office Hours |
| SEO | Meta Title, Meta Description, Default SEO Image |
| Social Media | Instagram, LinkedIn, Facebook, platform lainnya |

| ID | Requirement | Prio | Fase |
|---|---|---|---|
| FR-ST-001 | Administrator dapat melihat dan mengubah seluruh field di atas. | M | F1 |
| FR-ST-002 | Perubahan Settings tercatat di Activity Logs. | M | F1 |

---

# 9. Activity Logs

| ID | Requirement | Prio | Fase |
|---|---|---|---|
| FR-AL-001 | Setiap entri mencatat User, Action, Affected Object, Timestamp, Before Value, dan After Value. | M | F1 |
| FR-AL-002 | Aktivitas yang dicatat: Create, Update, Delete, Publish, Job Status Change, Applicant Status Change, Website Settings Change, User Management, dan penghapusan media. | M | F1 |
| FR-AL-003 | Informasi sensitif (password) tidak boleh dicatat. | M | F1 |
| FR-AL-004 | Halaman Activity Logs dengan filter dan pencarian **[USULAN]**. | S | F1 |

---

# 10. Notification System

| ID | Requirement | Prio | Fase |
|---|---|---|---|
| FR-NT-001 | Notifikasi internal melalui icon bell di topbar dengan unread badge. | M | F1 |
| FR-NT-002 | Jenis: New Application, Job Deadline, Project Published, Article Published. | S | F1 |
| FR-NT-003 | Dikirim kepada seluruh administrator aktif; riwayat tetap tersedia setelah logout. | S | F1 |
| FR-NT-004 | Mendukung mark as read; klik notifikasi membuka halaman terkait. | S | F1 |
| FR-NT-005 | Real-time tanpa refresh halaman. | S | F2 |
| FR-NT-006 | Notifikasi Job Deadline dipicu scheduler otomatis (misalnya H-3 dan hari-H, **[TBD]**). | S | F1 |

> Catatan: "Publishing Project/Article" pada v1.0 ditafsirkan sebagai notifikasi **saat konten berhasil dipublikasikan** (termasuk oleh jadwal). Perlu dikonfirmasi.

---

# 11. Content Workflow

## 11.1 Auto-save

| ID | Requirement | Prio | Fase |
|---|---|---|---|
| FR-WF-001 | Semua perubahan konten otomatis tersimpan sebagai draft ± 1–2 detik setelah pengguna berhenti mengetik. | M | F1 |
| FR-WF-002 | Upload disimpan setelah berhasil. | M | F1 |
| FR-WF-003 | Status ditampilkan: Saving, Saved, Failed, dengan opsi retry saat gagal. | M | F1 |
| FR-WF-004 | Request penyimpanan lama tidak boleh menimpa perubahan yang lebih baru. | M | F1 |

## 11.2 Manual Publish dan Matriks Status

Konten published tidak langsung berubah ketika diedit. Alur:

1. Konten published dibuka untuk diedit.
2. Perubahan disimpan sebagai draft.
3. Versi published lama tetap tampil di frontend.
4. Administrator melakukan preview.
5. Administrator mempublikasikan secara manual.
6. Versi baru menggantikan versi published sebelumnya.

**Matriks status konten** (menggantikan perbedaan daftar status pada v1.0):

| Status | Projects | Articles | About Us | Job Vacancies |
|---|---|---|---|---|
| Draft | ✔ | ✔ | — | ✔ |
| Scheduled | [TBD] | ✔ | — | [TBD] |
| Published | ✔ | ✔ | ✔ | ✔ |
| Unpublished Changes | ✔ | ✔ | ✔ | [TBD] |
| Archived | ✔ | [TBD] | — | — |
| Closed | — | — | — | ✔ |

| ID | Requirement | Prio | Fase |
|---|---|---|---|
| FR-WF-010 | Alur manual publish sesuai langkah di atas. | M | F1 |
| FR-WF-011 | Status konten mengikuti matriks di atas; sel [TBD] ditentukan di Bagian 15. | M | F1 |
| FR-WF-012 | Scheduled Publishing dieksekusi otomatis oleh sistem pada waktu terjadwal. | M | F1 |

## 11.3 Version History

| ID | Requirement | Prio | Fase |
|---|---|---|---|
| FR-WF-020 | Setiap publikasi menghasilkan versi baru berisi konten, waktu publikasi, administrator yang mempublikasikan, serta perubahan sebelum/sesudah. | S | F2 |
| FR-WF-021 | Fitur: View Version History, Compare Changes, Restore Version. | S | F2 |
| FR-WF-022 | Restore Version menghasilkan draft baru dan tidak langsung mengubah konten publik. | S | F2 |
| FR-WF-023 | Cakupan: Projects, Articles, About Us. Lowongan **[TBD]**. | S | F2 |

## 11.4 Edit Lock

| ID | Requirement | Prio | Fase |
|---|---|---|---|
| FR-WF-030 | Satu konten hanya dapat diedit satu administrator pada satu waktu; yang lain tetap dapat melihat. | S | F2 |
| FR-WF-031 | Nama administrator yang sedang mengedit ditampilkan. | S | F2 |
| FR-WF-032 | Lock dilepas saat administrator keluar dari editor. | S | F2 |
| FR-WF-033 | Heartbeat memperpanjang lock; jika berhenti, lock kedaluwarsa ± 2 menit setelah heartbeat terakhir. | S | F2 |

> Sebelum Edit Lock tersedia (F1), perlu mekanisme pengaman minimal agar dua administrator tidak saling menimpa (misalnya pengecekan versi saat simpan) **[USULAN]**.

---

# 12. Image Management System

## 12.1 Konsep Umum

- Pengelolaan terpusat, tetapi antarmuka berada di modul masing-masing: **Project Media Library** dan **Article Media Library**.
- Tidak ada menu Media Library global.
- Administrator dapat memakai gambar dari konten lain melalui **Select from Existing Media**.

## 12.2 Upload Management

| ID | Requirement | Prio | Fase |
|---|---|---|---|
| FR-IM-001 | Maksimal 50 MB per gambar; format JPG, JPEG, PNG, WebP; maksimal 20 gambar per batch. | M | F1 |
| FR-IM-002 | Upload paralel ± 3–4 file; image processing berjalan bertahap. | M | F1 |
| FR-IM-003 | Status per gambar: Waiting, Uploading, Processing, Completed, Failed. | M | F1 |
| FR-IM-004 | Progress per gambar, retry, hapus dari antrean sebelum diproses, upload batch berikutnya, dan informasi kegagalan. | M | F1 |
| FR-IM-005 | Isolated failure: kegagalan satu gambar tidak membatalkan batch; gambar gagal dapat di-retry individual. | M | F1 |

## 12.3 Temporary Upload dan Commit

| ID | Requirement | Prio | Fase |
|---|---|---|---|
| FR-IM-010 | Upload masuk ke temporary storage, lalu: validasi → image processing → pemilihan → commit sesuai penggunaan → pembersihan file sementara. | M | F1 |
| FR-IM-011 | File temporary yang terbengkalai dibersihkan otomatis (masa retensi **[TBD]**). | M | F1 |
| FR-IM-012 | Gambar yang masih dipakai versi published atau Version History tidak boleh terhapus. | M | F1 |

## 12.4 Varian dan Kualitas Gambar (Parameter Awal)

| Parameter | Nilai awal |
|---|---|
| Lebar varian | 400, 800, 1280, 1920, 2560, 3840 px |
| Format | WebP |
| Quality | 88 (dievaluasi dengan foto proyek asli sebelum produksi) |
| Aturan | Aspect ratio dipertahankan; tanpa upscaling; varian lebih besar dari sumber tidak dibuat |
| Pengolahan | EXIF dihapus setelah orientasi diterapkan; color profile dinormalisasi ke sRGB; sharpening ringan dan selektif |

| ID | Requirement | Prio | Fase |
|---|---|---|---|
| FR-IM-020 | Sistem menghasilkan varian sesuai parameter di atas. | M | F1 |
| FR-IM-021 | Frontend menggunakan responsive image selection. | M | F1 |

> Risiko kapasitas: pemrosesan banyak varian resolusi tinggi pada RAM 2 GB perlu diuji (lihat NFR-CAP-001).

## 12.5 Cover Image

| ID | Requirement | Prio | Fase |
|---|---|---|---|
| FR-IM-030 | Cover memiliki varian rasio: Desktop 16:9, Mobile 4:5, Tablet menyesuaikan layout. Foto asli tetap berasio asli. | M | F1 |
| FR-IM-031 | Manual Focal Point dan preview crop; posisi crop dapat diatur administrator. | S | F1 |
| FR-IM-032 | Focal point desktop dan mobile dapat dikonfigurasi terpisah. | S | F2 |

## 12.6 Metadata

| ID | Requirement | Prio | Fase |
|---|---|---|---|
| FR-IM-040 | Metadata: Alt Text, Caption, Original File Name. | M | F1 |
| FR-IM-041 | Sistem memberi rekomendasi alt text otomatis; admin dapat mengedit; rekomendasi tidak otomatis dianggap final. | C | F2 |
| FR-IM-042 | Caption dan alt text disimpan per penggunaan sehingga dapat berbeda di tiap konten. | M | F1 |

## 12.7 Deduplikasi

| ID | Requirement | Prio | Fase |
|---|---|---|---|
| FR-IM-050 | Checksum SHA-256 mendeteksi file identik; file fisik tidak disimpan ganda. | S | F2 |
| FR-IM-051 | Setiap penggunaan tetap memiliki metadata dan relasi konten masing-masing. | S | F2 |

## 12.8 Select from Existing Media

| ID | Requirement | Prio | Fase |
|---|---|---|---|
| FR-IM-060 | Gallery Picker: grid thumbnail, search nama file/proyek, filter Projects dan News & Articles, informasi ukuran dan dimensi, preview, indikator jumlah penggunaan. | S | F2 |
| FR-IM-061 | Multi-select tanpa batas khusus; admin dapat memilih gambar lintas proyek dan artikel tanpa upload ulang. | S | F2 |

## 12.9 Media Deletion dan Akses

| ID | Requirement | Prio | Fase |
|---|---|---|---|
| FR-IM-070 | Soft Delete + Garbage Collection: menghapus dari konten hanya menghapus relasi penggunaan. | M | F1 |
| FR-IM-071 | File fisik tetap ada selama masih direferensikan konten lain atau versi lama. | M | F1 |
| FR-IM-072 | File tanpa referensi masuk antrean penghapusan dengan retensi 7 hari; bila dipakai kembali, antrean dibatalkan; penghapusan permanen otomatis. | M | F1 |
| FR-IM-073 | Semua aktivitas penghapusan tercatat di Activity Logs. | M | F1 |
| FR-IM-074 | Semua administrator dapat memakai media milik administrator lain dan menghapus relasi penggunaan pada konten yang dikelola, tetapi tidak dapat menghapus file fisik yang masih dipakai konten lain. | M | F1 |

---

# 13. Kebutuhan Non-Fungsional

Target pada bagian ini berstatus **[USULAN]** kecuali disebut lain, dan perlu disepakati klien.

## 13.1 Performance

| ID | Requirement | Prio |
|---|---|---|
| NFR-PRF-001 | Lazy loading gambar, responsive image delivery, skeleton loading, dan explicit image dimensions untuk menghindari layout shift. | M |
| NFR-PRF-002 | Target Core Web Vitals pada halaman publik (koneksi seluler): LCP ≤ 2,5 detik, CLS ≤ 0,1, INP ≤ 200 ms. | S |
| NFR-PRF-003 | Respons API listing (tanpa gambar) p95 ≤ 500 ms pada beban normal. | S |
| NFR-PRF-004 | Pencarian live menampilkan hasil ≤ 1 detik pada p95. | S |

## 13.2 Responsive Design dan Kompatibilitas

| ID | Requirement | Prio |
|---|---|---|
| NFR-RSP-001 | Mendukung desktop, tablet, dan mobile (frontend publik dan dashboard). | M |
| NFR-RSP-002 | Mendukung dua versi terbaru Chrome, Edge, Firefox, dan Safari (desktop dan mobile). | M |

## 13.3 Security dan Privasi

| ID | Requirement | Prio |
|---|---|---|
| NFR-SEC-001 | Autentikasi administrator; seluruh halaman dashboard dan API admin memerlukan sesi valid. | M |
| NFR-SEC-002 | Validasi input sisi server pada semua formulir. | M |
| NFR-SEC-003 | Validasi MIME berdasarkan isi file; pembatasan ukuran, dimensi, dan jumlah pixel gambar (batas **[TBD]**). | M |
| NFR-SEC-004 | Perlindungan file lamaran: tidak dapat diakses publik, akses hanya melalui sesi administrator. | M |
| NFR-SEC-005 | Proteksi anti-spam formulir kontak dan formulir lamaran. | M |
| NFR-SEC-006 | Pembatasan akses draft dan konten privat. | M |
| NFR-SEC-007 | Seluruh trafik menggunakan HTTPS; password disimpan sebagai hash. | M |
| NFR-SEC-008 | Kebijakan retensi dan penghapusan data pelamar sesuai UU Pelindungan Data Pribadi (durasi **[TBD]**); formulir lamaran menyertakan persetujuan pemrosesan data. | S |

## 13.4 Reliability dan Backup

| ID | Requirement | Prio |
|---|---|---|
| NFR-REL-001 | Auto-save, upload queue, isolated failure, dan validasi referensi media sebelum penghapusan permanen. | M |
| NFR-REL-002 | Edit Lock dan Version History (F2) sebagai pengaman konflik dan pemulihan. | S |
| NFR-REL-003 | Backup berkala database dan file ke lokasi di luar server aplikasi; frekuensi dan retensi **[TBD]**. Backup ke Cloud Object Storage ditunda (lihat Bagian 14), tetapi backup lokal minimal tetap diperlukan. | S |

## 13.5 Aksesibilitas dan SEO

| ID | Requirement | Prio |
|---|---|---|
| NFR-ACC-001 | Frontend publik mengacu pada WCAG 2.1 level AA: kontras, navigasi keyboard, fokus terlihat, alt text. | S |
| NFR-SEO-001 | Halaman publik dapat diindeks (URL bersih, metadata dari Settings, sitemap.xml, robots.txt). Pengaturan SEO per halaman/konten **[TBD]**. | S |

## 13.6 Kapasitas

| ID | Requirement | Prio |
|---|---|---|
| NFR-CAP-001 | Pemrosesan gambar harus berjalan stabil pada 2 vCPU / 2 GB RAM dengan batas konkurensi yang dapat dikonfigurasi; perlu uji beban dengan batch 20 foto 50 MB sebelum produksi. | M |
| NFR-CAP-002 | Penyimpanan terstruktur agar dapat dimigrasi ke object storage tanpa perubahan model data. | S |

---

# 14. Batasan dan Scope

## 14.1 Kondisi Infrastruktur

- Kapasitas awal: 2 vCPU, RAM 2 GB, storage 1 TB, trafik rendah.
- Server belum disediakan; implementasi masih tahap perencanaan.

## 14.2 Di Luar Scope Saat Ini

- Contact Inbox.
- Quote Request Form.
- Applicant Account.
- HRIS atau ATS kompleks.
- Automated Interview Scheduling dan Invitation.
- Free-form Page Builder.
- Global Media Library Dashboard.
- Backup eksternal ke Cloud Object Storage pada tahap awal.

## 14.3 Pengembangan yang Ditunda

Backup eksternal menggunakan Cloud Object Storage menjadi rencana jangka panjang. Struktur storage tidak boleh menghambat migrasi ke object storage di masa depan. Detail arsitektur teknis, database, deployment, dan konfigurasi server dibahas pada fase berikutnya.

---

# 15. Keputusan yang Masih Perlu Dikonfirmasi

## 15.1 Dari Diskusi Awal (v1.0)

| # | Keputusan | Terkait |
|---|---|---|
| 1 | Pola URL kategori proyek dan artikel. | FR-FE-012, NFR-SEO-001 |
| 2 | Detail perilaku filter dan sorting pada halaman listing. | FR-FE-070, FR-FE-081 |
| 3 | Batas jumlah dan dimensi pixel maksimum gambar. | NFR-SEC-003 |
| 4 | Masa retensi file temporary yang tidak pernah di-commit. | FR-IM-011 |
| 5 | Detail pengelolaan konten Archived dan Unpublished Changes. | Matriks Status |
| 6 | Detail pengaturan SEO per halaman dan per konten. | NFR-SEO-001 |
| 7 | Mekanisme email notifikasi untuk pelamar. | FR-RC |
| 8 | Detail validasi dan batas ukuran dokumen lamaran. | FR-RC-020 |

## 15.2 Ditemukan pada Proses Perapian (v1.1)

| # | Keputusan | Terkait |
|---|---|---|
| 9 | Apakah Scheduled Publishing berlaku untuk proyek dan lowongan, atau hanya artikel? | Matriks Status |
| 10 | Apakah artikel dapat di-Archive? Apakah lowongan memiliki Unpublished Changes? | Matriks Status |
| 11 | Cakupan Version History dan Edit Lock (proyek, artikel, About Us, lowongan?). | FR-WF-023 |
| 12 | Arti notifikasi "Publishing" (saat publish, saat dijadwalkan, atau keduanya) dan jadwal pengingat Job Deadline. | FR-NT-002, FR-NT-006 |
| 13 | Apakah diperlukan alur *forgot password* mandiri? | FR-US-022 |
| 14 | Apakah website satu bahasa atau multi-bahasa? | Bagian 1.5 |
| 15 | Kebijakan retensi data pelamar dan frekuensi/retensi backup. | NFR-SEC-008, NFR-REL-003 |
| 16 | Persetujuan klien atas pembagian fase F1/F2 dan prioritas MoSCoW. | Bagian 2 |
| 17 | Persetujuan atas target non-fungsional (Core Web Vitals, WCAG, browser). | Bagian 13 |

---

# Lampiran A. Perubahan dari v1.0 ke v1.1

- Seluruh requirement diberi ID, prioritas, dan fase.
- Daftar status konten disatukan dalam Matriks Status; status *Scheduled* kini konsisten antara Bagian 5 dan Bagian 11.
- Notifikasi "Publishing Project/Article" dijelaskan ulang sebagai Project Published dan Article Published.
- Detail implementasi (SHA-256, WebP, concurrency, heartbeat) dipertahankan sebagai parameter awal dan akan dipindahkan ke dokumen desain teknis.
- Ditambahkan: aktor, glosarium, asumsi, perluasan non-functional, kebijakan password dan rate limit, backup, aksesibilitas, privasi data pelamar, dan keputusan terbuka baru (#9–#17).
- Seluruh isi v1.0 dipertahankan; file asli tersimpan sebagai `SOFTWARE REQUIREMENTS SPECIFICATION.v1.0.backup.md`.
