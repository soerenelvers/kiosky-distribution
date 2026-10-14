<?php

declare(strict_types=1);
namespace Kiosky\Core;

use RuntimeException;

final class ExternalRequest
{
    public static function body(string $raw): array
    {
        if (strlen($raw) > 1_000_000) throw new RuntimeException('Die API-Anfrage darf höchstens 1 MB groß sein.',413);
        if (trim($raw) === '') return [];
        try { $value = json_decode($raw,false,64,JSON_THROW_ON_ERROR); }
        catch (\JsonException $error) { throw new RuntimeException('Die Anfrage enthält ungültiges JSON.',400); }
        if (!$value instanceof \stdClass) throw new RuntimeException('Die Anfrage muss ein JSON-Objekt enthalten.',400);
        return json_decode($raw,true,64,JSON_THROW_ON_ERROR);
    }

    public static function headers(string $requestId, int $status): array
    {
        return ['Cache-Control'=>'no-store','X-Request-ID'=>$requestId] + ($status===401?['WWW-Authenticate'=>'Bearer realm="Kiosky External Control API"']:[]);
    }

    public static function error(\RuntimeException $error): array
    {
        return ['error'=>['code'=>$error->getCode()===413?'PAYLOAD_TOO_LARGE':'INVALID_REQUEST','message'=>$error->getMessage()]];
    }
}
