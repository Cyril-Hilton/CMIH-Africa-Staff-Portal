@php
 $afService=app(\App\Services\AssetTableFilters::class);$afValues=$afService->values(request());$afOptions=$afService->options($assetWarehouse??false);
 $afKeys=$assetFilterKeys??array_keys(\App\Services\AssetTableFilters::FIELDS);$afEmbedded=$assetFiltersEmbedded??false;
 $afKeep=collect(request()->query())->reject(fn($v,$k)=>in_array($k,array_keys(\App\Services\AssetTableFilters::FIELDS),true)||str_ends_with($k,'_page')||$k==='page'||$k==='hr_record_focus')->all();
@endphp
@if(!$afEmbedded)
<form method="get" action="{{ url()->current() }}" class="br-filters ops-form my-4" data-no-ajax data-no-silent aria-label="Filter asset inventory">
@foreach($afKeep as $key=>$value)@if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
@endif
@foreach($afKeys as $key)
<label class="text-xs">{{ \App\Services\AssetTableFilters::FIELDS[$key] }}
@if($key==='asset_department')<select name="{{ $key }}" class="mt-2 block w-full rounded-lg border border-brand-white/20 bg-brand-black p-3"><option value="">All departments</option>@foreach(config('performance.departments') as $id=>$label)<option value="{{ $id }}" @selected(($afValues[$key]??'')===$id)>{{ $label }}</option>@endforeach</select>
@elseif($key==='asset_handler')<select name="{{ $key }}" class="mt-2 block w-full rounded-lg border border-brand-white/20 bg-brand-black p-3"><option value="">All people</option>@foreach($afOptions[$key] as $person)<option value="{{ $person->id }}" @selected((int)($afValues[$key]??0)===$person->id)>{{ $person->name }}{{ $person->status!=='active'?' (inactive)':'' }}</option>@endforeach</select>
@elseif(in_array($key,['asset_condition','asset_status']))<select name="{{ $key }}" class="mt-2 block w-full rounded-lg border border-brand-white/20 bg-brand-black p-3"><option value="">All {{ strtolower(\App\Services\AssetTableFilters::FIELDS[$key]) }}</option>@foreach($afOptions[$key] as $option)<option value="{{ $option }}" @selected(($afValues[$key]??'')===$option)>{{ $option }}</option>@endforeach</select>
@else<input name="{{ $key }}" type="search" maxlength="150" value="{{ $afValues[$key]??'' }}" @if(isset($afOptions[$key])) list="af-options-{{ $key }}" @endif placeholder="{{ $key==='asset_project'?'Activation, project or client name':'Any matching value' }}" class="mt-2 block w-full rounded-lg border border-brand-white/20 bg-brand-black p-3">@if(isset($afOptions[$key]))<datalist id="af-options-{{ $key }}">@foreach($afOptions[$key] as $option)<option value="{{ $option }}">@endforeach</datalist>@endif
@endif</label>
@endforeach
@if(!$afEmbedded)<div class="flex flex-wrap items-end gap-3"><button class="ops-button hr-report-control">Apply asset filters</button><a class="ops-button hr-report-control" href="{{ url()->current().($afKeep?'?'.http_build_query($afKeep):'') }}">Clear asset filters</a></div><p class="br-help">Filters combine across the full inventory before pagination. Activation/project matches use linked allocations; handling matches use assignment, last handler or an issued item still in custody.</p></form>@endif
