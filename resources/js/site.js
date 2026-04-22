import '../css/site.css';

document.addEventListener('DOMContentLoaded', () => {
    initMobileDrawer();
    initUserDropdown();
    initSmoothScroll();
    initLightbox();
});

/**
 * Menu mobile em formato drawer lateral (slide da direita).
 */
function initMobileDrawer() {
    const openBtn = document.querySelector('[data-menu-open]');
    const closeBtn = document.querySelector('[data-menu-close]');
    const drawer = document.querySelector('[data-mobile-drawer]');
    const overlay = document.querySelector('[data-mobile-overlay]');
    const links = document.querySelectorAll('[data-menu-link]');

    if (!openBtn || !drawer || !overlay) return;

    function open() {
        overlay.classList.remove('hidden');
        requestAnimationFrame(() => {
            overlay.style.opacity = '1';
            drawer.style.transform = 'translateX(0)';
        });
        document.body.style.overflow = 'hidden';
    }

    function close() {
        overlay.style.opacity = '0';
        drawer.style.transform = 'translateX(100%)';
        setTimeout(() => {
            overlay.classList.add('hidden');
            document.body.style.overflow = '';
        }, 300);
    }

    openBtn.addEventListener('click', open);
    closeBtn?.addEventListener('click', close);
    overlay.addEventListener('click', close);

    // Fecha ao clicar num link interno
    links.forEach((link) => {
        link.addEventListener('click', () => setTimeout(close, 50));
    });

    // ESC fecha
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !overlay.classList.contains('hidden')) close();
    });
}

/**
 * Dropdown do usuário logado (header desktop).
 */
function initUserDropdown() {
    const trigger = document.querySelector('[data-user-trigger]');
    const menu = document.querySelector('[data-user-menu]');
    if (!trigger || !menu) return;

    trigger.addEventListener('click', (e) => {
        e.stopPropagation();
        menu.classList.toggle('hidden');
    });

    document.addEventListener('click', (e) => {
        if (!trigger.contains(e.target) && !menu.contains(e.target)) {
            menu.classList.add('hidden');
        }
    });
}

/**
 * Scroll suave em âncoras.
 */
function initSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
        anchor.addEventListener('click', (e) => {
            const hash = anchor.getAttribute('href');
            if (hash === '#' || hash.length < 2) return;
            const target = document.querySelector(hash);
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
}

/**
 * Lightbox da galeria (sem dependências).
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
        imgEl.src = item.dataset.src;
        imgEl.alt = item.dataset.caption || '';
        captionEl.textContent = item.dataset.caption || '';
        counterEl.textContent = `${index + 1} / ${items.length}`;
    }

    function open(index) {
        show(index);
        lightbox.classList.remove('hidden');
        lightbox.classList.add('flex');
        requestAnimationFrame(() => lightbox.style.opacity = '1');
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

    items.forEach((item, idx) => item.addEventListener('click', () => open(idx)));
    btnClose.addEventListener('click', close);
    btnPrev.addEventListener('click', (e) => { e.stopPropagation(); show(currentIndex - 1); });
    btnNext.addEventListener('click', (e) => { e.stopPropagation(); show(currentIndex + 1); });
    lightbox.addEventListener('click', (e) => { if (e.target === lightbox) close(); });

    document.addEventListener('keydown', (e) => {
        if (lightbox.classList.contains('hidden')) return;
        if (e.key === 'Escape') close();
        else if (e.key === 'ArrowLeft') show(currentIndex - 1);
        else if (e.key === 'ArrowRight') show(currentIndex + 1);
    });
}
