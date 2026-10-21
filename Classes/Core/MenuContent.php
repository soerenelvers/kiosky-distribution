<?php

declare(strict_types=1);
namespace Kiosky\Core;
use RuntimeException;

final class MenuContent
{
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
