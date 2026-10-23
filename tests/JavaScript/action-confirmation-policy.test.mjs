import test from 'node:test';
import assert from 'node:assert/strict';
import { actionFor } from '../../resources/js/action-confirmation-policy.js';
const element = (text, attrs = {}) => ({textContent:text, dataset:attrs.confirm?{confirm:attrs.confirm}:{}, value:attrs.value, getAttribute:key=>attrs[key]??null, hasAttribute:key=>(key==='data-confirm' ? Object.hasOwn(attrs,'confirm') : Object.hasOwn(attrs,key))});
const form = (action, method=null) => ({dataset:{}, getAttribute:key=>key==='action'?action:null, querySelector:()=>method?{value:method}:null});
test('all download variants require confirmation',()=>{
 for(const [label,url] of [['Download PDF','/report'],['Export Excel','/report'],['Payslip','/portal/payroll/export-register'],['File','/files/sample.pdf']]) assert.equal(actionFor(element(label,{href:url})),'Download');
 assert.equal(actionFor(element('File',{download:''})),'Download');
});
test('destructive and sensitive actions are classified',()=>{
 for(const action of ['Suspend','Archive','Deactivate','Revoke','Reject','Approve','Restore','Publish','Reset','Demote','Transfer']) assert.equal(actionFor(element(action)),action);
 assert.equal(actionFor(element('Continue'),form('/users/4','delete')),'Delete');
 assert.equal(actionFor(element('Save'),form('/users/4/archive')),'Archive');
});
test('ordinary navigation and edits do not trigger confirmations',()=>{
 for(const label of ['Save Changes','View Report','Dashboard','Cancel','Next Page']) assert.equal(actionFor(element(label)),null);
});
test('explicit confirmation text works for custom actions',()=>assert.equal(actionFor(element('Proceed',{confirm:'Proceed with this action?'})),'Continue'));

test('archive directory is navigation rather than an archive action',()=>assert.equal(actionFor(element('Archive Directory',{href:'/admin/users?status=archived'})),null));

test('soft deletion uses the archive label',()=>assert.equal(actionFor(element('Archive User'),form('/admin/users/4','delete')),'Archive'));


test('filter resets stay ordinary navigation while password resets require confirmation',()=>{
 assert.equal(actionFor(element('Reset',{href:'/portal/performance'})),null);
 assert.equal(actionFor(element('Reset Password',{href:'/staff/4/reset-password'})),'Reset');
 assert.equal(actionFor(element('Return for correction'),form('/review')),'Return');
});

test('print and save PDF controls require confirmation',()=>assert.equal(actionFor(element('Print / save PDF')),'Download'));

test('sign-out controls require confirmation',()=>{assert.equal(actionFor(element('Log Out')),'Sign Out');assert.equal(actionFor(element('Sign Out')),'Sign Out');});

test('closing a view is not a business close action',()=>{
 assert.equal(actionFor(element('Close Handover',{'data-oh-close':''})),null);
 assert.equal(actionFor(element('Close Client',{'data-cr-close':''})),null);
 assert.equal(actionFor(element('Close Case'),form('/cases/4/close')),'Close');
});
