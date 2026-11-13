/**
 * Infinite scroll untuk listing News (SRS FR-FE-071). Peningkatan bertahap: tanpa JavaScript, tautan
 * pagination "Older" tetap berfungsi. Server mengembalikan fragmen HTML untuk permintaan AJAX dan
 * menyertakan penanda berikutnya ([data-news-sentinel]) bila masih ada artikel.
 */
const grid = document.getElementById('news-grid');

if (grid && 'IntersectionObserver' in window) {
    let loading = false;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting && !loading) {
                load(entry.target);
            }
        });
    }, { rootMargin: '600px 0px' });

    const watch = () => {
        const sentinel = grid.querySelector('[data-news-sentinel]');

        if (sentinel) {
            observer.observe(sentinel);
        }
    };

    const load = async (sentinel) => {
        loading = true;
        observer.unobserve(sentinel);

        try {
            const response = await fetch(sentinel.dataset.next, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
            });

            if (!response.ok) {
                throw new Error(String(response.status));
            }

            const html = await response.text();
            sentinel.remove();
            grid.insertAdjacentHTML('beforeend', html);
            watch();
        } catch {
            // Gagal: pulihkan penanda agar dicoba lagi saat pengguna menggulir kembali.
            observer.observe(sentinel);
        } finally {
            loading = false;
        }
    };

    watch();
}
