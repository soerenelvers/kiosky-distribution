# Kiosky External Control API v1

Die External Control API steuert Kiosky aus Leitstellen, Gebäudeautomation, Eventsystemen oder eigenen Integrationen. Sie kann Kanäle und einzelne Slides an Displays beziehungsweise Displaygruppen zuweisen, Warnungen ein- und ausblenden und Betriebspresets ausführen.

Die maschinenlesbare Spezifikation liegt in [`openapi.yaml`](./openapi.yaml). Nach dem Build werden beide Dateien außerdem unter `/docs/` ausgeliefert. `GET /api/v1` liefert die aktuellen Dokumentationspfade.

## Authentifizierung und Sicherheit

Alle Endpunkte außer `/api/health` benötigen einen gültigen Zugang. Empfohlen wird ein eigener API-Benutzer, der in Kiosky unter **Schnittstellen → API-Zugänge** angelegt wird. Der dabei erzeugte Schlüssel wird nur einmal angezeigt und ist ausschließlich für `/api/v1` berechtigt:

```http
Authorization: Bearer <API-BENUTZERSCHLÜSSEL>
```

Alternativ wird `X-API-Key` unterstützt. Der globale `KIOSKY_ADMIN_API_KEY` bleibt als administrativer Systemschlüssel verfügbar, sollte für neue Integrationen aber nicht verwendet werden. API-Benutzer können in der Oberfläche jederzeit widerrufen werden. Schlüssel gehören ausschließlich in einen Secret Store, nie in Browsercode oder URLs. TLS/HTTPS ist im produktiven Betrieb verpflichtend. Jede Änderung wird mit Akteur, Request-ID, Quell-IP, Ziel und Aktion im Audit-Log protokolliert. Antworten enthalten `X-Request-ID`; diese Kennung sollte das aufrufende System in seinem Log speichern.

In der WordPress-Ausgabe lautet der Einstieg
`/wp-json/kiosky/v1/api?path=/api/v1`. Die Authentifizierung erfolgt mit einem
WordPress-Administrator und einem
[WordPress-Anwendungspasswort](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/).
In TYPO3 lautet der Einstieg `/kiosky-api/?path=/api/v1`; dort wird der in den
Extension-Einstellungen hinterlegte `externalApiKey` als Header `X-API-Key`
gesendet. In beiden CMS-Ausgaben sind die `/api/v1`-Schreibzugriffe ausschließlich
administrativ freigegeben.

## Grundregeln

- Basis-Pfad: `/api/v1`
- Content-Type für Schreibzugriffe: `application/json`
- Zeitangaben: ISO 8601, intern normalisiert auf UTC
- IDs werden über die Leseendpunkte ermittelt und nicht geraten.
- Maximale Request-Größe: 1 MB
- Maximal 500 Ziele pro Warnung oder Preset-Ausführung
- Maximal 200 Slides pro Kanal
- Fehler verwenden `{ "error": { "code": "…", "message": "…" } }`.

## Ressourcen ermitteln

```http
GET /api/v1/displays
GET /api/v1/display-groups
GET /api/v1/slides
GET /api/v1/channels
GET /api/v1/presets
```

## Slides einem Kanal zuordnen

`PUT` ersetzt die komplette Playlist atomar. Eine leere Liste leert den Kanal.

```http
PUT /api/v1/channels/{channelId}/slides
Content-Type: application/json

{
  "items": [
    { "slideId": "slide-uuid", "durationSeconds": 12, "transition": "fade" },
    { "slideId": "weitere-slide-uuid", "durationSeconds": 20, "transition": "inherit" }
  ]
}
```

Zulässige Übergänge: `inherit`, `fade`, `slide`, `zoom`, `none`. Die Dauer liegt zwischen 3 und 600 Sekunden.

## Kanal oder Slide einem Ziel zuordnen

Die kurzen, gut lesbaren Endpunkte sind für typische Automationen gedacht:

```http
PUT /api/v1/displays/{displayId}/channel
PUT /api/v1/displays/{displayId}/slide
PUT /api/v1/display-groups/{groupId}/channel
PUT /api/v1/display-groups/{groupId}/slide
Content-Type: application/json

{
  "contentId": "channel-oder-slide-uuid",
  "priority": 200,
  "startsAt": "2026-09-14T17:00:00+02:00",
  "expiresAt": "2026-09-14T23:00:00+02:00"
}
```

`startsAt` und `expiresAt` sind optional. Ohne Zeitfenster gilt die Zuweisung sofort und dauerhaft. Prioritäten reichen von 0 bis 1000; Standard ist 100. Eine direkte Display-Zuweisung gewinnt bei gleicher Laufzeit unabhängig von der Priorität gegen eine Gruppenzuweisung. Innerhalb derselben Zielebene gewinnt die höchste Priorität, danach die zuletzt aktualisierte Zuweisung.

Der generische Endpunkt eignet sich für dynamische Integrationen:

```http
POST /api/v1/assignments

{
  "target": { "type": "group", "id": "group-uuid" },
  "content": { "type": "slide", "id": "slide-uuid" },
  "priority": 500,
  "expiresAt": "2026-09-14T23:00:00Z"
}
```

```http
GET    /api/v1/assignments?targetType=group&targetId={id}
DELETE /api/v1/assignments/{assignmentId}
```

Der Player wertet gültige externe Zuweisungen vor Zeitplänen und dem Standardkanal aus. Direkte Slides werden als einzelne, dauerhaft wiederholte Player-Playlist ausgeliefert.

## Sicherheitswarnungen

Warnung aktivieren:

```http
POST /api/v1/emergencies
Content-Type: application/json

{
  "warningType": "evacuation",
  "title": "Gebäude bitte verlassen",
  "instructions": "Bitte nutzen Sie die ausgeschilderten Fluchtwege und folgen Sie dem Personal.",
  "targets": [
    { "type": "group", "id": "group-uuid" },
    { "type": "display", "id": "display-uuid" }
  ]
}
```

Warnungstypen: `evacuation`, `fire`, `severe_weather`, `security`, `medical`, `technical`, `information`, `custom`.

Es kann systemweit nur eine aktive Warnung geben. Eine neue Aktivierung beendet die vorherige. Ziel, Überschrift und Anweisung werden vollständig vom Aufrufer gesetzt. Status lesen und Warnung beenden:

```http
GET    /api/v1/emergencies/active
DELETE /api/v1/emergencies/active
```

## Betriebspresets ausführen

Ein Preset besitzt den Typ `channel`, `emergency` oder `reset`. Die Ausführung wird mit Ergebnis und Zielen protokolliert.

```http
POST /api/v1/presets/{presetId}/execute
Content-Type: application/json

{
  "targets": [
    { "type": "group", "id": "group-uuid" }
  ]
}
```

Bei Kanal-Presets kann `channelId` in der Anfrage den im Preset hinterlegten Kanal überschreiben. Bei Warn-Presets können `warningType`, `title` und `instructions` überschrieben werden. Ein Reset-Preset entfernt die externen Zuweisungen der Ziele und beendet standardmäßig die aktive Warnung; mit `"endEmergency": false` bleibt sie aktiv.

```http
GET /api/v1/preset-executions
GET /api/v1/preset-executions?presetId={presetId}
```

## Beispiel mit curl

```bash
curl --fail-with-body \
  -H "Authorization: Bearer $KIOSKY_API_KEY" \
  -H "Content-Type: application/json" \
  -X PUT "https://kiosky.example.org/api/v1/display-groups/GROUP_ID/channel" \
  -d '{"contentId":"CHANNEL_ID","priority":200}'
```

## Empfohlener Integrationsablauf

1. Beim Start Ressourcen-IDs lesen und lokal cachen.
2. Vor einer Änderung Ziel und Inhalt validieren.
3. Zuweisungen über `PUT` aktualisieren, wenn genau ein aktueller Zustand pro Ziel gewünscht ist.
4. HTTP-Status, Antwortkörper und `X-Request-ID` protokollieren.
5. Nach Warnungs- oder Preset-Aufrufen den aktiven Zustand lesen.
6. Bei `401` den Schlüssel prüfen, bei `403` die Rechte, bei `422` die Eingabe und bei `5xx` mit begrenztem Backoff wiederholen.

## Priorität und Auslieferung

Die effektive Inhaltsreihenfolge eines Displays ist:

1. direkte externe Display-Zuweisung
2. externe Gruppenzuweisung
3. veröffentlichter Zeitplan (`takeover`, `high`, `normal`)
4. Standardkanal des Displays

Eine zielende Sicherheitswarnung wird zusätzlich als Overlay ausgeliefert und übersteuert die normale Darstellung im Player.
