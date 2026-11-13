/**
 * Panel pencarian kecil (bukan layar penuh) dengan live search (SRS FR-FE-050): debounce 300 ms, hasil terkelompok,
 * Enter membuka halaman hasil lengkap. Peningkatan bertahap: tanpa JavaScript, ikon pencarian
 * membuka halaman /search yang sama.
 */
const trigger = document.getElementById('search-toggle');
const overlay = document.getElementById('search-overlay');
const input = document.getElementById('search-input');
const results = document.getElementById('search-results');
const close = document.getElementById('search-close');
const seeAll = document.getElementById('search-see-all');

if (trigger && overlay && input && results && close) {
    let timer = null;
    let controller = null;
    const DEBOUNCE_MS = 300;

    const open = (event) => {
        event.preventDefault();
        overlay.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        input.focus();
    };

    const dismiss = () => {
        overlay.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        trigger.focus();
    };

    const search = async () => {
        const q = input.value.trim();

        if (seeAll) {
            seeAll.hidden = q.length < 2;
            seeAll.href = `${seeAll.dataset.base}?q=${encodeURIComponent(q)}`;
        }

        if (q.length < 2) {
            results.innerHTML = '';

            return;
        }

        // Permintaan sebelumnya dibatalkan agar hasil lama tidak menimpa hasil yang lebih baru.
        controller?.abort();
        controller = new AbortController();

        try {
            const response = await fetch(`${input.dataset.live}?q=${encodeURIComponent(q)}`, {
                signal: controller.signal,
                headers: { Accept: 'text/html' },
            });

            if (response.ok) {
                results.innerHTML = await response.text();
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                results.textContent = 'Pencarian gagal. Tekan Enter untuk membuka hasil lengkap.';
            }
        }
    };

    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(search, DEBOUNCE_MS);
    });

    trigger.addEventListener('click', open);
    close.addEventListener('click', dismiss);

    // Klik di luar panel (latar penuh layar yang transparan) menutup pencarian.
    overlay.addEventListener('click', (event) => {
        if (!event.target.closest('form, #search-results, #search-see-all')) {
            dismiss();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (overlay.hidden) {
            return;
        }

        if (event.key === 'Escape') {
            dismiss();

            return;
        }

        // Fokus keyboard tetap di dalam overlay selama terbuka (WCAG 2.4.3).
        if (event.key === 'Tab') {
            const focusable = [...overlay.querySelectorAll('a[href], button, input')].filter((el) => !el.hidden && el.offsetParent !== null);
            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });
}
