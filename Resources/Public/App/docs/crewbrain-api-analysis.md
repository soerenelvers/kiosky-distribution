# CrewBrain API v2 – Analyse

Stand: 21. Juli 2026. Es wurden keine personenbezogenen Demo-Daten abgerufen oder dokumentiert.

## Spezifikation und Version

- Dokumentation: `https://demo.eu3.crewbrain.com/api/v2/`
- von Swagger UI geladene Spezifikation: `https://demo.eu3.crewbrain.com/339/api.json`
- Format: OpenAPI 3.0.0
- API-Titel: CrewBrain REST-API Version 2
- erkannte Mandantenversion: 2.339
- Basisstruktur: `https://{subdomain}.crewbrain.com/api/v2/{resource}`

Die Spezifikations-URL enthält die Mandantenversion und kann sich ändern. Das Generierungsscript ermittelt sie deshalb aus der Swagger-Seite, statt `/339/api.json` fest einzubauen.

## Authentifizierung

Dokumentiert sind:

- HTTP Basic Authentication (laut CrewBrain nicht empfohlen)
- Bearer Token
- API-Key im Header `X-API-KEY`
- OAuth2 Authorization Code und Password Flow; OAuth2 wird von CrewBrain bevorzugt

Zugriffsrechte entsprechen dem verwendeten CrewBrain-Benutzer. Kiosky führt alle Aufrufe serverseitig aus und gibt Secrets nicht an den Browser zurück.

## Für Kiosky relevante Endpunkte

| Methode | Pfad | Zweck |
| --- | --- | --- |
| GET | `/v2/me` | Identität und grundsätzliche Verbindung prüfen |
| GET | `/v2/me/rights` | Rechte prüfen |
| GET | `/v2/events` | Events, Projekte und Jobs auflisten |
| GET | `/v2/events/{eventId}` | einzelnes Event lesen |
| GET | `/v2/events/{eventId}/additionalDatas` | Werte der Zusatzfelder lesen |
| GET | `/v2/locations` | Veranstaltungsorte lesen |
| GET | `/v2/locations/{locationId}/rooms` | Räume eines Orts lesen |
| GET | `/v2/additionalDataTypes` | verfügbare Zusatzfelder und Typen lesen |

Die Demo-API wird von Kiosky ausschließlich lesend verwendet.

## Pagination, Filter und Sortierung

Sammlungen liefern `data`, `itemCount`, `totalItems`, `limit` und `offset`. Pagination erfolgt mit `limit` und `offset`. Viele Felder können direkt und mit Operatoren wie `[gte]`, `[lte]`, `[in]`, `[like]` gefiltert werden. Zusätzlich sind `search`, `sort`, `fields` und bei Events `expand` dokumentiert. Event-Unterobjekte können über `includeSubevents` eingeschlossen werden.

## Rate-Limits und Fehler

Dokumentiert sind maximal 100 Requests pro Minute und 5.000 Requests pro Stunde. Überschreitungen liefern HTTP 429. Die Spezifikation weist bei den relevanten Leseendpunkten insbesondere 200, 403 und teilweise 404 aus. Der Client behandelt zusätzlich 401, 408, 429 und 5xx kontrolliert, berücksichtigt `Retry-After`, nutzt exponentiellen Backoff und begrenzt Wiederholungen.

## Event-Modell

CrewBrain verwendet `/v2/events` sowohl für Projekte als auch Jobs:

- `Type`: `PROJECT` oder `JOB`
- `Subtype`: `PROJECT`, `MAINJOB`, `SUBJOB`, `SOLOJOB`
- Hierarchie: `ParentID`, `ParentIDs`, `ParentTitles`
- Identifikation: `ID`, `EventID`, `EventIDManual`, `EventIDFormatted`, `ExternalID`
- Inhalt: `Title`, `TitelExport`, `Description`, `ExternalDescription`, `PublicDescription`
- Zeit: `DateFrom`, `DateUntil`, `Admission`, `Start`, `BreakTimestamp`, `Timezone`
- Status: farbcodiertes `Status`, `Completed`, `Cancelled`, `Deleted`, `Public`, `TicketStatus`, `ExternalStatus`
- Ort: `LocationID` und expandierbares `Location`
- Kunde: `ClientID` und expandierbares `Client`
- Änderungen: `CreatedDate`, `ChangedDate`
- Tickets: `TicketURL`, `TicketStatus`, `Presale`
- Zusatzfelder: expandierbares `AdditionalDatas`

`Status` ist keine fachliche Freigabe-Bezeichnung, sondern eine dokumentierte Farbaufzählung. Eine fachliche Übersetzung muss deshalb konfigurierbar bleiben.

## Orte und Räume

Orte stehen unter `/v2/locations`. Das Modell enthält unter anderem `Description`, Adresse, Stadt, Land und Änderungszeitpunkt. Räume werden ortsbezogen über `/v2/locations/{locationId}/rooms` gelesen und enthalten `Description`, `LocationID`, Kapazität und Änderungszeitpunkt. Im Event-Modell ist kein direktes `RoomID` dokumentiert; eine Raumzuordnung darf daher nur über tatsächlich vorhandene Zusatzfelder oder ein mandantenspezifisches Mapping erfolgen.

## Zusatzfelder

`/v2/additionalDataTypes` liefert Feld-ID, Namen, Titel, Zuordnung (`JOB`, `PROJECT` usw.), Datentyp, Standardwert, Datenschutzkennzeichen und Änderungszeitpunkt. Unterstützte Typen umfassen unter anderem Text, Richtext, Datum, Zeit, Datum/Zeit, Checkbox, Dropdown, Hyperlink, Raum, Ort und Dokumente. Kiosky darf kundenspezifische Felder erst nach explizitem Mapping verwenden.

## Nicht sicher verfügbare Angaben

Die folgenden gewünschten Informationen sind nicht als universelle Standardfelder bestätigt und dürfen nicht erfunden werden: konkrete Pausenenden, Abendkassenöffnung, Restkartentext, Displayfreigabe, Displaygruppe, Slide-Vorlage, Werbebild, Veranstaltungslogo und ein direktes Raumfeld am Event. Sie werden über konfigurierbare Zusatzfeld-Mappings erschlossen, sofern der Produktivmandant sie bereitstellt.

## Bekannte Einschränkungen

- API v2 wird laut CrewBrain weiterentwickelt; Schemaänderungen sind möglich.
- Die Demo-Spezifikation beschreibt Funktionen, die abhängig von Benutzerrechten nicht zugänglich sein können.
- Ohne Zugangsdaten wurde nur die öffentliche OpenAPI-Spezifikation geprüft, nicht der Inhalt geschützter Endpunkte.
- ETags sind an den relevanten Endpunkten nicht dokumentiert. Synchronisation verwendet deshalb `ChangedDate` und einen normalisierten Inhalts-Hash.
