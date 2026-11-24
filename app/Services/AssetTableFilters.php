<?php
namespace App\Services;
use App\Models\{Asset,User};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
class AssetTableFilters
{
 public const FIELDS=['asset_item'=>'Item / tag / serial','asset_brand'=>'Brand','asset_location'=>'Location','asset_type'=>'Type / category','asset_condition'=>'Condition','asset_status'=>'Asset status','asset_handler'=>'Person handling / assigned','asset_project'=>'Activation / project','asset_department'=>'Assigned department'];
 public function values(Request $r):array{$rules=array_fill_keys(array_keys(self::FIELDS),'nullable|string|max:150');$rules['asset_handler']='nullable|integer|min:1';$rules['asset_department']='nullable|in:'.implode(',',array_keys(config('performance.departments')));return $r->validate($rules);}
 public function apply(Builder $q,Request $r):Builder{
  $f=$this->values($r);
  foreach(['asset_item'=>['name','asset_tag','serial_number'],'asset_brand'=>['brand'],'asset_location'=>['location','warehouse_location'],'asset_type'=>['type','category']] as $key=>$columns){if(empty($f[$key]))continue;$term='%'.str_replace(['!','%','_'],['!!','!%','!_'],mb_strtolower(trim($f[$key]))).'%';$generatedId=$key==='asset_item'&&preg_match('/^CMIH-AST-0*([1-9][0-9]*)$/i',trim($f[$key]),$code)?(int)$code[1]:null;$q->where(function($w)use($columns,$term,$generatedId){if($generatedId)$w->orWhere($w->getModel()->getQualifiedKeyName(),$generatedId);foreach($columns as $column)$w->orWhereRaw('LOWER('.$w->getModel()->qualifyColumn($column).") LIKE ? ESCAPE '!'",[$term]);});}
  foreach(['asset_condition'=>'condition','asset_status'=>'status'] as $key=>$col)if(!empty($f[$key]))$q->where($col,$f[$key]);
  if(!empty($f['asset_department']))$q->whereHas('assignee',fn($u)=>$u->whereIn('department',User::departmentAliases($f['asset_department'])));
  if(!empty($f['asset_handler'])){$id=(int)$f['asset_handler'];$q->where(fn($w)=>$w->where('assigned_to',$id)->orWhere('last_handled_by',$id)->orWhereHas('warehouseRequests',fn($req)=>$req->whereNotNull('issued_at')->whereNull('closed_at')->whereNull('returned_at')->where(fn($owner)=>$owner->where('custodian_id',$id)->orWhere(fn($fallback)=>$fallback->whereNull('custodian_id')->where('requested_by',$id)))));}
  if(!empty($f['asset_project'])){$term='%'.str_replace(['!','%','_'],['!!','!%','!_'],mb_strtolower(trim($f['asset_project']))).'%';$q->where(function($w)use($term){$w->whereHas('warehouseRequests',function($req)use($term){foreach(['operationsActivation'=>['title','project'],'project'=>['project_name','client_name'],'campaign'=>['name','client_name'],'report'=>['title','project']] as $relation=>$fields)$req->orWhereHas($relation,function($p)use($fields,$term){foreach($fields as $field)$p->orWhereRaw('LOWER('.$p->getModel()->qualifyColumn($field).") LIKE ? ESCAPE '!'",[$term]);});})->orWhereHas('assetLogs.campaign',fn($c)=>$c->whereRaw("LOWER(name) LIKE ? ESCAPE '!'",[$term]));});}
  return $q;
 }
 public function options(bool $warehouse):array{
  $base=Asset::query()->when($warehouse,fn($q)=>$q->where('is_warehouse_tracked',true),fn($q)=>$q->where(fn($w)=>$w->where('is_warehouse_tracked',false)->orWhereNull('is_warehouse_tracked')));
  $rows=$base->get(['brand','location','warehouse_location','type','category','condition','status']);$out=[];
  foreach(['asset_brand'=>['brand'],'asset_location'=>['location','warehouse_location'],'asset_type'=>['type','category'],'asset_condition'=>['condition'],'asset_status'=>['status']] as $key=>$fields)$out[$key]=$rows->flatMap(fn($row)=>collect($fields)->map(fn($f)=>$row->$f))->filter(fn($v)=>$v!==null&&trim($v)!=='')->unique()->sort()->values();
  $out['asset_handler']=User::internalStaff()->orderBy('name')->get(['id','name','status']);return $out;
 }
}
