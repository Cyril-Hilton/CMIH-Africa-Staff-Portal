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
