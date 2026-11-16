/**
 * Daftar proyek: mengeklik sebuah proyek membuka panelnya di tempat (info di kiri, foto digulir ke samping di kanan),
 * tanpa pindah halaman. Peningkatan bertahap: tanpa JavaScript, tautan membuka halaman detail seperti biasa.
 * Alamat ikut berubah menjadi `#slug` agar dapat dibagikan; halaman detail penuh tetap tersedia di /projects/{slug}.
 */
const grid = document.getElementById('project-grid');

if (grid) {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const cache = new Map();
    let panel = null;
    let openLink = null;
    let busy = false;

    const tileOf = (link) => link.closest('article');

    // Akhir baris tempat kartu berada: panel disisipkan setelah kartu terakhir di baris yang sama.
    const rowEnd = (article) => {
        let last = article;
        let next = article.nextElementSibling;

        while (next && next.tagName === 'ARTICLE' && Math.abs(next.offsetTop - article.offsetTop) < 4) {
            last = next;
            next = next.nextElementSibling;
        }

        return last;
    };

    const setHash = (slug) => {
        const url = new URL(window.location.href);
        url.hash = slug ? slug : '';
        history.replaceState(history.state, '', url);
    };

    const remove = (animate) => {
        if (!panel) {
            return;
        }

        const old = panel;
        const link = openLink;

        panel = null;
        openLink = null;
        link?.setAttribute('aria-expanded', 'false');

        if (!animate || reducedMotion.matches) {
            old.remove();

            return;
        }

        old.classList.remove('is-open');
        old.addEventListener('transitionend', () => old.remove(), { once: true });
        setTimeout(() => old.remove(), 700);
    };

    const wire = (el) => {
        const scroller = el.querySelector('[data-panel-scroller]');

        el.querySelector('[data-panel-close]')?.addEventListener('click', () => close());

        if (!scroller) {
            return;
        }

        // Jarak snap mengikuti padding deretan (margin halaman), supaya info dan foto sejajar dengan isi halaman saat awal.
        const syncSnapPadding = () => {
            scroller.style.scrollPaddingInline = getComputedStyle(scroller).paddingLeft;
        };

        syncSnapPadding();
        new ResizeObserver(syncSnapPadding).observe(scroller);

        // Foto memudar masuk saat siap (bukan muncul mendadak), supaya menggulir terasa halus.
        scroller.querySelectorAll('img').forEach((img) => {
            const ready = () => img.classList.add('is-loaded');

            if (img.complete && img.naturalWidth > 0) {
                ready();
            } else {
                img.addEventListener('load', ready, { once: true });
                img.addEventListener('error', ready, { once: true });
            }
        });

        const count = el.querySelector('[data-panel-count]');
        const slides = [...scroller.querySelectorAll('[data-panel-slide]')];
        const step = () => Math.max(scroller.clientWidth * 0.8, 240);

        el.querySelector('[data-panel-prev]')?.addEventListener('click', () => scroller.scrollBy({ left: -step(), behavior: reducedMotion.matches ? 'auto' : 'smooth' }));
        el.querySelector('[data-panel-next]')?.addEventListener('click', () => scroller.scrollBy({ left: step(), behavior: reducedMotion.matches ? 'auto' : 'smooth' }));

        // Penghitung foto: foto yang paling banyak terlihat di tepi kiri area gulir.
        scroller.addEventListener(
            'scroll',
            () => {
                const left = scroller.scrollLeft + 8;
                const index = slides.findIndex((slide) => slide.offsetLeft + slide.offsetWidth > left);

                if (count) {
                    count.textContent = String(Math.max(0, index) + 1).padStart(2, '0');
                }
            },
            { passive: true },
        );

        // Seret dengan mouse untuk menggulir (sentuhan dan trackpad sudah bekerja bawaan).
        let startX = 0;
        let startLeft = 0;
        let dragging = false;
        let moved = false;

        scroller.addEventListener('pointerdown', (event) => {
            if (event.pointerType !== 'mouse' || event.button !== 0) {
                return;
            }

            dragging = true;
            moved = false;
            startX = event.clientX;
            startLeft = scroller.scrollLeft;
        });

        window.addEventListener('pointermove', (event) => {
            if (!dragging) {
                return;
            }

            const dx = event.clientX - startX;

            if (!moved && Math.abs(dx) > 4) {
                moved = true;
                scroller.setAttribute('data-scroller-dragging', '');
            }

            if (moved) {
                scroller.scrollLeft = startLeft - dx;
            }
        });

        window.addEventListener('pointerup', () => {
            dragging = false;
            scroller.removeAttribute('data-scroller-dragging');
        });
    };

    async function load(link) {
        const url = link.dataset.panel;

        if (!cache.has(url)) {
            const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' } });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            cache.set(url, await response.text());
        }

        return cache.get(url);
    }

    function close() {
        const link = openLink;

        remove(true);
        setHash(null);
        link?.focus({ preventScroll: true });
    }

    async function open(link) {
        if (busy) {
            return;
        }

        busy = true;

        try {
            const html = await load(link);
            const article = tileOf(link);
            const topBefore = article.getBoundingClientRect().top;

            // Panel lain yang terbuka dihapus seketika; posisi layar dikoreksi agar kartu yang diklik tidak melompat.
            remove(false);
            window.scrollBy(0, article.getBoundingClientRect().top - topBefore);

            const template = document.createElement('template');
            template.innerHTML = html.trim();
            const el = template.content.firstElementChild;

            rowEnd(article).after(el);
            panel = el;
            openLink = link;
            link.setAttribute('aria-expanded', 'true');
            setHash(link.dataset.slug);
            wire(el);

            // Foto pertama didekode lebih dulu (maks. 700 ms) supaya animasi tidak tersendat oleh pekerjaan dekode.
            const first = el.querySelector('img');

            await Promise.race([
                first && first.decode ? first.decode().catch(() => {}) : Promise.resolve(),
                new Promise((resolve) => setTimeout(resolve, 700)),
            ]);

            // Dua frame: tinggi 0 harus sempat dihitung sebelum kelas buka dipasang, agar animasinya berjalan.
            // Pewaktu menjadi cadangan bila frame tidak berjalan (mis. tab di latar belakang).
            let revealed = false;

            const reveal = () => {
                if (revealed) {
                    return;
                }

                revealed = true;
                el.classList.add('is-open');

                // Gulir ke panel dimulai sedikit setelah tinggi mulai membesar, tidak berebut dengan animasinya.
                setTimeout(() => el.scrollIntoView({ behavior: reducedMotion.matches ? 'auto' : 'smooth', block: 'start' }), reducedMotion.matches ? 0 : 90);
            };

            requestAnimationFrame(() => requestAnimationFrame(reveal));
            setTimeout(reveal, 300);
        } catch (error) {
            // Gagal memuat panel: buka halaman detail seperti tautan biasa (penyebab dicatat di konsol untuk diagnosis).
            console.error('Panel proyek gagal dimuat, membuka halaman detail.', error);
            window.location.href = link.href;
        } finally {
            busy = false;
        }
    }

    grid.addEventListener('click', (event) => {
        const link = event.target.closest('a[data-project-link][data-panel]');

        if (!link || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
            return;
        }

        event.preventDefault();

        if (link === openLink) {
            close();
        } else {
            open(link);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && panel) {
            close();
        }
    });

    // Tautan langsung ke sebuah proyek (`/#slug`) membuka panelnya.
    const initial = window.location.hash.slice(1);

    if (initial) {
        const link = [...grid.querySelectorAll('a[data-project-link][data-slug]')].find((a) => a.dataset.slug === initial);

        if (link) {
            open(link);
        }
    }
}
