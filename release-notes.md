## [3.37.0] – 2026-11-09

- Leere Website-Adressen im Player ausdrücklich als leere Dokumente behandeln; versehentliches erneutes Einbetten des eigenen Players und dadurch verschachtelte Wiedergaben verhindern.
- Kanalrechte gegen Unicode-Varianten von Werbe-Tags und geerbte Tags bei partiellen CMS-Updates absichern; geschützte Menükarten aus generischen Mediengestalter-Aktionen herausnehmen und ausdrückliche Küchenrechte erhalten.
- Standalone-Passwortwechsel prüfen die aktuelle Sitzung auch nach dem Lesen der Anfrage und innerhalb der Schreibtransaktion; andere Sitzungen werden beendet, die geprüfte aktuelle Anmeldung bleibt erhalten.
- Standalone-Ersteinrichtung atomar gegen parallele Administrator-Registrierungen absichern, Auth-Anfragen auf 16 KB begrenzen und wechselnde Anmeldenamen zusätzlich über eine gemeinsame Fehlversuchsgrenze schützen.
- Mediengestalter mit eigenen Slides, eigenem Archiv und gezielten Kanalfreigaben ergänzen; Administratoren und Redakteure vergeben diese unter Slides & Kanäle. Eigentum, Versionen, Medienimport und jede Kanalzuweisung an der API prüfen; fremde Inhalte und Kanaleinstellungen erhalten.
- Redakteure für die Inhalts- und Betriebsverwaltung einschließlich Displays freigeben; Einstellungen, Zugänge, Benutzerverwaltung und endgültiges Löschen geschützt halten. Native TYPO3-Fachrollen sowie ausdrückliche Küchenrechte erhalten.
- CrewBrain-/easyjob-Anfragen gegen Weitergabe von API-Schlüsseln, Authorization-Headern und Anmeldeformularen über fremde Weiterleitungsziele schützen. Antwortgrößen, Streams, Weiterleitungsschleifen und Wiederholungswartezeiten begrenzen; bekannte Zugangsdaten vor dem Kürzen von Fehlertexten entfernen.
- HTML- und Attributinjektionen in Player-/Beschilderungsinhalten schließen und ausführbare JavaScript-URLs in Website-Elementen unterbinden; Vorschau und tatsächliche Ausgabe absichern.
- Schreiblast häufiger Sitzungs-/API-Abfragen verringern, ohne Ablauf-, Rollen- oder Widerrufsprüfungen zu überspringen; CMS-Zustände bei unveränderten API-Abfragen nicht erneut speichern.
- Gleichzeitige easyjob-Anmeldungen und Token-Erneuerungen bündeln; native Weiterleitungen teilen sich ein Zeitbudget.
- WordPress-Kopfzeile und DWD-Auswahlfelder auf schmalen Displays korrigieren; Hash-Navigation synchronisieren, mobile Kanalfreigaben und erreichbare Touch-Aktionen ergänzen. Chromium/WebKit prüfen sieben Bildschirmgrößen und alle drei Oberflächentransporte.
