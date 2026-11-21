import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';
const source=readFileSync(new URL('../../resources/js/theme.js',import.meta.url),'utf8');
test('theme switching still works when browser storage access is blocked',()=>{
 const doc={documentElement:{dataset:{},style:{}},querySelectorAll:()=>[],addEventListener(){}};
 const ctx=vm.createContext({document:doc,window:{localStorage:{getItem(){throw Error('Blocked');},setItem(){throw Error('Blocked');}}}});
 const f=vm.runInContext(source+';({getStoredTheme,applyTheme})',ctx);assert.equal(f.getStoredTheme(),null);f.applyTheme('light');assert.equal(doc.documentElement.dataset.theme,'light');f.applyTheme('dark');assert.equal(doc.documentElement.dataset.theme,'dark');
});
