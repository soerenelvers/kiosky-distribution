# Geprüfte Plattformbereiche für 3.11.0

Die Prüfung bezieht sich auf die gemeinsamen Inhalts- und Werbefunktionen. Sie ist kein Nachweis vollständiger Funktionsgleichheit des gesamten Produkts.

| Bereich | Prüfung und Ergebnis |
| --- | --- |
| Kanalauswahl | Gemeinsame Oberfläche: Einzel-/Mehrfachauswahl, Suche und Tagfilter, gefiltertes „Alle auswählen“, Zwischenzustand und Bereichswechsel. |
| Umbenennen und Tags | Aktuelle Daten nach Autosave lesen; Reihenfolge, Dauer, Übergänge, Wiederholung, Ausrichtung, Status, Beschreibungen, Veranstaltung und Standorte erhalten. Eindeutige Namen vor erstem Schreibzugriff; Tags additiv. |
| Inhaltspakete | Vorhandener Exportvertrag einschließlich abhängiger Slides, Medien, Veranstaltungen und Standorte. Browser lädt ein Paket mit zwei Kanälen und deduplizierten Slides herunter. |
| Duplikate | Eigenständige Kanal-/Playlistdaten, gemeinsame Slide-Verweise, Status Entwurf; keine Übernahme von Display-/Zeitplanzuweisungen. PHP-Regression prüft nachträgliche Unabhängigkeit und entfernt übernommene Automatikzuständigkeit. |
| Archivieren/Wiederherstellen | Bestehende API und Papierkorb; Browser prüft Wiederherstellung der Playlist. |
| Endgültiges Löschen | Gemeinsame Aktion, Bestätigung und Administratorgrenze geprüft. Direkter Löschweg und Papierkorb entfernen nur abhängige Zeitplan-/Ausspielzuweisungen und lösen Display-/Matrix-/Preset-Verweise; Archivieren/Restore erhalten diese. |
| WordPress-Werbenavigation | Fehlender nativer Menüeintrag und fehlende Ansichtszuordnung korrigiert. Regression mit echten signierten Freigaben prüft Automatik an/aus, allgemeinen Sichtbarkeitsschalter, Lizenzabgleich und native Capability. |
| TYPO3-Werbenavigation | Behält die gemeinsame Navigation; der Renderer filtert Werbung nicht heraus. Modulvorgaben und gespeicherte Werbesichtbarkeit werden im PHP-Vertrag geprüft. |
| Werbung/Automatik | Bestehende PHP-Verträge prüfen Vorlagen, Einstellungen, generierte Slides/Kanäle, Standorte, Scheduler und administrative Rechte. Node-Werbetests bleiben Bestandteil der Gesamtsuite. Kein externer Kundenserver wurde geprüft. |
| Rollen | CMS erlaubte Betrachtern bisher ausgewählte Schreibzugriffe auf Slides/Kanäle/Zeitpläne. Diese wurden wie in Standalone gesperrt. Separate Speisekartenfreigaben bleiben durch deren vorgelagerten Editorvertrag verfügbar. |
| API-Routen | Kanäle lesen/speichern/duplizieren/archivieren/löschen, Papierkorb lesen/wiederherstellen, Inhaltspaketexport und Werbekonfiguration/-synchronisation über vorhandene Node-/PHP-Verträge geprüft. Keine vollständige Bestandsaufnahme aller Produktrouten. |

## Prüfebenen und Grenzen

`tests/channel-bulk-browser-smoke.py` startet einen isolierten Node-Server mit einer Wegwerfdatenbank und prüft die tatsächliche gemeinsame Oberfläche. `KIOSKY_BROWSER_PLATFORM=wordpress` beziehungsweise `typo3` prüft zusätzlich den jeweiligen Transportadapter gegen diesen Testserver. Diese Durchläufe sind keine nativen CMS-Installationstests. Native Installation und Upgrade, WordPress REST/Benutzer/Medien sowie TYPO3 13/14/Composer werden separat durch die verpflichtende CI geprüft.

WordPress und TYPO3 behalten CMS-Anmeldung, CMS-Benutzerverwaltung und native Medienintegration. Zugangswerk bleibt ausschließlich Standalone. EasyJob-Uhrzeiten wurden nicht neu bearbeitet; es gab keinen neuen Fehlerhinweis und keinen Livezugriff auf Kundensysteme.

## Endgültiges Kanallöschen

Der Benutzer hat die Angleichung des CMS-Löschverhaltens ausdrücklich freigegeben. Sowohl direktes endgültiges Löschen als auch das Leeren des entsprechenden Papierkorbeintrags entfernen die abhängigen Zeitplaneinträge und Ausspielzuweisungen. Display-/Matrix-/Heartbeat- und Preset-Verweise werden gelöst; Bibliotheks-Slides, andere Kanäle und ihre Zuweisungen bleiben erhalten. Die PHP-Speicherung schreibt die geänderten Daten gemeinsam; Standalone verwendet dafür eine Datenbanktransaktion.

Preset-Konfigurationen können neben dem einzelnen Kanalverweis auch Kanalzustände und Zuweisungslisten enthalten. Beide Laufzeiten entfernen dort nur die Einträge des gelöschten Kanals und behalten die übrigen Einstellungen. Die Regressionen prüfen insbesondere gleichlautende Slide-IDs, damit keine anderen Inhaltstypen entfernt werden. Historische Audit- und Preset-Ausführungsdaten bleiben erhalten; Proof-of-Play-Daten behalten den Datensatz mit gelöstem Kanalverweis.

Archivieren und Wiederherstellen ändern keine dieser Verknüpfungen. Das endgültige Löschen aus dem Kanal-Papierkorb verlangt einen tatsächlich archivierten Kanal; Redakteure und Betrachter können beide endgültigen Löschwege nicht verwenden. Die gemeinsame Bestätigung benennt die Auswirkungen der endgültigen Löschung ausdrücklich.
