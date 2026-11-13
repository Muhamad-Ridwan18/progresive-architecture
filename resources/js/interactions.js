// Peningkatan interaksi: transisi cover tile -> detail dan reveal saat scroll.
// Semuanya opsional: bila API tidak didukung, halaman berjalan seperti biasa.

// ------------------------------------------------------------------ View Transitions (lintas halaman)
const COVER = 'project-cover';

function tileCoverFor(url) {
    const path = new URL(url, location.href).pathname;
    const link = [...document.querySelectorAll('a[data-project-link]')].find((a) => new URL(a.href).pathname === path);

    return link?.querySelector('[data-cover]') ?? null;
}

// Halaman lama: beri nama pada cover tile yang diklik sebelum snapshot diambil.
window.addEventListener('pageswap', (event) => {
    const to = event.activation?.entry?.url;

    if (!event.viewTransition || !to) {
        return;
    }

    const cover = tileCoverFor(to);

    if (cover) {
        cover.style.viewTransitionName = COVER;
    }
});

// Halaman baru (mis. kembali dari detail ke daftar): cocokkan dengan tile asal bila sudah ada di DOM.
window.addEventListener('pagereveal', (event) => {
    const from = window.navigation?.activation?.from?.url;

    if (!event.viewTransition || !from) {
        return;
    }

    const cover = tileCoverFor(from);

    if (cover) {
        cover.style.viewTransitionName = COVER;
        event.viewTransition.finished.finally(() => {
            cover.style.viewTransitionName = '';
        });
    }
});

// Bersihkan nama bila halaman dipulihkan dari bfcache.
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        document.querySelectorAll('[data-cover]').forEach((el) => (el.style.viewTransitionName = ''));
    }
});

// ------------------------------------------------------------------ umpan balik instan saat tautan proyek diklik
// Server membutuhkan waktu untuk membalas; tanpa reaksi, klik terasa tidak terjadi apa-apa. Tile lain meredup dan
// garis kemajuan berjalan sampai halaman berikutnya tampil. Gaya ada di app.css (dimatikan bila reduced motion).
const progress = document.createElement('div');
progress.id = 'nav-progress';
progress.setAttribute('aria-hidden', 'true');
document.body.append(progress);

const clearNavigating = () => {
    document.documentElement.classList.remove('is-navigating');
    document.querySelectorAll('.is-chosen').forEach((el) => el.classList.remove('is-chosen'));
};

document.addEventListener('click', (event) => {
    const link = event.target.closest('a[data-project-link], a[data-project-nav]');

    if (!link || event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
        return;
    }

    link.classList.add('is-chosen');
    document.documentElement.classList.add('is-navigating');
});

// Kembali lewat tombol Back (bfcache): keadaan "sedang pindah" tidak boleh tersisa.
window.addEventListener('pageshow', clearNavigating);

// ------------------------------------------------------------------ reveal saat scroll
if ('IntersectionObserver' in window) {
    const reveal = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    reveal.unobserve(entry.target);
                }
            });
        },
        { rootMargin: '0px 0px -6% 0px' },
    );

    const watch = (root) => root.querySelectorAll?.('[data-reveal]:not(.is-visible)').forEach((el) => reveal.observe(el));

    watch(document);

    // Tile baru dari infinite scroll / pergantian kategori.
    const grid = document.getElementById('project-grid');

    if (grid) {
        new MutationObserver(() => watch(grid)).observe(grid, { childList: true });
    }
} else {
    // Tanpa IntersectionObserver: tampilkan semuanya.
    document.querySelectorAll('[data-reveal]').forEach((el) => el.classList.add('is-visible'));
}
