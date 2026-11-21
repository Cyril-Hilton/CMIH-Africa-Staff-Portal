import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
const {updateModuleBreadcrumb}=await import('data:text/javascript;base64,'+Buffer.from(readFileSync(new URL('../../resources/js/module-breadcrumbs.js',import.meta.url))).toString('base64'));
test('client-rendered tabs update their trail without changing the all-departments trail',()=>{
 const crumb={textContent:'Overview'},doc={querySelector:()=>crumb};
 updateModuleBreadcrumb('creatives','Showcase',doc,'https://portal.cmih.africa/portal/creative');assert.equal(crumb.textContent,'Showcase');
 updateModuleBreadcrumb('creatives','Calendar',doc,'https://portal.cmih.africa/dashboard?weekly_department=creatives');assert.equal(crumb.textContent,'Calendar');
 updateModuleBreadcrumb('brands_marketing','People',doc,'https://portal.cmih.africa/dashboard?weekly_department=all');assert.equal(crumb.textContent,'Calendar');
});
