// Lightbox galeri proyek (SRS 3.3): layar penuh, latar gelap, navigasi panah/Escape/swipe,
// klik di luar foto menutup, indikator posisi 03/24. Resolusi tinggi dimuat saat dibuka.
import PhotoSwipeLightbox from 'photoswipe/lightbox';

const gallery = document.querySelector('[data-gallery]');

if (gallery) {
    const pad = (n) => String(n).padStart(2, '0');

    const lightbox = new PhotoSwipeLightbox({
        gallery,
        children: 'a',
        pswpModule: () => import('photoswipe'),
        bgOpacity: 1,
        showHideAnimationType: 'fade',
        closeOnVerticalDrag: true,
        // Klik di area gelap di luar foto menutup (SRS FR-FE-043). Klik pada foto tetap memakai
        // perilaku bawaan (zoom), bukan menutup.
        bgClickAction: 'close',
    });

    // Indikator posisi bergaya "03/24" menggantikan bawaan ("3 / 24").
    lightbox.on('uiRegister', () => {
        lightbox.pswp.ui.registerElement({
            name: 'position-indicator',
            order: 5,
            onInit: (element, pswp) => {
                element.className = 'pswp__position-indicator';
                const update = () => {
                    element.textContent = `${pad(pswp.currIndex + 1)}/${pad(pswp.getNumItems())}`;
                };
                pswp.on('change', update);
                update();
            },
        });
    });

    lightbox.init();
}
