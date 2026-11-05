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
