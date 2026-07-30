# Kiosky 1.0.0 – technische Änderungen

Stand: 23. Juli 2026

## Architektur

Build 1.0.0 erweitert den bestehenden TypeScript-/SQLite-Monolithen migrationssicher. Die Migrationen `013_kiosky_1_0_0.sql` und `014_matrix_targets_and_trash.sql` erhalten bestehende IDs und Inhalte und ergänzen normalisierte Standorte, Matrix-Displays, Soft Deletes, Warnungen, Warnprovider, feinere Berechtigungsdefinitionen sowie Matrix-/Standort-/Globalziele für externe Zuweisungen.

Die Priorität im Player ist:

1. aktiver Warnhinweis,
2. Betriebs-Preset beziehungsweise externe Zuweisung,
3. Matrix-Zeitplan/-Zuweisung,
4. Einzel- oder Gruppenzeitplan,
5. Standardkanal.

Nach dem Ende eines Warnhinweises wird der zu diesem Zeitpunkt gültige Resolver erneut ausgeführt. Dadurch gewinnt ein inzwischen begonnener regulärer Zeitplaneintrag gegenüber einem veralteten, vor der Warnung laufenden Inhalt.

## Datenmodell

- `locations`: einmalig gepflegte Adresse, Gebäudeteil, Etage, Raum, Koordinaten, Zeitzone und Warngebietskennung.
- `matrix_displays`, `matrix_display_members`: Raster, Position, Ausrichtung, physische Auflösung, Rahmen, Beschnitt, Offset und Skalierung. Ein eindeutiger Index und zusätzliche Servicevalidierung verhindern widersprüchliche aktive Zuordnungen.
- `warnings`, `warning_templates`, `warning_providers`: normalisiertes, providerunabhängiges Warnmodell.
- `permission_grants`: getrennte Rechte für Anzeige, Bearbeitung, Löschen, Warnungen, Presets und Proof of Play.
- Soft-Delete-Felder an Slides, Kanälen, Displays, Gruppen, Standorten und Matrix-Displays.
- Erweiterte Zieltypen `matrix`, `location` und `global` in externen Inhaltszuweisungen; `matrix` zusätzlich im Zeitplan.

## Neue interne API-Endpunkte

Alle Endpunkte verwenden die bestehende Sitzungs- oder Admin-Authentifizierung. Schreibzugriffe erfordern `admin` oder `editor`; endgültiges Löschen erfordert `admin`.

| Methode | Route | Zweck |
| --- | --- | --- |
| GET/POST | `/api/locations` | Standorte auflisten/anlegen |
| PUT/DELETE | `/api/locations/{id}` | Standort ändern/in Papierkorb verschieben |
| GET/POST | `/api/matrix-displays` | Matrix-Displays auflisten/anlegen |
| PUT/DELETE | `/api/matrix-displays/{id}` | Matrix ändern/in Papierkorb verschieben |
| POST | `/api/matrix-displays/{id}/duplicate` | deaktivierte Matrix-Kopie anlegen |
| GET | `/api/trash` | zentralen Papierkorb auflisten |
| POST/DELETE | `/api/trash/{type}/{id}` | wiederherstellen/endgültig löschen |
| GET | `/api/slides/{id}/usage` | Kanalabhängigkeiten einer Slide |
| GET | `/api/channels/{id}/usage` | Zeitplan-/Displayabhängigkeiten eines Kanals |
| GET | `/api/warning-templates` | editierbare Warnvorlagen |
| GET/POST | `/api/warnings` | Warnhinweise auflisten/anlegen |
| POST | `/api/warnings/{id}/publish` | Warnhinweis veröffentlichen |
| POST | `/api/warnings/{id}/end` | Warnhinweis beenden |

## Externe Steuer-API

Zusätzlich zu Displays und Gruppen unterstützt die External Control API Matrix-, Standort- und Globalziele.

| Methode | Route | Zweck |
| --- | --- | --- |
| GET | `/api/v1/matrix-displays` | verfügbare Matrix-Ziele |
| GET | `/api/v1/locations` | verfügbare Standortziele |
| GET | `/api/v1/presets/status` | aktive Zuweisungen und letzte Ausführungen |
| POST | `/api/v1/presets/stop` | Preset-Zuweisungen für Ziele stoppen |
| POST | `/api/v1/warnings/webhook` | externe Warnung authentifiziert einspeisen |

Beispiel:

```json
{
  "title": "Rauchentwicklung im Gebäudeteil B",
  "warningType": "fire",
  "severity": 4,
  "instructions": "Verlassen Sie Gebäudeteil B über die markierten Fluchtwege.",
  "targets": [{"type": "location", "id": "LOCATION_ID"}],
  "priority": 1000,
  "publish": true
}
```

Antworten verwenden `201` für erfolgreiche Anlage, `400` für Validierungsfehler, `401` für fehlende Authentifizierung, `403` für fehlende Rechte, `404` für unbekannte Ziele und `429` bei überschrittenem Login-Limit.

## Warnquellen

- DWD: Der Provider normalisiert CAP-nahe DWD-Daten in das interne Warnmodell. Für den Produktivabruf ist die offizielle DWD-Open-Data-Quelle zu konfigurieren und eine standortbezogene Auswahl über die Warngebietskennung vorzunehmen.
- NINA/MoWaS: Es wird bewusst kein undokumentierter App-Endpunkt und kein Scraper verwendet. Der Adapter bricht mit einer verständlichen Meldung ab, bis ein autorisierter CAP-/Warnmultiplikator-Zugang vorliegt.
- Cell Broadcast: Es wird kein direkter Webempfang behauptet. Identische Meldungen können über den authentifizierten Webhook eines externen Gateways übernommen werden.

## Oberfläche

- Lokale, minutengenau aktualisierte Begrüßung und deutsches Datum.
- Website-Kalender vollständig aus Navigation, Oberfläche und JavaScript entfernt.
- Tabellarische Displayverwaltung mit Suche, Status-/Standort-/Gruppen-/Matrixfiltern und Pagination.
- Eigene Tabs für Gruppen, Matrix-Displays, Standorte und Papierkorb.
- Visueller Matrix-Konfigurator mit Raster, Drag-and-drop, Auflösungsanzeige und Konfliktvalidierung.
- Zeitpläne akzeptieren Matrix-Displays als Ziel.
- Editor mit Shift-Mehrfachauswahl, gemeinsamem Verschieben/Löschen, Eck-Anfassern, X-Schaltfläche, Pfeiltasten, Duplizieren sowie Undo/Redo.
- Sichtbare Bezeichnung „Warnhinweis“ anstelle von „Notfallübernahme“.

## Installation und Test

```sh
pnpm install
pnpm db:migrate
pnpm test
pnpm build
pnpm start
```

Vor einem Produktiv-Update ist eine Kopie der SQLite-Datei anzulegen. Zeiten werden als UTC-ISO-Zeitpunkte gespeichert und im Browser in der lokalen Zeitzone dargestellt.

## Bekannte Einschränkungen

- Eine produktive NINA/MoWaS-Anbindung setzt einen autorisierten Zugang als Warnmultiplikator oder einen vertraglich bereitgestellten CAP-Gateway voraus.
- Die DWD-Automatik besitzt die Provider- und Datenmodellgrundlage; periodisches Polling und betriebliche Schwellenwertkonfiguration müssen installationsspezifisch aktiviert werden.
- Der Matrix-Player liefert Zuordnung und Betriebsmodus aus. Pixelgenaues Cropping erfolgt anhand der gespeicherten Matrix-Metadaten durch den jeweiligen Player; heterogene physische Controller müssen dies unterstützen.
- Das bestehende Rollenmodell bleibt für Anmeldung kompatibel. Die granularen Grants sind migriert; ältere Routen nutzen weiterhin die Rollen `admin`, `editor` und `viewer` als Obergrenze.

## Changelog 1.0.0

- normalisierte Multi-Standort-Verwaltung
- Matrix-Displays und Matrix-Ziele
- einheitlicher Papierkorb für Kerninhalte
- Warnmodell, zehn Vorlagen und externe Warnaufnahme
- erweiterte Preset-Steuerung
- modernisierte Displaytabelle
- Editor-Mehrfachauswahl, Resize und Tastatursteuerung
- dynamische Begrüßung und Datum
- Website-Kalender entfernt
- Version auf 1.0.0 angehoben
