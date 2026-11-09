# Vorgesehene Schriftverwaltung und Zugriffsschranken

- Administratoren registrieren maximal 20 eigene WOFF2/WOFF/TTF/OTF-Dateien, jeweils bis 2 MB.
- Alle Varianten bieten privaten Upload in den Einstellungen; WordPress/TYPO3 können zusätzlich vorhandene Schriften im nativen Medienbrowser auswählen. Die tatsächlichen Schriftbytes werden geprüft.
- Namen und Dateiformate werden validiert, CSS-Familien ausschließlich aus SHA-256-IDs gebildet. Keine frei eingebbaren CSS-/JavaScript-Regeln oder Remote-Import-URLs.
- Schriftbytes liegen getrennt von Zugangsdaten und außerhalb des Extension-/Plugin-Updateordners; keine binären Schriftdateien im gemeinsamen CMS-JSON-Zustand.

## Konkrete API-Verträge

| Aufruf | Erforderlicher Zugriff | Antwort |
|---|---|---|
| GET /api/fonts | gültige Kiosky-/CMS-Benutzersitzung | Namen und technische Font-Metadaten |
| GET /api/fonts/{SHA256} | gültige Kiosky-/CMS-Benutzersitzung | genau eine registrierte Schriftdatei |
| GET /api/player/{slug}/state?fontId={SHA256} | gültiger X-Player-Key für genau dieses Display; Schrift muss im aktuell zugewiesenen Inhalt vorkommen | genau diese Schriftdatei |
| GET/POST/DELETE /api/settings/fonts... | Administrator; native TYPO3-Rechte gemäß freigegebener Matrix | Verwaltung eigener Schriften |

Anonyme Aufrufe, ungültige/widerrufene Player-Schlüssel und Schriftanforderungen
außerhalb der Displayzuweisung werden abgewiesen. Die Font-Lese-Endpunkte bleiben
hinter der Benutzeranmeldung; es wird keine öffentliche Schrift-API hinzugefügt.
Für externe Integrations-Tokens bleibt die bestehende Begrenzung auf /api/v1 bestehen.

Dateiantworten enthalten nur Font-Metadaten und validierte Font-Bytes.
Zugangsdaten, Cookies, API-Keys, Original-Dateipfade und andere Mediadateien werden
nicht darüber ausgeliefert. Gekoppelte Player dürfen geladene Schriftbytes für
Offline-Wiedergabe innerhalb ihres bestehenden Inhaltscaches speichern.

## Verifikation vor Veröffentlichung

- Anonyme Requests an Katalog und Font-Dateien müssen scheitern.
- Redakteure/Betrachter dürfen keine Schriftverwaltung ausführen.
- Player A darf mit Schlüssel B nichts lesen; unbekannte/nicht zugewiesene Fonts werden abgewiesen.
- Gewöhnliche Mediendateien und beschädigte/zu große Dateien werden abgewiesen.
- Vorschau, Templates, laufende Player und Offline-Wiederanlauf mit geladenen Fonts prüfen.
- Vollständige erforderliche CI und echte CMS-Installations-/Upgradeprüfungen.

Status: Vom Betreiber freigegeben und mit expliziten nativen Lese-/Verwaltungsrechten umgesetzt. Siehe [Bedienung und Speicherpfade](../fonts.md).
