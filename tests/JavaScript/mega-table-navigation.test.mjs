import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';
const blade=readFileSync(new URL('../../resources/views/portal/dashboard.blade.php',import.meta.url),'utf8');
const start=blade.indexOf('// Generic filter navigation replaces this region');
const script=blade.slice(start,blade.indexOf('let megaTableRefreshing = false;',start));
test('date navigation restores the selected daily department and refreshes the live region reference',()=>{
 for(const selected of ['brands_marketing','client_relations','operations_projects','creatives','finance','hr_admin']){
  const old={},fresh={},calls=[];let onUpdate;
  const context=vm.createContext({document:{getElementById:()=>fresh,addEventListener:(name,fn)=>{assert.equal(name,'cmih:silent-content-updated');onUpdate=fn;}},window:{currentMegaTableTab:selected},departments:['brands_marketing','client_relations','operations_projects','creatives','finance','hr_admin'],megaTableRegion:old,switchTab:tab=>calls.push(tab)});
  vm.runInContext(script,context);onUpdate();
  assert.equal(context.megaTableRegion,fresh);assert.deepEqual(calls,[selected]);
  onUpdate();assert.deepEqual(calls,[selected]);
 }
});
test('a missing department selection has a visible fallback after replacement',()=>{
 let onUpdate;const calls=[];
 const context=vm.createContext({document:{getElementById:()=>({}),addEventListener:(_,fn)=>{onUpdate=fn;}},window:{currentMegaTableTab:'removed_department'},departments:['brands_marketing','finance'],megaTableRegion:{},switchTab:tab=>calls.push(tab)});
 vm.runInContext(script,context);onUpdate();assert.deepEqual(calls,['brands_marketing']);
});
