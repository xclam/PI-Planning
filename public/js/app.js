document.addEventListener('DOMContentLoaded', () => {
    const boxes = (group) => [...document.querySelectorAll(`input[data-check-group="${group}"]`)];

    // Compteur "n/N" + état de la case maître (cochée / indéterminée)
    const refresh = (group) => {
        const all = boxes(group);
        const checked = all.filter((b) => b.checked).length;

        document.querySelectorAll(`[data-count-for="${group}"]`).forEach((el) => {
            el.textContent = all.length ? `${checked}/${all.length} sélectionné(s)` : '';
        });

        const master = document.querySelector(`input[data-select-all="${group}"]`);
        if (master) {
            master.checked = all.length > 0 && checked === all.length;
            master.indeterminate = checked > 0 && checked < all.length;
        }
    };

    const groups = new Set(
        [...document.querySelectorAll('input[data-check-group]')].map((b) => b.dataset.checkGroup)
    );
    groups.forEach(refresh);

    document.addEventListener('change', (event) => {
        const target = event.target;
        if (target.matches('input[data-select-all]')) {
            const group = target.dataset.selectAll;
            boxes(group).forEach((b) => { b.checked = target.checked; });
            refresh(group);
        } else if (target.matches('input[data-check-group]')) {
            refresh(target.dataset.checkGroup);
        }
    });

    // Bascule par statut (page sprint)
    document.querySelectorAll('.btn-status-toggle').forEach((button) => {
        button.addEventListener('click', () => {
            const status = button.dataset.status;
            const rows = [...document.querySelectorAll('.demo-ticket-row')]
                .filter((row) => row.dataset.status.includes(status));
            const checkboxes = rows.map((row) => row.querySelector('input[data-check-group]'));
            const allChecked = checkboxes.every((cb) => cb.checked);

            checkboxes.forEach((cb) => { cb.checked = !allChecked; });
            refresh('demo');
        });
    });

    // Copie des clés / JQL sélectionnés
    const copyText = async (text) => {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }
        const area = document.createElement('textarea');
        area.value = text;
        document.body.appendChild(area);
        area.select();
        document.execCommand('copy');
        area.remove();
    };

    document.querySelectorAll('[data-copy-keys]').forEach((button) => {
        button.addEventListener('click', async () => {
            const keys = boxes(button.dataset.copyKeys).filter((b) => b.checked).map((b) => b.value);
            if (!keys.length) return;

            const text = button.dataset.format === 'jql'
                ? `key in (${keys.join(', ')}) ORDER BY key ASC`
                : keys.join(', ');

            const label = button.textContent;
            try {
                await copyText(text);
                button.textContent = `Copié (${keys.length}) ✓`;
            } catch {
                button.textContent = 'Échec de la copie';
            }
            setTimeout(() => { button.textContent = label; }, 2000);
        });
    });

    // Confirmation avant les actions qui écrivent dans Jira
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const selected = form.querySelectorAll('input[type="checkbox"][data-check-group]:checked').length;
            if (!selected || !window.confirm(`${form.dataset.confirm}\n\n${selected} ticket(s) sélectionné(s).`)) {
                event.preventDefault();
            }
        });
    });

    // Compatibilité avec l'ancienne page d'accueil de démo
    const btn = document.getElementById('actionBtn');
    if (btn) {
        btn.addEventListener('click', () => alert('JavaScript opérationnel sans bundler !'));
    }
});