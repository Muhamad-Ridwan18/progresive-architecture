# SOFTWARE REQUIREMENTS SPECIFICATION (SRS)
## Website Company Profile & Portfolio

**Versi:** 1.0  
**Status:** Draft — Hasil Diskusi Kebutuhan  
**Jenis Sistem:** Website Company Profile, Portfolio & Recruitment Management

---

# 1. Gambaran Umum

## 1.1 Tujuan Sistem

Membangun website company profile untuk perusahaan kontraktor yang berfungsi sebagai:

1. Media informasi dan profil perusahaan.
2. Media publikasi hasil proyek konstruksi.
3. Media publikasi berita dan artikel perusahaan.
4. Media informasi lowongan pekerjaan dan penerimaan lamaran.
5. Media komunikasi antara perusahaan dan pengunjung website.
6. Sistem pengelolaan konten terpusat melalui dashboard administrator.

Website mengadopsi pendekatan desain editorial yang minimalis, modern, dan berorientasi pada fotografi, dengan referensi visual utama BIG.dk.

## 1.2 Target Pengguna

- Pengunjung website: calon klien, mitra bisnis, dan masyarakat umum.
- Pelamar kerja: individu yang ingin melamar pekerjaan.
- Administrator: staf internal perusahaan yang mengelola website.

## 1.3 Prinsip Utama

- Desain minimalis dan editorial.
- Portfolio menjadi fokus utama website.
- Pengelolaan konten dinamis.
- Tidak menggunakan page builder yang terlalu kompleks.
- Pengelolaan gambar terpusat dengan antarmuka sesuai modul.
- Semua administrator memiliki hak akses yang setara.
- Sistem mudah dikembangkan pada tahap berikutnya.

# 2. Kebutuhan Website Frontend

## 2.1 Desain dan Navigasi

### Referensi visual
- BIG.dk sebagai referensi utama.
- Layout editorial dan asimetris.
- Dominasi warna putih, off-white, hitam, dan abu-abu.
- Tipografi sans-serif.
- Ruang kosong yang cukup.
- Fotografi proyek menjadi elemen visual utama.

### Navigasi utama

Navbar terdiri dari:
- Logo perusahaan.
- Global Search.
- Hamburger Menu.

Navbar bersifat sticky.

Hamburger menu menampilkan fullscreen navigation dengan menu:
- Projects
- About Us
- News & Articles
- Careers
- Contact

### Footer

Footer minimalis terdiri dari:
- Nama dan tagline perusahaan.
- Explore Links.
- Informasi kontak.
- Alamat perusahaan.
- Social Media.
- Copyright.

## 2.2 Homepage / Projects Listing

Homepage langsung menampilkan daftar proyek tanpa hero section konvensional.

### Kebutuhan tampilan
- Grid editorial asimetris.
- Ukuran gambar bervariasi.
- Tidak menggunakan masonry acak.
- Judul proyek ditampilkan secara minimal.
- Tidak menggunakan elemen dekoratif berlebihan.

### Navigasi kategori
- Kategori ditampilkan dalam bar horizontal.
- Kategori bersifat dinamis.
- Tidak menampilkan kategori All Projects.
- Hanya kategori yang memiliki proyek published.
- Urutan kategori dapat diatur administrator.
- Horizontal scroll dengan dukungan swipe mobile.
- Navigasi kategori bersifat sticky di bawah navbar.
- Pergantian kategori tidak memerlukan reload halaman.

### Infinite Scroll
- Memuat 8 proyek pertama.
- Memuat 8 proyek berikutnya ketika pengguna mendekati bagian bawah halaman.
- Menggunakan lazy loading gambar.
- Menampilkan skeleton loading.
- Menghindari duplikasi request.
- Menghentikan pemuatan ketika semua data telah ditampilkan.
- Mempertahankan posisi scroll ketika pengguna kembali dari detail proyek.
- Menyediakan fallback pagination jika JavaScript tidak tersedia.

## 2.3 Project Detail

Setiap proyek memiliki halaman detail yang berisi:

- Nama proyek.
- Kategori.
- Lokasi.
- Tahun.
- Deskripsi.
- Cover image.
- Galeri foto.

### Galeri
- Layout editorial asimetris.
- Pola layout ditentukan sistem berdasarkan urutan foto.
- Administrator tidak perlu mengatur layout setiap foto satu per satu.
- Responsif terhadap desktop, tablet, dan mobile.

### Lightbox
Ketika foto diklik:
- Foto ditampilkan fullscreen.
- Background gelap.
- Navigasi foto sebelumnya dan berikutnya.
- Tombol close.
- Navigasi keyboard menggunakan tombol panah dan Escape.
- Swipe pada perangkat mobile.
- Indikator posisi, misalnya 03/24.
- Klik area luar foto untuk menutup.
- Posisi scroll halaman dipertahankan.
- Resolusi tinggi dimuat ketika foto dibuka.

### Navigasi proyek
- Next Project.
- Previous Project.

## 2.4 Global Search Frontend

Global Search menggunakan fullscreen overlay.

### Fitur
- Live search.
- Debounce sekitar 300 ms.
- Hasil dikelompokkan berdasarkan:
  - Projects
  - News & Articles
  - Careers
- Setiap hasil dapat diklik.
- Enter atau See All Results membuka halaman hasil pencarian lengkap.

Halaman pencarian lengkap menyediakan filter berdasarkan jenis konten.

## 2.5 About Us

Halaman About Us menggunakan pendekatan corporate profile, bukan storytelling editorial bebas.

### Section
1. Company Overview.
2. Company History.
3. Vision & Mission.
4. Company Values.
5. Company Statistics.
6. Our Team (opsional).

### Pengelolaan
- Struktur section bersifat tetap.
- Setiap section dapat diedit secara terpisah.
- Statistics dapat ditambah, diedit, dihapus, dan diurutkan.
- Team members dapat ditambah, diedit, dihapus, dan diurutkan.
- Tidak menggunakan free-form page builder.
- Tersedia preview sebelum publikasi.

## 2.6 News & Articles

### Halaman listing
Menggunakan Editorial Magazine Layout:
- Satu artikel featured berukuran besar.
- Artikel lainnya menggunakan grid editorial asimetris.
- Navigasi kategori horizontal.
- Infinite scroll.
- Desain terinspirasi BIG.dk.

### Featured Article
- Hanya satu artikel featured pada satu waktu.
- Pemilihan dilakukan manual.
- Ketika artikel lain dipilih, artikel featured sebelumnya otomatis tidak menjadi featured.
- Jika belum ada artikel featured, sistem menampilkan artikel terbaru sebagai fallback.

### Detail artikel
Menampilkan:
- Kategori.
- Tanggal publikasi.
- Judul.
- Excerpt.
- Cover.
- Isi artikel.
- Inline images.
- Related Articles.

### Rich Text Content
Editor mendukung:
- Heading.
- Bold dan italic.
- Alignment.
- Ordered dan unordered list.
- Inline image dan caption.
- External link.
- Quote.
- Table.

### Publishing
- Draft.
- Publish Now.
- Scheduled Publishing.
- Published.

## 2.7 Careers

Careers merupakan sistem rekrutmen sederhana yang terintegrasi dengan website, bukan HRIS atau ATS kompleks.

### Job Listing
Menampilkan:
- Posisi.
- Kategori.
- Departemen.
- Lokasi.
- Employment Type.
- Deskripsi pekerjaan.
- Kualifikasi.
- Persyaratan dokumen.
- Deadline.

Tersedia filter kategori dan status lowongan.

### Job Detail
Menampilkan informasi lengkap lowongan serta formulir lamaran yang dinamis.

### Application Form
- Pelamar tidak perlu membuat akun.
- Posisi otomatis terisi berdasarkan lowongan.
- Formulir mengikuti konfigurasi masing-masing lowongan.
- Mendukung berbagai tipe input.
- Referensi nomor lamaran dibuat otomatis.

### Dynamic Recruitment Form Builder

Setiap kategori pekerjaan memiliki template formulir default.

Ketika lowongan dibuat:
1. Administrator memilih kategori.
2. Sistem mengambil template formulir kategori.
3. Formulir diwariskan ke lowongan.
4. Administrator dapat menyesuaikan formulir untuk lowongan tersebut.

Form Builder menggunakan pendekatan visual drag-and-drop.

Jenis field:
- Text.
- Email.
- Phone.
- Number.
- Textarea.
- Dropdown.
- Radio.
- Checkbox.
- Date.
- File Upload.
- Section.
- Repeater.

Konfigurasi field:
- Label.
- Description.
- Placeholder.
- Required.
- Options.
- File restrictions.
- Conditional fields.
- Urutan field.

Struktur formulir disimpan sebagai snapshot pada setiap lamaran agar jawaban lama tetap dapat dibaca walaupun template berubah.

## 2.8 Contact

Contact menggunakan pendekatan Email Only.

Form terdiri dari:
- Full Name.
- Email.
- Phone.
- Subject Dropdown.
- Message.

Ketentuan:
- Pesan dikirim langsung ke email perusahaan.
- Tidak disimpan sebagai data pesan di database.
- Menggunakan SMTP atau transactional email service.
- Memiliki validasi input dan perlindungan anti-spam.

Tidak ada Contact Inbox dan Quote Request Form pada tahap ini.

# 3. Kebutuhan Dashboard Administrator

## 3.1 Struktur Dashboard

Dashboard menggunakan pendekatan hybrid:
- Struktur admin tradisional.
- Visual minimalis dan editorial.
- Dominasi warna putih dan off-white.
- Tipografi hitam.
- Garis pemisah tipis.
- Tidak menggunakan terlalu banyak card atau grafik dekoratif.

### Layout
- Left Sidebar.
- Topbar.
- Main Content Area.

### Sidebar
Sidebar dapat:
- Expanded: icon dan label.
- Collapsed: icon saja.
- Menampilkan tooltip ketika collapsed.
- Mempertahankan status collapse.
- Berubah menjadi drawer pada mobile.

### Topbar
Berisi:
- Sidebar toggle.
- Page title dan breadcrumb.
- Global Search.
- Notification icon dan unread badge.
- User Profile.

Pada mobile, pencarian dapat dibuka melalui icon.

### User Profile Menu
- My Profile.
- Change Password.
- Logout.

## 3.2 Navigasi Sidebar

### GENERAL
- Dashboard.

### CONTENT MANAGEMENT
- Projects.
- News & Articles.
- Categories.
- About Us.

### RECRUITMENT
- Job Vacancies.
- Applicants.
- Form Builder.

### SYSTEM
- Website Settings.
- Users.
- Activity Logs.

Tidak ada menu Media Library global.

## 3.3 Dashboard Overview

Menampilkan empat metrik utama:
- Total Projects.
- Published Articles.
- Active Job Vacancies.
- Total Applications.

### Recent Activities
Menampilkan:
- Siapa yang melakukan aktivitas.
- Apa yang dilakukan.
- Objek yang terdampak.
- Waktu aktivitas.

### Recent Applications
Menampilkan lima pelamar terbaru:
- Nama pelamar.
- Posisi yang dilamar.
- Tanggal lamaran.
- Status.

Nama pelamar dapat diklik untuk membuka detail aplikasi.

## 3.4 Global Search Dashboard

Pencarian terpusat untuk:
- Projects.
- Articles.
- Categories.
- Jobs.
- Applicants.
- Users.

Hasil pencarian dikelompokkan berdasarkan jenis konten dan dapat membuka halaman pengelolaan terkait.

# 4. Content Management

## 4.1 Project Management

### Project Fields
- Name.
- Slug.
- Category.
- Location.
- Year.
- Description.
- Cover Image.
- Gallery.
- Publication Status.

Status:
- Draft.
- Published.
- Unpublished Changes.
- Archived.

### Pengelolaan
- Create Project.
- Edit Project.
- Delete Project.
- Preview Project.
- Publish Project.
- Manage Cover.
- Manage Gallery.
- Reorder Gallery.
- Version History.

Setiap proyek hanya memiliki satu kategori.

Kategori yang sedang digunakan tidak dapat dihapus sebelum proyek dipindahkan ke kategori lain.

## 4.2 Article Management

### Article Fields
- Title.
- Slug.
- Category.
- Excerpt.
- Cover.
- Rich Content.
- Author.
- Status.
- Publish Date/Time.
- Featured Status.

### Dashboard Article List
Menggunakan Table View.

Kolom:
- Thumbnail.
- Title.
- Category.
- Author.
- Publish Date.
- Status.
- Featured Indicator.
- Action.

Fitur:
- Search.
- Filter.
- Sort.
- Pagination.
- Add Article.

Create dan Edit menggunakan Dedicated Page.

## 4.3 Category Management

Satu menu Categories memiliki dua tab:

1. Project Categories.
2. Article Categories.

Setiap tab memiliki:
- Table View.
- Create.
- Edit.
- Delete.
- Reorder.
- Search.

Kategori yang sedang digunakan tidak dapat dihapus.

## 4.4 About Us Management

Menggunakan Section-based Editor.

Administrator dapat mengedit masing-masing section secara terpisah.

Section yang memiliki item berulang mendukung:
- Add.
- Edit.
- Delete.
- Reorder.

Tersedia preview sebelum publikasi.

# 5. Recruitment Management

## 5.1 Job Vacancies

Dashboard menggunakan Table View.

Kolom:
- Position.
- Category.
- Location.
- Employment Type.
- Applicant Count.
- Deadline.
- Status.
- Action.

Applicant Count dapat diklik untuk membuka daftar pelamar yang sudah difilter berdasarkan lowongan.

Status lowongan:
- Draft.
- Published.
- Closed.

## 5.2 Applicant Management

Menggunakan dua tampilan:

### Table View
Kolom:
- Applicant Name.
- Position.
- Email.
- Phone.
- Application Date.
- Status.
- Action.

Filter:
- Job.
- Category.
- Status.
- Date.

### Kanban View

Tahapan:
1. Submitted.
2. Reviewing.
3. Shortlisted.
4. Interview.
5. Accepted.
6. Rejected.

Fitur:
- Drag and drop.
- Perubahan status manual.
- Status change history.

### Applicant Detail

Menggunakan tab:

1. Profile.
2. Application Answers.
3. Documents.
4. Activity History.

Fitur tambahan:
- Status tetap terlihat di bagian atas.
- Internal Notes.
- Preview dan download dokumen.

Data pelamar dan dokumen bersifat privat serta hanya dapat diakses administrator.

Tidak ada:
- Applicant account.
- Automated interview scheduling.
- Automated interview invitation.

# 6. User Management & Authentication

## 6.1 Authentication

- Login administrator.
- Logout.
- Change Password.
- Profile Management.

## 6.2 User Management

- Akun dibuat secara manual oleh administrator yang sudah ada.
- Tidak ada public self-registration.
- Tidak ada invitation email.
- Semua administrator memiliki hak akses setara.
- Setiap pengguna memiliki akun dan password sendiri.

### User Table
Kolom:
- Name.
- Email.
- Status.
- Last Login.
- Created At.
- Action.

### User Actions
- Create User.
- Edit User.
- Reset Password.
- Deactivate User.
- Reactivate User.

### User Profile
Setiap pengguna dapat mengelola:
- Nama.
- Email.
- Foto profil.
- Password.

Dapat melihat informasi akun dan tanggal bergabung.

### Security Rules
- Tidak boleh menonaktifkan akun sendiri jika menyebabkan tidak ada administrator aktif.
- Tidak boleh menonaktifkan administrator aktif terakhir.
- Perubahan akun tercatat dalam Activity Logs.
- Password tidak boleh masuk ke Activity Logs.

# 7. Website Settings

Menggunakan satu halaman Settings dengan tab:

### General
- Site Name.
- Logo.
- Favicon.
- Tagline.

### Company Information
- Company Name.
- Description.
- Address.

### Contact
- Email.
- Phone.
- Office Hours.

### SEO
- Meta Title.
- Meta Description.
- Default SEO Image.

### Social Media
- Instagram.
- LinkedIn.
- Facebook.
- Platform lainnya.

Informasi ini digunakan oleh frontend, terutama navbar, footer, dan metadata halaman.

# 8. Activity Logs

Sistem menyediakan pencatatan aktivitas secara terperinci.

Informasi yang dicatat:
- User.
- Action.
- Affected Object.
- Timestamp.
- Before Value.
- After Value.

Aktivitas yang dicatat meliputi:
- Create.
- Update.
- Delete.
- Publish.
- Job Status Change.
- Applicant Status Change.
- Website Settings Change.
- User Management.

Informasi sensitif seperti password tidak boleh dicatat.

# 9. Notification System

Sistem memiliki notifikasi internal melalui icon bell di topbar.

### Jenis notifikasi
- New Application.
- Job Deadline.
- Publishing Project.
- Publishing Article.

### Mekanisme
- In-app notification.
- Real-time notification tanpa refresh halaman.
- Notifikasi dikirim kepada seluruh administrator aktif.
- Riwayat notifikasi tetap tersedia setelah logout.
- Mendukung mark as read.
- Klik notifikasi membuka halaman terkait.

# 10. Content Workflow

## 10.1 Auto-save

Semua perubahan konten otomatis disimpan sebagai draft.

Ketentuan:
- Auto-save sekitar 1–2 detik setelah pengguna berhenti mengetik.
- Upload disimpan setelah berhasil.
- Menampilkan status Saving, Saved, dan Failed.
- Mendukung retry ketika penyimpanan gagal.
- Request penyimpanan lama tidak boleh menimpa perubahan yang lebih baru.

## 10.2 Manual Publish

Konten published tidak langsung berubah ketika diedit.

Alur:
1. Konten published dibuka untuk diedit.
2. Perubahan disimpan sebagai draft.
3. Versi published lama tetap ditampilkan di frontend.
4. Administrator melakukan preview.
5. Administrator melakukan publish secara manual.
6. Versi baru menggantikan versi published sebelumnya.

Status yang direncanakan:
- Draft.
- Published.
- Unpublished Changes.
- Scheduled.
- Archived.

## 10.3 Version History

Setiap publikasi menghasilkan versi baru.

Informasi versi:
- Konten.
- Waktu publikasi.
- Administrator yang melakukan publikasi.
- Perubahan sebelum dan sesudah publikasi.

Fitur:
- View Version History.
- Compare Changes.
- Restore Version.

Restore Version menghasilkan draft baru dan tidak langsung mengubah konten publik.

## 10.4 Edit Lock

Konten hanya dapat diedit oleh satu administrator pada satu waktu.

Ketentuan:
- Administrator lain tetap dapat melihat konten.
- Administrator lain tidak dapat mengedit selama lock aktif.
- Nama administrator yang sedang mengedit ditampilkan.
- Lock dilepas ketika administrator keluar dari editor.
- Menggunakan heartbeat untuk memperpanjang lock.
- Jika heartbeat berhenti, lock kedaluwarsa sekitar 2 menit setelah heartbeat terakhir.

# 11. Image Management System

## 11.1 Konsep Umum

Sistem gambar menggunakan pengelolaan terpusat.

Antarmuka pengelolaan media berada di masing-masing modul:
- Project Media Library.
- Article Media Library.

Tidak ada menu Media Library global.

Namun, administrator dapat menggunakan gambar dari konten lain melalui Select from Existing Media.

## 11.2 Upload Management

Ketentuan:
- Maksimal 30 MB per gambar.
- Format JPG, JPEG, PNG, WebP.
- Maksimal 20 gambar per batch.
- Upload concurrency sekitar 3–4 file.
- Image processing berjalan secara bertahap.

### Upload Queue

Setiap gambar memiliki status:
- Waiting.
- Uploading.
- Processing.
- Completed.
- Failed.

Fitur:
- Progress per gambar.
- Retry.
- Hapus dari antrean sebelum diproses.
- Upload batch berikutnya.
- Informasi kegagalan.

### Isolated Failure

Kegagalan satu gambar tidak membatalkan seluruh batch.

Gambar yang berhasil tetap disimpan, sedangkan gambar gagal dapat di-retry secara individual.

## 11.3 Temporary Upload + Commit

Gambar yang diunggah pertama kali masuk ke temporary storage.

Alur:
1. Upload.
2. Validasi.
3. Image Processing.
4. Pemilihan gambar.
5. Penyimpanan dan commit berdasarkan penggunaan konten.
6. Pembersihan file sementara yang tidak digunakan.

File temporary yang terbengkalai dibersihkan secara otomatis.

Gambar yang masih digunakan oleh versi published atau Version History tidak boleh terhapus.

## 11.4 Responsive Image Variants

Ukuran gambar yang direncanakan:

- 400 px.
- 800 px.
- 1280 px.
- 1920 px.
- 2560 px.
- 3840 px.

Ketentuan:
- Format WebP.
- Quality 88 sebagai nilai awal.
- Aspect ratio dipertahankan.
- Tidak melakukan upscaling.
- Varian yang lebih besar dari sumber tidak dibuat.
- Frontend menggunakan responsive image selection.

## 11.5 Image Quality

Standar awal:
- WebP Quality 88.
- EXIF dihapus setelah orientasi diterapkan.
- Color profile dinormalisasi ke sRGB.
- Sharpening ringan dan selektif.

Nilai tersebut akan dievaluasi menggunakan foto proyek asli sebelum produksi.

## 11.6 Cover Image

Cover memiliki varian khusus dengan rasio:
- Desktop: 16:9.
- Mobile: 4:5.
- Tablet: menyesuaikan layout.

Fitur:
- Manual Focal Point.
- Preview crop.
- Posisi crop dapat diatur administrator.
- Konfigurasi focal point desktop dan mobile terpisah.
- Foto asli tetap mempertahankan rasio aslinya.

## 11.7 Image Metadata

Metadata sederhana:
- Alt Text.
- Caption.
- Original File Name.

Alt Text:
- Sistem memberikan rekomendasi otomatis.
- Administrator dapat mengeditnya.
- Rekomendasi tidak otomatis dianggap sebagai deskripsi final.

Caption dan alt text disimpan berdasarkan penggunaan gambar sehingga dapat berbeda pada setiap konten.

## 11.8 Deduplication

Menggunakan checksum SHA-256 untuk mendeteksi file identik.

Jika gambar identik sudah tersedia:
- Tidak perlu menyimpan file fisik duplikat.
- Sistem menggunakan file yang sudah ada.
- Setiap penggunaan tetap memiliki metadata dan relasi konten masing-masing.

## 11.9 Select from Existing Media

Gallery Picker menampilkan:
- Grid thumbnail.
- Search nama file atau nama proyek.
- Filter Projects dan News & Articles.
- Informasi ukuran dan dimensi.
- Preview gambar.
- Indikator jumlah penggunaan.
- Multi-select tanpa batas khusus.

Administrator dapat memilih gambar dari berbagai proyek dan artikel tanpa upload ulang.

## 11.10 Media Deletion

Menggunakan Soft Delete + Garbage Collection.

Ketentuan:
- Penghapusan dari konten hanya menghapus relasi penggunaan.
- File fisik tidak langsung dihapus.
- File fisik tetap tersedia selama masih direferensikan oleh konten lain atau versi lama.
- File yang tidak memiliki referensi masuk antrean penghapusan.
- Masa retensi: 7 hari.
- Jika gambar digunakan kembali selama masa retensi, antrean penghapusan dibatalkan.
- Penghapusan permanen dilakukan otomatis.

Semua aktivitas penghapusan tercatat dalam Activity Logs.

## 11.11 Media Access

Semua administrator memiliki hak akses yang setara.

- Bisa menggunakan media milik administrator lain.
- Bisa menghapus relasi penggunaan gambar dari konten yang dikelola.
- Tidak boleh menghapus file fisik yang masih digunakan oleh konten lain.

# 12. Kebutuhan Non-Functional

## 12.1 Performance
- Lazy loading gambar.
- Responsive image delivery.
- Infinite scroll.
- Skeleton loading.
- Explicit image dimensions untuk menghindari layout shift.
- Optimasi ukuran gambar.

## 12.2 Responsive Design
Website harus mendukung:
- Desktop.
- Tablet.
- Mobile.

## 12.3 Security
- Autentikasi administrator.
- Validasi input.
- Validasi MIME berdasarkan isi file.
- Pembatasan ukuran file.
- Pembatasan dimensi dan jumlah pixel gambar.
- Perlindungan file lamaran.
- Proteksi anti-spam formulir kontak.
- Pembatasan akses draft dan konten privat.

## 12.4 Reliability
- Auto-save.
- Upload queue.
- Isolated failure.
- Version History.
- Edit Lock.
- Validasi referensi media sebelum penghapusan permanen.

# 13. Batasan dan Prioritas Implementasi

## 13.1 Kondisi Infrastruktur

Rencana kapasitas awal:
- 2 vCPU.
- RAM 2 GB.
- Storage 1 TB.
- Trafik rendah.

Server belum disediakan dan implementasi masih dalam tahap perencanaan.

## 13.2 Fitur yang Tidak Termasuk Scope Saat Ini

- Contact Inbox.
- Quote Request Form.
- Applicant Account.
- HRIS atau ATS kompleks.
- Automated Interview Scheduling.
- Automated Interview Invitation.
- Free-form Page Builder.
- Global Media Library Dashboard.
- Backup eksternal Cloud Object Storage pada tahap awal.

## 13.3 Pengembangan yang Ditunda

Backup eksternal menggunakan Cloud Object Storage disiapkan sebagai rencana jangka panjang, tetapi belum menjadi kebutuhan implementasi awal.

Struktur pengelolaan storage diharapkan tidak menghambat migrasi ke layanan object storage di masa depan.

Detail arsitektur teknis, database, deployment, dan konfigurasi server akan dibahas pada fase berikutnya.

# 14. Keputusan yang Masih Perlu Dikonfirmasi

Beberapa hal belum memiliki keputusan final dan perlu dibahas sebelum requirement dianggap final:

1. Pola URL kategori proyek dan artikel.
2. Detail perilaku filter dan sorting pada halaman listing.
3. Batas jumlah dan dimensi pixel maksimum gambar.
4. Masa retensi file temporary yang tidak pernah di-commit.
5. Detail pengelolaan konten Archived dan Unpublished Changes.
6. Detail pengaturan SEO per halaman dan per konten.
7. Mekanisme email notifikasi untuk pelamar (belum ditetapkan).
8. Detail validasi dan batas ukuran dokumen lamaran.

---