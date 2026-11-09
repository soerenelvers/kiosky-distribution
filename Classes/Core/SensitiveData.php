<?php
declare(strict_types=1);
namespace Kiosky\Core;
final class SensitiveData
{
    public static function text(string $value): string
    {
        $patterns=[
            '~\b(Bearer|Basic)\s+[A-Za-z0-9+/=_.:-]+~i'=>'$1 [REDACTED]',
            '~(https?://)[^\s/:@]+:[^\s/@]+@~i'=>'$1[REDACTED]@',
            '~([?&](?:access[_-]?token|api[_-]?key|key|token|secret|password|client_secret)=)[^&#\s]+~i'=>'$1[REDACTED]',
            '~((?:["\']?(?:access[_-]?token|refresh[_-]?token|api[_-]?key|password|passwd|secret|client_secret|authorization|cookie)["\']?)\s*[:=]\s*)(?:"[^"\r\n]*"|\'[^\'\r\n]*\'|[^\s,;&]+)~i'=>'$1[REDACTED]',
            '~\bkiosky_[a-f0-9]{16}_[A-Za-z0-9_-]{43}\b~'=>'[REDACTED]',
            '~\bgh[pousr]_[A-Za-z0-9]{16,}\b~'=>'[REDACTED]',
        ];
        return preg_replace(array_keys($patterns),array_values($patterns),$value) ?? '[REDACTED]';
    }
    public static function redact(mixed $value,int $depth=0): mixed
    {
        if(is_string($value))return self::text($value);if(!is_array($value))return $value;if($depth>=20)return '[TRUNCATED]';
        $out=[];foreach(array_slice($value,0,500,true) as $key=>$item)$out[$key]=preg_match('/password|passwd|secret|authorization|cookie|(?:access|refresh|id)[_-]?token|api[_-]?key|encrypted[_-]?credentials|token[_-]?hash|token$/i',(string)$key)?'[REDACTED]':self::redact($item,$depth+1);return $out;
    }
}
