document.addEventListener('change', (e: Event) => {
    if (!(e.target instanceof HTMLInputElement)) return;

    if (e.target.id === 'select-all-checkbox') {
        document.querySelectorAll<HTMLInputElement>('.article-select-checkbox').forEach((cb) => {
            cb.checked = e.target instanceof HTMLInputElement && e.target.checked;
        });
        updateBulkDeleteButton();
        return;
    }

    if (e.target.classList.contains('article-select-checkbox')) {
        syncSelectAllCheckbox();
        updateBulkDeleteButton();
    }
});

document.body.addEventListener('htmx:afterSwap', (e: Event) => {
    const detail = (e as CustomEvent).detail as { target?: HTMLElement } | undefined;
    const isInfiniteScroll = detail?.target?.id === 'scroll-sentinel';

    if (isInfiniteScroll) {
        const selectAll = document.getElementById('select-all-checkbox');
        if (selectAll instanceof HTMLInputElement && selectAll.checked) {
            document.querySelectorAll<HTMLInputElement>('.article-select-checkbox').forEach((cb) => {
                cb.checked = true;
            });
        }
    } else {
        const selectAll = document.getElementById('select-all-checkbox');
        if (selectAll instanceof HTMLInputElement) selectAll.checked = false;
    }

    updateBulkDeleteButton();
});

function updateBulkDeleteButton(): void {
    const checked = document.querySelectorAll('.article-select-checkbox:checked').length;
    const btn = document.getElementById('bulk-delete-btn');
    if (btn instanceof HTMLButtonElement) {
        btn.disabled = checked === 0;
    }
}

function syncSelectAllCheckbox(): void {
    const all = document.querySelectorAll('.article-select-checkbox');
    const checked = document.querySelectorAll('.article-select-checkbox:checked');
    const selectAll = document.getElementById('select-all-checkbox');
    if (selectAll instanceof HTMLInputElement) {
        selectAll.checked = all.length > 0 && all.length === checked.length;
    }
}
