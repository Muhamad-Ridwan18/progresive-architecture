// Menu kecil di kanan atas (SRS FR-FE-002). Tanpa JavaScript, tautan yang sama tersedia di footer.
const toggle = document.getElementById('menu-toggle');
const menu = document.getElementById('site-menu');
const close = document.getElementById('menu-close');

if (toggle && menu && close) {
    const open = () => {
        menu.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        close.focus();
    };

    const dismiss = () => {
        menu.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
        toggle.focus();
    };

    toggle.addEventListener('click', open);
    close.addEventListener('click', dismiss);

    document.addEventListener('keydown', (event) => {
        if (menu.hidden) {
            return;
        }

        if (event.key === 'Escape') {
            dismiss();

            return;
        }

        // Fokus keyboard tetap di dalam menu selama terbuka (WCAG 2.4.3).
        if (event.key === 'Tab') {
            const focusable = [...menu.querySelectorAll('a[href], button')];
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

    // Pindah halaman lewat tautan menu: tidak perlu menutup manual, tetapi kembalikan scroll body.
    menu.addEventListener('click', (event) => {
        if (event.target.closest('a')) {
            document.body.style.overflow = '';
        }
    });

    // Klik di luar panel menutup menu (latar penuh layar transparan).
    menu.addEventListener('click', (event) => {
        if (!event.target.closest('a, button') && !event.target.closest('.bg-overlay')) {
            dismiss();
        }
    });
}
