<?php
declare(strict_types=1);
namespace Kiosky\Core;
final class EventImageEdit
{
    public static function normalize(mixed $value): ?array
    {
        if(!is_array($value)||array_is_list($value))return null;
        $number=static function(string $key,float $fallback,float $min,float $max)use($value):float{if(!isset($value[$key])||!is_numeric($value[$key])||!is_finite((float)$value[$key]))return $fallback;return round(max($min,min($max,(float)$value[$key])),4);};
        $width=$number('width',100,5,100);$height=$number('height',100,5,100);
        return ['x'=>$number('x',0,0,100-$width),'y'=>$number('y',0,0,100-$height),'width'=>$width,'height'=>$height,'naturalWidth'=>$number('naturalWidth',1,1,100000),'naturalHeight'=>$number('naturalHeight',1,1,100000)];
    }
}
