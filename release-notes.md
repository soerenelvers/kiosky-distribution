## [3.47.0]

- Sicherheitsblocker im optionalen Update-Dienst behoben: unnötigen Next/Vite/Vinext-Starter durch statische Statusseite und eigenständigen Worker ersetzt; anfällige picomatch-Abhängigkeit entfernt, keine Audit-Ausnahmen. Lizenz-API, signierte Downloads, Hosting-Bindungen und SQL-Migrationen bleiben erhalten.
- DWD-Gemeindekennungen wie 806532023 (Stadt Wetzlar) verwenden den offiziellen gemeindegenauen WFS; Kreiskennungen bleiben kompatibel. Vollständigkeit, Gültigkeit und Gebiet werden vor einer Übernahme geprüft.
- DWD-Warnungen je Standort einmalig oder alle X Minuten mit Dauer in Minuten:Sekunden einblenden; alter Dauermodus bleibt erhalten. Persistente Anzeigezähler je Display, lokale Zeitgrenzen und Offline-Ausblenden verhindern ungewollte Neustarts oder endlose Einblendungen.
- Gemeinsame Filter-/Bestätigungslogik in Standalone, WordPress und TYPO3; bestätigte Meldungen bleiben bestätigt, beendete werden nicht reaktiviert. Erfolgreiche vollständige Snapshots beenden zurückgenommene/abgelaufene Providerwarnungen.
- DWD-Vorschau und Freigabe sind klar getrennt; Warnstufen, angekündigte Meldungen, Filter und Abruffehler sind sichtbar und mobil bedienbar.
- Parallele Konfigurationsänderungen brechen veraltete Importe ab. Native CMS-Snapshots erhalten den ursprünglichen Schreibvergleich; Anzeigezähler bleiben mit parallelen Inhaltsänderungen und vollständigen Systemumzügen kompatibel.
