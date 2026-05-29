/**
 * Unified table row selection and CSV export for dashboard tables.
 */
(function () {
    /** RFC 4180 field encoding (quotes doubled, field wrapped in quotes). */
    function escapeCsvField(val) {
        const s = String(val ?? '');
        return `"${s.replace(/"/g, '""')}"`;
    }

    function initTableExport(tableId, options) {
        const table = document.getElementById(tableId);
        if (!table) return;

        const selectAllId = options.selectAllId || `selectAll_${tableId}`;
        const checkboxClass = options.checkboxClass || `row-checkbox-${tableId}`;
        const exportBtnId = options.exportBtnId || `exportCsv_${tableId}`;
        const filename = options.filename || `${tableId}-export.csv`;
        const columns = options.columns || [];

        const thead = table.querySelector('thead tr');
        if (thead && !thead.querySelector(`#${selectAllId}`) && options.addSelectColumn !== false) {
            const th = document.createElement('th');
            th.innerHTML = `<input type="checkbox" id="${selectAllId}" title="Select all">`;
            thead.insertBefore(th, thead.firstChild);
        }

        table.querySelectorAll('tbody tr').forEach((row, idx) => {
            if (row.querySelector(`.${checkboxClass}`)) return;
            const td = document.createElement('td');
            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.className = checkboxClass;
            cb.dataset.rowIndex = String(idx);
            columns.forEach(col => {
                if (row.dataset[col.key] === undefined && col.selector) {
                    const el = row.querySelector(col.selector);
                    if (el) row.dataset[col.key] = el.textContent.trim();
                }
            });
            td.appendChild(cb);
            row.insertBefore(td, row.firstChild);
        });

        const selectAll = document.getElementById(selectAllId);
        if (selectAll) {
            selectAll.addEventListener('change', () => {
                table.querySelectorAll(`.${checkboxClass}`).forEach(cb => {
                    cb.checked = selectAll.checked;
                });
            });
        }

        const exportBtn = document.getElementById(exportBtnId);
        if (exportBtn) {
            exportBtn.addEventListener('click', () => exportTableCsv(tableId, checkboxClass, columns, filename));
        }
    }

    function exportTableCsv(tableId, checkboxClass, columns, filename) {
        const table = document.getElementById(tableId);
        const selected = table.querySelectorAll(`tbody .${checkboxClass}:checked`);
        const rows = selected.length ? selected : table.querySelectorAll(`tbody .${checkboxClass}`);

        if (!rows.length) {
            if (window.toast) window.toast.warning('No rows selected to export.');
            return;
        }

        const lines = [columns.map(c => escapeCsvField(c.label)).join(',')];

        rows.forEach(cb => {
            const tr = cb.closest('tr');
            const values = columns.map(col => {
                let val = tr.dataset[col.key] ?? '';
                if (!val && col.selector) {
                    const el = tr.querySelector(col.selector);
                    val = el ? el.textContent.trim() : '';
                }
                return escapeCsvField(val);
            });
            lines.push(values.join(','));
        });

        const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        link.click();
        URL.revokeObjectURL(link.href);
        if (window.toast) window.toast.success(`Exported ${rows.length} row(s).`);
    }

    window.TaciTableExport = { init: initTableExport, exportCsv: exportTableCsv };
})();
