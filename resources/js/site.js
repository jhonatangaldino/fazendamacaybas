import '../css/site.css';

document.addEventListener('DOMContentLoaded', () => {
    // Menu mobile toggle
    const toggle = document.querySelector('[data-menu-toggle]');
    const menu = document.querySelector('[data-menu]');
    if (toggle && menu) {
        toggle.addEventListener('click', () => {
            menu.classList.toggle('hidden');
        });
    }

    // Scroll suave para âncoras
    document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
        anchor.addEventListener('click', (e) => {
            const target = document.querySelector(anchor.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    initLightbox();
});

/**
 * Lightbox simples, sem dependências:
 * - Clique em qualquer [data-lightbox-item] abre a imagem em tela cheia
 * - Setas (← / →) ou botões para navegar
 * - ESC ou backdrop para fechar
 */
function initLightbox() {
    const gallery = document.querySelector('[data-lightbox-gallery]');
    const lightbox = document.getElementById('lightbox');
    if (!gallery || !lightbox) return;

    const items = Array.from(gallery.querySelectorAll('[data-lightbox-item]'));
    if (!items.length) return;

    const imgEl = lightbox.querySelector('[data-lightbox-img]');
    const captionEl = lightbox.querySelector('[data-lightbox-caption]');
    const counterEl = lightbox.querySelector('[data-lightbox-counter]');
    const btnClose = lightbox.querySelector('[data-lightbox-close]');
    const btnPrev = lightbox.querySelector('[data-lightbox-prev]');
    const btnNext = lightbox.querySelector('[data-lightbox-next]');

    let currentIndex = 0;

    function show(index) {
        if (index < 0) index = items.length - 1;
        if (index >= items.length) index = 0;
        currentIndex = index;

        const item = items[index];
        const src = item.dataset.src;
        const caption = item.dataset.caption || '';

        imgEl.src = src;
        imgEl.alt = caption;
        captionEl.textContent = caption;
        counterEl.textContent = `${index + 1} / ${items.length}`;
    }

    function open(index) {
        show(index);
        lightbox.classList.remove('hidden');
        lightbox.classList.add('flex');
        requestAnimationFrame(() => {
            lightbox.style.opacity = '1';
        });
        document.body.style.overflow = 'hidden';
    }

    function close() {
        lightbox.style.opacity = '0';
        setTimeout(() => {
            lightbox.classList.add('hidden');
            lightbox.classList.remove('flex');
            imgEl.src = '';
            document.body.style.overflow = '';
        }, 200);
    }

    items.forEach((item, idx) => {
        item.addEventListener('click', () => open(idx));
    });

    btnClose.addEventListener('click', close);
    btnPrev.addEventListener('click', (e) => { e.stopPropagation(); show(currentIndex - 1); });
    btnNext.addEventListener('click', (e) => { e.stopPropagation(); show(currentIndex + 1); });

    // Clique no backdrop fecha (mas não no próprio painel da imagem)
    lightbox.addEventListener('click', (e) => {
        if (e.target === lightbox) close();
    });

    // Teclado
    document.addEventListener('keydown', (e) => {
        if (lightbox.classList.contains('hidden')) return;
        if (e.key === 'Escape') close();
        else if (e.key === 'ArrowLeft') show(currentIndex - 1);
        else if (e.key === 'ArrowRight') show(currentIndex + 1);
    });
}
