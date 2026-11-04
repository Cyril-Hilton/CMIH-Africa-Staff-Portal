const saving=new WeakSet();
async function refresh(root,message){
 const response=await fetch(root.dataset.endpoint,{headers:{Accept:'text/html'},credentials:'same-origin',cache:'no-store'});
 if(!response.ok)throw Error('Saved, but the updated table could not load. Refresh this page before retrying.');
 const doc=new DOMParser().parseFromString(await response.text(),'text/html');
 const next=doc.querySelector('[data-action-points]');if(!next)throw Error('Saved. Refresh this page to see the updated table.');
 root.replaceWith(next);mount(next);next.querySelector('[data-action-status]').textContent=message;
}
async function save(root,form,select=null){
 const key=select??form;if(saving.has(key))return;saving.add(key);
 const button=form?.querySelector('button[type=submit]');const previous=button?.textContent;
 const status=root.querySelector('[data-action-status]');
 const body=form?new FormData(form):new FormData();
 if(select){body.set('_method','PATCH');body.set('status',select.value);body.set('_token',document.querySelector('meta[name=csrf-token]')?.content??'');select.disabled=true;}
 if(button){button.disabled=true;button.textContent='Saving…';}status.textContent='Saving action point…';
 let saved=false;
 try{
  const response=await fetch(form?.action??select.dataset.updateUrl,{method:'POST',body,credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]')?.content??''}});
  const data=await response.json();if(!response.ok)throw Error(Object.values(data.errors??{}).flat().join(' ')||data.message||'The action point could not be saved.');
  saved=true;
  if(form?.hasAttribute('data-action-delete'))form.closest('[data-action-row]')?.remove();
  await refresh(root,data.message??'Action point saved.');
 }catch(error){if(select&&!saved)select.value=select.dataset.currentStatus;status.textContent=error.message;status.setAttribute('role','alert');if(saved)status.textContent=error.message;}
 finally{saving.delete(key);if(button){button.disabled=false;button.textContent=previous;}if(select)select.disabled=false;}
}
function mount(root){if(root.dataset.actionMounted)return;root.dataset.actionMounted='true';root.addEventListener('click',event=>{const button=event.target.closest('button');if(!button)return;if(button.hasAttribute('data-action-edit'))root.querySelector(`[data-action-dialog="${button.dataset.actionEdit}"]`)?.showModal();if(button.hasAttribute('data-action-close'))button.closest('dialog')?.close();});
 root.addEventListener('submit',async event=>{const form=event.target;if(!(form instanceof HTMLFormElement))return;event.preventDefault();event.stopPropagation();if(!form.reportValidity()||saving.has(form))return;if(form.hasAttribute('data-action-delete')&&!await window.cmihConfirm('Delete this meeting action point?','Delete'))return;await save(root,form);});
 root.addEventListener('change',event=>{if(!event.target.matches('.action-point-quick-status-select'))return;event.stopPropagation();save(root,null,event.target);});
}
const init=()=>document.querySelectorAll('[data-action-points]').forEach(mount);
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
new MutationObserver(records=>{if(records.some(r=>r.addedNodes.length))init();}).observe(document.documentElement,{childList:true,subtree:true});
