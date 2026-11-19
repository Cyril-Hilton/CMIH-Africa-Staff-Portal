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
test('weekly planner clips work to the week and distinguishes internal ETA from requester deadline',()=>{const s=make();s.items=[brief()];const html=s.weekPlanner(s.week,new Date('2026-11-15T00:00:00Z'));assert.match(html,/grid-column:2\/9;grid-row:1/);assert.match(html,/Starts before this week/);assert.match(html,/Continues next week/);assert.match(html,/Creative HOD deadline/);assert.match(html,/16 Nov 2026, 15:00/);assert.match(html,/Brief requester deadline: 18 Nov 2026, 16:00/);assert.match(html,/Awaiting Review/);assert.match(html,/&lt;script&gt;/);assert.doesNotMatch(html,/<script>/);assert.match(html,/data-cs-open="1"/);});
test('only current creative members have lanes and parent packages are not counted twice',()=>{const s=make();s.items=[brief({id:2,assignee:{id:8,name:'Former Creative'}}),brief({id:3,is_group:true})];const html=s.weekPlanner(s.week,new Date('2026-11-15T00:00:00Z'));assert.doesNotMatch(html,/data-person-id="8"/);assert.doesNotMatch(html,/Former Creative/);assert.match(html,/No planned work in this selection/);assert.doesNotMatch(html,/data-cs-open="3"/);s.filter.assignee='8';assert.doesNotMatch(s.weekPlanner(s.week,new Date('2026-11-15T00:00:00Z')),/Creative One/);});
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
 assert.match(studio.calendar(), /COLLABORATORS \/ SUPPORT/);assert.match(studio.calendar(), /Research &amp; Insights/);assert.match(studio.calendar(), /Operations Staff/);
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

test('external supporting staff stay on project badges without member tabs or calendar lanes', () => {
 const studio = make();
 studio.data.support_roster = [{id: 4, name:'Operations Colleague', department:'operations_projects'}];
 studio.items = [brief({allowed:[],requester:{name:'Requester'},department:'creatives',
  supporting_staff:[{id:4,name:'Operations Colleague'},{id:1,name:'Creative One'}]})];
 const end = new Date('2026-11-15T00:00:00Z');
 const week = studio.weekPlanner(studio.week, end);
 assert.match(week,/data-person-id="1"[\s\S]*?data-assignment="lead" data-cs-open="1"/);
 assert.doesNotMatch(week,/data-person-id="4"/);assert.equal(studio.participantRoster().length,1);
 assert.match(week,/Operations Colleague/);
 assert.equal([...week.matchAll(/data-cs-open="1"/g)].length,1);
 const board = studio.board();
 assert.equal([...board.matchAll(/data-cs-card="1"/g)].length,1);
 assert.match(board,/cs-support-chip">Operations Colleague/);
 studio.filter.assignee='4';
 assert.match(studio.weekPlanner(studio.week,end),/data-person-id="1"/);assert.doesNotMatch(studio.weekPlanner(studio.week,end),/data-person-id="4"/);
 assert.match(studio.board(),/data-assignment="support"/);
});

test('showcase credits people and exposes timeline facts without requiring an image or inventing fault',()=>{
 const s=make();s.view='report';s.root={dataset:{embedded:'true',week:'2026-11-09',reportTo:'2026-11-15'}};s.params=()=>new URLSearchParams({from:'2026-11-09',to:'2026-11-15'});
 s.items=[brief({requester:{name:'Client Relations Person'},client_relations_contact:{name:'Account Owner'},supporting_staff:[{id:2,name:'Support Artist'}],accountability_events:[{kind:'changes_requested',feedback_type:'scope_change',actor:'Account Owner',department:'Client Relations',at:'2026-11-12T10:00:00Z',iteration:1}],on_time_submission:true,final_preview:null,next_action:'Awaiting client approval'})];
 const html=s.exhibit();assert.match(html,/Concepts Visualised/);assert.match(html,/Account Owner/);assert.match(html,/Support Artist/);assert.match(html,/Scope change requested by/);assert.match(html,/met the recorded Creative HOD deadline/);assert.match(html,/Visual preview optional/);assert.match(html,/No effect on KPI scores/);assert.doesNotMatch(html,/<script>/);assert.equal(s.primaryView(),'report');
});

test('showcase slider cycles images in both directions and pauses after manual input',()=>{
 const s=make();s.galleryIndices=new Map();s.galleryPausedUntil=new Map();s.root={querySelector(){return null;}};
 const item=brief({final_previews:[{url:'/front',mime:'image/png',name:'Front'},{url:'/side',mime:'image/png',name:'Side'},{url:'/back',mime:'image/png',name:'Back'}]});s.items=[item];
 assert.match(s.galleryMedia(item),/1 \/ 3/);s.moveGallery(1,1,true);assert.match(s.galleryMedia(item),/src="\/side"/);assert.ok(s.galleryPausedUntil.get(1)>Date.now());s.moveGallery(1,-1);s.moveGallery(1,-1);assert.match(s.galleryMedia(item),/3 \/ 3/);
});
test('calendar and showcase use the same recorded responsibility findings',()=>{
 const s=make();const item=brief({requester:{name:'Request Owner'},client_relations_contact:{name:'Account Lead'},supporting_staff:[{id:2,name:'Support Artist'}],on_time_submission:false,next_action:'Assigned creative',accountability_events:[]});s.items=[item];
 const html=s.calendarAccountability();assert.match(html,/Account Lead/);assert.match(html,/Support Artist/);assert.match(html,/after the recorded Creative HOD deadline/);assert.match(html,/Review due/);assert.match(html,/data-cs-open="1"/);
});

test('overview combines period statistics and progress charts without duplicate presentation tabs',()=>{
 const s=make();s.report={period:'weekly',label:'9–15 Nov 2026',total:4,received:3,submitted:2,verified:1,waiting:1,stages:{in_progress:3,completed:1},buckets:[{label:'9 Nov',verified:0},{label:'10 Nov',verified:1}]};
 const html=s.presentation();assert.match(html,/Week at a Glance/);assert.match(html,/Work Progress/);assert.match(html,/Briefs, Submissions & Deliveries/);assert.match(html,/width:100%/);assert.match(html,/cy="200"/);assert.match(html,/<svg[^>]+role="img"/);assert.match(html,/data-cs-overview-filter/);for(const name of ['period','anchor','from','to'])assert.match(html,new RegExp('name="'+name+'"'));assert.equal([...html.matchAll(/<small>Verified Deliveries<\/small>/g)].length,1);assert.doesNotMatch(html,/data-cs-slide|cs-presentation-section-tabs|Work Showcase|Performance Snapshot|Saved Weekly Reports/);
});

test('overview date selection clears stale ranges and applies week, month, year and custom dates',()=>{
 const s=make();s.root={dataset:{embedded:'false'}};s.load=()=>{};s.view='overview';
 const form=(period,from='2026-01-01',to='2026-12-31')=>({elements:{period:{value:period},anchor:{value:'2026-11-19'},from:{value:from},to:{value:to,setCustomValidity(){},reportValidity(){throw new Error('Invalid range');}}}});
 for(const [mode,from,to] of [['weekly','2026-11-16','2026-11-22'],['monthly','2026-11-01','2026-11-30'],['yearly','2026-01-01','2026-12-31']]){s.filter={q:'Dove',from:'2020-01-01',to:'2020-01-02'};s.applyOverviewDates(form(mode));assert.equal(s.params().get('from'),from);assert.equal(s.params().get('to'),to);assert.equal(s.filter.q,'Dove');}
 s.applyOverviewDates(form('custom','2026-11-04','2026-11-18'));assert.equal(s.params().get('from'),'2026-11-04');assert.equal(s.reportParams().get('to'),'2026-11-18');
});
test('activity graph plots all three dated milestones and retains a true zero baseline',()=>{
 const s=make();const html=s.activityChart([{label:'16 Nov',received:10,submitted:2,verified:0},{label:'17 Nov',received:3,submitted:0,verified:0}]);assert.equal([...html.matchAll(/<polyline /g)].length,3);assert.match(html,/16 Nov · Briefs received: 10/);assert.match(html,/First submissions: 2/);assert.match(html,/green series remains on the zero baseline/);assert.match(html,/cy="200"/);
});

test('existing brief exposes editable project connection with selected project and owner while readers retain facts',()=>{
 const s=make();s.data.connected_projects=[{id:7,title:'Christmas Concept',client:'Lush Hair',owner:'Client Relations Owner'}];
 const d={id:1,revision:4,can_link_project:true,connected_project:{id:7,title:'Christmas Concept',client:'Lush Hair'},client_relations_contact:{name:'Client Relations Owner'}};
 const html=s.projectConnection(d);assert.match(html,/data-cs-form="project"/);assert.match(html,/value="7" selected/);assert.match(html,/Client Relations Owner/);assert.match(html,/name="revision" value="4"/);assert.match(html,/Save Project Connection/);
 const read=s.projectConnection({...d,can_link_project:false});assert.match(read,/Christmas Concept/);assert.doesNotMatch(read,/<form/);
 assert.match(s.projectConnection({...d,connected_project:null}),/Direct brief/);
});

test('calendar and showcase share the linked CRM project, full deadlines and recorder provenance',()=>{
 const s=make();s.root={dataset:{embedded:'true',week:'2026-11-09',reportTo:'2026-11-15'}};s.view='report';
 const item=brief({requester:{name:'Brief Creator'},client_relations_contact:{name:'Account Owner'},connected_project:{id:9,title:'Campus Mirror & Christmas Tree',client:'Lush Hair'},timeline_provenance:{client_relations_deadline:'2026-11-18T12:30:00Z',client_relations_recorded_by:'Brief Creator',client_relations_recorded_at:'2026-11-10T10:00:00Z',hod_recorded_by:'Creative HOD',hod_recorded_at:'2026-11-11T09:00:00Z',history:[]}});s.items=[item];
 for(const html of [s.weekPlanner(s.week,new Date('2026-11-15T00:00:00Z')),s.exhibit(),s.calendarAccountability()]){assert.match(html,/Campus Mirror &amp; Christmas Tree/);assert.match(html,/Client Relations deadline/);assert.match(html,/18 Nov 2026, 12:30/);assert.match(html,/Creative HOD deadline/);assert.match(html,/16 Nov 2026, 15:00/);}
 const details=s.timelineSummary(item)+s.timelineHistory(item);assert.match(details,/Recorded by Brief Creator/);assert.match(details,/Recorded by Creative HOD/);assert.match(details,/10 Nov 2026, 10:00/);
});

test('a shared brief is plotted once in its lead lane with prominent collaborator names and supporter filtering retains the lead lane',()=>{
 const s=make();s.data.staff.push({id:2,name:'Support Creative'},{id:3,name:'Other Support Creative'});s.items=[brief({supporting_staff:[{id:1,name:'Creative One'},{id:2,name:'Support Creative'},{id:3,name:'Other Support Creative'}]})];
 const end=new Date('2026-11-15T00:00:00Z');let html=s.weekPlanner(s.week,end);
 assert.equal([...html.matchAll(/data-cs-open="1"/g)].length,1);assert.doesNotMatch(html,/data-assignment="support"/);assert.match(html,/COLLABORATORS \/ SUPPORT/);assert.match(html,/cs-calendar-collaborator"><span[^>]+>SC<\/span><strong>Support Creative/);assert.match(html,/Lead: Creative One/);
 s.filter.assignee='2';html=s.weekPlanner(s.week,end);assert.match(html,/data-person-id="1"/);assert.doesNotMatch(html,/data-person-id="2"/);assert.doesNotMatch(html,/data-person-id="3"/);assert.equal([...html.matchAll(/data-cs-open="1"/g)].length,1);
});


test('calendar preserves a visible full brief title above a large supporting team',()=>{
 const s=make();const item=brief({title:'Complete studio interface and brand experience',supporting_staff:Array.from({length:5},(_,i)=>({id:i+2,name:'Supporting Colleague '+i}))});
 const html=s.calendarWorkBar(item,0,3,0,s.week,new Date('2026-11-15T00:00:00Z'));
 assert.match(html,/<strong class="cs-planner-work-title">Complete studio interface and brand experience<\/strong>/);
 assert.ok(html.indexOf('cs-planner-work-title')<html.indexOf('Lead:'));assert.ok(html.indexOf('cs-planner-work-title')<html.indexOf('COLLABORATORS / SUPPORT'));
 const css=readFileSync(new URL('../../resources/css/creative-studio.css',import.meta.url),'utf8');
 assert.match(css,/\.cs-studio \.cs-planner-work> \*\{flex-shrink:0\}/);assert.match(css,/\.cs-planner-work-title\{[^}]*flex:none[^}]*white-space:normal[^}]*overflow-wrap:anywhere/);
});
