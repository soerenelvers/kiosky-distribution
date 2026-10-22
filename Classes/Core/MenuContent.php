<?php

declare(strict_types=1);
namespace Kiosky\Core;
use RuntimeException;

final class MenuContent
{
    public const DEFAULT_SETTINGS = ['defaultTemplate'=>'blue','defaultOrientation'=>'landscape','defaultTitle'=>'Speisekarte','defaultSubtitle'=>'Frisch für Sie zubereitet','defaultFooter'=>'Alle Preise in Euro.'];
    public static function settings(array $input): array
    {
        if (array_diff(array_keys($input),array_keys(self::DEFAULT_SETTINGS))) throw new RuntimeException('Unbekannte Speisekarten-Einstellung.',422);
        $settings = array_replace(self::DEFAULT_SETTINGS,$input);
        if ((!is_string($settings['defaultTemplate']) || (!in_array($settings['defaultTemplate'],['blue','light','dark'],true) && !preg_match('/^template:[a-zA-Z0-9-]{16,64}$/',$settings['defaultTemplate']))) || !in_array($settings['defaultOrientation'],['portrait','landscape','auto'],true)) throw new RuntimeException('Vorlage oder Format ist ungültig.',422);
        foreach (['defaultTitle'=>60,'defaultSubtitle'=>90,'defaultFooter'=>120] as $field=>$max) {
            $value = $settings[$field];
            if (!is_string($value) || preg_match('/[\x00-\x1f\x7f]/u',$value) || preg_match_all('/./us',$value) > $max || ($field === 'defaultTitle' && trim($value) === '')) throw new RuntimeException('Die Standardtexte sind ungültig oder zu lang.',422);
            $settings[$field] = trim($value);
        }
        return $settings;
    }

    /** Percentage positions keep complete menu rows together at any player resolution. */
    public static function responsiveDocument(array $document,string $orientation): array
    {
        unset($document['responsiveLayouts']);
        if($orientation==='auto'){
            $elements=array_map(static fn(array $e):array=>($e['type'] ?? '')==='text' && !empty($e['menuField'])?array_replace($e,['autoFontSize'=>true,'responsiveText'=>true]):$e,$document['elements'] ?? []);
            $document['responsiveLayouts']=['landscape'=>$elements,'portrait'=>$elements];
        }
        return $document;
    }

    public static function pdfDocument(mixed $page,string $orientation,?array $existing=null): array
    {
        if (!is_array($page) || array_diff(array_keys($page),['src','fileName','pageNumber','pageCount']) || !is_string($page['fileName'] ?? null) || strlen($page['fileName'])>640 || preg_match('/[\x00-\x1f\x7f]/',$page['fileName']) || !is_int($page['pageNumber'] ?? null) || !is_int($page['pageCount'] ?? null) || $page['pageNumber']<1 || $page['pageCount']>20 || $page['pageNumber']>$page['pageCount']) throw new RuntimeException('Ungültige PDF-Seitenangaben.',422);
        $src=$page['src'] ?? null;
        if (!is_string($src) || strlen($src)>2800000 || !preg_match('#^data:image/jpeg;base64,[A-Za-z0-9+/]+={0,2}$#D',$src)) throw new RuntimeException('Ungültiges PDF-Seitenbild.',422);
        $image=base64_decode(substr($src,23),true);$size=$image!==false?@getimagesizefromstring($image):false;
        if (!$size || $size[2]!==IMAGETYPE_JPEG || $size[0]!==($orientation==='portrait'?1080:1920) || $size[1]!==($orientation==='portrait'?1920:1080)) throw new RuntimeException('PDF-Seitenbild hat ein ungültiges Format.',422);
        $metadata=array_intersect_key($page,array_flip(['fileName','pageNumber','pageCount']));
        if ($existing!==null) {
            $found=false;
            foreach ($existing['elements'] ?? [] as $index=>$element) if (($element['menuPdfImage'] ?? false)===true) {$existing['elements'][$index]['src']=$src;$found=true;}
            if (!$found) throw new RuntimeException('PDF-Bildfeld fehlt in der Karte.',422);
            $existing['menuPdf']=$metadata;return $existing;
        }
        return ['menuPdf'=>$metadata,'designOrientation'=>$orientation,'settings'=>['background'=>['mode'=>'color','color1'=>'#ffffff'],'clock'=>['enabled'=>false],'logo'=>['enabled'=>false]],'elements'=>[['id'=>'menu-pdf-image','type'=>'image','menuPdfImage'=>true,'x'=>0,'y'=>0,'width'=>100,'height'=>100,'src'=>$src,'opacity'=>100,'rotation'=>0]]];
    }

    public static function validateTemplate(mixed $document): void
    {
        if (!is_array($document) || strlen(json_encode($document)) > 80000) throw new RuntimeException('Ungültige Speisekarten-Vorlage.',422);
        self::document($document,['title'=>'Speisekarte','subtitle'=>'','footer'=>'','items'=>[]]);
    }

    public static function validate(mixed $value): array
    {
        if (!is_array($value) || array_diff(array_keys($value),['title','subtitle','footer','items'])) throw new RuntimeException('Ungültige Speisekartenfelder.',422);
        $text = static function (mixed $value, int $max, bool $required = false): string {
            if (!is_string($value) || preg_match('/[\x00-\x1f\x7f]/u',$value) || preg_match_all('/./us',$value) > $max) throw new RuntimeException('Text ist ungültig oder zu lang.',422);
            $value = trim($value);
            if ($required && $value === '') throw new RuntimeException('Titel und Speisen dürfen nicht leer sein.',422);
            return $value;
        };
        if (!is_array($value['items'] ?? null) || !array_is_list($value['items']) || count($value['items']) > 8) throw new RuntimeException('Pro Slide sind höchstens acht Speisen möglich.',422);
        $items = array_map(static function ($item) use ($text): array {
            if (!is_array($item) || array_diff(array_keys($item),['name','description','price'])) throw new RuntimeException('Ungültige Speisenfelder.',422);
            $price = $text($item['price'] ?? null,16);
            if ($price !== '' && !preg_match('/^\d{1,5}(?:[,.]\d{1,2})?(?:\s?€)?$/u',$price)) throw new RuntimeException('Preis bitte als Zahl, zum Beispiel 12,50 €, eingeben.',422);
            return ['name'=>$text($item['name'] ?? null,50,true),'description'=>$text($item['description'] ?? null,90),'price'=>$price];
        },$value['items']);
        return ['title'=>$text($value['title'] ?? null,60,true),'subtitle'=>$text($value['subtitle'] ?? null,90),'footer'=>$text($value['footer'] ?? null,120),'items'=>$items];
    }
    public static function document(array $document,array $menu): array
    {
        $fields = ['title'=>$menu['title'],'subtitle'=>$menu['subtitle'],'footer'=>$menu['footer']];
        for ($i=0;$i<8;$i++) foreach (['name','description','price'] as $field) $fields["items.$i.$field"] = $menu['items'][$i][$field] ?? '';
        if (!is_array($document['elements'] ?? null)) throw new RuntimeException('Die Speisekartenvorlage enthält keine Elemente.',422);
        $found = [];
        foreach ($document['elements'] as &$element) {
            if (!is_array($element)) throw new RuntimeException('Ungültige Vorlage.',422);
            $field = (string)($element['menuField'] ?? '');
            if ($field === '') continue;
            if (!array_key_exists($field,$fields) || ($element['type'] ?? '') !== 'text') throw new RuntimeException('Ungültiges Speisekartenfeld in der Vorlage.',422);
            $found[] = $field;
            $element['text'] = $fields[$field];
        }
        unset($element);
        if (array_diff(array_keys($fields),$found)) throw new RuntimeException('Die Vorlage ist unvollständig. Bitte alle Speisekartenfelder im Layout erhalten.',422);
        $document['menu'] = $menu;
        return $document;
    }
}
