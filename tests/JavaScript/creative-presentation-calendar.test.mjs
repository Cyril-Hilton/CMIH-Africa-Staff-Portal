import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';
const source=readFileSync(new URL('../../resources/js/creative-studio.js',import.meta.url),'utf8').split('function initCreativeStudios')[0];
const Studio=vm.runInNewContext(source+';CreativeStudio',{URLSearchParams,Intl,Date,FormData: class {
 constructor(form) { this.entries = form.entries; }
 getAll(key) { return this.entries.filter(([name]) => name === key).map(([, value]) => value); }
 [Symbol.iterator]() { return this.entries[Symbol.iterator](); }
}});
const make=()=>{const s=Object.create(Studio.prototype);Object.assign(s,{week:new Date('2026-11-09T00:00:00Z'),calendarMode:'week',filter:{},items:[],data:{staff:[{id:1,name:'Creative One',title:'Motion Designer'}],stages:{scheduled:'Scheduled',in_progress:'In Progress',in_review:'Awaiting Review',delivered:'Approved for Delivery',completed:'Verified & Completed'},capacity:[],pagination:{total:0}}});return s;};
const brief=(extra={})=>({id:1,title:'Packaging <script>alert(1)</script>',client:'Client & Brand',reference:'CR-001',assignee:{id:1,name:'Creative One'},stage:'in_progress',priority:'High',estimated_hours:18,dates:{planned_start:'2026-11-06T09:00:00Z',internal_eta:'2026-11-16T15:00:00Z',requested_deadline:'2026-11-18T16:00:00Z'},...extra});
test('weekly planner clips work to the week and distinguishes internal ETA from requester deadline',()=>{const s=make();s.items=[brief()];const html=s.weekPlanner(s.week,new Date('2026-11-15T00:00:00Z'));assert.match(html,/grid-column:2\/9;grid-row:1/);assert.match(html,/Starts before this week/);assert.match(html,/Continues next week/);assert.match(html,/ETA 16 Nov/);assert.match(html,/Client 18 Nov/);assert.match(html,/Awaiting Review/);assert.match(html,/&lt;script&gt;/);assert.doesNotMatch(html,/<script>/);assert.match(html,/data-cs-open="1"/);});
test('historical creatives retain a lane and parent work packages are not counted twice',()=>{const s=make();s.items=[brief({id:2,assignee:{id:8,name:'Former Creative'}}),brief({id:3,is_group:true})];const html=s.weekPlanner(s.week,new Date('2026-11-15T00:00:00Z'));assert.match(html,/Former Creative/);assert.match(html,/Retained staff history/);assert.match(html,/No planned work in this selection/);assert.doesNotMatch(html,/data-cs-open="3"/);s.filter.assignee='8';assert.doesNotMatch(s.weekPlanner(s.week,new Date('2026-11-15T00:00:00Z')),/Creative One/);});
test('workload labels distinguish remaining production from planned hours and flag overload',()=>{const s=make();s.data.capacity=[{id:1,remaining:20,planned:36,available:16,overload:4}];const html=s.calendarAvailability(1);assert.match(html,/20h remaining \/ 16h capacity/);assert.match(html,/36h planned/);assert.match(html,/4h above available capacity/);assert.match(html,/width:100%/);s.data.capacity[0].available=null;assert.match(s.calendarAvailability(1),/Capacity not set/);});
test('presentation calendar discloses pagination and preserves period modes and planning follow-ups',()=>{const s=make();s.items=[brief({assignee:{id:null,name:'Unassigned'},dates:{requested_deadline:'2026-11-13T16:00:00Z'}})];s.data.pagination.total=120;const html=s.calendar();assert.match(html,/Showing 1 of 120/);assert.match(html,/Presentation View/);assert.match(html,/Full Screen/);for(const mode of ['week','month','year','agenda'])assert.match(html,new RegExp('data-cs-cal-mode="'+mode+'"'));assert.match(html,/Needs a Plan/);assert.match(html,/Decisions &amp; Deadline Risks|Decisions & Deadline Risks/);assert.match(html,/data-cs-open="1"/);});

test('a planned ETA beyond the requester deadline appears in calendar risks', () => {
 const studio = make();
 const item = brief({dates: {planned_start:'2026-11-09T09:00:00Z', internal_eta:'2026-11-13T17:00:00Z', requested_deadline:'2026-11-13T15:00:00Z'}});
 studio.items = [item];
 assert.match(studio.calendar(), /ETA after requester deadline/);
 assert.match(studio.calendarFollowups(), /Internal ETA 13 Nov is after the requester deadline/);
});

test('presentation focus removes the dashboard backdrop and restores it on exit', () => {
 const studio = make();
 const styles = new Map([['backdrop-filter', ['blur(16px)', '']]]);
 const style = {
  getPropertyValue: key => styles.get(key)?.[0] ?? '',
  getPropertyPriority: key => styles.get(key)?.[1] ?? '',
  setProperty: (key, value, priority) => styles.set(key, [value, priority]),
  removeProperty: key => styles.delete(key),
 };
 studio.root = {classList: {toggle() {}}, closest: () => ({style})};
 studio.applyCalendarFocus(true);
 studio.applyCalendarFocus(true);
 assert.deepEqual(styles.get('backdrop-filter'), ['none', 'important']);
 assert.deepEqual(styles.get('-webkit-backdrop-filter'), ['none', 'important']);
 studio.applyCalendarFocus(false);
 assert.deepEqual(styles.get('backdrop-filter'), ['blur(16px)', '']);
 assert.equal(styles.has('-webkit-backdrop-filter'), false);
});

test('collaborators display on calendar cards and can be selected, saved or cleared', async () => {
 const studio = make();
 studio.data.collaborators = [{id: 3, name: 'Research & Insights', department: 'brands_marketing'}, {id: 4, name: 'Operations Staff'}];
 studio.items = [brief({supporting_staff: studio.data.collaborators})];
 assert.match(studio.calendar(), /With Research &amp; Insights, Operations Staff/);
 const picker = studio.supportingStaffPicker(studio.data.collaborators.slice(0, 1));
 assert.match(picker, /value="3" checked/);
 assert.doesNotMatch(picker, /value="4" checked/);

 let saved;
 studio.mutate = async (url, data) => { saved = data; };
 const form = {querySelectorAll: () => [], reportValidity: () => true, dataset: {csForm: 'plan', id: '1'}};
 await studio.submit({...form, entries: [['supporting_staff_ids[]', '3'], ['supporting_staff_ids[]', '4']]});
 assert.equal(JSON.stringify(saved.supporting_staff_ids), '[3,4]');
 assert.equal(saved['supporting_staff_ids[]'], undefined);
 await studio.submit({...form, entries: []});
 assert.equal(JSON.stringify(saved.supporting_staff_ids), '[]');
});
