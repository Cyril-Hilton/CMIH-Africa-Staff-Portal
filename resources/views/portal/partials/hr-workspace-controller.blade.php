<script>
(() => {
    const initialise = () => {
        const root = document.querySelector('[data-hr-workspace]');
        if (!root || root.dataset.ready) return;
        root.dataset.ready = 'true';
        const panels = [...document.querySelectorAll('[data-hr-panel]')];
        const tabs = [...root.querySelectorAll('[data-hr-tab]')];
        document.querySelectorAll('[data-hr-group]').forEach(tool => {
            document.querySelector(`[data-hr-panel="${tool.dataset.hrGroup}"]`)?.append(tool);
        });
        // Empty groups never suggest a privilege the user does not possess.
        tabs.forEach(tab => {
            const panel = panels.find(panel => panel.dataset.hrPanel === tab.dataset.hrTab);
            const tools = panel.querySelectorAll('[data-hr-group], .hr-workspace-tool');
            tab.hidden = !tools.length;
            if (tab.hidden) panel.hidden = true;
        });
        const available = () => tabs.filter(tab => !tab.hidden);
        const activate = (key, focus = false, remember = true) => {
            const tab = available().find(tab => tab.dataset.hrTab === key) || available()[0];
            if (!tab) return;
            tabs.forEach(item => {
                const selected = item === tab;
                item.setAttribute('aria-selected', String(selected)); item.tabIndex = selected ? 0 : -1;
            });
            panels.forEach(panel => panel.hidden = panel.dataset.hrPanel !== tab.dataset.hrTab);
            if (remember) {
                const url = new URL(location.href); url.searchParams.set('hr_view', tab.dataset.hrTab);
                history.replaceState(null, '', url);
            }
            if (focus) tab.focus();
        };
        const reveal = target => {
            if (!target) return false;
            const panel = target.closest('[data-hr-panel]');
            if (!panel) return false;
            activate(panel.dataset.hrPanel);
            let parent = target;
            while (parent && parent !== panel) { if (parent.tagName === 'DETAILS') parent.open = true; parent = parent.parentElement; }
            return true;
        };
        root.addEventListener('click', event => {
            const tab = event.target.closest('[data-hr-tab]'); if (tab) activate(tab.dataset.hrTab);
        });
        root.addEventListener('keydown', event => {
            const tab = event.target.closest('[data-hr-tab]');
            if (!tab || !['ArrowLeft','ArrowRight','Home','End'].includes(event.key)) return;
            event.preventDefault(); const choices=available(); const index=choices.indexOf(tab);
            const next=event.key==='Home'?0:event.key==='End'?choices.length-1:(index+(event.key==='ArrowRight'?1:-1)+choices.length)%choices.length;
            activate(choices[next].dataset.hrTab,true);
        });
        document.addEventListener('invalid', event => reveal(event.target), true);
        document.addEventListener('click', event => {
            const link=event.target.closest('[data-hr-panel] a[href]'); if (!link) return;
            const url=new URL(link.href,location.href);
            if (url.origin===location.origin && url.pathname===location.pathname) { url.searchParams.set('hr_view',link.closest('[data-hr-panel]').dataset.hrPanel);link.href=url.toString(); }
        });
        const routeHash = () => { let id;try{id=decodeURIComponent(location.hash.slice(1));}catch{return false;}return id?reveal(document.getElementById(id)):false; };
        const query=new URLSearchParams(location.search);
        let initial=query.get('hr_view');
        if (!initial) {
            if (query.has('leave_page')) initial='leave';
            else if (['v_page','pt_page','d_page','visitors_page','scheduled_page','directory_page'].some(key=>query.has(key))) initial='workplace';
            else if ((query.has('loan_page') || query.has('loans_page'))) initial='pay';
        }
        activate(initial||'people',false,false);
        routeHash(); window.addEventListener('hashchange',routeHash);
        const errorKeys=@json($errors->keys());
        if (errorKeys.length) {
            let field;
            for (const key of errorKeys) {
                const fieldName=key.replace(/\.(\d+|[^.]+)/g,'[$1]');
                field=[...document.querySelectorAll('input[name],select[name],textarea[name]')].find(input=>input.name===fieldName||input.name===key||input.name.startsWith(fieldName+'['));
                if (field) break;
            }
            if (field && reveal(field)) { field.setAttribute('aria-invalid','true'); field.scrollIntoView({block:'center'}); field.focus({preventScroll:true}); }
        }
    };
    if (document.readyState==='loading') document.addEventListener('DOMContentLoaded',initialise); else initialise();
})();
</script>
