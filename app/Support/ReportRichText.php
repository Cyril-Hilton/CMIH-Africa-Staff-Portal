<?php
namespace App\Support;

use DOMDocument;
use DOMNode;

/** Presentation allow-list for saved CKEditor reports; never executes source HTML. */
class ReportRichText
{
    public static function html(?string $source): string
    {
        if (!trim($source ?? '')) return '';
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try { $doc->loadHTML('<?xml encoding="utf-8" ?><html><body>'.$source.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        $body = $doc->getElementsByTagName('body')->item(0);
        return $body ? self::children($body) : '';
    }

    private static function children(DOMNode $parent): string
    {
        $html='';
        foreach ($parent->childNodes as $node) {
            if ($node->nodeType === XML_TEXT_NODE) { $html .= htmlspecialchars($node->textContent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); continue; }
            if ($node->nodeType !== XML_ELEMENT_NODE) continue;
            $tag=strtolower($node->nodeName);
            if (in_array($tag,['script','style','iframe','object','embed','svg','math','form','input','button','textarea','link','meta','base'],true)) continue;
            if (!in_array($tag,['p','div','span','strong','b','em','i','u','s','sup','sub','h2','h3','h4','h5','h6','ul','ol','li','blockquote','br','hr','table','thead','tbody','tfoot','tr','th','td','figure','figcaption','img','a'],true)) { $html.=self::children($node); continue; }
            $attrs='';
            foreach (['colspan','rowspan','width','height','start'] as $attr) {
                $value=$node->getAttribute($attr);
                if ($value!=='' && preg_match('/^\d{1,4}%?$/D',$value)) $attrs.=' '.$attr.'="'.$value.'"';
            }
            if ($tag==='img' || $tag==='a') {
                $attr=$tag==='img'?'src':'href';$value=trim($node->getAttribute($attr));
                if (preg_match('~^https?://[^\s<>"\x00-\x1f]+$~iD',$value) || preg_match('~^/(?!/)[^\s<>"\x00-\x1f]*$~D',$value)) $attrs.=' '.$attr.'="'.htmlspecialchars($value,ENT_QUOTES,'UTF-8').'"';
                elseif ($tag==='img') continue;
                if ($tag==='a') $attrs.=' rel="noopener noreferrer"';
                if ($tag==='img') $attrs.=' loading="lazy" alt="'.htmlspecialchars($node->getAttribute('alt'),ENT_QUOTES,'UTF-8').'"';
            }
            $styles=[];
            foreach (explode(';',$node->getAttribute('style')) as $declaration) {
                $pair=explode(':',$declaration,2);if(count($pair)!==2)continue;
                [$property,$value]=array_map('trim',$pair);$property=strtolower($property);
                $valid=match($property) {
                    'text-align'=>preg_match('/^(left|center|right|justify)$/D',$value),
                    'color','background-color'=>preg_match('/^(#[a-f0-9]{3,8}|[a-z]{1,20}|rgba?\([\d.,%\s]+\))$/iD',$value),
                    'font-size'=>preg_match('/^\d{1,2}(\.\d{1,2})?(px|pt|em|rem|%)$/D',$value),
                    'width','height'=>preg_match('/^\d{1,4}(\.\d{1,2})?(px|%)$/D',$value),
                    'font-weight'=>preg_match('/^(bold|normal|[1-9]00)$/D',$value),
                    'font-style'=>preg_match('/^(italic|normal)$/D',$value),
                    'text-decoration'=>preg_match('/^(underline|line-through|none)$/D',$value),
                    default=>false,
                };
                if($valid)$styles[]=$property.':'.$value;
            }
            if($styles)$attrs.=' style="'.htmlspecialchars(implode(';',$styles),ENT_QUOTES,'UTF-8').'"';
            $html.='<'.$tag.$attrs.'>';
            if(!in_array($tag,['img','br','hr'],true))$html.=self::children($node).'</'.$tag.'>';
        }
        return $html;
    }
}
