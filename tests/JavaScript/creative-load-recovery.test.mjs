import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';
const source=readFileSync(new URL('../../resources/js/creative-studio.js',import.meta.url),'utf8').split('function initCreativeStudios')[0];
const timers=[];
const Studio=vm.runInNewContext(source+';CreativeStudio',{URL,URLSearchParams,Intl,Date,TypeError,location:{href:'https://portal.cmih.africa/portal/creative'},document:{hidden:false,activeElement:null},setTimeout:(fn,delay)=>{timers.push({fn,delay});return timers.length;},clearTimeout:()=>{}});
const make=()=>{timers.length=0;const s=Object.create(Studio.prototype);Object.assign(s,{root:{isConnected:true,dataset:{endpoint:'/data'},matches:()=>true},content:{setAttribute(){},innerHTML:''},dialog:{open:false},view:'board',items:[],params:()=>'',render(){this.rendered=true;},message(text){this.notice=text;}});return s;};
test('temporary outage retries a read despite hover and replaces the error with real data',async()=>{
 const s=make();let calls=0;s.api=async()=>{if(++calls===1)throw Object.assign(Error('Service Unavailable'),{status:503});return {items:[{id:7}],pagination:{total:1}};};
 await s.load();assert.match(s.notice,/Reconnecting automatically/);assert.equal(timers[0].delay,5000);timers[0].fn();await new Promise(resolve=>setImmediate(resolve));
 assert.equal(s.rendered,true);assert.equal(s.items[0].id,7);assert.match(s.notice,/1 matching records/);assert.equal(s.loadRetryAttempt,0);
});
test('background outage retains loaded data and recovers without a manual refresh',async()=>{
 const s=make();s.data={};s.items=[{id:9}];s.api=async()=>{throw Object.assign(Error('Service Unavailable'),{status:503});};await s.load(false,true);
 assert.equal(s.items[0].id,9);assert.match(s.notice,/last loaded results/);assert.equal(timers.length,1);
});
test('retry defers while a dialog is open and never replays a write',async()=>{
 const s=make();s.loadSequence=1;s.dialog.open=true;let reads=0;s.load=()=>reads++;s.scheduleLoadRetry();timers[0].fn();assert.equal(reads,0);assert.equal(timers.length,2);s.dialog.open=false;timers[1].fn();assert.equal(reads,1);
});
test('permission failures do not trigger automatic retry',async()=>{
 const s=make();s.api=async()=>{throw Object.assign(Error('Forbidden'),{status:403});};await s.load();assert.equal(timers.length,0);assert.equal(s.notice,'Forbidden');
});
