/**
 * Halaman Team: awalnya semua anggota hanya kartu. Mengeklik kartu membuka profilnya dengan halus di atas daftar,
 * tanpa pindah halaman; mengeklik kartu yang sama, tombol Close, atau Esc menutupnya.
 * Peningkatan bertahap: semua profil sudah ada di HTML, dan tanpa JavaScript kartu tetap berupa tautan
 * `?member=id` yang memuat ulang halaman dengan profil terbuka.
 */
const drawer = document.querySelector('[data-team-profiles]');

if (drawer) {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const cards = [...document.querySelectorAll('a[data-member]')];
    let current = drawer.querySelector('[data-profile]:not([hidden])')?.dataset.profile ?? null;

    const syncUrl = (id) => {
        // Alamat ikut berubah agar profil dapat dibagikan; tidak menambah riwayat browser.
        const url = new URL(window.location.href);

        if (id) {
            url.searchParams.set('member', id);
            url.hash = 'profile';
        } else {
            url.searchParams.delete('member');
            url.hash = '';
        }

        history.replaceState({}, '', url);
    };

    const mark = (id) => {
        cards.forEach((card) => {
            if (card.dataset.member === id) {
                card.setAttribute('aria-current', 'true');
            } else {
                card.removeAttribute('aria-current');
            }
        });
    };

    const open = (id) => {
        const target = drawer.querySelector(`[data-profile="${CSS.escape(id)}"]`);

        if (!target) {
            return false;
        }

        const wasOpen = drawer.dataset.open === 'true';

        drawer.querySelectorAll('[data-profile]').forEach((profile) => {
            profile.hidden = profile !== target;
        });

        drawer.dataset.open = 'true';
        current = id;
        mark(id);
        syncUrl(id);

        // Berganti profil saat laci sudah terbuka: isi baru memudar masuk (membuka pertama sudah dianimasikan CSS).
        if (wasOpen && !reducedMotion) {
            target.animate([{ opacity: 0, transform: 'translateY(8px)' }, { opacity: 1, transform: 'none' }], { duration: 380, easing: 'ease-out' });
        }

        drawer.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'start' });

        // Fokus pindah ke nama agar pembaca layar membacakan profil yang baru.
        target.querySelector('h2')?.focus({ preventScroll: true });

        return true;
    };

    const close = () => {
        const opener = cards.find((card) => card.dataset.member === current);

        drawer.dataset.open = 'false';
        current = null;
        mark(null);
        syncUrl(null);
        opener?.focus({ preventScroll: true });
    };

    cards.forEach((card) => {
        card.addEventListener('click', (event) => {
            // Biarkan "buka di tab baru" dan sejenisnya bekerja.
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
                return;
            }

            event.preventDefault();

            if (card.dataset.member === current) {
                close();
            } else {
                open(card.dataset.member);
            }
        });
    });

    drawer.querySelectorAll('[data-team-close]').forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            close();
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && current) {
            close();
        }
    });
}
