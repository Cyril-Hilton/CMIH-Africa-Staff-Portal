import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
const source=readFileSync(new URL('../../resources/js/action-confirmation.js',import.meta.url),'utf8').replace(/^import .*\n/,'');
for(const phase of ['click','submit'])test(`existing Creator dialog owns ${phase} confirmation without a second dialog`,async()=>{
 const handlers={};let prevented=false;let created=false;
 const form={closest:()=>null,hasAttribute:key=>key==='data-confirm',getAttribute:()=>null};
 const button={form,tagName:'BUTTON',type:'submit',getAttribute:()=>null,closest:()=>null};
 const document={addEventListener:(event,handler)=>handlers[event]=handler,querySelector:selector=>selector==='[data-confirm-dialog]'?{}:null,createElement:()=>{created=true;throw Error('Duplicate dialog');}};
 vm.runInNewContext(source,{document,window:{},WeakSet,actionFor:()=> 'Continue',URL});
 await handlers[phase]({target:phase==='click'?{closest:()=>button}:form,submitter:button,preventDefault:()=>{prevented=true;},stopImmediatePropagation:()=>{}});
 assert.equal(prevented,false);assert.equal(created,false);
});

test('the existing dialog accept button is not intercepted as another sensitive action', async () => {
 const handlers = {}; let prevented = false;
 const button = { form: null, tagName: 'BUTTON', type: 'button', getAttribute: () => null, closest: selector => selector === '[data-confirm-dialog]' ? {} : null };
 const document = { addEventListener: (event, handler) => handlers[event] = handler, querySelector: () => null, createElement: () => { throw Error('Duplicate dialog'); } };
 vm.runInNewContext(source, { document, window: {}, WeakSet, actionFor: () => 'Sign Out', URL });
 await handlers.click({ target: { closest: () => button }, preventDefault: () => { prevented = true; }, stopImmediatePropagation: () => {} });
 assert.equal(prevented, false);
});
