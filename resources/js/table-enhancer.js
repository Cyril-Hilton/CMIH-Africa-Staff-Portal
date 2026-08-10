const enhancedTables = new WeakSet();
const tableControls = new WeakMap();
const skipHeaderPattern = /^(action|actions|photo|public url|link)$/i;

const normaliseText = (value) => (value || '')
    .replace(/\s+/g, ' ')
    .trim();

const comparableValue = (value) => {
    const text = normaliseText(value);
    const numeric = text.replace(/[,\s]/g, '').replace(/^[A-Z]{2,4}\s*/i, '').replace(/[%₵$£€]/g, '');

    if (numeric !== '' && /^-?\d+(\.\d+)?$/.test(numeric)) {
        return { type: 'number', value: Number(numeric) };
    }

    const timestamp = Date.parse(text);
    if (!Number.isNaN(timestamp) && /(\d{1,4}[-/]\d{1,2}|\d{1,2}\s+[A-Za-z]{3,}|\bJan\b|\bFeb\b|\bMar\b|\bApr\b|\bMay\b|\bJun\b|\bJul\b|\bAug\b|\bSep\b|\bOct\b|\bNov\b|\bDec\b)/i.test(text)) {
        return { type: 'date', value: timestamp };
    }

    return { type: 'string', value: text.toLocaleLowerCase() };
};

const compareValues = (a, b) => {
    if (a.type === b.type && (a.type === 'number' || a.type === 'date')) {
        return a.value - b.value;
    }

    return String(a.value).localeCompare(String(b.value), undefined, {
        numeric: true,
        sensitivity: 'base',
    });
};

const getTableRows = (table) => Array.from(table.tBodies)
    .flatMap((tbody) => Array.from(tbody.rows))
    .filter((row) => row.cells.length > 1 && !row.dataset.tableStatic);

const headerLabel = (th, fallback) => normaliseText(th.dataset.label || th.textContent || `Column ${fallback + 1}`)
    .replace(/[▲▼↕]/g, '')
    .trim();

const tableStorageKey = (table) => {
    if (table.dataset.tableKey) {
        return `cmih-table:${window.location.pathname}:${table.dataset.tableKey}`;
    }

    const tables = Array.from(document.querySelectorAll('table'));
    const index = tables.indexOf(table);

    return `cmih-table:${window.location.pathname}:${index}`;
};

const readState = (table) => {
    try {
        return JSON.parse(sessionStorage.getItem(tableStorageKey(table)) || '{}');
    } catch {
        return {};
    }
};

const writeState = (table, patch) => {
    try {
        sessionStorage.setItem(tableStorageKey(table), JSON.stringify({
            ...readState(table),
            ...patch,
        }));
    } catch {
        // Storage can be unavailable in strict privacy modes. Sorting/filtering still works.
    }
};

const shouldEnhanceTable = (table) => {
    if (enhancedTables.has(table)) return false;
    if (table.dataset.tableEnhancer === 'off') return false;
    if (table.closest('[data-table-enhancer-skip], .weekly-rich-content, .ck-content, .ck-editor')) return false;

    const headers = table.tHead ? Array.from(table.tHead.querySelectorAll('th')) : [];
    const rows = getTableRows(table);

    return headers.length > 1 && rows.length > 0;
};

const applyFilter = (table, input, columnSelect) => {
    const query = normaliseText(input.value).toLocaleLowerCase();
    const selectedColumn = columnSelect.value;
    let visibleCount = 0;

    getTableRows(table).forEach((row) => {
        const cells = Array.from(row.cells);
        const haystack = selectedColumn === ''
            ? normaliseText(row.textContent).toLocaleLowerCase()
            : normaliseText(cells[Number(selectedColumn)]?.textContent || '').toLocaleLowerCase();
        const visible = !query || haystack.includes(query);

        row.classList.toggle('hidden', !visible);
        if (visible) visibleCount += 1;
    });

    const meta = tableControls.get(table)?.querySelector('[data-table-filter-count]');
    if (meta) {
        meta.textContent = `${visibleCount} row${visibleCount === 1 ? '' : 's'} shown`;
    }

    writeState(table, {
        filter: input.value,
        filterColumn: selectedColumn,
    });
};

const sortTable = (table, columnIndex, direction) => {
    const body = table.tBodies[0];
    if (!body) return;

    const rows = getTableRows(table).map((row, index) => ({ row, index }));
    const sortedRows = rows.sort((left, right) => {
        const leftValue = comparableValue(left.row.cells[columnIndex]?.textContent || '');
        const rightValue = comparableValue(right.row.cells[columnIndex]?.textContent || '');
        const result = compareValues(leftValue, rightValue);

        return result === 0 ? left.index - right.index : result;
    });

    if (direction === 'desc') {
        sortedRows.reverse();
    }

    sortedRows.forEach(({ row }) => body.appendChild(row));

    Array.from(table.tHead.querySelectorAll('th')).forEach((th, index) => {
        th.setAttribute('aria-sort', index === columnIndex ? (direction === 'asc' ? 'ascending' : 'descending') : 'none');
        const indicator = th.querySelector('[data-table-sort-indicator]');
        if (indicator) {
            indicator.textContent = index === columnIndex ? (direction === 'asc' ? '▲' : '▼') : '↕';
        }
    });

    writeState(table, {
        sortColumn: columnIndex,
        sortDirection: direction,
    });
};

const enhanceTable = (table) => {
    if (!shouldEnhanceTable(table)) return;

    enhancedTables.add(table);
    table.classList.add('app-enhanced-table');

    const headers = Array.from(table.tHead.querySelectorAll('th'));
    const sortableHeaders = headers
        .map((th, index) => ({ th, index, label: headerLabel(th, index) }))
        .filter(({ th, label }) => th.dataset.sort === 'off' ? false : !skipHeaderPattern.test(label));

    if (sortableHeaders.length === 0) return;

    const controls = document.createElement('div');
    controls.className = 'app-table-tools';
    controls.dataset.silentGenerated = 'true';
    controls.innerHTML = `
        <div class="app-table-tools__label">
            <span>Filter table</span>
            <span data-table-filter-count></span>
        </div>
        <div class="app-table-tools__controls">
            <select class="app-table-tools__select" aria-label="Choose table column to filter">
                <option value="">All columns</option>
            </select>
            <input class="app-table-tools__input" type="search" placeholder="Search visible rows…" aria-label="Filter table rows">
            <button type="button" class="app-table-tools__clear">Clear</button>
        </div>
    `;

    const columnSelect = controls.querySelector('select');
    const filterInput = controls.querySelector('input');
    const clearButton = controls.querySelector('button');

    sortableHeaders.forEach(({ index, label }) => {
        const option = document.createElement('option');
        option.value = String(index);
        option.textContent = label;
        columnSelect.appendChild(option);
    });

    const parent = table.parentElement;
    const wrapper = parent?.classList.contains('overflow-x-auto')
        || parent?.classList.contains('weekly-consolidated-scroll')
        ? parent
        : table;
    const controlsBelongInsideWrapper = wrapper !== table && (
        wrapper.classList.contains('dept-tab-pane')
        || wrapper.classList.contains('hidden')
        || wrapper.id?.startsWith('tab-pane-')
        || wrapper.dataset.tableControlsInside === 'true'
    );

    if (controlsBelongInsideWrapper) {
        table.before(controls);
    } else {
        wrapper.before(controls);
    }
    tableControls.set(table, controls);

    sortableHeaders.forEach(({ th, index }) => {
        th.classList.add('app-table-sortable');
        th.setAttribute('tabindex', '0');
        th.setAttribute('role', 'button');
        th.setAttribute('aria-sort', 'none');

        if (!th.querySelector('[data-table-sort-indicator]')) {
            const indicator = document.createElement('span');
            indicator.dataset.tableSortIndicator = '';
            indicator.className = 'app-table-sort-indicator';
            indicator.textContent = '↕';
            th.appendChild(indicator);
        }

        const handleSort = (event) => {
            if (event.target.closest('a, button, input, select, textarea, label')) return;

            const current = th.getAttribute('aria-sort');
            const direction = current === 'ascending' ? 'desc' : 'asc';
            sortTable(table, index, direction);
        };

        th.addEventListener('click', handleSort);
        th.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                handleSort(event);
            }
        });
    });

    const filter = () => applyFilter(table, filterInput, columnSelect);
    filterInput.addEventListener('input', filter);
    columnSelect.addEventListener('change', filter);

    const tableKey = table.dataset.tableKey;
    const isMegaTable = tableKey && tableKey.startsWith('mega-table-');
    const isWeeklyTable = tableKey && tableKey.startsWith('weekly-consolidated-');

    if (isMegaTable || isWeeklyTable) {
        const handleServerSearch = (query) => {
            const url = new URL(window.location.href);
            let paramName = '';
            let pageParamName = '';

            if (isMegaTable) {
                const deptKey = tableKey.replace('mega-table-', '');
                paramName = `search_mega_${deptKey}`;
                pageParamName = `mega_${deptKey}_page`;
            } else if (isWeeklyTable) {
                paramName = 'search_weekly';
                pageParamName = 'weekly_page';
            }

            if (query) {
                url.searchParams.set(paramName, query);
            } else {
                url.searchParams.delete(paramName);
            }
            url.searchParams.delete(pageParamName); // reset page to 1

            // Preserve active tab in URL
            if (isMegaTable) {
                const deptKey = tableKey.replace('mega-table-', '');
                url.searchParams.set('tab', deptKey);
            } else if (isWeeklyTable) {
                let storedWeeklyDept = '';
                try {
                    storedWeeklyDept = window.sessionStorage.getItem(window.CMIHDashboardWeeklyStorageKey || 'cmih.dashboard.weeklyDepartment') || '';
                } catch (error) {
                    storedWeeklyDept = '';
                }

                const weeklyDept = tableKey.replace('weekly-consolidated-', '')
                    || window.currentWeeklyConsolidatedDepartment
                    || storedWeeklyDept;
                if (weeklyDept) {
                    url.searchParams.set('weekly_department', weeklyDept);
                    window.currentWeeklyConsolidatedDepartment = weeklyDept;
                    try {
                        window.sessionStorage.setItem(window.CMIHDashboardWeeklyStorageKey || 'cmih.dashboard.weeklyDepartment', weeklyDept);
                    } catch (error) {
                        // Ignore disabled storage; the URL still keeps the active department.
                    }
                }
            }

            if (isMegaTable && typeof window.loadMegaTableUrl === 'function') {
                const deptKey = tableKey.replace('mega-table-', '');
                window.loadMegaTableUrl(url.toString(), {
                    pushState: true,
                    preserveScroll: true,
                    activeTab: deptKey,
                    isUserNavigation: true,
                });
            } else if (isWeeklyTable && typeof window.loadWeeklyConsolidatedUrl === 'function') {
                window.loadWeeklyConsolidatedUrl(url.toString(), {
                    pushState: true,
                    preserveScroll: true,
                    isUserNavigation: true,
                    weeklyDepartment: url.searchParams.get('weekly_department'),
                });
            } else if (window.CMIHSilentNavigation?.navigate) {
                window.CMIHSilentNavigation.navigate(url.toString(), {
                    source: table,
                    updateHistory: true,
                });
            }
        };

        filterInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                handleServerSearch(filterInput.value);
            }
        });

        clearButton.addEventListener('click', () => {
            filterInput.value = '';
            columnSelect.value = '';
            handleServerSearch('');
        });

        // Set initial search value from URL
        const urlParams = new URLSearchParams(window.location.search);
        let paramName = '';
        if (isMegaTable) {
            const deptKey = tableKey.replace('mega-table-', '');
            paramName = `search_mega_${deptKey}`;
        } else if (isWeeklyTable) {
            paramName = 'search_weekly';
        }
        const initialQuery = urlParams.get(paramName);
        if (initialQuery !== null) {
            filterInput.value = initialQuery;
        } else {
            const state = readState(table);
            if (state.filter) {
                filterInput.value = state.filter;
            }
        }
    } else {
        clearButton.addEventListener('click', () => {
            filterInput.value = '';
            columnSelect.value = '';
            filter();
        });

        const state = readState(table);
        if (state.filter) {
            filterInput.value = state.filter;
        }
    }

    const state = readState(table);
    if (state.filterColumn !== undefined && Array.from(columnSelect.options).some((option) => option.value === String(state.filterColumn))) {
        columnSelect.value = String(state.filterColumn);
    }
    filter();

    if (state.sortColumn !== undefined && headers[Number(state.sortColumn)]) {
        sortTable(table, Number(state.sortColumn), state.sortDirection === 'desc' ? 'desc' : 'asc');
    }
};

const enhanceTables = (root = document) => {
    root.querySelectorAll('table').forEach(enhanceTable);
};

window.enhanceTables = enhanceTables;

document.addEventListener('DOMContentLoaded', () => {
    enhanceTables();

    const observer = new MutationObserver((mutations) => {
        const needsEnhance = mutations.some((mutation) => Array.from(mutation.addedNodes).some((node) => {
            if (!(node instanceof HTMLElement)) return false;

            return node.matches?.('table') || node.querySelector?.('table');
        }));

        if (needsEnhance) {
            window.requestAnimationFrame(() => enhanceTables());
        }
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
    });
});
