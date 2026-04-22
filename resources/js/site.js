import '../css/site.css';

// Menu mobile toggle
document.addEventListener('DOMContentLoaded', () => {
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

    // Form de newsletter (async)
    document.querySelectorAll('form[data-newsletter]').forEach((form) => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const status = form.querySelector('[data-newsletter-status]');
            const formData = new FormData(form);
            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });
                const json = await res.json();
                if (status) {
                    status.textContent = json.message || 'Inscrição realizada!';
                    status.className = 'text-sm mt-3 ' + (res.ok ? 'text-green-200' : 'text-red-200');
                }
                if (res.ok) form.reset();
            } catch (err) {
                if (status) {
                    status.textContent = 'Erro ao enviar. Tente novamente.';
                    status.className = 'text-sm mt-3 text-red-200';
                }
            }
        });
    });
});
