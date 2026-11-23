@once
<script>
(() => {
    if (window.cmihRankingAttentionTimer) clearInterval(window.cmihRankingAttentionTimer);
    let refreshing = false;
    const refresh = async () => {
        if (document.hidden || refreshing || !document.querySelector('[data-ranking-attention-panel], [data-ranking-attention-indicator]')) return;
        refreshing = true;
        try {
            const response = await fetch(location.href, {headers: {'Accept': 'text/html'}, credentials: 'same-origin', cache: 'no-store'});
            if (!response.ok || response.redirected || !response.headers.get('content-type')?.includes('text/html')) return;
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            for (const selector of ['[data-ranking-attention-panel]', '[data-ranking-attention-indicator]']) {
                const current = document.querySelector(selector);
                const updated = page.querySelector(selector);
                if (current && updated) {
                    const expanded = new Map([...current.querySelectorAll('details[data-ranking-disclosure]')].map(node => [node.dataset.rankingDisclosure, node.open]));
                    const replacement = document.importNode(updated, true);
                    replacement.querySelectorAll('details[data-ranking-disclosure]').forEach(node => { if (expanded.has(node.dataset.rankingDisclosure)) node.open = expanded.get(node.dataset.rankingDisclosure); });
                    current.replaceWith(replacement);
                }
            }
        } catch (_) {
            // Keep the last valid flags visible if the connection is temporarily unavailable.
        } finally { refreshing = false; }
    };
    window.cmihRankingAttentionTimer = setInterval(refresh, 60000);
})();
</script>
@endonce
