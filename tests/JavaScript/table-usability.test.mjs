import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';
const source=readFileSync(new URL('../../resources/js/table-enhancer.js',import.meta.url),'utf8').split('const enhanceTable =')[0];
let user='1';const location={pathname:'/dashboard',search:'?weekly_department=hr_admin&hr_tab=supplies&page=1'};let tables=[];
const context=vm.createContext({URLSearchParams,Date,window:{location},sessionStorage:{getItem:()=>null,setItem(){}},document:{querySelector:()=>({content:user}),querySelectorAll:()=>tables}});
const funcs=vm.runInContext(source+';({comparableValue,compareValues,tableStorageKey,sortTable})',context);
test('day-first Ghana dates sort chronologically across month and year boundaries',()=>{
 const compare=(a,b)=>funcs.compareValues(funcs.comparableValue(a),funcs.comparableValue(b));
 assert.ok(compare('30/10/2026','02/11/2026')<0);assert.ok(compare('31/12/2025','01/01/2026')<0);assert.ok(compare('21/11/2026 09:00','21/11/2026 10:00')<0);assert.equal(funcs.comparableValue('31/02/2026').type,'string');assert.ok(compare('GH₵ 1,200.00','GH₵ 950.00')>0);
});
test('sorting equal values keeps row order in both directions',()=>{
 const rows=[{id:'a',cells:[{textContent:'10'},{textContent:'A'}],dataset:{}},{id:'b',cells:[{textContent:'10'},{textContent:'B'}],dataset:{}},{id:'c',cells:[{textContent:'20'},{textContent:'C'}],dataset:{}}];let result=[];const body={rows,appendChild:r=>result.push(r.id)};const table={dataset:{},tBodies:[body],tHead:{querySelectorAll:()=>[]},closest:()=>null};tables=[table];funcs.sortTable(table,0,'desc');assert.deepEqual(result,['c','a','b']);result=[];funcs.sortTable(table,0,'asc');assert.deepEqual(result,['a','b','c']);
});
test('table filters do not leak between accounts or department tabs but survive pagination',()=>{
 const table={dataset:{},closest:()=>null};tables=[table];const first=funcs.tableStorageKey(table);location.search='?weekly_department=hr_admin&hr_tab=supplies&page=2';assert.equal(funcs.tableStorageKey(table),first);location.search='?weekly_department=hr_admin&hr_tab=people';assert.notEqual(funcs.tableStorageKey(table),first);location.search='?weekly_department=hr_admin&hr_tab=supplies';user='119';assert.notEqual(funcs.tableStorageKey(table),first);
});
