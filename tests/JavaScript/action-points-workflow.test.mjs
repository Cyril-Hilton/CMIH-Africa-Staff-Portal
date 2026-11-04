import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';
const source=readFileSync(new URL('../../resources/js/action-points.js',import.meta.url),'utf8');
function environment(){
 const requests=[],status={textContent:'',setAttribute(){}};let replaced=0,removed=0;
 const next={dataset:{actionMounted:'true'},querySelector:()=>status};
 const root={dataset:{endpoint:'/portal/action-points/table'},querySelector:()=>status,replaceWith(node){assert.equal(node,next);replaced++;}};
 const button={textContent:'Update',disabled:false};
 const form={action:'/portal/action-points/23',querySelector:()=>button,hasAttribute:()=>true,closest:()=>({remove(){removed++;}})};
 const context={WeakSet,FormData:class {},document:{readyState:'loading',documentElement:{},addEventListener(){},querySelector:()=>({content:'token'}),querySelectorAll:()=>[]},MutationObserver:class{observe(){}},DOMParser:class{parseFromString(){return {querySelector:()=>next};}},fetch:async(url,options)=>{requests.push({url,options});if(options?.method==='POST')return {ok:true,json:async()=>({message:'Saved action point.'})};return {ok:true,text:async()=>'<div data-action-points></div>'};},window:{}};
 vm.createContext(context);vm.runInContext(source,context);return {context,requests,status,root,form,button,get replaced(){return replaced;},get removed(){return removed;}};
}
test('a saved deletion refreshes the action table without reloading a department dashboard',async()=>{const e=environment();await e.context.save(e.root,e.form);assert.deepEqual(e.requests.map(r=>r.url),['/portal/action-points/23','/portal/action-points/table']);assert.equal(e.removed,1);assert.equal(e.replaced,1);assert.equal(e.status.textContent,'Saved action point.');assert.equal(e.button.disabled,false);});
test('a second submission while the first is pending cannot delete the same record twice',async()=>{const e=environment();let release;const first=new Promise(resolve=>release=resolve);e.context.fetch=async(url,options)=>{e.requests.push({url,options});if(options?.method==='POST'){await first;return {ok:true,json:async()=>({message:'Saved'})};}return {ok:true,text:async()=>''};};const a=e.context.save(e.root,e.form);await e.context.save(e.root,e.form);assert.equal(e.requests.length,1);release();await a;assert.equal(e.requests.filter(r=>r.options?.method==='POST').length,1);});
test('a failed status update restores the displayed saved status and reports the actual error',async()=>{const e=environment();e.context.FormData=class{set(){}};e.context.fetch=async()=>({ok:false,json:async()=>({message:'Your session expired. Please sign in again.'})});const select={value:'done',dataset:{currentStatus:'pending',updateUrl:'/portal/action-points/23'}};await e.context.save(e.root,null,select);assert.equal(select.value,'pending');assert.match(e.status.textContent,/session expired/);assert.equal(select.disabled,false);assert.equal(e.replaced,0);});
