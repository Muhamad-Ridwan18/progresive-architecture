// Daftar proyek: infinite scroll, pergantian kategori tanpa reload, dan pemulihan posisi scroll
// (SRS 3.2). Tanpa JavaScript, halaman tetap berfungsi lewat tautan dan pagination biasa.
const grid = document.getElementById('project-grid');

if (grid) {
    const bar = document.getElementById('category-bar');
    const homeUrl = bar?.dataset.home ?? '/';

    let loadedPages = Number(grid.dataset.loadedPages || 1);
    let loading = false;
    let observer = null;
    let controller = null; // membatalkan permintaan yang masih berjalan saat kategori diganti

    // Posisi scroll dipulihkan sendiri (lihat restoreScroll), bukan oleh browser.
    history.scrollRestoration = 'manual';

    // Kerangka satu batch penuh dari server, berpola sama dengan item asli.
    const skeletonTemplate = document.getElementById('project-skeleton');
    const skeletonHtml = skeletonTemplate?.innerHTML ?? '';

    /*
     * Selama masih ada data, ruang untuk batch berikutnya SELALU tersedia di bawah daftar
     * (kerangka), sebelum diminta. Dengan begitu footer tidak pernah berada di layar saat konten
     * masih akan dimuat, dan konten nyata mengisi tempat kerangka itu tanpa menggeser apa pun
     * (menghindari layout shift, SRS 12.1). Dipasang oleh JavaScript, jadi pengguna tanpa
     * JavaScript tidak melihat kerangka permanen.
     */
    const SKELETON_REGION = '[data-skeleton-region]';

    function addSkeleton() {
        const sentinel = grid.querySelector('[data-sentinel]');

        if (!sentinel || !skeletonTemplate || grid.querySelector(SKELETON_REGION)) {
            return;
        }

        // Sebanyak batch berikutnya yang sebenarnya (batch terakhir bisa lebih pendek dari 8),
        // supaya tinggi kerangka = tinggi konten asli dan tidak ada yang bergeser saat digantikan.
        const count = Number(sentinel.dataset.nextCount) || skeletonTemplate.content.children.length;
        const region = document.createElement('div');

        region.className = 'contents';
        region.setAttribute('data-skeleton-region', '');
        region.setAttribute('aria-hidden', 'true');

        [...skeletonTemplate.content.children].slice(0, count).forEach((cell) => region.appendChild(cell.cloneNode(true)));

        sentinel.before(region);
    }

    const removeSkeleton = () => grid.querySelector(SKELETON_REGION)?.remove();

    async function fetchFragment(url, signal) {
        const response = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
            signal,
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        return response.text();
    }

    // ------------------------------------------------------------------ infinite scroll

    function observe() {
        observer?.disconnect();

        const sentinel = grid.querySelector('[data-sentinel]');

        // Tidak ada sentinel = semua data sudah tampil, berhenti memuat (SRS FR-FE-018).
        if (!sentinel) {
            return;
        }

        observer = new IntersectionObserver(
            (entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    loadMore();
                }
            },
            { rootMargin: '600px 0px' }, // sentinel ada di ujung kerangka, jadi pemicunya sudah lebih awal
        );
        observer.observe(sentinel);
    }

    async function loadMore() {
        const sentinel = grid.querySelector('[data-sentinel]');

        // Satu permintaan dalam satu waktu: cegah duplikasi (SRS FR-FE-018).
        if (!sentinel || loading) {
            return;
        }

        loading = true;
        observer?.disconnect();
        grid.querySelector('[data-retry]')?.remove();
        addSkeleton();

        controller = new AbortController();

        try {
            const html = await fetchFragment(sentinel.dataset.next, controller.signal);

            removeSkeleton();
            sentinel.remove();
            grid.insertAdjacentHTML('beforeend', html);
            addSkeleton(); // ruang untuk batch berikutnya, bila masih ada

            loadedPages += 1;
            grid.dataset.loadedPages = String(loadedPages);
            loading = false;
            observe();
        } catch (error) {
            loading = false;

            if (error.name === 'AbortError') {
                return;
            }

            // Gagal: tampilkan tombol coba lagi, bukan kerangka yang menggantung selamanya.
            removeSkeleton();
            sentinel.insertAdjacentHTML(
                'beforebegin',
                '<div data-retry class="col-span-1 py-10 text-center md:col-span-12">' +
                    '<p class="text-ink-muted">Proyek belum dapat dimuat.</p>' +
                    '<button type="button" class="label mt-3 underline">Coba lagi</button></div>',
            );
            grid.querySelector('[data-retry] button')?.addEventListener('click', () => {
                observer?.disconnect();
                loadMore();
            });
        }
    }

    // ------------------------------------------------------------------ kategori tanpa reload

    function markActive(url) {
        const slug = new URL(url, location.href).searchParams.get('category');

        bar?.querySelectorAll('a[data-category]').forEach((link) => {
            const isActive = link.dataset.category === slug;

            if (isActive) {
                link.setAttribute('aria-current', 'true');
            } else {
                link.removeAttribute('aria-current');
            }

            // Mengeklik kategori aktif membatalkan pilihan; lainnya memilihnya.
            link.href = isActive ? homeUrl : `${homeUrl}?category=${encodeURIComponent(link.dataset.category)}`;
        });
    }

    async function switchTo(url, { push = true } = {}) {
        controller?.abort();
        const mine = new AbortController();
        controller = mine;
        observer?.disconnect();
        loading = true;

        grid.innerHTML = skeletonHtml;

        try {
            const html = await fetchFragment(url, mine.signal);

            grid.innerHTML = html;
            addSkeleton();
            loadedPages = 1;
            grid.dataset.loadedPages = '1';

            markActive(url);

            if (push) {
                history.pushState({}, '', url);
            }

            window.scrollTo({ top: 0 });
        } catch (error) {
            if (error.name === 'AbortError') {
                return; // digantikan permintaan yang lebih baru
            }

            // Gagal total: kembali ke navigasi biasa supaya pengguna tidak melihat grid kosong.
            window.location.assign(url);

            return;
        }

        loading = false;
        observe();
    }

    bar?.addEventListener('click', (event) => {
        const link = event.target.closest('a[data-category]');

        if (!link || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
            return; // biarkan "buka di tab baru" bekerja
        }

        event.preventDefault();
        switchTo(link.href);
    });

    window.addEventListener('popstate', () => switchTo(location.href, { push: false }));

    // ------------------------------------------------------------------ pulihkan posisi scroll

    // Simpan posisi sebelum meninggalkan halaman lewat tautan proyek (SRS FR-FE-019).
    grid.addEventListener('click', (event) => {
        if (event.target.closest('a[data-project-link]')) {
            history.replaceState({ ...history.state, scrollY: window.scrollY, loadedPages }, '');
        }
    });

    async function restoreScroll() {
        const state = history.state;

        if (!state || typeof state.scrollY !== 'number') {
            return;
        }

        // Bila pengguna sempat memuat beberapa halaman, muat ulang sekaligus (?upto=N) lalu gulir.
        if (state.loadedPages > 1) {
            const url = new URL(location.href);
            url.searchParams.set('upto', String(state.loadedPages));

            try {
                grid.innerHTML = await fetchFragment(url.toString());
                addSkeleton();
                loadedPages = state.loadedPages;
                grid.dataset.loadedPages = String(loadedPages);
            } catch {
                return; // biarkan halaman pertama; pengguna tetap bisa menggulir
            }
        }

        requestAnimationFrame(() => window.scrollTo({ top: state.scrollY }));
    }

    addSkeleton();
    restoreScroll().finally(observe);
}
