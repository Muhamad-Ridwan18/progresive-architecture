# SOFTWARE REQUIREMENTS SPECIFICATION (SRS)
## Website Company Profile & Portfolio Perusahaan Kontraktor

**Versi:** 1.0  
**Status:** Final  
**Jenis Dokumen:** Software Requirements Specification  
**Platform:** Website publik dan Dashboard Administrator

---

# 1. PENDAHULUAN

## 1.1 Tujuan Dokumen

Dokumen ini menjelaskan kebutuhan dan spesifikasi sistem website company profile dan portfolio perusahaan kontraktor.

SRS menjadi acuan untuk:
- Perancangan UI/UX.
- Pengembangan frontend dan backend.
- Perancangan database.
- Pengujian sistem.
- Validasi hasil pengembangan dengan kebutuhan perusahaan.

## 1.2 Ruang Lingkup Sistem

Sistem terdiri dari dua bagian utama:

**A. Website Publik**
- Homepage dan portfolio proyek.
- Detail proyek.
- About Us.
- News & Articles.
- Careers.
- Detail lowongan pekerjaan dan formulir lamaran.
- Contact.
- Global Search.

**B. Dashboard Administrator**
- Dashboard Overview.
- Manajemen Projects.
- Manajemen News & Articles.
- Manajemen Categories.
- Manajemen About Us.
- Manajemen Job Vacancies.
- Manajemen Applicants.
- Form Builder.
- Website Settings.
- User Management.
- Activity Logs.
- Notifications.
- Image Management System yang terintegrasi dengan pengelolaan konten.

## 1.3 Prinsip Pengembangan

Sistem dirancang dengan prinsip:
- Minimalis dan berorientasi pada konten visual.
- Pengelolaan konten yang mudah oleh administrator.
- Struktur konten dinamis pada bagian yang memang membutuhkan fleksibilitas.
- Penggunaan satu sistem pengelolaan gambar terpusat.
- Antarmuka publik dan dashboard konsisten secara visual.
- Pengembangan modular agar dapat dikembangkan lebih lanjut.

---

# 2. KONSEP DESAIN DAN PENGALAMAN PENGGUNA

## 2.1 Referensi Desain

Referensi utama adalah website BIG (Bjarke Ingels Group), yaitu https://big.dk/.

Arah desain yang diadopsi:
- Editorial dan minimalis.
- Banyak ruang kosong (whitespace).
- Mengutamakan fotografi proyek.
- Layout asimetris.
- Tipografi tegas.
- Tidak menggunakan tampilan website company profile konvensional yang terlalu banyak kartu dan dekorasi.

## 2.2 Palet Warna Awal

Palet warna awal yang diusulkan:

| Elemen | Warna |
|---|---|
| Background utama | #FFFFFF |
| Background sekunder | #F5F5F3 |
| Teks utama | #171717 |
| Teks sekunder | #777777 |

Warna dapat disesuaikan kembali berdasarkan identitas visual perusahaan.

## 2.3 Tipografi

Menggunakan jenis huruf sans-serif seperti Inter, Arial, atau Helvetica Neue.

Karakteristik:
- Headline tegas dan berukuran besar.
- Teks isi mudah dibaca.
- Hierarki tipografi jelas.
- Konsisten antara website publik dan dashboard.

## 2.4 Navigasi Website

Navbar:
- Logo perusahaan di sebelah kiri.
- Global Search di sebelah kanan.
- Hamburger menu di sebelah kanan.

Navbar bersifat sticky.

Hamburger menu membuka fullscreen navigation dengan menu:
- Projects.
- About Us.
- News & Articles.
- Careers.
- Contact.

## 2.5 Footer

Footer menggunakan desain minimalis dengan informasi:
- Nama dan tagline perusahaan.
- Tautan navigasi.
- Informasi kontak.
- Alamat perusahaan.
- Social media.
- Copyright.

---

# 3. KEBUTUHAN FUNGSIONAL WEBSITE PUBLIK

## 3.1 Homepage dan Project Listing

Homepage langsung menampilkan daftar proyek tanpa hero section.

Ketentuan:
- Daftar proyek ditampilkan menggunakan editorial asymmetric grid.
- Tidak menggunakan masonry acak.
- Setiap project card menampilkan gambar, nama proyek, kategori, lokasi, dan tahun.
- Foto menggunakan variasi rasio yang terencana.
- Informasi proyek tetap terlihat tanpa harus hover.
- Hover desktop memberikan efek zoom-in ringan tanpa overlay.
- Tidak ada item kategori "All Projects".
- Kategori ditampilkan secara horizontal dan dapat digulir.
- Kategori hanya menampilkan kategori yang memiliki proyek Published.
- Proyek ditampilkan menggunakan infinite scroll.
- Data berikutnya dimuat secara bertahap ketika pengunjung mendekati bagian bawah halaman.
- Tidak memuat seluruh gambar sekaligus.

### 3.1.1 Kategori Proyek

Kategori bersifat dinamis dan dikelola administrator.

Ketentuan:
- Satu proyek hanya dapat memiliki satu kategori.
- Kategori dapat ditambahkan, diubah, dihapus, dan diurutkan.
- Kategori yang masih digunakan tidak dapat dihapus sebelum proyek dipindahkan.
- Kategori tanpa proyek Published tidak ditampilkan pada website.
- Jika URL kategori diakses secara langsung tanpa proyek Published, website menampilkan empty state sederhana.

## 3.2 Detail Project

Halaman detail proyek menampilkan:
- Breadcrumb: Home / Projects / Category / Project Name.
- Nama proyek.
- Kategori.
- Lokasi.
- Tahun.
- Deskripsi.
- Cover image.
- Galeri foto.

Ketentuan galeri:
- Layout editorial asimetris.
- Pola layout ditentukan sistem berdasarkan urutan foto.
- Administrator tidak perlu mengatur layout setiap foto satu per satu.
- Semua foto ditampilkan dalam satu halaman.
- Tidak menggunakan pagination atau tombol Load More.
- Foto dimuat secara lazy loading dan progresif ketika mendekati viewport.
- Jika foto gagal dimuat, placeholder mempertahankan dimensi gambar.
- Sistem mencoba ulang maksimal dua kali secara otomatis dan menyediakan Retry manual.

### 3.2.1 Lightbox

Ketika foto diklik:
- Foto ditampilkan dalam tampilan besar.
- Caption ditampilkan jika tersedia.
- Menampilkan posisi foto, misalnya 03/24.
- Mendukung navigasi keyboard.
- Tombol Escape untuk menutup.
- Mendukung swipe pada perangkat mobile.
- Dapat ditutup dengan klik area luar.
- Posisi scroll halaman dipertahankan setelah ditutup.
- Resolusi tinggi dimuat ketika foto dibuka.

### 3.2.2 Navigasi Antarproyek

Pada bagian bawah detail proyek ditampilkan:
- Related Projects.
- Previous Project.
- Next Project.

Related Projects:
- Maksimal tiga proyek.
- Hanya proyek Published.
- Berdasarkan kategori yang sama.
- Tidak menampilkan proyek yang sedang dibuka.
- Mengikuti konfigurasi pengurutan proyek.
- Jika tidak ada proyek terkait, bagian disembunyikan.

Previous dan Next:
- Berdasarkan kategori yang sama.
- Hanya proyek Published.
- Mengikuti konfigurasi pengurutan.
- Menampilkan thumbnail dan nama proyek.
- Jika tidak ada proyek sebelumnya atau berikutnya, arah tersebut disembunyikan.
- Desktop menggunakan tampilan berdampingan.
- Mobile menggunakan susunan vertikal, Previous di atas Next.
- Navigasi tanpa full page reload.
- Transisi menggunakan fade ringan sekitar 150–250 ms dan menghormati pengaturan reduced motion.

### 3.2.3 Perilaku Navigasi

- Navigasi dari listing ke detail menggunakan pendekatan SPA/AJAX.
- URL browser berubah sesuai proyek yang dibuka.
- Browser Back dan Forward harus berfungsi.
- Direct URL dan refresh halaman tetap harus menampilkan halaman dengan benar.
- Ketika kembali dari detail, kategori, posisi scroll, data infinite scroll yang telah dimuat, dan pengurutan sebelumnya dipertahankan.

## 3.3 Tombol Back to Top

- Muncul setelah pengunjung melewati ambang scroll tertentu.
- Ditempatkan pada sudut bawah layar.
- Menggunakan smooth scroll.
- Tidak mengganggu kontrol Lightbox.
- Posisi responsif pada mobile.

## 3.4 Project Sharing

Halaman proyek menyediakan:
- WhatsApp.
- Facebook.
- LinkedIn.
- Copy Link.

Informasi sharing menggunakan metadata SEO proyek.

## 3.5 About Us

Halaman About Us merupakan halaman profil korporat, bukan halaman editorial storytelling.

Bagian yang tersedia:
1. Company Overview.
2. Company History.
3. Vision & Mission.
4. Company Values.
5. Company Statistics.
6. Our Team.

Ketentuan:
- Struktur section ditentukan sistem.
- Tidak menggunakan free-form page builder.
- Setiap section dikelola secara terpisah.
- Administrator dapat melakukan preview sebelum publikasi.
- Company Statistics bersifat dinamis: administrator dapat menambah, mengubah, menghapus, dan mengatur urutan statistik.
- Our Team bersifat opsional dan dapat ditampilkan atau disembunyikan.
- Jika Our Team aktif, administrator dapat mengelola foto, nama, jabatan, dan urutan anggota tim.

## 3.6 News & Articles

### 3.6.1 Article Listing

Halaman artikel menggunakan konsep Editorial Magazine.

Ketentuan:
- Satu artikel utama (Featured Article) ditampilkan pada bagian atas.
- Artikel lainnya menggunakan editorial asymmetric grid.
- Navigasi kategori horizontal.
- Infinite scroll.
- Hanya satu artikel yang dapat menjadi Featured pada satu waktu.
- Ketika artikel lain dipilih sebagai Featured, artikel sebelumnya otomatis tidak lagi menjadi Featured.
- Jika tidak ada artikel yang dipilih, artikel terbaru menjadi Featured secara otomatis.

### 3.6.2 Detail Artikel

Halaman detail artikel menampilkan:
- Kategori.
- Tanggal publikasi.
- Judul.
- Excerpt.
- Cover image.
- Isi artikel.
- Inline images dan caption.
- Related Articles.

Related Articles:
- Maksimal tiga artikel.
- Berdasarkan kategori yang sama.
- Hanya artikel Published.
- Tidak menampilkan artikel yang sedang dibuka.
- Jika hanya tersedia satu atau dua artikel, tampilkan yang tersedia.
- Jika tidak ada, bagian disembunyikan.

### 3.6.3 URL Artikel

URL artikel menggunakan format:

`/articles/judul-artikel`

Ketentuan:
- URL tidak mengikuti kategori.
- Perubahan kategori tidak mengubah URL artikel.
- Slug dibuat otomatis dan tidak dapat diedit secara manual.
- Jika judul berubah, slug berubah otomatis.
- Slug lama disimpan dan diarahkan menggunakan HTTP 301.
- Slug harus unik.

### 3.6.4 Pengelolaan Artikel

Data artikel:
- Title.
- Slug.
- Category.
- Excerpt.
- Cover Image.
- Rich Content.
- Author.
- Status.
- Publish Date dan Time.

Identitas penulis ditentukan secara manual oleh administrator, sehingga dapat berupa nama individu, departemen, atau tim perusahaan.

Rich Text Editor mendukung:
- Heading.
- Bold dan italic.
- Alignment.
- Bullet dan numbered list.
- Inline images dan captions.
- External links.
- Blockquote.
- Tables.

Cover dan inline image menggunakan sistem Image Management terpusat.

### 3.6.5 Status dan Workflow Artikel

Status artikel:
- Draft.
- Scheduled.
- Published.
- Archived.
- Trash.
- Unpublished Changes.

Workflow:
- Draft dapat diterbitkan langsung atau dijadwalkan.
- Scheduled dapat diubah jadwalnya kapan saja selama belum terbit.
- Artikel Published yang diedit menggunakan mekanisme Draft Changes.
- Versi lama tetap tampil kepada publik sampai perubahan diterbitkan.
- Administrator dapat melakukan preview.
- Administrator dapat menerbitkan perubahan atau membuang perubahan.
- Unpublish menyembunyikan artikel dari website tetapi tetap menyimpannya di dashboard.
- Move to Trash memindahkan artikel ke Trash.
- Trash dapat dipulihkan selama 30 hari.
- Penghapusan permanen dapat dilakukan sebelum masa retensi berakhir.
- Penghapusan permanen dicatat dalam Activity Logs.

Tidak ada fitur komentar pengunjung pada artikel.

### 3.6.6 Dashboard Artikel

Pengelolaan artikel menggunakan Table View.

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
- Edit Article.
- Preview.
- Publish.
- Unpublish.
- Move to Trash.
- Restore.

Form Add/Edit Article menggunakan halaman khusus (Dedicated Page).

## 3.7 Careers dan Recruitment

Sistem menyediakan pengelolaan lowongan dan pelamar sederhana yang terintegrasi dengan website.

Sistem bukan HRIS atau ATS kompleks.

### 3.7.1 Job Listing

Pengunjung dapat:
- Melihat daftar lowongan.
- Melihat kategori lowongan.
- Memfilter lowongan berdasarkan kategori dan status.
- Membuka detail lowongan.
- Mengirim lamaran selama pendaftaran masih dibuka.

### 3.7.2 Data Job Vacancy

Setiap lowongan memiliki:
- Position.
- Category.
- Department.
- Location.
- Employment Type.
- Description.
- Qualifications.
- Required Documents.
- Opening Date.
- Closing Date.
- Status.

Status lowongan:
- Draft.
- Published.
- Closed.

Kategori lowongan bersifat dinamis dan dikelola administrator.

### 3.7.3 Formulir Lamaran

Pelamar tidak diwajibkan membuat akun.

Formulir lamaran bersifat dinamis berdasarkan kategori pekerjaan.

Ketentuan:
- Setiap kategori memiliki default form template.
- Lowongan mewarisi template kategori.
- Administrator dapat menyesuaikan formulir untuk setiap lowongan.
- Form Builder menggunakan visual drag-and-drop tanpa coding.
- Formulir dapat memiliki conditional fields.
- Setiap aplikasi menyimpan snapshot struktur formulir saat lamaran dikirim.
- Perubahan template di kemudian hari tidak mengubah data lamaran yang sudah masuk.

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

Pengaturan field:
- Label.
- Description.
- Placeholder.
- Required.
- Options.
- File restrictions.
- Urutan field.

Administrator dapat menentukan jenis file yang diterima dan batas ukuran file untuk setiap field upload.

### 3.7.4 Penutupan Lowongan

- Lowongan yang berstatus Closed tidak menerima lamaran.
- Jika tanggal penutupan terlewati, formulir lamaran otomatis dinonaktifkan.
- Pengunjung melihat informasi bahwa pendaftaran telah ditutup.
- Lamaran baru tidak dapat dikirim setelah penutupan.

### 3.7.5 Manajemen Pelamar

Dashboard menyediakan Table View dan Kanban View.

Table View menampilkan:
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

Kanban stages:
1. Submitted.
2. Reviewing.
3. Shortlisted.
4. Interview.
5. Accepted.
6. Rejected.

Ketentuan:
- Status dapat diubah secara manual.
- Perubahan status melalui drag-and-drop.
- Semua perubahan status dicatat dalam riwayat.
- Tidak ada penjadwalan wawancara otomatis.
- Tidak ada pengiriman undangan wawancara otomatis.

### 3.7.6 Detail Pelamar

Detail pelamar memiliki tab:
1. Profile.
2. Application Answers.
3. Documents.
4. Activity History.

Informasi status tetap terlihat pada bagian atas halaman.

Administrator dapat menambahkan internal notes.

Setiap lamaran memiliki nomor referensi.

Dokumen dan data pelamar bersifat privat, hanya dapat diakses oleh administrator melalui dashboard yang terautentikasi. Tidak ada URL publik untuk dokumen lamaran.

Tidak ada akun atau login untuk pelamar.

### 3.7.7 Notifikasi Lamaran

- Administrator menerima notifikasi lamaran baru di dashboard.
- Pelamar tidak menerima email konfirmasi otomatis.
- Website memberikan konfirmasi bahwa formulir berhasil dikirim.

### 3.7.8 Dashboard Job Vacancies

Table View menampilkan:
- Position.
- Category.
- Location.
- Employment Type.
- Applicant Count.
- Deadline.
- Status.
- Action.

Applicant Count dapat diklik untuk membuka daftar pelamar yang sudah difilter berdasarkan lowongan tersebut.

## 3.8 Contact

Fitur Contact menggunakan mekanisme Email Only.

Formulir publik:
- Full Name.
- Email.
- Phone.
- Subject Dropdown.
- Message.

Ketentuan:
- Pesan dikirim langsung ke email perusahaan.
- Pesan tidak disimpan di database.
- Tidak tersedia Contact Inbox di dashboard.
- Tidak ada notifikasi dashboard untuk pesan Contact baru.
- Form dilengkapi validasi dan perlindungan anti-spam, misalnya CAPTCHA atau rate limiting.

Dashboard menyediakan pengaturan:
- Recipient Email.
- Company Sender Name.
- Sender Email.
- Contact Information.
- Address.
- Office Hours.

Pengiriman menggunakan SMTP atau layanan transactional email.

Tidak termasuk fitur Quote Request.

## 3.9 Global Search Website

Global Search tersedia pada navbar.

Ketentuan:
- Membuka fullscreen overlay.
- Menampilkan hasil secara langsung ketika pengguna mengetik.
- Debounce sekitar 300 ms.
- Hasil dikelompokkan berdasarkan Projects, News & Articles, dan Careers.
- Enter atau tombol See All Results membuka halaman hasil pencarian.
- Halaman hasil pencarian menyediakan filter jenis konten.

---

# 4. KEBUTUHAN FUNGSIONAL DASHBOARD

## 4.1 Struktur Dashboard

Dashboard menggunakan pendekatan hybrid:
- Struktur administrasi tradisional.
- Tampilan minimalis dan editorial.
- Sidebar kiri.
- Topbar.
- Main content area.

### 4.1.1 Sidebar

Sidebar:
- Dapat diperluas dan diperkecil.
- Expanded menampilkan ikon dan label.
- Collapsed menampilkan ikon saja.
- Menyediakan tooltip.
- Status collapse disimpan per pengguna.
- Pada mobile berubah menjadi drawer.

Menu:

**GENERAL**
- Dashboard.

**CONTENT MANAGEMENT**
- Projects.
- News & Articles.
- Categories.
- About Us.

**RECRUITMENT**
- Job Vacancies.
- Applicants.
- Form Builder.

**SYSTEM**
- Website Settings.
- Users.
- Activity Logs.

Categories menggunakan satu menu dengan dua tab:
- Project Categories.
- Article Categories.

### 4.1.2 Topbar

Topbar menampilkan:
- Sidebar Toggle.
- Page Title/Breadcrumb.
- Global Search.
- Notifications Icon dan unread badge.
- User Profile.

Pada mobile, Global Search dapat berubah menjadi ikon yang membuka overlay.

## 4.2 Dashboard Overview

Dashboard menampilkan metrik:
- Total Projects.
- Published Articles.
- Active Job Vacancies.
- Total Applications.

Di bawah metrik:
- Recent Activities.
- Recent Applications.

Recent Activities menampilkan siapa melakukan apa, objek yang diubah, dan waktu kejadian.

Recent Applications menampilkan lima pelamar terbaru dengan nama, posisi yang dilamar, tanggal, dan status.

Nama pelamar dapat diklik untuk membuka detail pelamar.

Dashboard tidak menggunakan grafik dekoratif yang berlebihan.

## 4.3 User Management

Sistem mendukung beberapa pengguna internal.

Ketentuan:
- Semua pengguna memiliki hak akses penuh dan setara.
- Tidak ada role atau permission hierarchy.
- Pembuatan akun dilakukan secara manual oleh pengguna yang sudah ada.
- Tidak ada registrasi publik.
- Tidak ada invitation email.
- Setiap pengguna dapat membuat, mengedit, menonaktifkan, mengaktifkan, dan mengelola pengguna lain.
- Pengguna dapat mereset password pengguna lain.

User Table:
- Name.
- Email.
- Status.
- Last Login.
- Created At.
- Action.

Pengelolaan profil pribadi:
- Edit Name.
- Edit Email.
- Upload Profile Photo.
- Change Password.
- View Account Information.
- View Date Joined.

Proteksi:
- Pengguna tidak dapat menonaktifkan akunnya sendiri jika menyebabkan tidak ada pengguna aktif.
- Pengguna tidak dapat menonaktifkan pengguna aktif terakhir.
- Perubahan dicatat di Activity Logs.
- Password tidak pernah disimpan di Activity Logs.

## 4.4 Website Settings

Website Settings menggunakan satu halaman dengan tab:

**General**
- Site Name.
- Logo.
- Favicon.
- Tagline.

**Company Information**
- Company Name.
- Description.
- Address.

**Contact**
- Email.
- Phone.
- Office Hours.

**SEO**
- Default Meta Title.
- Default Meta Description.
- Default SEO Image.

**Social Media**
- Instagram.
- LinkedIn.
- Facebook.
- Platform sosial lain yang diperlukan.

Informasi digunakan oleh frontend, termasuk navbar, footer, metadata, dan informasi kontak.

## 4.5 Activity Logs

Sistem menyediakan Activity Logs terperinci.

Data yang dicatat:
- Who.
- What.
- When.
- Affected Object.
- Before Value.
- After Value.

Aktivitas yang dicatat:
- Pembuatan data.
- Perubahan data.
- Penghapusan.
- Publikasi.
- Perubahan status lowongan.
- Perubahan status pelamar.
- Perubahan Website Settings.
- Perubahan pengguna.
- Aktivitas pengelolaan media yang relevan.

Data sensitif seperti password tidak dicatat.

## 4.6 Notifications

Dashboard menyediakan notifikasi internal melalui ikon bell di topbar.

Jenis notifikasi:
- New Application.
- Job Deadline.
- Publishing Project.
- Publishing Article.
- Upload Completion.

Ketentuan:
- Notifikasi bersifat in-app.
- Notifikasi real-time tanpa refresh.
- Menggunakan Laravel Reverb sebagai rencana teknologi real-time.
- Notifikasi tetap tersimpan setelah logout.
- Semua pengguna aktif menerima notifikasi karena hak akses setara.
- Pengguna dapat menandai notifikasi sebagai read.
- Klik notifikasi membuka halaman terkait.

Upload Completion:
- Satu notifikasi ringkasan untuk setiap batch.
- Menampilkan jumlah file berhasil dan gagal.
- Menyediakan tautan menuju Media Library terkait.

---

# 5. IMAGE MANAGEMENT SYSTEM

## 5.1 Konsep Umum

Sistem gambar menggunakan pengelolaan terpusat yang terintegrasi dengan Laravel.

Setiap Project dan Article memiliki Media Library masing-masing dari sisi antarmuka, tetapi pemrosesan dan penyimpanan dikelola melalui satu sistem.

Tidak ada menu Global Media Library tersendiri pada sidebar.

## 5.2 Format dan Batas Upload

Format yang diterima:
- JPG/JPEG.
- PNG.
- WebP.

SVG tidak diterima.

Batas upload:
- Maksimal dimensi 12.000 × 12.000 piksel.
- Maksimal total 40 megapiksel.
- Maksimal ukuran file 30 MB.
- Maksimal 20 gambar per batch.

Sistem harus memvalidasi MIME berdasarkan isi file, bukan hanya ekstensi.

## 5.3 Upload dan Pemrosesan

Alur:
1. Administrator memilih gambar.
2. Gambar diunggah ke temporary storage.
3. Sistem memvalidasi file.
4. Sistem melakukan pemrosesan gambar.
5. Hasil pemrosesan disimpan.
6. Relasi media dikomit ketika konten disimpan.
7. Temporary files dibersihkan setelah proses selesai.

Ketentuan:
- Upload mendukung multiple selection.
- Browser upload concurrency sekitar 3–4.
- Sharp worker concurrency awal satu.
- Progress ditampilkan per gambar dan keseluruhan batch.
- Status: Uploading, Processing, Completed, Failed.
- Batch dapat dibatalkan.
- Upload aktif dibatalkan jika memungkinkan.
- Temporary files dibersihkan setelah pembatalan.
- File yang sudah committed tidak terpengaruh.
- Gangguan koneksi dicoba ulang maksimal dua kali otomatis.
- Retry manual tersedia.
- File yang sudah berhasil diunggah tidak perlu dikirim ulang.
- Setelah file diterima server, pemrosesan tetap berjalan meskipun browser ditutup.
- Sistem membersihkan temporary upload yang tidak pernah dikomit setelah tujuh hari.

## 5.4 Image Processing

Varian responsif:
- 400 px.
- 800 px.
- 1280 px.
- 1920 px.
- 2560 px.
- 3840 px.

Ketentuan:
- Mempertahankan aspect ratio.
- Tidak melakukan upscaling.
- Hanya menghasilkan ukuran yang lebih kecil dari sumber.
- Format output awal WebP.
- Quality awal 88.
- Menggunakan srcset dan sizes pada frontend.
- Menghapus EXIF setelah orientasi dan pemrosesan color profile.
- Menormalisasi atau mempertahankan warna sRGB.
- Menggunakan selective sharpening ringan.
- Kualitas dan waktu proses perlu divalidasi menggunakan foto proyek sebenarnya.

## 5.5 Cover Image

Cover image wajib tersedia sebelum proyek diterbitkan.

Ketentuan:
- Cover dipilih dari Media Library.
- Cover otomatis menjadi foto pertama pada galeri detail.
- Tidak perlu mengunggah atau menambahkan foto yang sama dua kali.
- Ketika cover berubah, cover baru menjadi foto pertama.
- Cover lama tetap berada di galeri jika masih digunakan sebagai foto galeri.
- Media fisik tidak diduplikasi.
- Cover tidak dapat dipindahkan dari urutan pertama melalui drag-and-drop.

Varian crop:
- Desktop 16:9.
- Mobile 4:5.
- Tablet menyesuaikan layout.

Sistem menyimpan focal point terpisah untuk desktop dan mobile.

Administrator dapat mengatur focal point secara manual melalui visual crop preview dengan drag/adjust.

Sumber foto tetap mempertahankan aspect ratio aslinya.

## 5.6 Gallery Management

- Administrator dapat mengunggah banyak foto.
- Foto dapat dipilih dan disusun manual dengan drag-and-drop.
- Gallery Picker menggunakan grid.
- Gallery Picker memiliki tahap pemilihan sebelum foto ditambahkan ke galeri.
- Administrator dapat memilih, membatalkan pilihan, mengatur urutan, dan menekan Add to Gallery.
- Urutan foto mengikuti urutan seleksi.
- Cover selalu menjadi foto pertama.
- Foto galeri dapat dihapus dari relasi konten dengan konfirmasi.
- Penghapusan massal dari galeri didukung.
- Penghapusan relasi tidak langsung menghapus file fisik.

## 5.7 Gallery Picker

Gallery Picker menggunakan satu grid terpusat, tidak dikelompokkan berdasarkan proyek atau artikel.

Fitur:
- Search berdasarkan filename, project, atau article.
- Filter Projects.
- Filter News & Articles.
- Menampilkan thumbnail.
- Menampilkan ukuran dan dimensi gambar.
- Preview gambar.
- Multiple selection.
- Usage Indicator: Used atau Unused.
- Klik usage indicator untuk melihat daftar konten yang menggunakan media.
- Setiap item penggunaan dapat membuka halaman edit konten terkait.

## 5.8 Metadata Media

Metadata yang dikelola:
- Alt Text.
- Caption.
- Original Filename.

Alt Text:
- Sistem dapat memberikan saran otomatis berdasarkan project, kategori, dan filename.
- Saran merupakan draft.
- Administrator dapat mengedit secara manual.
- Alt Text disimpan per penggunaan konten.

Caption:
- Opsional.
- Disimpan per penggunaan konten.
- Dapat berbeda untuk media yang sama pada konten berbeda.

Original Filename disimpan pada data media fisik.

Tanggal foto diambil (Photo Taken Date) tidak disimpan.

## 5.9 Deduplication

Sistem menggunakan SHA-256 untuk mendeteksi file identik.

Jika file yang sama diunggah:
- Sistem menggunakan file fisik yang sudah tersedia.
- Relasi penggunaan tetap dibuat secara terpisah.
- Alt Text dan Caption setiap penggunaan tetap independen.

## 5.10 Penghapusan Media

Sistem menggunakan Soft Delete dan Garbage Collection.

Ketentuan:
- Media yang masih digunakan tidak dapat dihapus permanen.
- Media dapat digunakan oleh beberapa konten.
- Relasi media yang dihapus dari konten tidak langsung menghapus file fisik.
- Jika tidak ada referensi tersisa, file dijadwalkan untuk dihapus setelah tujuh hari.
- Jika media digunakan kembali selama masa tunggu, penghapusan dibatalkan.
- Media yang masih direferensikan versi publikasi atau version history tetap dilindungi.
- Penghapusan permanen dicatat dalam Activity Logs.

Cover harus diganti terlebih dahulu sebelum dapat dihapus dari penggunaan sebagai cover.

## 5.11 Penggantian File

Tidak tersedia fitur Replace File.

Jika gambar ingin diganti:
1. Upload gambar baru.
2. Pilih gambar baru pada konten.
3. Hapus relasi gambar lama jika sudah tidak digunakan.

File lama tetap tersedia sampai tidak lagi memiliki referensi dan masa Garbage Collection selesai.

## 5.12 Keamanan Media

- Upload hanya oleh pengguna terautentikasi.
- UUID digunakan sebagai nama file.
- Temporary storage tidak boleh dapat diakses publik.
- Validasi dimensi, pixel count, ukuran, dan MIME.
- File diproses setelah orientasi dan color profile ditangani.
- File sementara dibersihkan ketika proses gagal atau ditinggalkan.

---

# 6. MANAJEMEN PROJECTS DI DASHBOARD

## 6.1 Data Project

Field:
- Name.
- Slug.
- Category.
- Location.
- Year.
- Description.
- Cover Image.
- Gallery.
- Publication Status.

Location menggunakan teks biasa, misalnya Bandung, Jawa Barat.

Year menggunakan input tahun numerik manual.

Description menggunakan Rich Text Editor yang mendukung:
- Heading dan subheading.
- Bold dan italic.
- Paragraph.
- Bullet dan numbered list.
- Hyperlink.

Inline image tidak diunggah melalui editor deskripsi; gambar dikelola melalui Media Library.

## 6.2 Status Project

Status:
- Draft.
- Published.
- Unpublished Changes.
- Archived.
- Trash.

Ketentuan:
- Draft dapat dibuat tanpa cover.
- Published wajib memiliki cover.
- Perubahan pada proyek Published disimpan sebagai Draft Changes.
- Versi lama tetap tampil kepada publik sampai perubahan diterbitkan.
- Administrator dapat membuang perubahan untuk kembali ke versi publik terakhir.
- Archived tidak tampil di website, tetapi tetap tersedia di dashboard.
- Archived dapat dikembalikan ke Draft.
- Trash memiliki masa retensi 30 hari.
- Proyek dapat dipulihkan selama masa retensi.
- Penghapusan permanen dapat dilakukan lebih awal dengan konfirmasi mengetik nama proyek.
- Semua penghapusan permanen dicatat.
- Semua administrator memiliki hak melakukan penghapusan permanen.

## 6.3 Slug

- Slug dibuat otomatis dari nama proyek.
- Tidak dapat diedit manual.
- Perubahan nama menghasilkan slug baru.
- Slug lama disimpan dan diarahkan menggunakan HTTP 301.
- Slug baru harus unik.

## 6.4 SEO Project

Pengaturan SEO:
- Meta Title.
- Meta Description.
- SEO Image.

Ketentuan:
- Meta Title dan Meta Description otomatis dibuat dari informasi proyek.
- Administrator dapat mengubahnya.
- Jika nilai manual kosong, sistem menggunakan nilai otomatis.
- SEO Image secara default menggunakan cover.
- Administrator dapat memilih gambar lain.

## 6.5 SEO Otomatis

XML Sitemap:
- Hanya memasukkan proyek Published.
- Tidak memasukkan Draft, Unpublished, Archived, dan Trash.
- Diperbarui saat publish, unpublish, delete, atau perubahan slug.
- Penghapusan permanen menghapus URL dari sitemap.

JSON-LD:
- Dibuat otomatis pada halaman detail proyek.
- Menggunakan struktur data yang sesuai dengan informasi tersedia.
- Tidak memaksakan schema yang tidak relevan.

Social Metadata:
- Open Graph Title menggunakan Meta Title.
- Open Graph Description menggunakan Meta Description.
- Open Graph Image menggunakan SEO Image.
- Twitter Card mengikuti metadata yang sama.

## 6.6 Preview

- Preview menggunakan tampilan frontend sebenarnya.
- Mendukung Desktop, Tablet, dan Mobile.
- Preview menampilkan versi draft tersimpan.
- Hanya dapat diakses administrator.
- Tidak dapat diakses publik.
- Tidak boleh diindeks Google.

## 6.7 Duplicate Project

Fitur Duplicate Project:
- Menggandakan informasi proyek.
- Menggandakan relasi galeri.
- Menggunakan ulang file media fisik.
- Membuat slug baru secara otomatis.
- Hasil duplikasi berstatus Draft.
- Tidak menyalin riwayat publikasi.
- Tidak mengubah proyek sumber.

## 6.8 Import dan Export

Export:
- Excel.
- CSV.

Kolom export:
- Name.
- Category.
- Location.
- Year.
- Status.
- Publish Date.
- Slug.

Export mengikuti filter dan pencarian aktif.

Gambar tidak dimasukkan ke file export.

Import:
- Mendukung Excel dan CSV.
- Menyediakan template unduhan.
- Validasi data per baris.
- Menampilkan kesalahan agar dapat diperbaiki sebelum konfirmasi.
- Data hasil import berstatus Draft.
- Slug dibuat otomatis.
- Gambar tidak diimpor dan dikelola melalui Media Library setelah import.

Tidak tersedia bulk import gambar ZIP.

## 6.9 Bulk Actions

Administrator dapat memilih beberapa proyek dan melakukan:
- Publish.
- Unpublish.
- Move to Trash.
- Change Category.

Semua bulk action membutuhkan konfirmasi dan dicatat di Activity Logs.

Bulk action harus mengikuti aturan versioning dan publikasi yang berlaku.

## 6.10 Pencarian dan Filter

Dashboard Projects menyediakan:
- Search berdasarkan nama.
- Filter kategori.
- Filter status.
- Kombinasi filter dan pencarian.
- Pagination.

Tidak diperlukan filter tahun atau tanggal pada tahap ini.

## 6.11 Pengurutan Project

Pengurutan listing mendukung:
- Manual Ordering.
- Newest First.

Konfigurasi pengurutan digunakan secara konsisten pada listing, Related Projects, Previous Project, dan Next Project.

## 6.12 Konfirmasi Publish

Publish membutuhkan Confirmation Dialog yang menampilkan nama proyek dan ringkasan perubahan.

Setelah publikasi berhasil, aktivitas dicatat.

Tidak tersedia Scheduled Publishing untuk Projects.

## 6.13 Tampilan Tabel Project

- Sepuluh proyek per halaman.
- Thumbnail kecil di samping nama proyek.
- Thumbnail menggunakan varian yang dioptimalkan.
- Jika tidak tersedia, tampilkan placeholder.

Status ditampilkan sebagai badge sederhana:
- Draft.
- Published.
- Unpublished Changes.
- Archived.
- Trash.

Tidak perlu menampilkan informasi waktu atau jumlah tambahan pada kolom status.

## 6.14 Edit Project

Halaman edit menggunakan tab:

**General Information**
- Name.
- Category.
- Location.
- Year.
- Description.

**Media & Gallery**
- Cover.
- Gallery.
- Ordering.
- Caption.
- Alt Text.

**SEO Settings**
- Meta Title.
- Meta Description.
- SEO Image.

Sticky Bottom Action Bar:
- Preview.
- Publish.
- Discard Changes jika tersedia.
- Auto-save state: Saving, Saved, Failed.

Tombol Publish hanya aktif jika perubahan siap diterbitkan.

Tampilan responsif untuk mobile.

---

# 7. PENGELOLAAN KATEGORI

Menu Categories terdiri dari dua tab:
1. Project Categories.
2. Article Categories.

Setiap kategori mendukung:
- Create.
- Edit.
- Delete.
- Reorder.
- Search.
- Table View.

Kategori yang masih digunakan tidak dapat dihapus sampai konten dipindahkan ke kategori lain.

---

# 8. NON-FUNCTIONAL REQUIREMENTS

## 8.1 Usability

- Antarmuka mudah dipahami administrator.
- Form pengelolaan konten memiliki validasi yang jelas.
- Pengguna memperoleh feedback untuk aksi penting.
- Tampilan responsif desktop, tablet, dan mobile.
- Interaksi tidak menggunakan animasi berlebihan.

## 8.2 Performance

- Website menggunakan lazy loading untuk gambar.
- Infinite scroll memuat data secara bertahap.
- Gambar menggunakan responsive variants.
- Pemrosesan gambar berjalan melalui background worker.
- Konten detail dimuat secara progresif.
- Skeleton atau placeholder digunakan ketika data sedang dimuat.
- Tidak menggunakan animasi staggered yang berlebihan.

Target performa numerik perlu divalidasi melalui pengujian setelah lingkungan hosting tersedia.

## 8.3 Security

- Dashboard hanya dapat diakses pengguna terautentikasi.
- Tidak ada registrasi administrator publik.
- Password disimpan dengan mekanisme hashing yang aman.
- Data pelamar dan dokumen lamaran bersifat privat.
- Upload gambar dan dokumen divalidasi.
- File temporary tidak dapat diakses publik.
- Aktivitas penting dicatat di Activity Logs.
- Password dan data kredensial tidak dicatat ke log.

## 8.4 Compatibility

Website harus responsif dan diuji pada browser modern:
- Google Chrome.
- Microsoft Edge.
- Mozilla Firefox.
- Safari.

## 8.5 Reliability

- Kegagalan pemrosesan gambar tidak boleh merusak file media yang sudah committed.
- Upload yang gagal dapat dicoba kembali.
- Kegagalan pemuatan foto menyediakan placeholder dan Retry.
- Perubahan konten yang belum diterbitkan tidak boleh menggantikan versi publik.
- Sistem harus menangani URL lama melalui redirect ketika slug berubah.

---

# 9. KEBIJAKAN PUBLIKASI DAN VERSIONING

## 9.1 Draft

Konten baru dapat disimpan sebagai Draft dan belum tampil di website publik.

## 9.2 Published

Konten Published ditampilkan di website publik.

## 9.3 Unpublished Changes

Jika konten Published diedit:
- Versi publik lama tetap tampil.
- Perubahan disimpan sebagai draft.
- Administrator dapat preview.
- Administrator dapat publish perubahan.
- Administrator dapat discard perubahan.

## 9.4 Discard Changes

- Memerlukan konfirmasi.
- Mengembalikan konten ke versi publik terakhir.
- Menghapus perubahan draft.
- Media yang hanya digunakan oleh draft dapat masuk proses Garbage Collection jika tidak memiliki referensi lain.
- Aktivitas dicatat.

## 9.5 Archived

- Tidak ditampilkan di website.
- Tetap tersedia di dashboard.
- Dapat dikembalikan ke Draft.
- Perubahan status dicatat.

## 9.6 Trash

- Konten dipindahkan ke Trash.
- Dapat dipulihkan selama 30 hari.
- Penghapusan permanen dapat dilakukan lebih awal.
- Penghapusan permanen tidak boleh menghapus media yang masih digunakan konten lain.

---

# 10. KEBUTUHAN TEKNIS AWAL DAN BATASAN DESAIN

Bagian ini menjadi acuan awal perancangan teknis, bukan spesifikasi implementasi yang sudah final.

## 10.1 Arah Teknologi

Rencana teknologi:
- Backend: Laravel.
- Runtime: FrankenPHP.
- Database: MySQL.
- Queue: Redis.
- Real-time notifications: Laravel Reverb.
- Image processing: Node.js dan Sharp.
- Frontend: teknologi frontend yang mendukung pendekatan interaktif dan responsif.

## 10.2 Infrastruktur Awal

Target spesifikasi awal:
- 2 vCPU.
- RAM 2 GB.
- Storage 1 TB.
- Trafik rendah.

Server belum disediakan pada tahap perencanaan ini.

## 10.3 Image Processing Worker

Sharp dijalankan sebagai worker terpisah dari proses utama Laravel, tetapi tetap menjadi bagian dari sistem aplikasi dan bukan microservice mandiri.

Rencana:
- Laravel mengelola antrean pekerjaan.
- Sharp melakukan pemrosesan gambar.
- Satu worker awal.
- Concurrency pemrosesan satu.
- Resource usage perlu dibatasi dan diuji.

## 10.4 Storage

Tahap awal menggunakan local filesystem pada VPS.

Desain penyimpanan harus memungkinkan migrasi ke object storage seperti S3, Cloudflare R2, atau Backblaze B2 pada masa mendatang.

Penyimpanan original file secara permanen tidak diwajibkan. Sistem mempertahankan hasil varian yang telah diproses. Karena itu, jika suatu saat dibutuhkan varian baru yang memerlukan kualitas sumber lebih tinggi, file sumber harus diunggah kembali.

## 10.5 Backup

Backup otomatis belum menjadi kebutuhan tahap awal.

Backup dapat ditangani secara manual atau melalui infrastruktur server pada tahap berikutnya.

---

# 11. BATASAN RUANG LINGKUP

Fitur berikut tidak termasuk dalam ruang lingkup tahap awal:

- Contact Inbox.
- Quote Request.
- Komentar artikel.
- Akun dan login pelamar.
- HRIS atau ATS kompleks.
- Penjadwalan wawancara otomatis.
- Email undangan wawancara otomatis.
- Email konfirmasi lamaran otomatis.
- Scheduled Publishing untuk Projects.
- General Image Editor.
- Penggantian file media secara langsung (Replace File).
- Upload gambar melalui ZIP.
- Global Media Library sebagai menu dashboard terpisah.
- Backup otomatis sebagai fitur aplikasi.
- Pembagian role dan permission administrator.
- Free-form page builder untuk About Us.
- Peta interaktif untuk lokasi proyek.

---

# 12. KRITERIA PENERIMAAN SISTEM

Sistem dapat dinyatakan memenuhi kebutuhan utama apabila:

1. Pengunjung dapat melihat portfolio proyek, detail proyek, dan galeri foto.
2. Pengunjung dapat mencari konten melalui Global Search.
3. Administrator dapat mengelola proyek dan kategori secara dinamis.
4. Administrator dapat mengelola artikel, kategori, Featured Article, dan jadwal publikasi.
5. Perubahan pada konten Published tidak langsung mengganti versi publik sebelum diterbitkan.
6. Administrator dapat mengelola halaman About Us sesuai section yang tersedia.
7. Administrator dapat membuat lowongan dan formulir lamaran dinamis.
8. Pengunjung dapat mengirim lamaran tanpa membuat akun.
9. Administrator dapat mengelola pelamar melalui Table dan Kanban.
10. Lowongan yang sudah ditutup tidak menerima lamaran baru.
11. Pesan Contact dikirim ke email perusahaan tanpa disimpan dalam database.
12. Administrator dapat mengelola website settings dan pengguna.
13. Aktivitas penting tercatat di Activity Logs.
14. Notifikasi dashboard bekerja untuk aktivitas yang telah ditentukan.
15. Image Management mendukung upload, pemrosesan, pemilihan, pengurutan, penggunaan ulang, dan penghapusan media sesuai kebijakan.
16. Website responsif pada desktop, tablet, dan mobile.
17. Metadata SEO, sitemap, dan structured data tersedia sesuai kebutuhan konten.
18. Data pelamar dan dokumen lamaran terlindungi dari akses publik.

---

# 13. PENUTUP

Dokumen SRS Final v1.0 ini merupakan acuan kebutuhan fungsional dan nonfungsional website company profile dan portfolio perusahaan kontraktor berdasarkan hasil diskusi.

Perubahan kebutuhan setelah dokumen ini disetujui harus dicatat sebagai revisi requirement agar ruang lingkup dan hasil pengembangan tetap terkontrol.

**Status dokumen: Final v1.0 — Requirement utama telah disepakati.**

