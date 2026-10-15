# Kiosky External Control API v1

Die External Control API steuert Kiosky aus Leitstellen, Gebäudeautomation, Eventsystemen oder eigenen Integrationen. Sie kann Kanäle und einzelne Slides an Displays beziehungsweise Displaygruppen zuweisen, Warnungen ein- und ausblenden und Betriebspresets ausführen.

Die maschinenlesbare Spezifikation liegt in [`openapi.yaml`](./openapi.yaml). Nach dem Build werden beide Dateien außerdem unter `/docs/` ausgeliefert. `GET /api/v1` liefert die aktuellen Dokumentationspfade.

## Einrichtung und Home Assistant

Die [Einrichtungsanleitung](setup-guide.md#4-externe-api-für-gebäudeautomation-und-integrationen)
erklärt Schlüsselanlage, plattformspezifische Adressen und Fehlerbehebung.
Die öffentliche [HACS-Integration](https://github.com/soerenelvers/hacs-kiosky)
unterstützt diese API ab Kiosky 3.3.1 mit Home Assistant ab 2026.3.0.
Installation und Automationsbeispiele: [Home Assistant einrichten](setup-guide.md#5-home-assistant-über-hacs-einrichten).

## Authentifizierung und Sicherheit

Alle Endpunkte außer `/api/health` benötigen einen gültigen Zugang. Empfohlen wird ein eigener API-Benutzer, der in Kiosky unter **Schnittstellen → API-Zugänge** angelegt wird. Der dabei erzeugte Schlüssel wird nur einmal angezeigt und ist ausschließlich für `/api/v1` berechtigt:

```http
Authorization: Bearer <API-BENUTZERSCHLÜSSEL>
```

Alternativ wird `X-API-Key` unterstützt. Der globale `KIOSKY_ADMIN_API_KEY` bleibt als administrativer Systemschlüssel verfügbar, sollte für neue Integrationen aber nicht verwendet werden. API-Benutzer können in der Oberfläche jederzeit widerrufen werden. Schlüssel gehören ausschließlich in einen Secret Store, nie in Browsercode oder URLs. TLS/HTTPS ist im produktiven Betrieb verpflichtend. Jede Änderung wird mit Akteur, Request-ID, Quell-IP, Ziel und Aktion im Audit-Log protokolliert. Antworten enthalten `X-Request-ID`; diese Kennung sollte das aufrufende System in seinem Log speichern.

## Betrieb als Standalone, WordPress-Plugin oder TYPO3-Extension

Ab Kiosky 3.3.1 lassen sich in **allen drei Varianten** unter **Schnittstellen → Externe API → API-Zugänge** eigene Maschinenzugänge anlegen und widerrufen. Das Anlegen eines aktiven Zugangs schaltet den externen Zugriff frei. Dafür ist kein CMS-Login, Cookie oder CMS-Nonce im aufrufenden Automationssystem erforderlich. Die Oberfläche zeigt die tatsächlich verwendete externe API-Basisadresse an; dort steht auch die plattformgerecht verlinkte Dokumentation.

| Variante | Externe API-Basisadresse (Beispiel) |
| --- | --- |
| Standalone | `https://kiosky.example/api/v1` |
| WordPress | `https://website.example/wp-json/kiosky/v1/control` |
| TYPO3 | `https://website.example/kiosky-api/v1` |

An diese Basisadresse wird der Ressourcenpfad angehängt, beispielsweise `/displays`, `/assignments` oder `/presets/{id}/execute`. Alle folgenden Beispiele mit `/api/v1` verwenden den Standalone-Präfix; die Ressourcen und JSON-Daten sind in den CMS-Ausgaben identisch. Bei Installationen in Unterverzeichnissen oder WordPress ohne sprechende Permalinks die in Kiosky angezeigte Basisadresse verwenden.

Beispiel für WordPress:

```sh
curl --fail-with-body 'https://website.example/wp-json/kiosky/v1/control/displays' \
  --header "Authorization: Bearer $KIOSKY_API_TOKEN"
```

Beispiel für TYPO3:

```sh
curl --fail-with-body 'https://website.example/kiosky-api/v1/displays' \
  --header "X-API-Key: $KIOSKY_API_TOKEN"
```

`KIOSKY_API_TOKEN` ist hier der in **Kiosky** erzeugte Maschinenzugang, kein GitHub-Update-Token. Pro Integration einen eigenen Zugang anlegen. Die Schlüssel werden einmal angezeigt, nur als SHA-256-Hash gespeichert und können sofort widerrufen werden. In der Zugangsübersicht ist die letzte Verwendung sichtbar; die CMS-Ausgaben aktualisieren diesen Zeitstempel höchstens einmal pro Minute, damit häufige Statusabfragen keine unnötigen Schreibzugriffe verursachen. Erstellen, Auflisten und Widerrufen von Zugängen bleibt CMS-/Kiosky-Administratoren vorbehalten. Maschinenzugänge erhalten ausschließlich Zugriff auf die External Control API, nicht auf Benutzerverwaltung, Einstellungen, Medien oder Updates.

Bestehende CMS-Integrationen funktionieren weiterhin: WordPress unterstützt den Einstieg `/wp-json/kiosky/v1/api?path=/api/v1/displays` sowie die bisherige Anmeldung eines berechtigten Administrators mit einem WordPress-Anwendungspasswort. TYPO3 unterstützt `/kiosky-api/?path=/api/v1/displays` und den bisherigen Extension-Konfigurationsschlüssel `externalApiKey`; dieser kann mit `X-API-Key` oder Bearer-Authentifizierung gesendet werden. Neue Integrationen sollen die einzeln widerrufbaren API-Zugänge verwenden.

Fehlende, ungültige oder widerrufene Schlüssel liefern HTTP **401**. Administrationszugriffe mit einem Maschinenzugang werden abgewiesen; im Standalone/Core-Gateway mit **403**, an geschützten CMS-Verwaltungsrouten entsprechend der CMS-Authentifizierung. Nicht unterstützte Ressourcen liefern **404**, ungültige JSON-Anfragen **400**, zu große Anfragen **413**. Schreibzugriffe werden unter der Identität des Maschinenzugangs und mit Request-ID im Audit-Log protokolliert. Die HTTP-Antworten des externen CMS-Gateways enthalten `X-Request-ID` und `Cache-Control: no-store`.

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
