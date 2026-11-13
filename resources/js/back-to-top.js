/**
 * Tombol melayang "Back to top": muncul setelah halaman di-scroll cukup jauh, kembali ke atas dengan halus
 * (langsung bila pengguna memilih reduced motion). Tanpa JavaScript tombol tetap tersembunyi; footer punya tautan
 * "Back to top" sebagai gantinya.
 */
const button = document.getElementById('back-to-top');

if (button) {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const threshold = () => Math.max(window.innerHeight * 0.8, 480);
    let ticking = false;

    const update = () => {
        ticking = false;
        const show = window.scrollY > threshold();

        button.dataset.visible = show ? 'true' : 'false';
        button.tabIndex = show ? 0 : -1;
    };

    window.addEventListener(
        'scroll',
        () => {
            if (!ticking) {
                ticking = true;
                window.requestAnimationFrame(update);
            }
        },
        { passive: true },
    );

    button.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: reducedMotion.matches ? 'auto' : 'smooth' });

        // Fokus kembali ke awal halaman agar pengguna keyboard tidak "tertinggal" di bawah.
        document.getElementById('main')?.focus({ preventScroll: true });
    });

    window.addEventListener('pageshow', update);
    update();
}
