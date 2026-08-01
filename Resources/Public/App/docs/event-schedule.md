# Veranstaltungsablauf und easyjob-Raumnutzung

## Architektur

Kiosky trennt zwei fachlich unterschiedliche Zeitplanungen:

- `schedule_entries` steuert die Ausspielung von Signage-Inhalten auf Displays.
- `event_schedule_entries` enthält Ablaufpunkte einer Veranstaltung und Raumnutzung.

Der vorhandene `events`-Datensatz bleibt Eigentümer der Ablaufpunkte. Die Implementierung besteht aus `EventScheduleRepository` (Persistenz, CRUD, Filter, Upsert und Display-Selector), `schedule-mapper.ts` (austauschbarer Adapter), `schedule-type-mapping.ts` (Normalisierung und Regeln) sowie `EasyJobScheduleImportService` (Vorschau, Import und Protokoll). Controller und UI enthalten kein easyjob-Feldmapping.

## Datenmodell

| Feld | Bedeutung |
|---|---|
| `id` | lokale UUID |
| `eventId` | lokale Veranstaltung, Pflicht |
| `roomId` | optionale zukünftige lokale Raumzuordnung |
| `externalRoomId` | externe easyjob-Raum-ID |
| `roomName` | externe/freie Raumbezeichnung |
| `roomConfiguration` | Raumkonfiguration |
| `type` | unveränderter Originalwert |
| `normalizedType` | nur für Vergleich, Regeln und Filter |
| `caption` | individuelle Bezeichnung; Leerraum wird `null` |
| `displayCaption` | `caption ?? type` |
| `category` | allgemeine Kiosky-Kategorie |
| `startAt`, `endAt` | ISO-8601-Zeitpunkte; Ende optional |
| `isApproximate` | steuert ausschließlich die Ausgabe von `ca.` |
| `sortOrder` | zweite Sortierstufe nach `startAt` |
| `source` | `manual` oder `easyjob` |
| `sourceId` | externe RoomUse-ID, sofern vorhanden |
| `sourceKey` | technischer eindeutiger Upsert-Schlüssel |
| `externalUpdatedAt` | optionaler Änderungszeitpunkt der Quelle |
| `metadata` | ergänzende, nicht zentrale easyjob-Werte |
| `active`, `missingSince` | sichere Behandlung externer Löschungen |
| `createdAt`, `updatedAt` | lokale Zeitstempel |

Migration `028_event_schedule.sql` legt außerdem `easyjob_room_use_type_mappings` für administrative Überschreibungen und `easyjob_schedule_import_logs` für kompakte Importprotokolle an. Sie ist additiv; bestehende Event- und Signage-Daten werden nicht geändert. Ein Rollback erfolgt nach einem Datenbank-Backup durch Entfernen der drei neuen Tabellen. Wegen möglicher produktiver Ablaufdaten wird kein automatischer destruktiver Down-Schritt ausgeliefert.

## Easyjob-Mapping

| Kiosky-Feld | Easyjob-Quelle | Pflicht | Bemerkung |
|---|---|---:|---|
| `type` | `RoomUseType.CaptionNew` | ja | Originalwert bleibt erhalten |
| `caption` | `RoomUse.Caption` | nein | leer wird `null` |
| `displayCaption` | Caption oder Type | ja | Caption hat Vorrang |
| `startAt` | `RoomUse.StartDate` | ja | ISO-8601 mit Offset |
| `endAt` | `RoomUse.EndDate` | nein | ISO-8601 mit Offset |
| `roomName` | `Room.CaptionNew` | nein | Raumbezeichnung |
| `externalRoomId` | `Room.Id` | nein | gegen reale Schnittstelle prüfen |
| `roomConfiguration` | `RoomConfiguration.CaptionNew` | nein | Raumkonfiguration |
| `source` | fester Wert `easyjob` | ja | Datenherkunft |
| `sourceId` | `RoomUse.Id` | möglichst | bevorzugter Upsert-Schlüssel |
| `externalUpdatedAt` | `RoomUse.UpdatedAt` | nein | Feldname noch zu verifizieren |
| `metadata.roomAllocationStart` | `RoomAllocation.StartDate` | nein | Zusatzinformation |
| `metadata.*Id` | `RoomUseType.Id`, `Project.Id`, `Job.Id`, `Event.Id` | nein | externe Zuordnungshilfen |

Der Adapter versteht verschachtelte Reportobjekte (`roomUseType.captionNew`) sowie verschachtelte oder flache Originalnamen (`RoomUseType.CaptionNew`).

### Normalisierung und Kategorien

`normalizeEasyjobRoomUseType()` trimmt, vereinheitlicht Mehrfach-Leerzeichen, entfernt abschließende `°`/`º` und normalisiert die Schreibweise für Vergleiche. Sie überschreibt nie `type`.

Die zentrale Konfiguration ordnet bekannte Werte `access`, `setup`, `rehearsal`, `event`, `break`, `hospitality`, `teardown`, `internal`, `cancelled` und als Fallback `other` zu. `Abbau` und `Ende` setzen standardmäßig `isApproximate=true`. Interne Typen sind unsichtbar. Administratoren können Kategorie, `ca.`, Sichtbarkeit und Sortierpriorität im vorhandenen easyjob-Bereich oder per API überschreiben. Icon und Farbe sind optional vorbereitet; Komponenten verwenden weiterhin das Kiosky-Designsystem.

Beim Veranstaltungsimport werden die Ablaufpunkte zusätzlich in die zentralen Zeitfelder verdichtet: `Einlass` setzt den Einlass, die erste öffentliche Veranstaltung/Show den Programmbeginn, eine Pausennutzung Beginn und – sofern vorhanden – Ende der Pause und `Ende` das Publikumsende. Fehlt bei einer kulturellen Veranstaltung das Pausenende, setzt Kiosky zunächst 20 Minuten; die Zeit bleibt redaktionell änderbar. `DayTimeOut` bleibt getrennt als Aufbau beziehungsweise Ankunft des Veranstalters erhalten.

## Upsert und externe Löschungen

Der bevorzugte Schlüssel ist `easyjob + RoomUse.Id`. Ohne ID erzeugt der Mapper zentral einen SHA-256-Schlüssel aus Event, Raum, Originaltyp, Startzeit und Caption. Der fachliche Klartext entspricht `easyjob:{eventId}:{room}:{type}:{startAt}:{caption}`. Verschiedene `sourceId`-Werte bleiben bei gleicher Uhrzeit getrennt; manuelle Einträge werden nie getroffen.

Teilimporte löschen oder deaktivieren nichts und geben eine Warnung zurück. Nur ein ausdrücklich mit `fullSnapshot: true` markierter Import setzt nicht mehr enthaltene easyjob-Punkte auf `active=false` und belegt `missingSince`. Eine spätere erneute Lieferung reaktiviert sie. Es gibt kein ungeprüftes physisches Löschen.

## API und Berechtigungen

| Methode | Route | Rolle |
|---|---|---|
| `GET` | `/api/events/:eventId/schedule` | Admin, Redaktion, Betrachter |
| `POST` | `/api/events/:eventId/schedule` | Admin, Redaktion |
| `PATCH` | `/api/events/:eventId/schedule/:entryId` | Admin, Redaktion; nur `manual` |
| `DELETE` | `/api/events/:eventId/schedule/:entryId` | Admin, Redaktion; nur `manual` |
| `POST` | `/api/integrations/easyjob/schedule/preview` | Admin |
| `POST` | `/api/integrations/easyjob/schedule/import` | Admin |
| `GET`, `PUT` | `/api/integrations/easyjob/schedule/types` | Admin |
| `GET` | `/api/public/events/:eventId/schedule` | öffentlich, intern gefiltert |

GET-Filter: `roomId`, `roomName`, `category`, `type`, `date`, `source`. Sortierung: `startAt`, `sortOrder`, `displayCaption`. Der öffentliche Selector unterstützt `room`, mehrfaches `category`, `limit`, `from`, `until`, `includePast` und `source`; `internal` wird zentral entfernt.

Beispiel:

```json
{
  "eventId": "lokale-event-uuid",
  "fullSnapshot": false,
  "roomUses": [{
    "id": "room-use-1",
    "caption": null,
    "startDate": "2024-04-10T07:00:00+02:00",
    "roomUseType": { "captionNew": "Einlass" },
    "room": { "captionNew": "Saal 1" }
  }]
}
```

Die Vorschau liefert je Zeile `new`, `changed`, `unchanged` oder `invalid` mitsamt normalisiertem Typ und Kategorie, schreibt aber nichts. Der Import liefert `created`, `updated`, `unchanged`, `skipped`, `invalid`, `unknownTypes`, `warnings` und `errors`.

## Datum, Displays und Importprotokoll

easyjob liefert in den dokumentierten Projekt-, Job- und Raumkalenderantworten lokale ISO-8601-Zeitwerte ohne Offset. Kiosky interpretiert diese Werte deshalb ausdrücklich in `Europe/Berlin` und speichert sie anschließend als kanonische UTC-Zeitpunkte. Bereits mit `Z`, `+01:00` oder `+02:00` gelieferte Werte werden unverändert korrekt umgerechnet. Die Vorschau weist auf die vorgenommene Zeitzonenannahme hin.

`getUpcomingScheduleEntries()` unterstützt Raum, Kategorien, Limit, Zeitraum, Vergangenheit, Quelle und `publicOnly`. Öffentliche Ausgaben müssen `publicOnly=true` verwenden; die öffentliche Route erzwingt dies.

Jeder Import speichert Start/Ende, Dauer, Benutzer/Systemprozess, alle Zähler, unbekannte Typen, Warnungen und Fehler. Rohpayloads und sensible Daten werden nicht protokolliert.

## Zentral markierte offene Easyjob-Fragen

Die Namen stammen aus einem Report, nicht aus einer verifizierten WebApi-/Exportspezifikation. Vor der echten Anbindung sind im Adapter zu klären:

1. Endpoint für RoomUse/RoomAllocation und Paging-/Snapshot-Semantik;
2. stabile IDs und Datentypen von RoomUse, Room, Project, Job und Event;
3. reales Änderungs-/Löschkennzeichen und Name von `RoomUse.UpdatedAt`;
4. Beziehung eines RoomUse zum lokal importierten Projekt- oder Job-Event;
5. garantierter Zeitzonenoffset und easyjob-Serverzeitzone;
6. weitere abschließende Sonderzeichen neben `°`/`º`;
7. Herkunft und Format der easyjob-Typfarbe.

Neue Typen werden in `schedule-type-mapping.ts` ergänzt oder über die Verwaltungs-API überschrieben. Unbekannte Typen bleiben importierbar, werden als `other` gespeichert und im Bericht ausgewiesen.
