# Kiosky Admin-API

Veranstaltungsabläufe, easyjob-Raumnutzungen und der sichere öffentliche Display-Selector sind in [event-schedule.md](event-schedule.md) dokumentiert.

Die Weboberfläche verwendet eine serverseitige Sitzung in einem `HttpOnly`-, `SameSite=Strict`-Cookie. Für optionale Maschinenzugriffe kann weiterhin `KIOSKY_ADMIN_API_KEY` als `Authorization: Bearer …` oder `X-API-Key` verwendet werden.

## Systemzustand

```http
GET /api/health
```

```json
{
  "status": "ok",
  "version": "2.2.5",
  "buildNumber": "2.2.5-20260727103000+abcdef0",
  "buildTimestamp": "2026-07-23T10:30:00.000Z",
  "buildCommit": "abcdef0",
  "database": "connected"
}
```

## Anmeldung und Ersteinrichtung

```http
GET  /api/auth/setup-status
POST /api/auth/setup
POST /api/auth/login
POST /api/auth/logout
GET  /api/auth/me
POST /api/auth/password
GET  /api/auth/invitations/{token}
POST /api/auth/invitations/{token}
```

`POST /api/auth/setup` steht ausschließlich zur Verfügung, solange noch kein Benutzer existiert. Die Anmeldung akzeptiert Benutzername oder E-Mail-Adresse. Passwörter benötigen mindestens 12 Zeichen, einen Buchstaben und eine Zahl. Nach fünf fehlgeschlagenen Anmeldeversuchen wird die Kombination aus IP-Adresse und Anmeldekennung für 15 Minuten gesperrt.

## Benutzerverwaltung

Nur Administratoren dürfen diese Endpunkte verwenden:

```http
GET  /api/users
POST /api/users
GET  /api/users/invitations
POST /api/users/invitations
PUT  /api/users/invitations/{id}
DELETE /api/users/invitations/{id}
PUT  /api/users/{id}
DELETE /api/users/{id}
POST /api/users/{id}/reset-password
```

Rollen sind `admin`, `editor` und `viewer`. Konten benötigen einen eindeutigen Benutzernamen; die E-Mail-Adresse ist optional und kann später ergänzt werden. Einladungen sind sieben Tage gültig und werden bei konfigurierter Resend-HTTPS-API direkt verschickt; ein SMTP-Server ist nicht erforderlich. Eine deaktivierte Einladung kann nicht angenommen werden. Der letzte aktive Administrator kann nicht deaktiviert, herabgestuft oder gelöscht werden; das eigene Konto kann ebenfalls nicht deaktiviert oder gelöscht werden. Ein Passwort-Reset beendet alle bestehenden Sitzungen des betroffenen Benutzers.

## Slide-Versionen

```http
GET  /api/slides/{id}/versions
POST /api/slides/{id}/versions/{version}/restore
```

Eine Wiederherstellung überschreibt keine Historie, sondern legt den gewählten Stand als neue aktuelle Version ab.

## Aktive Veranstaltungsquelle

```http
GET /api/integrations/sources
PUT /api/integrations/sources
Authorization: Bearer <KIOSKY_ADMIN_API_KEY>
Content-Type: application/json

{ "activeSource": "crewbrain" }
```

`activeSource` ist `crewbrain`, `easyjob` oder `null`. Kiosky lässt höchstens eine aktive externe Veranstaltungsquelle zu. Mit `null` bleiben ausschließlich manuelle Eingaben aktiv. Eine Schnittstelle kann erst aktiviert werden, nachdem ihre Zugangsdaten gespeichert wurden.

## CrewBrain-Konfiguration lesen

```http
GET /api/integrations/crewbrain
Authorization: Bearer <KIOSKY_ADMIN_API_KEY>
```

Secrets werden nie zurückgegeben. `hasCredential` zeigt nur an, ob ein Zugang hinterlegt ist.

## CrewBrain-Konfiguration speichern

```http
PUT /api/integrations/crewbrain
Authorization: Bearer <KIOSKY_ADMIN_API_KEY>
Content-Type: application/json

{
  "baseUrl": "https://shwz.crewbrain.com/api/v2",
  "documentationUrl": "https://shwz.crewbrain.com/api/v2",
  "authType": "api_key",
  "credential": { "apiKey": "<CREWBRAIN_API_KEY>" },
  "syncEnabled": true,
  "syncIntervalMinutes": 15,
  "lookbackDays": 30,
  "lookaheadDays": 365,
  "pageSize": 100,
  "importStrategy": "project",
  "autoCreateChannels": true,
  "autoCreateSlides": true
}
```

Zugangsdaten werden mit AES-256-GCM und `KIOSKY_ENCRYPTION_KEY` verschlüsselt. Bei späteren Änderungen kann `credential` weggelassen werden; das gespeicherte Secret bleibt dann erhalten.

Die Weboberfläche bietet diese Konfiguration unter **Schnittstellen → CrewBrain konfigurieren** an. Der Zugriff erfolgt über die angemeldete Administrator-Sitzung; ein technischer API-Key muss dafür nicht im Browser eingegeben werden.

Alternativ kann ein Administrator Benutzername und Passwort einmalig gegen einen CrewBrain-Access-Token tauschen:

```http
POST /api/integrations/crewbrain/access-token
Content-Type: application/json

{
  "baseUrl": "https://shwz.crewbrain.com/api/v2",
  "username": "<CREWBRAIN-BENUTZER>",
  "password": "<CREWBRAIN-PASSWORT>",
  "syncEnabled": true,
  "syncIntervalMinutes": 15,
  "lookbackDays": 30,
  "lookaheadDays": 365,
  "pageSize": 100,
  "importStrategy": "project",
  "autoCreateChannels": true,
  "autoCreateSlides": true
}
```

Kiosky ruft damit serverseitig `/api/accesstoken` beim CrewBrain-Mandanten auf. Benutzername und Passwort werden nicht gespeichert; ausschließlich der erhaltene Token wird verschlüsselt abgelegt.

## CrewBrain-Feldzuordnung

```http
GET /api/integrations/crewbrain/mapping
PUT /api/integrations/crewbrain/mapping
Authorization: Bearer <KIOSKY_ADMIN_API_KEY>
```

Die Zuordnung steuert Titel, Beschreibung, Datum, Zeiten, Spielort und Ticketinformationen in der Importvorschau. Zulässig sind ausschließlich serverseitig freigegebene CrewBrain-Felder.

## Verbindung testen

```http
POST /api/integrations/crewbrain/test
Authorization: Bearer <KIOSKY_ADMIN_API_KEY>
```

Der Test liest nacheinander `/v2/me`, `/v2/me/rights` und maximal einen Event-Datensatz. Fehler werden als `INVALID_URL`, `DNS`, `NETWORK`, `TIMEOUT`, `UNAUTHORIZED`, `FORBIDDEN`, `NOT_FOUND`, `RATE_LIMIT`, `SERVER` oder `INVALID_RESPONSE` klassifiziert.

## Importvorschau

```http
POST /api/integrations/crewbrain/import-preview
Authorization: Bearer <KIOSKY_ADMIN_API_KEY>
Content-Type: application/json

{
  "from": "20260701T000000Z",
  "until": "20270721T235959Z",
  "status": "GREEN",
  "search": "Stadthalle",
  "limit": 50,
  "offset": 0
}
```

Die Antwort enthält CrewBrain-ID, dokumentierten Objekt- und Untertyp, Nummer, Änderungszeitpunkt und das normalisierte Kiosky-Zielobjekt. `excludedCount` nennt die Anzahl der Datensätze, deren interner oder exportierter Titel eine Zeichenfolge der gemeinsamen Titel-Ausschlussliste enthält. Die Vorschau verändert noch keine Daten.

Die gemeinsame, schreibungsabhängige Titel-Ausschlussliste für alle Veranstaltungsquellen wird unabhängig von der jeweiligen Verbindung verwaltet:

```http
GET /api/integrations/title-exclusions
PUT /api/integrations/title-exclusions
```

```json
{
  "titleExclusions": ["(St)", "(B)"]
}
```

## Veranstaltungen selektiv importieren

```http
POST /api/integrations/crewbrain/import
Authorization: Bearer <KIOSKY_ADMIN_API_KEY>
Content-Type: application/json

{ "ids": ["1234", "5678"] }
```

Nur die gewählten CrewBrain-IDs werden serverseitig erneut geladen und in der Tabelle `events` angelegt oder aktualisiert. Die Antwort unterscheidet `created`, `updated`, `unchanged`, `excluded` und `errors`. Derselbe Datensatz wird anhand von Quelle, externer ID und Objekttyp nicht doppelt angelegt.

## easyjob WebApi

```http
GET  /api/integrations/easyjob
PUT  /api/integrations/easyjob
GET  /api/integrations/easyjob/mapping
PUT  /api/integrations/easyjob/mapping
POST /api/integrations/easyjob/test
POST /api/integrations/easyjob/import-preview
POST /api/integrations/easyjob/import
Authorization: Bearer <KIOSKY_ADMIN_API_KEY>
```

Als `baseUrl` wird der Serverstamm der WebApi gespeichert, beispielsweise `https://easyjob.example:8008/`. Eingefügte `/token`- und `/api.json/...`-Pfade werden automatisch entfernt. Für eine easyjob-Installation, die im geschützten internen Netzwerk ausschließlich HTTP anbietet, muss `allowInsecureHttp: true` ausdrücklich gesetzt werden. Bei einem selbstsignierten HTTPS-Zertifikat kann `allowSelfSignedCertificate: true` die Zertifikatsprüfung gezielt nur für easyjob deaktivieren. Netzwerkdiagnosen unterscheiden unter anderem DNS-, Port-, Firewall-, Timeout- und TLS-Zertifikatsfehler.

Die Feldzuordnung steuert für Projekte und Jobs Titel, Untertitel, Beschreibung, Datum, Einlass, Beginn, Pause, Ende, Spielort, Raum, öffentliche Hinweise, Ticket-Link und Ticketstatus. Mit `__project.<Feld>` kann ein Job gezielt einen Wert seines übergeordneten Projekts übernehmen; `__none` deaktiviert die Übernahme eines optionalen Feldes. Detailantworten werden sowohl direkt als auch in den easyjob-üblichen Hüllen `Data`, `Project`, `Job` oder `Item` verarbeitet.

## Veranstaltungen verwalten

```http
GET /api/events
POST /api/events
GET /api/events/{id}
PUT /api/events/{id}
Authorization: Bearer <KIOSKY_ADMIN_API_KEY>
```

`POST` legt eine eigene Veranstaltung an. `PUT` speichert redaktionelle Änderungen und trägt die bearbeiteten Felder in `locked_fields_json` ein. Ein späterer CrewBrain-Reimport aktualisiert weiterhin technische Quelldaten, überschreibt diese geschützten Inhalte aber nicht.

## Slides und Kanäle verwalten

```http
GET    /api/slides
POST   /api/slides
PUT    /api/slides/{id}
DELETE /api/slides/{id}

GET    /api/channels
POST   /api/channels
PUT    /api/channels/{id}
DELETE /api/channels/{id}
POST   /api/channels/{id}/duplicate
```

Der Slide-Editor speichert sein Layout als strukturiertes Dokument in der Datenbank. Bei jeder Bearbeitung entsteht automatisch eine neue Version. Kanäle speichern ihre Abspielreihenfolge einschließlich Dauer und Übergang je Slide sowie Standardübergang, Ausrichtung und Wiederholung. `DELETE` archiviert den Inhalt, statt ihn endgültig zu entfernen.

## Vorlagen, Displays und Ausspielung

```http
GET|POST       /api/templates
PUT|DELETE     /api/templates/{id}
GET|POST       /api/presets
PUT|DELETE     /api/presets/{id}
GET|POST       /api/displays
PUT|DELETE     /api/displays/{id}
GET|POST       /api/display-groups
PUT|DELETE     /api/display-groups/{id}
GET|POST       /api/schedules
DELETE         /api/schedules/{id}
POST           /api/schedules/publish
GET            /api/operations
POST|DELETE    /api/operations/emergency
POST           /api/operations/displays/{id}/commands
```

Vorlagen können gesperrte dynamische Veranstaltungsfelder enthalten. Unterstützt werden unter anderem Titel, Datum, Einlass, Beginn, Pause, Ende, Spielort, Raum, Restkarten und Abendkasse. Displays erhalten einen zufälligen Geräteschlüssel in ihrer festen Player-URL. Zeitplan, Warnhinweise, Heartbeats und Player-Befehle liegen zentral in der Datenbank. Proof of Play ist deaktiviert; alte Player-Aufrufe werden ohne Datenbankschreibzugriff verworfen.

Der Player verwendet mit seinem Geräteschlüssel folgende Endpunkte:

```http
GET  /api/player/{slug}/state
POST /api/player/{slug}/heartbeat
X-Player-Key: <Geräteschlüssel>
```

Der Standalone-Server schreibt kompakte JSON-Zeilen für Start, Stopp, Fehler,
langsame Anfragen und unbehandelte Prozessfehler in das von Plesk erfasste
stdout/stderr-Protokoll.

## Audit-Log

```http
GET /api/audit-logs
Authorization: Bearer <KIOSKY_ADMIN_API_KEY>
```

Konfigurationsänderungen, Verbindungstests und Importvorschauen werden protokolliert. Zugangsdaten und vollständige CrewBrain-Payloads werden nicht in das Audit-Log geschrieben.
