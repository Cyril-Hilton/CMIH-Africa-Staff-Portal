import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';
const context={Intl,Date,document:{readyState:'loading',documentElement:{},addEventListener(){}},MutationObserver:class{observe(){}}};
vm.createContext(context);vm.runInContext(readFileSync(new URL('../../resources/js/brands-reports.js',import.meta.url),'utf8'),context);
const renderer=vm.runInContext('BrandsReports.prototype',context);
test('collaborator markup separates names and duties while treating both as text',()=>{
 const html=renderer.cell([{name:'<img src=x onerror=alert(1)>',responsibility:'<script>unsafe()</script>\nBudget sign-off'}],'people');
 assert.match(html,/<strong>&lt;img/);assert.match(html,/class="br-person-duty">&lt;script&gt;/);assert.doesNotMatch(html,/<img|<script/);assert.match(html,/Budget sign-off/);
});
test('tables expose column sorting and keep pagination scoped to their own table',()=>{
 const t={id:'people',title:'People',available:14,total:4,page:1,last:1,filter:{column:'count',q:'10',sort:'count',direction:'desc'},columns:[{key:'count',label:'Count',format:'number'}],rows:[{count:10,source_type:'register',id:12}]};
 const html=renderer.table.call({form:{elements:{per_page:{value:'10'}}},data:{can_enter:true},cell:renderer.cell},t);
 assert.match(html,/aria-sort="descending"/);assert.match(html,/data-br-column-sort="count"/);assert.match(html,/Search across all 14 entries/);assert.match(html,/data-br-table="people" data-br-page/);assert.match(html,/class="br-row-action"/);
});

test('workflow states use distinct labelled colours',()=>{
 for(const [value,tone] of [['Planned','info'],['In Progress','attention'],['Done','success'],['Blocked','danger'],['Urgent','danger']]){
  const html=renderer.cell(value,'text','status');assert.match(html,new RegExp('br-status-'+tone));assert.ok(html.includes(value));
 }
});

test('a failed report request preserves the last displayed table', async () => {
 const original = '<table>Last loaded report</table>';
 context.FormData = class { *[Symbol.iterator]() {} };
 context.URLSearchParams = URLSearchParams;
 const busy = [];
 const messages = [];
 const workspace = {
  content: {innerHTML: original, setAttribute: (_, value) => busy.push(value)},
  form: {}, root: {dataset: {endpoint: '/reports'}}, data: {tab: 'workstream'},
  tab: 'workstream', partner: 'castel', page: 1, tableFilters: {},
  api: async () => {throw new Error('This action could not be saved.');},
  message: (...args) => messages.push(args),
 };
 await renderer.load.call(workspace);
 assert.equal(workspace.content.innerHTML, original);
 assert.match(messages.at(-1)[0], /last loaded results are still shown/);
 assert.deepEqual(busy, ['true', 'false']);
});

for (const [file, className, parameters, backgroundParameters] of [
 ['creative-studio.js', 'CreativeStudio', [], [false, true]],
 ['client-relations-centre.js', 'ClientBusinessCentre', [], [true]],
 ['operations-handover.js', 'HandoverDesk', [], null],
]) {
 test(file + ' keeps loaded results when a request fails', async () => {
  const sandbox = vm.createContext({
   document: {readyState: 'loading', documentElement: {}, addEventListener() {}},
   MutationObserver: class {observe() {}},
   URLSearchParams,
   window: {},

  });
  vm.runInContext(readFileSync(new URL('../../resources/js/' + file, import.meta.url), 'utf8'), sandbox);
  const load = vm.runInContext(className + '.prototype.load', sandbox);
  const content = {innerHTML: '<table>Previously loaded results</table>', setAttribute() {}};
  const messages = [];
  const workspace = {
   content, data: {}, root: {dataset: {endpoint: '/reports'}},
   params: () => '', api: async () => {throw new Error('Temporary failure');},
   message: (...args) => messages.push(args),
  };
  await load.apply(workspace, parameters);
  assert.match(content.innerHTML, /Previously loaded results/);
  assert.match(messages.at(-1)[0], /last loaded results are still shown/);
  if (backgroundParameters) {
   messages.length = 0;
   await load.apply(workspace, backgroundParameters);
   assert.match(content.innerHTML, /Previously loaded results/);
   assert.deepEqual(messages, []);
  }
 });
}
test('People scopes follow their group and do not retain an incompatible selection',()=>{
 const group={value:'activations'},scope={value:'unilever',innerHTML:''};
 const workspace={body:{querySelector:selector=>selector==='[name=cohort]'?group:scope},queueData:{people_scopes:{activations:{vaseline:'Vaseline',heineken:'Heineken'},unilever:{unilever:'Unilever'}}}};
 renderer.peopleScopes.call(workspace);assert.equal(scope.value,'');assert.match(scope.innerHTML,/Vaseline/);assert.match(scope.innerHTML,/Heineken/);assert.doesNotMatch(scope.innerHTML,/Unilever/);
 group.value='unilever';renderer.peopleScopes.call(workspace);assert.equal(scope.value,'unilever');assert.equal(group.required,true);assert.equal(scope.required,true);
});

test('new report forms omit correction notes while published corrections require them', async()=>{
 const render=async(entry)=>{let html='';const workspace={queueData:{schemas:{people:{}},can_review:true,actor:1},tab:'people',cohort:'internal',root:{querySelector:()=>({textContent:'People'})},form:{elements:{date:{value:'2026-11-09'}}},data:{staff:[{id:1,name:'William'}]},show:(_,value)=>{html=value;},body:{querySelector:()=>({value:''})},fields:()=>{},message:message=>{throw Error(message);}};await renderer.entryForm.call(workspace,entry);return html;};
 assert.doesNotMatch(await render(null),/name="reason"/);
 assert.doesNotMatch(await render({source_id:1,is_draft:true,payload:{category:'people',week:'2026-11-09'}}),/name="reason"/);
 assert.match(await render({source_id:1,payload:{category:'people',week:'2026-11-09'}}),/Correction Note<textarea name="reason" required/);
});

test('People entry management keeps history and correction access without repeating the figures table',()=>{
 let dialog='';const workspace={data:{tables:[{id:'people',total:12,page:1,last:2,rows:[{id:7,source_type:'register',title:'<script>Report</script>',week:'2026-11-09',scope_label:'Unilever',owner:'William',can_edit:true,planned_count:123456}]},{id:'saved_reports',total:0,rows:[]}]},show:(_,html)=>{dialog=html;}};
 renderer.peopleRecords.call(workspace);
 assert.doesNotMatch(dialog,/<table|123456|<script>/);assert.match(dialog,/&lt;script&gt;Report/);assert.match(dialog,/data-br-detail="register:7"/);assert.match(dialog,/View \/ Correct/);assert.match(dialog,/data-br-record-page data-br-table="people" data-br-page="2"/);assert.match(dialog,/Page 1 of 2/);
});
