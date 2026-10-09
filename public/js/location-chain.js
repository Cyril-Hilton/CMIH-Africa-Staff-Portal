(() => {
 const bindings=new WeakMap();
 class Autocomplete {
  constructor(input) {
   if(bindings.has(input))return bindings.get(input);
   bindings.set(input,this);this.input=input;this.listeners=[];this.place={};this.items=[];this.index=-1;this.sequence=0;
   const panel=document.createElement('div');this.panel=panel;panel.hidden=true;panel.setAttribute('role','listbox');panel.id='location-results-'+Math.random().toString(36).slice(2);panel.style.cssText='position:fixed;z-index:100000;background:#fff;color:#111;border:1px solid #bbb;border-radius:8px;box-shadow:0 8px 24px #0003;max-height:240px;overflow:auto;';document.body.append(panel);
   input.setAttribute('role','combobox');input.setAttribute('aria-controls',panel.id);input.setAttribute('aria-expanded','false');input.setAttribute('aria-autocomplete','list');
   const close=()=>{panel.hidden=true;input.setAttribute('aria-expanded','false');};this.close=close;
   input.addEventListener('input',()=>{clearTimeout(this.timer);this.sequence++;this.abort?.abort();close();const q=input.value.trim();if(q.length<3||/^https?:/.test(q))return;const seq=this.sequence;this.timer=setTimeout(()=>this.search(q,seq),400);});
   input.addEventListener('keydown',e=>{if(panel.hidden)return;if(e.key==='Escape'){close();return;}if(['ArrowDown','ArrowUp'].includes(e.key)){e.preventDefault();this.index=(this.index+(e.key==='ArrowDown'?1:-1)+this.items.length)%this.items.length;[...panel.querySelectorAll('[role=option]')].forEach((node,i)=>{node.setAttribute('aria-selected',i===this.index?'true':'false');node.style.background=i===this.index?'#eee':'#fff';});input.setAttribute('aria-activedescendant',panel.children[this.index]?.id||'');}else if(e.key==='Enter'&&this.index>=0){e.preventDefault();this.select(this.items[this.index]);}});
   document.addEventListener('pointerdown',e=>{if(e.target!==input&&!panel.contains(e.target))close();});window.addEventListener('scroll',close,{passive:true});window.addEventListener('resize',close);
  }
  addListener(event,fn){if(event==='place_changed')this.listeners.push(fn);return{remove:()=>{this.listeners=this.listeners.filter(x=>x!==fn);}};}
  getPlace(){return this.place;}
  async search(q,seq){
   this.abort=new AbortController();try{const response=await fetch('/api/location-fallback?q='+encodeURIComponent(q),{headers:{Accept:'application/json'},signal:this.abort.signal});if(!response.ok){if(seq===this.sequence)this.close();return;}const data=await response.json();if(seq!==this.sequence)return;this.items=data.results||[];this.index=-1;this.panel.replaceChildren();
    this.items.forEach((item,i)=>{const option=document.createElement('button');option.type='button';option.id=this.panel.id+'-'+i;option.setAttribute('role','option');option.style.cssText='display:block;width:100%;padding:12px;text-align:left;border:0;border-bottom:1px solid #eee;background:#fff;color:#111;white-space:normal;font:inherit;';option.textContent=item.name+(item.address&&item.address!==item.name?' — '+item.address:'');option.addEventListener('click',()=>this.select(item));this.panel.append(option);});
    const attribution=document.createElement('a');attribution.textContent=data.provider==='geoapify'?'© OpenStreetMap contributors · Geoapify':'Google Maps';attribution.href=data.provider==='geoapify'?'https://www.geoapify.com/':'https://maps.google.com/';attribution.target='_blank';attribution.rel='noopener';attribution.style.cssText='display:block;padding:8px;font-size:11px;color:#555';this.panel.append(attribution);const rect=this.input.getBoundingClientRect();Object.assign(this.panel.style,{left:Math.max(8,Math.min(rect.left,innerWidth-rect.width-8))+'px',top:Math.min(rect.bottom,innerHeight-100)+'px',width:Math.min(rect.width,innerWidth-16)+'px',maxHeight:Math.max(80,Math.min(240,innerHeight-rect.bottom-8))+'px'});this.panel.hidden=!this.items.length;this.input.setAttribute('aria-expanded',String(this.items.length>0));
   }catch(e){if(e.name!=='AbortError')this.close();}
  }
  select(item){this.close();this.input.value=item.name;this.place={name:item.name,formatted_address:item.address,url:`https://www.google.com/maps?q=${item.latitude},${item.longitude}`,geometry:{location:{lat:()=>item.latitude,lng:()=>item.longitude}}};
   const prefix={promo_location_ac:'promo',retail_location_ac:'retail',venue_modal_location:'venue_modal'}[this.input.id];if(prefix){for(const [suffix,value]of[['lat',item.latitude],['lng',item.longitude],['address',item.address]]){const field=document.getElementById(prefix+'_'+suffix);if(field)field.value=value;}}
   if(this.input.id==='location_url'){this.input.value=this.place.url;const label=document.getElementById('location_label');if(label&&!label.value)label.value=item.name;}
   this.listeners.forEach(fn=>fn());const hint=document.querySelector('[data-coordinate-hint-for="'+this.input.id+'"]');if(hint)hint.textContent='Location coordinates selected.';
  }
 }
 window.CmihPlacesAutocomplete=Autocomplete;
 const mount=()=>document.querySelectorAll('#location_url,#promo_location_ac,#retail_location_ac,#venue_modal_location').forEach(input=>{new Autocomplete(input);if(input.dataset.chainMounted)return;input.dataset.chainMounted='true';input.addEventListener('input',()=>{const prefix={promo_location_ac:'promo',retail_location_ac:'retail',venue_modal_location:'venue_modal'}[input.id];if(prefix)for(const suffix of ['lat','lng','address']){const field=document.getElementById(prefix+'_'+suffix);if(field)field.value='';}});});
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',mount);else mount();
})();
