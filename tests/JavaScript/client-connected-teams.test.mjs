import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';
const source=readFileSync(new URL('../../resources/js/client-relations-centre.js',import.meta.url),'utf8').replace(/^import .*;\n/,'').split('function init(scope=')[0];
const Centre=vm.runInNewContext(source+';ClientBusinessCentre',{Intl,Date});
const centre=()=>Object.create(Centre.prototype);
test('Client Relations project panel exposes the same live team return endpoint',()=>{
 const c=centre();c.data={stages:{}};c.stage=()=> 'In progress';
 const html=c.projectOverview({id:7,title:'Shared launch',stage:'ongoing',owner:{name:'Account Owner'},week:'2026-11-16',money:{},connected_url:'/projects/7/accountability'});
 assert.match(html,/Connected teams · commitments &amp; updates|Connected teams · commitments & updates/);assert.match(html,/data-cr-connected-return="\/projects\/7\/accountability"/);
});
test('team return refreshes from the server and does not write an outdated project response into a detached panel',async()=>{
 const c=centre();const element={dataset:{crConnectedReturn:'/projects/7/accountability'},isConnected:true,innerHTML:''};c.content={querySelectorAll:()=>[element]};
 let calls=0;c.api=async()=>({html:'Shared HOD commitment '+(++calls)});await c.loadConnectedTeams();assert.equal(element.innerHTML,'Shared HOD commitment 1');await c.loadConnectedTeams();assert.equal(element.innerHTML,'Shared HOD commitment 2');
 c.api=async()=>{element.isConnected=false;return {html:'Old project response'};};await c.loadConnectedTeams();assert.equal(element.innerHTML,'Shared HOD commitment 2');assert.equal(element.dataset.loading,'false');
});
test('failed shared team loads offer a retry and recover without stale cache',async()=>{
 const c=centre();const element={dataset:{crConnectedReturn:'/projects/7/accountability'},isConnected:true,innerHTML:''};c.content={querySelectorAll:()=>[element]};c.api=async()=>{throw Error('Offline');};await c.loadConnectedTeams();assert.match(element.innerHTML,/data-cr-connection-retry/);
 c.api=async()=>({html:'Current team feedback'});await c.loadConnectedTeams();assert.equal(element.innerHTML,'Current team feedback');
});

test('focus rate sits beside the engaged-period heading and stays independent of pagination',()=>{
 const c=centre();c.data={summary:{portfolio_clients:81,engaged_clients:6,focus_rate:7.4},client_options:Array.from({length:81},(_,i)=>({name:'Account '+i,key:'account-'+i,engaged:i<6}))};
 const html=c.accountTabs();assert.match(html,/<h4>Engaged This Period <span>6<\/span><small class="cr-focus-rate"/);assert.match(html,/Focus Rate <strong>7.4%/);assert.match(html,/6 \/ 81 accounts/);assert.match(html,/All Accounts <span>81/);
 c.data.summary={portfolio_clients:0,engaged_clients:0,focus_rate:null};c.data.client_options=[];assert.match(c.accountTabs(),/Focus Rate <strong>—/);assert.doesNotMatch(c.accountTabs(),/NaN|Infinity/);
});
