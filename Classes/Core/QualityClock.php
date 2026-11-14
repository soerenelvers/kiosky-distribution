<?php
declare(strict_types=1);
namespace Kiosky\Core;
final class QualityClock {
 private static ?float $at=null;
 public static function now():int{return (int)(self::$at??time());}
 public static function micro():float{return self::$at??microtime(true);}
 public static function run(float $at,callable $work):mixed{$previous=self::$at;self::$at=$at;try{return $work();}finally{self::$at=$previous;}}
}
