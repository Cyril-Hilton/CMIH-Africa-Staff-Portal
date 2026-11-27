import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';
const source=readFileSync(new URL('../../resources/js/creative-studio.js',import.meta.url),'utf8').split('function initCreativeStudios')[0];
const Studio=vm.runInNewContext(source+';CreativeStudio',{URL,URLSearchParams,Intl,Date});
function studio(metrics){const s=Object.create(Studio.prototype);Object.assign(s,{data:{delivery_metrics:metrics,kpi_snapshot:{items:[]},capacity:[]},items:[],filter:{},list:()=>'',kpis:()=>''});return s;}
test('Creative overview keeps unavailable rates unmeasured instead of substituting 0%',()=>{
 const html=studio({on_time_credit:null,delivery_credit:null,quality_credit:null}).overview();
 assert.equal((html.match(/Not Measured/g)??[]).length,3);
 assert.doesNotMatch(html,/<strong>0%<\/strong>/);
});
test('Creative overview preserves measured zero and a genuine 75 percent result',()=>{
 const html=studio({on_time_credit:75,delivery_credit:0,quality_credit:null}).overview();
 assert.match(html,/<strong>75%<\/strong>/);assert.match(html,/<strong>0%<\/strong>/);
});
test('Creative performance cards do not turn missing deadline measurement into zero',()=>{
 assert.match(studio({on_time_credit:null}).performance(),/Not measured/);
 assert.match(studio({on_time_credit:0}).performance(),/0%/);
});
