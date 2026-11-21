import test from 'node:test';
import assert from 'node:assert/strict';
import {createNotificationPoller} from '../../resources/js/notification-polling.js';
const data={timestamp:'2026-11-21T09:00:00Z',notifications:[]};
test('expired sessions stop every subsequent poll, including focus/visibility retries',async()=>{
 for(const status of [401,403]){let calls=0;const states=[];const poll=createNotificationPoller({getUrl:()=>'/poll',fetcher:async()=>{calls++;return {status};},onData:()=>assert.fail('Expired session produced data'),onState:s=>states.push(s)});await poll();await poll();assert.equal(calls,1);assert.deepEqual(states,['signed_out']);}
});
test('temporary HTML outages retain data and recover with backoff without parsing an error page',async()=>{
 let calls=0,received=0,time=0;const states=[];const poll=createNotificationPoller({getUrl:()=>'/poll',clock:()=>time,fetcher:async()=>++calls===1?{status:503,ok:false,json:()=>assert.fail('Parsed HTML outage')}:{status:200,ok:true,json:async()=>data},onData:()=>received++,onState:s=>states.push(s)});
 await poll();await poll();assert.equal(calls,1);assert.equal(received,0);time=15000;await poll();assert.equal(received,1);assert.deepEqual(states,['reconnecting','live']);
});
test('overlapping polls are suppressed and authenticated reads are never cached',async()=>{
 let resolve,calls=0;const wait=new Promise(r=>resolve=r);const poll=createNotificationPoller({getUrl:()=>'/poll',fetcher:async(url,opts)=>{calls++;assert.equal(opts.credentials,'same-origin');assert.equal(opts.cache,'no-store');await wait;return {status:200,ok:true,json:async()=>data};},onData:()=>{}});
 const first=poll();await poll();assert.equal(calls,1);resolve();await first;
});
test('incomplete JSON does not overwrite badges or advance the notification cursor',async()=>{
 const states=[];const poll=createNotificationPoller({getUrl:()=>'/poll',fetcher:async()=>({status:200,ok:true,json:async()=>({message:'Maintenance'})}),onData:()=>assert.fail('Invalid data accepted'),onState:s=>states.push(s)});await poll();assert.deepEqual(states,['reconnecting']);
});
