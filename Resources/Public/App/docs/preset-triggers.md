# Presets zeitgesteuert auslösen (ab 3.31.0)

Unter **Zeitplanung → ＋ Preset-Termin** ein gespeichertes Preset auswählen,
Datum, Uhrzeit und Zeitzone einstellen und den Termin speichern. Alternativ
öffnet die Uhr-Schaltfläche am Preset dieselbe Eingabe mit vorausgewähltem Preset.
Die Serverzeit steuert die Ausführung; ein geöffneter Editor ist nicht erforderlich.

Ein Termin löst das **vollständige Preset mit seinen gespeicherten Zielen und
Aktionen** aus. Das umfasst Kanalzustände, direkte Zuweisungen, vorbereitete
Zeitzuweisungen und Warn-Presets. Beim Auslösen gilt die dann gespeicherte
Preset-Definition. Eine spätere Änderung des Presets gilt deshalb auch für
künftige Termine. Ein archiviertes oder fehlendes Preset wird nicht ausgeführt;
der Fehler erscheint im Protokoll.

## Abläufe und Wiederholung

Beispiel: 18:30 Preset „Einlass“, 19:00 Preset „Vorstellung“, 20:00 Preset „Pause“.
Dafür drei Termine mit den jeweiligen Presets anlegen. Ein Preset kann selbst
mehrere [vorbereitete Zeitzuweisungen](preset-schedules.md) enthalten. Diese
werden beim Termin zu regulären Zeitplaneinträgen. Eine reine Zeitzuweisung
legt weiterhin keine unbegrenzte direkte Content-Zuweisung an.

Einmalige, tägliche, wöchentliche und Intervall-Termine sind möglich. Wöchentlich
lassen sich mehrere Wochentage und ein Wochenintervall wählen; ohne Auswahl
gilt der Wochentag des Starts. „Alle X Tage“ verwendet ein Tagesintervall.
Eine optionale Endgrenze beendet Wiederholungen **vor** dem angegebenen Zeitpunkt.
Die Wiederholung folgt der gewählten IANA-Zeitzone, z. B. Europe/Berlin.
Nicht vorhandene Uhrzeiten beim Sommerzeitwechsel werden ausgelassen; die
zweimal vorkommende Winterzeit-Uhrzeit wird nur einmal, beim ersten Vorkommen,
ausgelöst. Die Oberfläche weist eine bereits beim Anlegen fehlende Uhrzeit ab.

Bestehende Prioritäten bleiben erhalten: Warnungen überlagern Presets. Ein später
manuell zugewiesener Inhalt oder eine spätere Zeitzuweisung kann ein Preset wie
bisher ablösen. Ein Preset-Termin ist eine Auslösung und kein neues dauerhaftes
Prioritätsniveau. Ein unbefristetes Warn-Preset bleibt bis zu seiner Rücknahme
aktiv; für wiederkehrende Warn-Presets eine passende Dauer im Preset einstellen.

## Verwalten und prüfen

Der Bereich **Preset-Termine** zeigt den nächsten Termin, die Zeitzone und das
letzte Ergebnis. Bearbeiten, Pausieren, Aktivieren und Entfernen sind dort möglich.
Pausieren oder Entfernen verhindert künftige Auslösungen; bereits erzeugte
Zuweisungen, Warnungen und Zeitplaneinträge bleiben erhalten. Ein abgeschlossener
einmaliger Termin wird zum erneuten Aktivieren auf einen zukünftigen Zeitpunkt
gesetzt. Änderungen werden mit **Termin speichern** gespeichert; Abbrechen
verwirft den Dialoginhalt.

Jede Ausführung erhält einen dauerhaften Nachweis pro Termin und Zeitpunkt.
Mehrere Player, parallele Serverzugriffe oder Neustarts lösen denselben Termin
nicht erneut aus. Auch ein Fehler wird nur einmal protokolliert, ohne teilweise
angewendete Preset-Aktionen. Nach einer Fehlerkorrektur einen neuen zukünftigen
Termin setzen. Die nächste reguläre Wiederholung bleibt unabhängig davon möglich.

Bis zu fünf Minuten verspätete Termine werden nachgeholt. Ältere Termine werden
als **Übersprungen** protokolliert und erzeugen keine alten Warnungen oder
Content-Wechsel. Bei mehreren verpassten Wiederholungen wird nur der jüngste noch rechtzeitige Termin berücksichtigt; ältere Wiederholungen werden nicht als Schleife nachgespielt.
Nach Systemausfällen oder Wartung lässt sich das Ergebnis im Protokoll prüfen.

Es gelten höchstens 500 gespeicherte Termine. Die letzten 100 Ausführungen werden
angezeigt; maximal 2.000 kompakte Nachweise werden aufbewahrt. Die Protokolle
enthalten keine zweite Kopie der erzeugten Slides oder Zeitpläne.

Systemtransfers enthalten die Termine zusammen mit den Presets. Im Zielsystem
werden sie mit neu zugeordneten Presets **pausiert** importiert und erst nach
Prüfung aktiviert. Ausführungsnachweise werden nicht übertragen.

## Hintergrundbetrieb

- **Standalone:** Der laufende Server prüft jede Sekunde auf fällige Termine.
- **WordPress:** Das Update registriert die eigene minütliche WP-Cron-Aufgabe
  `kiosky_preset_triggers`. Sie arbeitet unabhängig von Veranstaltungsimporten
  und liest keine komplette Mediathek ein. Für Betrieb ohne Seitenbesuche einen
  System-Cronjob für WP-Cron einrichten; beispielsweise einmal pro Minute
  `wp cron event run kiosky_preset_triggers --path=/pfad/zu/wordpress` ausführen.
- **TYPO3:** Die Scheduler-Aufgabe **Kiosky: Preset-Termine auslösen** jede Minute
  ausführen lassen. Alternativ per System-Cron einmal pro Minute
  `vendor/bin/typo3 kiosky:presets:run-due` im TYPO3-Projektverzeichnis aufrufen.
  Das benötigt keine Veranstaltungs-Datenquelle und führt keine Importe aus.
- In beiden CMS-Varianten prüfen zusätzlich die authentifizierten Statusabrufe
  laufender Player auf fällige Termine. Üblicherweise geschieht das alle fünf
  Sekunden. Ohne Player und ohne laufenden CMS-/System-Cron findet keine
  Hintergrundausführung statt. Ein CMS-Cron im Minutenraster kann entsprechend
  bis zu einer Minute später auslösen; es ist keine harte Echtzeitsteuerung.

Zeitplanung und Presets müssen freigeschaltet sein. Administratoren und
Redakteure verwalten die Termine; Betrachter erhalten keinen Zugriff. Der
interne API-Vertrag lautet `GET/POST /api/preset-triggers` und
`PUT/DELETE /api/preset-triggers/{id}`. POST/PUT verwenden `name`, `presetId`,
`startsAt` als ISO-Zeitpunkt mit Offset, `timezone`, `recurrence`, `interval`,
`weekdays`, optional `until` und `enabled`. Die GET-Antwort enthält `data`,
`runs` und `serverTime`. Die vorhandene Companion-Auslösung von Presets bleibt
unverändert; die Termine laufen nach ihrer Einrichtung selbständig.

Ab 3.32.0 können [Folge-Preset-Timer direkt in einem Preset](preset-followups.md) gespeichert und erst bei dessen Aufruf angelegt werden.
