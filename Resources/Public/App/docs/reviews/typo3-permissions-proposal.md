# Konkreter Korrekturvorschlag für TYPO3-Rechte

Prüfstand: Kiosky 3.34.0, Commit 625e1d2. Isolierte In-Memory-Fixtures;
keine Produktivdaten und keine echten Notfallmeldungen.

## Bestätigte Überschreitungen

| TYPO3-Rolle | Deklariertes Recht | Aktueller API-Zugriff | Soll |
|---|---|---|---|
| manager | keine Systemeinstellungen | PUT /api/settings/features: 200 | 403 |
| event_editor | keine Notfallsteuerung | POST /api/operations/emergency: 201 | 403 |
| display_operator | keine Inhaltsbearbeitung | POST /api/slides: 201 | 403 |

Ursache: `ApiController` bildet manager auf die gemeinsame Rolle admin und
weitere Fachrollen auf editor ab. `PermissionService` definiert engere Rechte;
diese werden bislang nur für einzelne Endpunktgruppen geprüft.

## Vorgesehene Rechte nach Korrektur

Die vorhandenen, bereits dokumentierten TYPO3-Rollen gelten an jeder API-Grenze:

| Rolle | Inhalte | Veranstaltungen | Displays verwalten | Zeitplanung/Displaysteuerung | Warnungen/Presets auslösen | Systemeinstellungen/Zugänge |
|---|---|---|---|---|---|---|
| TYPO3-Administrator / Kiosky administrator/admin | ja | ja | ja | ja | ja | ja |
| manager | ja | ja | ja | ja | ja | nein |
| editor | ja | nein | nein | nein | nein | nein |
| event_editor | nein | ja | nein | nein | nein | nein |
| display_operator | nein | nein | nein | ja | nein | nein |
| viewer | nein | nein | nein | nein | nein | nein |

Leserechte bleiben innerhalb der vorhandenen API- und Modulgrenzen bestehen.
Gesonderte ausdrücklich erteilte Küchen-/Speisekartenrechte werden weiterhin
im dafür zuständigen Modul geprüft. Warnungsfähige Presets und deren Timer
benötigen das Warnungsrecht, damit indirekte Notfallauslösungen die Prüfung
nicht umgehen. Nicht zugeordnete neue Schreib-Endpunkte bleiben Administratoren
vorbehalten, bis ihnen ein konkretes Fachrecht zugeordnet wird.

Die Prüfung erfolgt vor dem Laden sensibler Zustände oder dem Schreiben.
Bestehende TYPO3-Benutzer, Rollenfelder, Zugangsdaten und produktive
CMS-Konfigurationen werden nicht automatisch geändert. Native Administratoren
behalten vollständigen Zugriff. Externe API-Tokens und Player-Schlüssel erhalten
keine zusätzlichen Rechte.

## Abnahme

- Negative HTTP-/Controller-Prüfungen für jede Rolle und Endpunktgruppe.
- Erlaubte Verwaltungs-, Veranstaltungs-, Inhalts- und Displayaktionen bleiben nutzbar.
- Direkte und indirekte Notfallauslösungen sowie Exporte und Zugangsverwaltung prüfen.
- Echte TYPO3-13/14-Installation und Upgrade, bestehende Speisekarten-/Player-Tests.
- Vollständige erforderliche CI; Veröffentlichung erst nach Freigabe und bestandenen Checks.

Status: Vom Betreiber freigegeben und vor dem gemeinsamen API-Einstieg umgesetzt. Regressionstests prüfen negative Rechtefälle; native CMS-Prüfungen sind Teil des Release-Gates.
