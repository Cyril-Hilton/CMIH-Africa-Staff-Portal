<?php
namespace App\Services;
use App\Models\{User,Asset};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class OperationsTableQuery
{
    public function validateAll(Request $request): void
    {
        $keys=array_merge(array_keys(OperationsReporting::schemas()),['sources','movements','posm_undated','breakdown_brand','breakdown_location','breakdown_lead']);
        $request->validate(['ops_tables'=>'nullable|array:'.implode(',',$keys)]);
        foreach(array_keys($request->input('ops_tables')??[]) as $key)$this->values($request,$key);
    }
    public function fields(string $key): array
    {
        if ($key==='movements') return ['request_code'=>'Reference','asset_name'=>'Asset','brand'=>'Brand','destination_location'=>'Location','requested_by'=>'Requested by','custodian_id'=>'Custodian','requested_quantity'=>'Quantity','issued_at'=>'Issued','returned_at'=>'Returned','status'=>'Status'];
        if ($key==='sources') return ['number'=>'Sheet row','cells'=>'Original cells'];
        if (str_starts_with($key,'breakdown_')) return ['name'=>'Category','count'=>'Activations'];
        $fields=['title'=>'Activity / activation','client'=>'Client','project'=>'Project','owner_id'=>'Owner','status'=>'Status','period_start'=>'Reporting week'];
        if($key==='activation')$fields['owner_id']='Project lead';
        foreach(OperationsReporting::fields($key==='posm_undated'?'posm_deployment':$key,false) as $field=>$type)$fields['data->'.$field]=str($field)->headline()->toString();
        return $fields;
    }
    public function values(Request $request,string $key): array
    {
        abort_unless(preg_match('/^[a-z_]+$/',$key),422);
        $data=$request->input('ops_tables.'.$key,[]);abort_unless(is_array($data),422,'Invalid table filters.');
        $rules=['q'=>'nullable|string|max:150','field'=>['nullable',Rule::in(array_keys($this->fields($key)))],'value'=>'nullable|string|max:150','sort'=>['nullable',Rule::in(array_keys($this->fields($key)))],'direction'=>'nullable|in:asc,desc','rows'=>'nullable|in:10,25,50'];
        if($key==='activation')$rules+=array_fill_keys(['brand','location','lead'],'nullable|string|max:150');
        return validator($data,$rules)->validate();
    }
    public function apply(Builder $query,Request $request,string $key):Builder
    {
        $f=$this->values($request,$key);$columns=array_keys($this->fields($key));
        if($key==='activation')foreach(['brand'=>'data->brand','location'=>'data->location','lead'=>'owner_id'] as $filter=>$field)if(!empty($f[$filter]))$query->where(fn($w)=>$this->match($w,$field,$f[$filter],$key));
        if(!empty($f['q']))$query->where(function($w)use($columns,$f,$key){foreach($columns as $column)$this->match($w,$column,$f['q'],$key,'or');});
        if(!empty($f['field'])&&isset($f['value'])&&$f['value']!=='')$query->where(fn($w)=>$this->match($w,$f['field'],$f['value'],$key));
        if(!empty($f['sort'])){
            $field=$f['sort'];$direction=$f['direction']??'asc';
            if($key==='movements'&&in_array($field,['asset_name','brand']))$query->orderBy(Asset::select($field==='asset_name'?'name':'brand')->whereColumn('assets.id','asset_warehouse_requests.asset_id'),$direction);
            elseif(in_array($field,['owner_id','requested_by','custodian_id']))$query->orderBy(User::select('name')->whereColumn('users.id',$query->getModel()->qualifyColumn($field)),$direction);
            else{$numeric=in_array($field,['requested_quantity'])||in_array(OperationsReporting::fields($key,false)[substr($field,6)]??'', ['number','money']);$wrapped=$query->getQuery()->getGrammar()->wrap($query->getModel()->qualifyColumn($field));$query->orderByRaw(($numeric?'CAST('.$wrapped.' AS DECIMAL(20,4))':$wrapped).' '.$direction);}
        }
        return $query;
    }
    private function match(Builder $query,string $field,string $value,string $key,string $boolean='and'):void
    {
        $term='%'.str_replace(['!','%','_'],['!!','!%','!_'],mb_strtolower(trim($value))).'%';
        if($key==='movements'&&in_array($field,['asset_name','brand'])){$method=$boolean==='or'?'orWhereHas':'whereHas';$query->$method('asset',fn($a)=>$a->whereRaw("LOWER(".($field==='asset_name'?'name':'brand').") LIKE ? ESCAPE '!'",[$term]));return;}
        if(in_array($field,['owner_id','requested_by','custodian_id'])){$query->whereIn($field,User::select('id')->whereRaw("LOWER(name) LIKE ? ESCAPE '!'",[$term]),$boolean);return;}
        $wrapped=$query->getQuery()->getGrammar()->wrap($query->getModel()->qualifyColumn($field));$query->whereRaw('LOWER('.$wrapped.") LIKE ? ESCAPE '!'",[$term],$boolean);
    }
    public function collection(Collection $rows,Request $request,string $key):Collection
    {
        $f=$this->values($request,$key);
        $text=fn($r,$field=null)=>mb_strtolower(is_array($r)?($field?json_encode($r[$field]??''):json_encode($r)):strval($r));
        if(!empty($f['q']))$rows=$rows->filter(fn($r)=>str_contains($text($r),mb_strtolower($f['q'])));
        if(!empty($f['field'])&&isset($f['value'])&&$f['value']!=='')$rows=$rows->filter(fn($r)=>str_contains($text($r,$f['field']),mb_strtolower($f['value'])));
        if(!empty($f['sort']))$rows=$rows->sortBy($f['sort'],SORT_REGULAR,($f['direction']??'asc')==='desc');
        return $rows->values();
    }
}
