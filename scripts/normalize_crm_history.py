"""Extract operational pipeline snapshots with explicit provenance; never infer revenue from PO/forecast.
Copies/undated historical blocks retain their period label but are excluded from performance totals.
"""
import argparse,json,datetime,re,hashlib,calendar
from pathlib import Path
import openpyxl
p=argparse.ArgumentParser();p.add_argument('workbook');p.add_argument('output');args=p.parse_args()
w=openpyxl.load_workbook(args.workbook,data_only=True)
months={name.lower():i for i in range(1,13) for name in [calendar.month_name[i],calendar.month_abbr[i]]}
months['sept']=9
stages={'idea':'idea','contacted':'contacted','proposal sent':'proposal_sent','terms negotiated':'terms_negotiated','verbal yes':'verbal_yes','project ongoing':'project_ongoing','project on-going':'project_ongoing','project completed':'project_completed','project lost':'lost','lost':'lost','on hold':'on_hold','cancelled':'cancelled'}
def text(v):return str(v).strip() if v is not None else ''
def number(v):return float(v) if isinstance(v,(int,float)) and not isinstance(v,bool) else None
def monthof(v):
 for token in re.findall(r'[a-z]+',text(v).lower()):
  if token in months:return months[token]
 return None
records=[];targets=[];warnings=[]
# Bounds identify the maintained 2026 blocks; later blocks include copied historic templates with missing years.
active_limits={'2026 ALFAH BARAKAH Pipeline':175,'2026 Nicolette Nana Ekua Pipeli':153,'2026 MASANA ALFAH  Pipeline':53,'2026 JOVITA ANANKUM pipeline':61}
for s in w:
 header=None;period=None;lastclient='';yearmatch=re.search(r'20\d{2}',s.title);year=int(yearmatch[0]) if yearmatch else None
 for row in s:
  vals={c.column_letter:c.value for c in row if c.value is not None}
  strings={k:text(v).lower().rstrip(':').strip() for k,v in vals.items()}
  stagecol=next((k for k,v in strings.items() if v=='sales stage'),None)
  if stagecol:
   header={}
   for k,v in strings.items():
    if v in ['client','client name']:header['client']=k
    elif v in ['project','brand/project','project name']:header['project']=k
    elif v in ['team member','person responsible']:header['owner']=k
    elif v=='sales stage':header['stage']=k
    elif v=='proposed amount':header['proposed']=k
    elif v=='po amount':header['po']=k
    elif v=='amount':header['amount']=k
    elif v=='weighted forecast':header.setdefault('forecast',k)
    elif 'bottleneck' in v:header.setdefault('bottlenecks',[]).append(k)
    elif 'next line' in v or v=='progress points':header.setdefault('actions',[]).append(k)
   label=next((text(v) for k,v in vals.items() if k in ['A','B'] and monthof(v)),None)
   period=label;lastclient='';continue
  if not header or 'client' not in header:continue
  client=text(vals.get(header['client']));stage=text(vals.get(header.get('stage')))
  if client.lower() in ['monthly target','ytd target']:
   amount=number(vals.get(header.get('po'))) or number(vals.get(header.get('amount'))) or number(vals.get(header.get('proposed')))
   targets.append({'sheet':s.title,'row':row[0].row,'label':period or 'Period not stated','kind':'ytd' if 'ytd' in client.lower() else 'monthly','amount':amount,'year':year,'month':monthof(period),'confirmed':bool(year and monthof(period) and s.title in active_limits and row[0].row<=active_limits[s.title]),'source_values':{k:(v.isoformat() if isinstance(v,datetime.datetime) else v) for k,v in vals.items()}})
   continue
  if client.lower() in ['total','variance','difference','actual','ytd actuals','ytd expected actuals']:continue
  if client:lastclient=client
  project=text(vals.get(header.get('project')))
  proposed=number(vals.get(header.get('proposed')));po=number(vals.get(header.get('po')));ambiguous=number(vals.get(header.get('amount')))
  # Only genuine project/stage records, not formulas on empty template rows.
  if stage.lower() not in stages and not(project and client and (proposed is not None or po is not None)):continue
  if not(project or client):continue
  m=monthof(period);confirmed=bool(year and m and s.title in active_limits and row[0].row<=active_limits[s.title])
  start=datetime.date(year,m,1).isoformat() if confirmed else None
  records.append({'key':hashlib.sha256((s.title+'|'+str(row[0].row)).encode()).hexdigest(),'sheet':s.title,'row':row[0].row,'client':client or lastclient or '[Client not stated in source]','project':project or '[Project not stated in source]','source_owner':text(vals.get(header.get('owner'))),'stage':stages.get(stage.lower(),'on_hold'),'source_stage':stage,'period_label':(str(year)+' · ' if year else '')+(period or 'Reporting period not stated'),'confirmed':confirmed,'period_start':start,'proposed_amount':proposed,'po_amount':po,'unclassified_amount':ambiguous,'source_forecast':number(vals.get(header.get('forecast'))),'bottlenecks':'\n'.join(text(vals.get(k)) for k in header.get('bottlenecks',[]) if vals.get(k)),'next_action':'\n'.join(text(vals.get(k)) for k in header.get('actions',[]) if vals.get(k)),'source_values':{k:(v.isoformat() if isinstance(v,datetime.datetime) else v) for k,v in vals.items()}})
json.dump({'source_id':'1ydsMLK86iDG5XPp1PcC3_qjh_2CjeJ_-','projects':records,'targets':targets},open(args.output,'w'),ensure_ascii=False)
print(json.dumps({'project_snapshots':len(records),'dated_current_snapshots':sum(r['confirmed'] for r in records),'target_rows':len(targets),'confirmed_numeric_targets':sum(t['confirmed'] and t['amount'] is not None for t in targets)}))
