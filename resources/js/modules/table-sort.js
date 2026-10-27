/**
 * Fast client-side column sorting for the Mod Inventory Table
 */
export function initTableSort() {
    const tables = document.querySelectorAll('[data-sortable-table]');

    tables.forEach((table) => {
        const headers = table.querySelectorAll('thead th[data-sort]');
        const tbody = table.querySelector('tbody');
        if (!tbody) return;

        headers.forEach((th, index) => {
            th.addEventListener('click', () => {
                const type = th.getAttribute('data-sort') || 'text';
                const currentDir = th.getAttribute('data-dir') === 'asc' ? 'desc' : 'asc';

                headers.forEach((h) => h.removeAttribute('data-dir'));
                th.setAttribute('data-dir', currentDir);

                const rows = Array.from(tbody.querySelectorAll('tr[data-row]'));
                rows.sort((rowA, rowB) => {
                    const cellA = rowA.children[index]?.getAttribute('data-sort-value')
                        ?? rowA.children[index]?.textContent?.trim()
                        ?? '';
                    const cellB = rowB.children[index]?.getAttribute('data-sort-value')
                        ?? rowB.children[index]?.textContent?.trim()
                        ?? '';

                    if (type === 'number') {
                        const numA = parseFloat(cellA) || 0;
                        const numB = parseFloat(cellB) || 0;
                        return currentDir === 'asc' ? numA - numB : numB - numA;
                    }

                    return currentDir === 'asc'
                        ? cellA.localeCompare(cellB, 'en')
                        : cellB.localeCompare(cellA, 'en');
                });

                rows.forEach((row) => tbody.appendChild(row));
            });
        });
    });
}
