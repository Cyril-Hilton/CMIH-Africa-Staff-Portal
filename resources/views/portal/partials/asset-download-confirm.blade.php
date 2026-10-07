<dialog id="asset-download-confirm" aria-labelledby="asset-download-title" style="border:1px solid #444;border-radius:18px;padding:24px;background:#151515;color:#fff;max-width:380px;width:calc(100% - 32px)">
    <h2 id="asset-download-title" style="font-size:20px;font-weight:700;margin:0 0 12px">Download asset label?</h2>
    <p style="color:#bbb;margin:0 0 24px">Download this QR code or sticker to save and print?</p>
    <div style="display:flex;justify-content:flex-end;gap:12px">
        <button type="button" data-download-cancel style="padding:10px 18px;border:1px solid #555;border-radius:24px;background:transparent;color:#fff;cursor:pointer">Cancel</button>
        <button type="button" data-download-confirm style="padding:10px 18px;border:0;border-radius:24px;background:#e50914;color:#fff;cursor:pointer">Download</button>
    </div>
</dialog>
<style>#asset-download-confirm::backdrop { background:rgba(0,0,0,.75); }</style>
<script>
(() => {
    if (window.cmihAssetDownloadConfirmation) return;
    window.cmihAssetDownloadConfirmation = true;
    let pending = null;
    let approved = null;
    document.addEventListener('click', (event) => {
        const control = event.target.closest('[data-asset-download]');
        if (!control) return;
        if (approved === control) { approved = null; return; }
        event.preventDefault();
        event.stopImmediatePropagation();
        pending = control;
        const loader = document.getElementById('global-loader');
        if (loader) loader.classList.add('hidden', 'opacity-0');
        sessionStorage.removeItem('show-loader');
        document.getElementById('asset-download-confirm').showModal();
    }, true);
    document.addEventListener('click', (event) => {
        const dialog = document.getElementById('asset-download-confirm');
        if (event.target.closest('[data-download-cancel]')) { pending = null; dialog.close(); }
        if (event.target.closest('[data-download-confirm]') && pending) {
            const control = pending;
            pending = null;
            dialog.close();
            approved = control;
            control.click();
            approved = null;
        }
    });
})();
</script>
