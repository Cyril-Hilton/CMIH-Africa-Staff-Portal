<?php
namespace App\Services;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
class LocationProviderChain
{
 public function search(string $query): array
 {
  foreach (['new','legacy','geoapify'] as $provider) {
   $key=config('location_chain.'.$provider);
   if (!$key || Cache::has('location-chain:cooldown:'.$provider) || !$this->reserve($provider)) continue;
   try {
    $http=Http::acceptJson()->connectTimeout(2)->timeout(5);
    if ($provider==='new') {
     $response=$http->withHeaders(['X-Goog-Api-Key'=>$key,'X-Goog-FieldMask'=>'places.displayName,places.formattedAddress,places.location'])->post('https://places.googleapis.com/v1/places:searchText',['textQuery'=>$query,'pageSize'=>5]);
     $raw=$response->json('places',[]);
    } elseif ($provider==='legacy') {
     $response=$http->get('https://maps.googleapis.com/maps/api/place/textsearch/json',['query'=>$query,'key'=>$key]);
     if (!in_array($response->json('status'),['OK','ZERO_RESULTS'],true)) throw new \RuntimeException('Unavailable');
     $raw=$response->json('results',[]);
    } else {
     $response=$http->get('https://api.geoapify.com/v1/geocode/search',['text'=>$query,'limit'=>5,'apiKey'=>$key]);
     $raw=$response->json('features',[]);
    }
    $response->throw();$results=[];
    foreach (array_slice($raw,0,5) as $item) {
     $p=$provider==='geoapify'?($item['properties']??[]):$item;
     $lat=$provider==='new'?($p['location']['latitude']??null):($provider==='legacy'?($p['geometry']['location']['lat']??null):($p['lat']??null));
     $lng=$provider==='new'?($p['location']['longitude']??null):($provider==='legacy'?($p['geometry']['location']['lng']??null):($p['lon']??null));
     if (!is_numeric($lat)||!is_numeric($lng)||abs($lat)>90||abs($lng)>180) continue;
     $name=$provider==='new'?($p['displayName']['text']??''):($p['name']??'');$address=$p['formattedAddress']??$p['formatted_address']??$p['formatted']??'';
     $results[]=['name'=>$name?:$address,'address'=>$address,'latitude'=>(float)$lat,'longitude'=>(float)$lng,'provider'=>$provider];
    }
    // An empty valid answer is not a provider outage.
    return ['results'=>$results,'provider'=>$provider,'available'=>true];
   } catch (\Throwable $e) {Cache::put('location-chain:cooldown:'.$provider,true,now()->addMinutes(5));}
  }
  return ['results'=>[],'available'=>false];
 }
 protected function reserve(string $provider): bool
 {
  $daily=$provider==='geoapify';$limit=$daily?350:200;$period=gmdate($daily?'Y-m-d':'Y-m');
  $path=storage_path('app/private/location-chain-budget.json');if(!is_dir(dirname($path)))mkdir(dirname($path),0700,true);$f=fopen($path,'c+');if(!$f)return false;
  try{if(!flock($f,LOCK_EX))return false;$data=json_decode(stream_get_contents($f),true)?:[];$slot=$provider.':'.$period;if(($data[$slot]??0)>=$limit)return false;$data[$slot]=($data[$slot]??0)+1;ftruncate($f,0);rewind($f);$ok=fwrite($f,json_encode($data))!==false;fflush($f);chmod($path,0600);return $ok;}finally{flock($f,LOCK_UN);fclose($f);}
 }
}
