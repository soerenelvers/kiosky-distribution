## [3.10.0] – 2026-10-22

- Website-Kalender für WordPress und TYPO3 mit fünf bedienbaren Ansichten: Agenda als Vorgabe, Monatskalender, Kategorienfilter, Wochenraster und großer Tag mit verschiebbarer Wochenleiste. Datumsnavigation, Suche, Details, Ticketlinks und Mobilansicht bleiben verfügbar.
- Akzent-, Akzenttext- und drei Steuerelementfarben automatisch aus dem Website-Theme übernehmen oder einzeln einstellen. Zentrale Vorgaben lassen sich je Shortcode beziehungsweise TYPO3-Inhaltselement überschreiben.
- Zeit/Einlass, Ort/Raum, Kategorie, Veranstalter, Beschreibung, Status, Ticketlinks, Restkarten und Details separat ausblenden; Datum und Titel bleiben sichtbar. Bestehende TYPO3-Werte bleiben bei additiver Schemaerweiterung erhalten.
- Fehlende CMS-Routen für manuelle Veranstaltungsablaufpunkte und die DWD-Warngebietssuche ergänzen. Ablaufpunkte validieren, fremde und importierte Einträge schützen; offizielle DWD-Gebietsliste zwischenspeichern und bei Dienstausfall erhalten.
- Easyjob-Vorschau liefert auswählbare Jobs; explizite Projekt-/Jobauswahl beim Import beachten und bestehende Veranstalter-/Veranstaltungstypzuordnungen übernehmen.

- Easyjob-Einstellungen in WordPress und TYPO3 laden wieder ohne „API-Endpunkt wurde nicht gefunden“: fehlende GET-/PUT-Routen für Raumnutzungsarten im gemeinsamen PHP-Kern ergänzen, Vorgaben mit Standalone abgleichen und administrative Änderungen dauerhaft speichern.
- PHP-Aufrufe für Projekt-/Jobdetails auf dieselben dokumentierten pluralen Endpunkte und `id`-Queryparameter wie Standalone korrigieren. Easyjob-Clientheader bei Token-, JSON- und Dokumentabrufen mitsenden.
- Verbindungstest unterscheidet ungeprüfte Prüfungen von Fehlern und zeigt nur tatsächlich geprüfte Projekt-/Jobzahlen. Neue Regressionen prüfen den gemeinsamen Einstellungsloader gegen echte PHP-Antworten, Kataloggleichheit, Speicherung, Rechte und native CMS-Routen.
