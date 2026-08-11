/*
 * Themen-Modul – Board-Interaktionen (Vite-Bundle)
 *
 * Prioritäts-Slider: speichert die Priorität per AJAX, aktualisiert den
 * Fortschrittsbalken und sortiert die Tabelle neu – ohne Seiten-Reload.
 * Nutzt das global geladene jQuery (Layout).
 */
function initThemeBoard() {
    const $ = window.jQuery;
    if (!$) return;

    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    $(document).on('change', '.theme-wrapper input[type=range]', function () {
        const themeId = $(this).data('theme');

        $.ajax({
            type: 'POST',
            url: '/priorities',
            data: { priority: $(this).val(), theme: themeId, _token: token },
            success(response) {
                const percent = 100 - response.priority;
                const cell = document.getElementById('priority_' + themeId);
                if (cell) {
                    cell.innerHTML = '<div class="thm-progress"><span style="width:' + percent + '%"></span></div>';
                }
                const row = document.getElementById(themeId);
                if (row) {
                    row.dataset.priority = response.priority;
                    sortThemeContainer(row.closest('[data-theme-list]') || row.closest('table'));
                }
            },
        });
    });
}

/**
 * Sortiert eine Themen-Liste oder -Tabelle nach Priorität.
 * Ein höherer data-priority-Wert bedeutet eine höhere Priorität und
 * muss oben stehen – daher absteigend nach data-priority sortieren.
 * Themen ohne gesetzte Priorität werden ans Ende sortiert.
 */
function sortThemeContainer(container) {
    if (!container) return;

    // Tabellen-Variante (indexType / indexPriority)
    if (container.tagName === 'TABLE') {
        const tbody = container.tBodies[0];
        if (!tbody) return;
        sortByPriority(tbody, tbody.rows);
        return;
    }

    // Listen-Variante (index)
    sortByPriority(container, container.children);
}

function sortByPriority(parent, children) {
    // Themen ohne gesetzte Priorität (leerer Wert) ans Ende sortieren.
    const prio = (el) => {
        const v = el.dataset.priority;
        return v === undefined || v === '' ? -Infinity : Number(v);
    };
    const items = Array.from(children).filter((el) => el.dataset.priority !== undefined);
    items.sort((a, b) => prio(b) - prio(a));
    items.forEach((el) => parent.appendChild(el));
}

if (document.readyState !== 'loading') {
    initThemeBoard();
} else {
    document.addEventListener('DOMContentLoaded', initThemeBoard);
}

