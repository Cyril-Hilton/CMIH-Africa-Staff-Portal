const escapeText = value => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
let copyDialog;
document.addEventListener('click', async event => {
    const trigger=event.target.closest('[data-copy-week]');
    if(!trigger)return;
    copyDialog?.remove();
    copyDialog=document.createElement('dialog');
    copyDialog.className='dashboard-copy-dialog';
    copyDialog.setAttribute('aria-labelledby','copy-week-title');
    copyDialog.innerHTML=`<form data-copy-week-form><h2 id="copy-week-title">Copy Entire Week</h2><p>Preview every saved activity in the source week, across all pages. Existing destination entries stay unchanged.</p><div class="copy-week-dates"><label>Source week<input name="source_week" type="date" value="${escapeText(trigger.dataset.sourceWeek)}" required></label><label>Destination week<input name="target_week" type="date" value="${escapeText(trigger.dataset.targetWeek)}" required max="${escapeText(trigger.dataset.targetWeek)}"></label></div><p>Weeks begin on Monday. Only weeks that have already begun can receive drafts.</p><button type="button" data-copy-preview>Preview Activities</button><p role="status" data-copy-status></p><div data-copy-items></div><p>Reusable targets, responsibilities and formatting are retained. Previous progress and results remain labelled references; approvals, completion and actual figures are reset.</p><div class="copy-week-actions"><button type="button" data-copy-cancel>Cancel</button><button type="submit" data-copy-submit disabled>Copy Selected Activities</button></div></form>`;
    document.body.append(copyDialog);copyDialog.showModal();
    const form=copyDialog.querySelector('form'), status=copyDialog.querySelector('[data-copy-status]'), list=copyDialog.querySelector('[data-copy-items]'), submit=copyDialog.querySelector('[data-copy-submit]');
    let previewKey='';
    const dates=()=>({department:trigger.dataset.department,source_week:form.source_week.value,target_week:form.target_week.value});
    const api=async(url,options={})=>{
        const response=await fetch(url,{...options,headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,...options.headers}});
        const data=await response.json();if(!response.ok)throw Error(Object.values(data.errors??{}).flat().join(' ')||data.message||'Please refresh and try again.');return data;
    };
    const count=()=>{const selected=list.querySelectorAll('input:checked').length;submit.disabled=!selected;submit.textContent=`Copy ${selected} Selected ${selected===1?'Activity':'Activities'}`;};
    const preview=async()=>{
        submit.disabled=true;list.innerHTML='';status.textContent='Loading source activities…';
        const key=JSON.stringify(dates());previewKey='';
        try{const data=await api(trigger.dataset.previewUrl+'?'+new URLSearchParams(dates()));if(key!==JSON.stringify(dates()))return;
            list.innerHTML=data.items.map(item=>`<label class="copy-week-item"><input name="items[]" type="checkbox" value="${item.id}" ${item.selected?'checked':''}><span><strong>${escapeText(item.title)}</strong><br>${escapeText(item.client||'Internal')} · ${escapeText(item.status)}${item.existing?' · Already exists; will be kept unchanged':''}</span></label>`).join('');
            status.textContent=`${data.total} source activities. Completed and deferred activities are unselected by default.`;previewKey=key;count();
        }catch(error){status.textContent=error.message;}
    };
    copyDialog.querySelector('[data-copy-preview]').addEventListener('click',preview);
    copyDialog.querySelector('[data-copy-cancel]').addEventListener('click',()=>{copyDialog.close();copyDialog.remove();});
    form.addEventListener('change',event=>{if(event.target.type==='date'){previewKey='';submit.disabled=true;status.textContent='Dates changed. Preview the activities again.';}else count();});
    form.addEventListener('submit',async event=>{event.preventDefault();if(previewKey!==JSON.stringify(dates()))return;submit.disabled=true;status.textContent='Creating weekly drafts…';
        try{const body=new FormData(form);body.set('department',trigger.dataset.department);const data=await api(trigger.dataset.storeUrl,{method:'POST',body});status.textContent=data.message;location.assign(data.url);}catch(error){status.textContent=error.message;count();}
    });
    await preview();
});
document.addEventListener('change',event=>{
    if(!event.target.matches('[data-week-picker]')||!event.target.value)return;
    const url=new URL(location.href);url.searchParams.set('weekly_from',event.target.value);url.searchParams.set('weekly_to',event.target.selectedOptions[0].dataset.end);url.searchParams.delete('weekly_auto');
    for(const key of [...url.searchParams.keys()])if(key.endsWith('_page')||['brands_search','department_entry_search','search_weekly'].includes(key))url.searchParams.delete(key);
    url.hash='staff-weekly-performance';location.assign(url);
});
document.addEventListener('click',event=>{
    if(!event.target.closest('[data-dashboard-present]'))return;
    document.body.classList.toggle('dashboard-presentation');
    const button=event.target.closest('[data-dashboard-present]');
    const active=document.body.classList.contains('dashboard-presentation');button.setAttribute('aria-pressed',String(active));button.textContent=active?'Exit Presentation Mode':'Presentation Mode';
});

document.addEventListener('keydown',event=>{if(event.key==='Escape'&&document.body.classList.contains('dashboard-presentation')){document.body.classList.remove('dashboard-presentation');const b=document.querySelector('[data-dashboard-present]');if(b){b.setAttribute('aria-pressed','false');b.textContent='Presentation Mode';}}});
