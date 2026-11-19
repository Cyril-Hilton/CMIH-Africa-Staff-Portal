import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';
const source=readFileSync(new URL('../../resources/js/creative-studio.js',import.meta.url),'utf8').split('function initCreativeStudios')[0];
const Studio=vm.runInNewContext(source+';CreativeStudio',{URLSearchParams,Intl,Date,document:{activeElement:null}});
const make=()=>{const s=Object.create(Studio.prototype);Object.assign(s,{base:'/portal/creative-studio',data:{types:['Poster'],connected_projects:[]},root:{querySelector:()=>({textContent:''})},body:{innerHTML:'',querySelector:()=>({focus(){}})},dialog:{open:false,showModal(){this.open=true;}},supportingStaffPicker:()=>''});return s;};
test('opening a creative brief fetches fresh project options and includes account ownership',async()=>{
 const s=make();let generation=0;s.api=async url=>{assert.equal(url,'/portal/creative-studio/projects');return {projects:[{id:7,title:'Client launch '+(++generation),client:'Dove',owner:'Account Owner'}]};};
 await s.newBrief();assert.match(s.body.innerHTML,/Dove · Client launch 1 — Account Owner/);await s.newBrief();assert.match(s.body.innerHTML,/Client launch 2/);assert.doesNotMatch(s.body.innerHTML,/Client launch 1/);
});
test('project lookup errors are surfaced instead of silently opening an empty dropdown',async()=>{
 const s=make();s.api=async()=>{throw Error('Connection failed');};let message='';s.message=value=>{message=value;};await s.newBrief();assert.match(message,/Project choices could not load/);assert.equal(s.dialog.open,false);
});
