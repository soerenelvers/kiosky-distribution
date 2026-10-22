## [3.9.0] – 2026-10-22

- Zentrale Lizenzverwaltung auf einer Standalone-Masterinstanz mit einmalig angezeigten Lizenzschlüsseln, Modulfreigaben, Installationslimit und Instanzregister für Standalone, WordPress und TYPO3.
- Signierte, an Instanz und Root-Prüfschlüssel gebundene Modulfreigaben; verschlüsselte Speicherung, Abgleich alle 15 Minuten und dauerhafter Erhalt der letzten bestätigten Freigabe bei Serverausfall. Lokale Modulschalter und direkte APIs können fehlende Freigaben nicht umgehen.
- Ausdrücklich bestätigte Lizenz-/Instanzsperren sperren auch laufende Player und ersetzen deren bisherigen Inhaltscache ohne Seitennavigation. Ein reiner Verbindungsfehler widerruft keine Freigaben.
- Lizenzverwaltung in den Einstellungen, native WordPress-Menüprüfung und TYPO3-Scheduler/CLI. Bestehende Installationen bleiben bis zur serverseitigen Einrichtung der Vertrauensbasis im gekennzeichneten Übergangsbetrieb. Einrichtung, Modulabhängigkeiten, Wiederherstellung und Sicherheitsgrenzen dokumentiert.
