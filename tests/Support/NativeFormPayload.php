<?php
namespace Tests\Support;
trait NativeFormPayload
{
    private function nativeForm(string $html, string $action): array
    {
        $dom=new \DOMDocument; @$dom->loadHTML($html); $xp=new \DOMXPath($dom);
        $forms=$xp->query('//form[@action="'.$action.'"]'); $this->assertGreaterThan(0,$forms->length,'Rendered form missing: '.$action);
        $pairs=[];
        foreach($xp->query('.//input|.//select|.//textarea',$forms->item(0)) as $n){
            $name=$n->getAttribute('name');$type=$n->getAttribute('type');
            if(!$name||$n->hasAttribute('disabled')||in_array($type,['submit','button','file'])||(in_array($type,['checkbox','radio'])&&!$n->hasAttribute('checked')))continue;
            if($n->tagName==='select'){$options=$xp->query('.//option[@selected]',$n);if(!$options->length&&!$n->hasAttribute('multiple'))$options=$xp->query('.//option[1]',$n);foreach($options as $o)$pairs[]=urlencode($name).'='.urlencode($o->hasAttribute('value')?$o->getAttribute('value'):$o->textContent);continue;}
            $pairs[]=urlencode($name).'='.urlencode($n->tagName==='textarea'?$n->textContent:$n->getAttribute('value'));
        }
        parse_str(implode('&',$pairs),$data);return $data;
    }
}
