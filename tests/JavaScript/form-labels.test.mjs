import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
const {associateFormLabels}=await import('data:text/javascript;base64,'+Buffer.from(readFileSync(new URL('../../resources/js/form-labels.js',import.meta.url))).toString('base64'));
test('visible labels associate with unique controls and existing accessible names stay intact',()=>{
 const label={tagName:'LABEL',textContent:'Asset condition',htmlFor:''};
 const field={labels:[],hasAttribute:()=>false,previousElementSibling:label,id:''};
 const named={labels:[],hasAttribute:n=>n==='aria-label'};
 const root={querySelectorAll:()=>[field,named],getElementById:id=>id==='portal-field-1'?{}:null};
 associateFormLabels(root);assert.equal(field.id,'portal-field-2');assert.equal(label.htmlFor,field.id);assert.equal(named.id,'portal-field-2');
});
