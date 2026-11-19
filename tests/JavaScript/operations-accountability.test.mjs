import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';
const source=readFileSync(new URL('../../resources/js/operations-handover.js',import.meta.url),'utf8').split('function init()')[0];
const Desk=vm.runInNewContext(source+';HandoverDesk',{Intl,Date});
test('handover timeline distinguishes reported receipt, handover, both approvals and execution targets',()=>{
 const s=Object.create(Desk.prototype);const html=s.timeline({meeting:{held:'2026-11-17T09:00:00Z',cr_signoff:'2026-11-17T10:00:00Z',ops_signoff:'2026-11-17T11:00:00Z'},received:'2026-11-16T09:00:00Z',handover_approved_at:'2026-11-17T12:00:00Z',requester:{name:'Client Owner'},lead:{name:'Operations Owner'},client_brief_received_at:'2026-11-10T09:00:00Z',setup_due_at:'2026-11-20T09:00:00Z',event_at:'2026-11-21T09:00:00Z',execution_due_at:'2026-11-22T17:00:00Z',agreed_execution_due_at:'2026-11-22T18:00:00Z',stage:'planning'});
 for(const name of ['Client brief received · reported','Handover entered in portal','Both teams signed off','Operations HOD approved handover','Client Relations execution completion deadline','Operations HOD completion commitment','Execution evidence submitted in portal','Independent completion verified'])assert.ok(html.includes(name),name);
 assert.match(html,/17 Nov 2026, 11:00/);assert.match(html,/22 Nov 2026, 18:00/);assert.match(html,/Operations Owner/);
});
