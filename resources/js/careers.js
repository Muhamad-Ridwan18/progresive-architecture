/**
 * Repeater pada formulir lamaran (SRS FR-RC-020). Peningkatan bertahap: tanpa JavaScript, baris
 * yang dirender server tetap dapat diisi; tombol tambah dan hapus muncul setelah skrip ini jalan.
 */
document.querySelectorAll('[data-repeater]').forEach((repeater) => {
    const rows = repeater.querySelector('[data-repeater-rows]');
    const template = repeater.querySelector('[data-repeater-template]');
    const addButton = repeater.querySelector('[data-repeater-add]');
    const max = Number(repeater.dataset.max || 20);
    let nextIndex = rows.querySelectorAll('[data-repeater-row]').length;

    const refresh = () => {
        const count = rows.querySelectorAll('[data-repeater-row]').length;
        addButton.hidden = count >= max;
        rows.querySelectorAll('[data-repeater-remove]').forEach((button) => {
            button.hidden = count <= 1;
        });
    };

    addButton.addEventListener('click', () => {
        if (rows.querySelectorAll('[data-repeater-row]').length >= max) {
            return;
        }

        const html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex++));
        rows.insertAdjacentHTML('beforeend', html);
        rows.lastElementChild.querySelector('input')?.focus();
        refresh();
    });

    repeater.addEventListener('click', (event) => {
        const button = event.target.closest('[data-repeater-remove]');

        if (button) {
            button.closest('[data-repeater-row]').remove();
            refresh();
        }
    });

    refresh();
});
