## [3.16.0] – 2026-11-04

- Slide-Archiv direkt in der Bibliothek öffnen, durchsuchen und einzelne Slides wiederherstellen; Ablaufdaten und Verknüpfungen erhalten. Bild-/Icon-Zoom in der Wegweisung auf 10–250 % erweitern.

- Standardinhalt je Display zwischen Kanal und einzelner Slide umschaltbar machen; bisherige Kanalzuweisungen bleiben Standard. Leere beziehungsweise nur gelöschte, abgelaufene oder noch nicht gültige Slides enthaltende Zeitplankanäle verwenden den Standardinhalt. Verfügbare Zeitplaninhalte übernehmen anschließend automatisch wieder.
- Vollbildanforderung neben Neu laden und Identifizieren ergänzen. Alle drei Aktionen sowie Synchronisieren über die authentifizierte externe API bereitstellen; Companion-Modul 1.2.0 um passende Aktionen erweitern.
- Windows-Launcher 2.1.0 um eine auf die konfigurierte Installation begrenzte Browsersteuerung ergänzen. Normale oder durch Browserrichtlinien eingeschränkte Player verlangen gegebenenfalls eine lokale Vollbildbestätigung; blockierte Anforderungen bleiben unbestätigt.
- Migrationen für Standardslides und den zusätzlichen Playerbefehl ergänzen; neue Referenzen beim Import/Export umschreiben und bestehende Befehle beim Upgrade erhalten.

- Bulk-Slide-Import um Tag-Auswahl und direkte Neuanlage erweitern; alle gewählten Dateien erhalten die Tags gemeinsam. WordPress verwendet weiterhin seinen nativen Medienbrowser.
- Die Tags Werbung, Werbung quer und Werbung Hochkant in der gemeinsamen Auswahl bereitstellen. Fehlende Ablauffristen beim Taggen einmalig auf sieben Tage setzen; bestehende Fristen beim Bearbeiten erhalten.
- Getaggte veröffentlichte Slides zusammen mit Veranstaltungswerbung automatisch in Werbung quer und Werbung Hochkant bündeln. Abgelaufene Slides aus der Wiedergabe und beim Abgleich aus den Kanälen entfernen, ohne Inhalte zu löschen.
- Gesamtzahl der Slides je Werbekanal separat vom Veranstaltungslimit einstellbar machen. Die bestehende automatische Kanal-ID bleibt für Querformat erhalten; Hochkant erhält einen eigenen Kanal.
- Regressionen für Fristerhalt, Formatzuordnung, Publikationsstatus, Kanalgrenzen, atomaren Import, native CMS-Tags und gemeinsame mobile Importdialoge ergänzen. Anleitung: [Werbung mit Tags](docs/tag-advertising.md).
