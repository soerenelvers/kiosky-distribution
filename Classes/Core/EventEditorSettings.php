<?php
declare(strict_types=1);
namespace Kiosky\Core;
final class EventEditorSettings
{
    public const FIELDS=['title','subtitle','status','organizer','eventType','date','venue','room','setupStart','admissionStart','eventStart','breakStart','breakEnd','eventEnd','description','publicNotes','internalNotes','ticketUrl','remainingTickets','imageUrl','boxOfficeAvailable','boxOfficeOpenAt','schedule'];
    public static function normalize(array $input):array
    {
        $input=is_array($input['fields'] ?? null)?$input['fields']:[];$fields=[];
        foreach(self::FIELDS as $key)$fields[$key]=$key==='title'||($input[$key] ?? true)!==false;
        return ['fields'=>$fields];
    }
}
