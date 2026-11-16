# Veranstaltungsabläufe (ab 3.46.0)

Unter **Zeitplan → Veranstaltungsabläufe** lassen sich Ablaufvorlagen anlegen und
Veranstaltungen sowie Displays, Gruppen oder Matrizen zuordnen. Die Oberfläche
ist in Standalone, WordPress und TYPO3 gleich und passt sich an Smartphones und
Tablets an.

## Einrichten

1. **Ablaufvorlage** wählen und einen Namen vergeben, zum Beispiel „Konzert“.
2. Schritte hinzufügen: Name, Slide oder Kanal, Zeitbezug und Versatz in Sekunden.
   Negative Werte schalten früher, positive später. Zur Verfügung stehen Aufbau,
   Einlass, Beginn, Pausenbeginn, Pausenende und Veranstaltungsende.
3. Entweder eine Ausspieldauer in Minuten wählen oder einen End-Zeitbezug mit
   eigenem Sekundenversatz. Das Endfeld hat Vorrang vor der Dauer. Zeitfenster
   müssen positiv und höchstens 24 Stunden lang sein; Versätze maximal ±24 Stunden.
4. Schritte über die Pfeile umsortieren, entfernen und die Vorlage speichern.
   Die Ausspielzeit ergibt sich aus den Zeitbezügen, nicht aus der Listenposition.
5. **Veranstaltung zuordnen** wählen, Vorlage und Ziel-Displays/Gruppen/Matrizen
   auswählen und aktivieren. Die Suche grenzt die Zielauswahl ein.
6. Vor dem Einsatz für das betreffende Display simulieren.

Beispiel: Einlass-Slide ab `Einlass −600 s` bis `Beginn`; Veranstaltungskanal ab
`Beginn` bis `Pause beginnt`; Pausen-Slide ab `Pause beginnt` bis `Pause endet`.
Ein expliziter Abschiedsschritt kann ab `Veranstaltungsende` für zehn Minuten laufen.

Eine Vorlage wird gemeinsam verwendet: Ihre Änderungen betreffen alle zugeordneten
Veranstaltungen. **Kopieren** erzeugt eine unabhängige Vorlage. Eine verwendete
Vorlage kann erst nach Entfernen ihrer Zuordnungen gelöscht werden. Deaktivieren
oder Entfernen einer Zuordnung beendet ihre Wirkung beim nächsten Player-Abruf.
Das Ausführungsprotokoll bleibt dabei erhalten.

## Ausspielung und Sicherheit

Die Zeiten stammen aus den Veranstaltungsdaten und der Serverzeit. Vollständige
Zeitstempel mit Zeitzonenoffset haben Vorrang; lokale Datum-/Uhrzeitwerte werden in
Europe/Berlin interpretiert. Ändert sich Einlass oder Beginn, ändern sich die
berechneten Zeitfenster automatisch. Bei fehlenden Zeiten wird der betroffene
Schritt übersprungen und unter **Zeitfenster und fehlende Angaben** erklärt.

Nur veröffentlichte Veranstaltungen laufen. Entwürfe, entfernte und manuell
archivierte Veranstaltungen bleiben gesperrt. Abgesagte Veranstaltungen können
pro Zuordnung ausdrücklich zugelassen werden. Automatisch archivierte, zuvor
veröffentlichte Veranstaltungen dürfen noch ihre ausdrücklich konfigurierten
Endschritte beenden. Automatisch archivierte Entwürfe erlangen dadurch keine
Veröffentlichung. Slides/Kanäle behalten ihre eigenen Freigaben, Gültigkeiten und
Standortbeschränkungen; leere/ungültige Inhalte folgen der bestehenden Rückfalllogik.

Nicht fest an eine Veranstaltung gebundene Slides erhalten im Ablauf den Kontext
der zugeordneten Veranstaltung. Explizite Slide-/Kanalverknüpfungen und die
Auswahlmodi „aktuell“, „heute“ und „nächstes Event“ bleiben erhalten.

Abläufe sind zusätzliche **virtuelle Zeitfenster** und ändern oder löschen keine
manuellen Zeitplaneinträge. Aktive manuelle Zeitplanung hat Vorrang vor einem
Ablauf; Warnungen und direkte Zuweisungen behalten ihre bestehenden Prioritäten.
Die vorhandene Preset-Ablösung durch eine nachfolgende Zeitzuweisung berücksichtigt
auch neu beginnende Ablaufschritte. Nach Fensterende greift wieder die reguläre
Ausspielung. Überlappende Abläufe nutzen die bestehende Reihenfolge: zuletzt
zugeordneter/geänderter Ablauf zuerst, danach Zielrang Display/Matrix/Gruppe.
Bei gleichem Rang und Zeitstempel bleibt die gespeicherte Reihenfolge maßgeblich.

Verwaltung: Administratoren und Redakteure mit globalen Standortrechten;
TYPO3 prüft zusätzlich die nativen Inhalts-, Veranstaltungs- und Displaysteuerungsrechte.
Betrachter, Mediengestalter und Maschinen-/Player-Schlüssel erhalten keine
Verwaltungsrechte. Erforderliche Lizenzmodule: Veranstaltungen, Zeitplanung,
Presets/Betrieb, Inhalte/Kanäle und Displays. Entzogene Module sperren Oberfläche,
Verwaltung und automatische Ausspielung. Es entsteht kein neuer Lizenzmodul-Typ.

## Simulation und Protokoll

Ein Display, Zeitpunkt und bis zu 24 Stunden auswählen. Die Simulation nutzt
dieselbe Inhaltsauflösung wie der Player, einschließlich Warnungen, Presets,
manueller Zeitplanung, Abläufen, Gültigkeit und Standardinhalt. Wechsel werden an
den tatsächlichen Grenzen der Zeitfenster berechnet. Die Vorschau zeigt auch
aktive Ablaufschritte, die überlagert werden. Die vorhandene **Zeitplansimulation**
mit Inhaltsvorschau berücksichtigt dieselben Abläufe.

Simulationen speichern keine Aktionen, Ausführungsprotokolle oder Zeitplaneinträge.
Zukünftige manuelle Änderungen, noch unveröffentlichte Veranstaltungen und neue
externe Warnmeldungen können nicht vorhergesagt werden. Bei zu vielen Wechseln
ist ein kürzerer Simulationszeitraum erforderlich.

Das Protokoll erfasst bei authentifizierten Player-Abfragen **Änderungen der
Serverauswahl**: Zeitpunkt, Regel/Schritt, Veranstaltung, Display, Zeitbezug/Versatz,
gewünschter Inhalt, ausgewählter Inhalt und Grund. Zustände sind „Ausgewählt“,
„Überlagert“ und „Beendet“. Gleichbleibende Abfragen erzeugen keine neuen Einträge.
Es ist kein Nachweis, dass ein ausgeschalteter oder gestörter Fernseher den Inhalt
physisch angezeigt hat. Ohne Player-Abrufe entstehen keine Ausführungsbelege.
Die letzten 500 Einträge werden gespeichert; die aktuelle Auswahl lässt sich mit
**Aktualisieren** nachladen.

## Daten, API und Betrieb

- Standalone speichert Konfiguration/Protokoll unter `integration_settings.event_automation`;
  die CMS-Ausgaben unter `eventAutomation` im vorhandenen State Store.
- Additive Migration `051_event_automation.sql` ergänzt nur das Merkmal für
  automatische Veranstaltungsarchivierung. Bestehende Zeitplaneinträge bleiben erhalten.
- Höchstens 100 Vorlagen, 200 Zuordnungen, 20 Schritte pro Vorlage, 50 Ziele pro
  Zuordnung und insgesamt 10.000 berechnete Ziel-Zeitfenster.
- Die Ausspielung benötigt keinen neuen Cronjob. Sie wird bei Player-Abfragen aus
  aktuellen Zeitfenstern berechnet; es gibt keine mehrfach ausführbaren Nebenaktionen.
- Schreibzugriffe verwenden die zuletzt geladene globale `revision`. Veraltete
  Änderungen erhalten HTTP 409 und müssen nach Neuladen erneut vorgenommen werden.
  WordPress führt unabhängige Player-Protokolle zusammen und erzwingt bei veralteten
  Entscheidungen eine Neuberechnung.
- `GET /api/event-automation` liefert Vorlagen, Zuordnungen, Diagnose, berechnete
  Zeitfenster, Protokoll und einen kompakten Auswahlkatalog.
- `POST /api/event-automation/templates` / `bindings` legt an;
  `PUT` / `DELETE /api/event-automation/{templates|bindings}/:id` ändert/entfernt.
  Dies sind geschützte Verwaltungsrouten mit Benutzersitzung, keine neuen Companion-
  oder External-Control-Endpunkte.
- `POST /api/displays/:id/simulate` nutzt den vorhandenen Simulationsvertrag.
- Inhaltsübertragung exportiert die Abläufe mit dem Abschnitt „Betrieb/Presets“;
  importierte Zuordnungen werden wie Preset-Termine bewusst **deaktiviert** und
  erhalten neu zugeordnete Inhalts-, Veranstaltungs- und Zielkennungen. Benötigte
  Inhalte, Veranstaltungen und Displays mit exportieren.
- Ein vollständiger verschlüsselter Systemumzug erhält Kennungen, Zuordnungen,
  Aktivierung und Protokoll. Quell- und Zielinstanz dafür mindestens auf 3.46.0 bringen.
- Vor dem Update konsistentes Backup erstellen. Bei einem Rollback die Abläufe
  deaktivieren; ältere Versionen ignorieren die neue Konfiguration. Für unveränderte
  Archivierungsmetadaten das passende Datenbankbackup wiederherstellen.

## Verifikation

`pnpm test` umfasst Node-/PHP-Funktionsparität, echte Standalone-HTTP-Ausspielung,
beide CMS-APIs, idempotente Protokolle und verschlüsselte Umzüge in allen neun
Plattformkombinationen. `python3 tests/event-automation-browser.py` prüft den
Editor, Persistenz, Schrittfolge, Zuordnung, Ausspielung, Simulation und Protokoll
in allen drei UI-Transporten bei 320/390/820/1440 Pixeln. Mit
`KIOSKY_BROWSER_ENGINE=webkit` und passender Playwright-WebKit-Installation wird
zusätzlich WebKit geprüft. Die CMS-Transporttests ergänzen die nativen PHP-Tests;
sie ersetzen keine Abnahme auf realen Smartphones oder Tablets.
