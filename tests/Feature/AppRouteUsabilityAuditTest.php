<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AppRouteUsabilityAuditTest extends TestCase
{
 use RefreshDatabase;
 public function test_every_registered_controller_action_exists(): void
 {
  $missing=[];
  foreach(Route::getRoutes() as $route){$action=$route->getActionName();if(str_starts_with($route->uri(),'brands/'))continue;if($action==='Closure'||!str_contains($action,'@'))continue;[$class,$method]=explode('@',$action,2);if(!class_exists($class)||!method_exists($class,$method))$missing[]=$route->uri().' => '.$action;}
  $this->assertSame([],$missing,implode("\n",$missing));
 }
 public function test_rendered_navigation_and_forms_match_real_application_routes(): void
 {
  $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-21 09:00:00','Africa/Accra'));
  $admin=User::factory()->create(['status'=>'active','access_role'=>'super_admin']);$this->actingAs($admin);$errors=[];$checks=[];$names=[];$presentation=[];$fieldLabels=[];
  foreach(Route::getRoutes() as $route){$name=$route->getName();$uri=$route->uri();if(!$name||!in_array('GET',$route->methods())||str_contains($uri,'{'))continue;
   if(!preg_match('~^(portal/|admin/|merchandisers/admin)~',$uri))continue;
   if(preg_match('~poll|export|callback|dropbox|/data$|/live$|/refresh|/read|/sync|/download|/template|/id-card|/cvo$|/suggest|/api|/attendance/clock|/notification|/account-link|/login~',$uri))continue;$names[$name]=[];
  }
  foreach(['overview','projects','calendar','resources','compliance','accountability','reports'] as $view)$names['dashboard:'.$view]=['exec_view'=>$view];
  $names['dashboard:all']=['weekly_department'=>'all'];
  foreach($names as $key=>$query){$name=explode(':',$key)[0];$response=$this->get(route($name,$query));$checks[$key]=$response->status();if(in_array($response->status(),[404,405,419])||$response->status()>=500){$errors[]=$key.' returned '.$response->status().' '.($response->exception?->getMessage()??'');continue;}if($response->status()!==200||!str_contains($response->headers->get('Content-Type',''),'text/html'))continue;
   $dom=new \DOMDocument();@$dom->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());$xp=new \DOMXPath($dom);
   $ids=[];foreach($xp->query('//*[@id]') as $node){$id=$node->getAttribute('id');if($id!==''&&isset($ids[$id]))$presentation[]=$key.' duplicate ID: '.$id;$ids[$id]=true;}
   foreach($xp->query('//input[not(@type="hidden") and not(@type="submit") and not(@type="button")] | //select | //textarea') as $field){$id=$field->getAttribute('id');$labelled=$field->hasAttribute('aria-label')||$field->hasAttribute(':aria-label')||$field->hasAttribute('aria-labelledby')||$field->hasAttribute('title')||$xp->query('ancestor::label',$field)->length||($id&&$xp->query('//label[@for="'. $id .' "]')->length);if(!$labelled){$found=false;foreach($dom->getElementsByTagName('label') as $label)if($id!==''&&$label->getAttribute('for')===$id)$found=true;if(!$found)$fieldLabels[$key][]=$field->tagName.':'.($field->getAttribute('name')?:$id);}}
   foreach($xp->query('//button') as $node)if(trim($node->textContent)===''&&!$node->hasAttribute('aria-label')&&!$node->hasAttribute(':aria-label')&&!$node->hasAttribute('aria-labelledby')&&!$node->hasAttribute('title')&&!$node->hasAttribute('x-text'))$presentation[]=$key.' unnamed button '.$dom->saveHTML($node);
   foreach($xp->query('//a[@href] | //form[@action]') as $element){$href=html_entity_decode($element->getAttribute($element->tagName==='a'?'href':'action'));if(!$href||preg_match('~^(#|mailto:|tel:|javascript:|data:)~i',$href))continue;$host=parse_url($href,PHP_URL_HOST);if($host&&!in_array($host,['127.0.0.1','localhost','portal.cmih.africa',parse_url(config('app.url'),PHP_URL_HOST)]))continue;
    $path=parse_url($href,PHP_URL_PATH)?:'/';if(preg_match('~\.(png|jpe?g|webp|svg|css|js|ico|pdf|xlsx)$~i',$path))continue;$method=$element->tagName==='a'?'GET':strtoupper($element->getAttribute('method')?:'GET');if($method==='POST')foreach($element->getElementsByTagName('input') as $input)if($input->getAttribute('name')==='_method')$method=strtoupper($input->getAttribute('value'));
    try{app('router')->getRoutes()->match(Request::create($href,$method));}catch(\Throwable $e){$errors[]=$key.' '.$element->tagName.' '.trim($element->textContent).' => '.$method.' '.$path.' ('.class_basename($e).')';}
   }
  }
  $errors=array_values(array_unique($errors));$output=getenv('CMIH_APP_AUDIT_OUTPUT');if($output)file_put_contents($output,json_encode(['page_checks'=>$checks,'unlabelled_fields'=>$fieldLabels,'route_contract_errors'=>$errors,'presentation_findings'=>array_values(array_unique($presentation))],JSON_PRETTY_PRINT));
  $this->assertSame([],$errors,implode("\n",$errors));$this->assertSame([],$presentation,implode("\n",$presentation));$this->assertGreaterThan(30,count($checks));
 }
}
