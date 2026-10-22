# Easyjob in WordPress und TYPO3: Einstellungs- und Endpunktkorrektur

Ab Kiosky 3.10.0 enthält der gemeinsame PHP-Kern die vom Einstellungsformular
benötigten Routen `GET` und `PUT /api/integrations/easyjob/schedule/types`.
Zuvor konnte bereits das Öffnen oder Nachladen der Easyjob-Konfiguration den
lokalen Kiosky-Fehler „API-Endpunkt wurde nicht gefunden“ auslösen, obwohl
Serveradresse, Benutzername und Passwort korrekt waren. Die drei parallelen
Aufrufe für Konfiguration, Feldzuordnung und Raumnutzungsarten müssen alle
erfolgreich sein, damit das Formular seine gespeicherten Werte anzeigt.

Die Raumnutzungsarten verwenden denselben Vorgabenkatalog wie Standalone.
Administrative Änderungen werden im CMS-State unter
`easyjob.roomUseTypeRules` gespeichert; Kategorie, Sichtbarkeit, Näherungsangabe
und Priorität bleiben nach erneutem Laden erhalten. Bestehende Zugangsdaten und
Importe bleiben erhalten. Diese Korrektur ergänzt keine neuen Raumnutzungs-
Importverfahren; sie repariert den vom vorhandenen Formular benötigten Vertrag.

Auch die ausgehenden PHP-Detailaufrufe entsprechen jetzt Standalone:

| Aufruf | Easyjob-WebApi-Pfad |
| --- | --- |
| Projektdetails | `api.json/Projects/Details/?id=…` |
| Jobdetails | `api.json/Jobs/Details/?id=…` |

Der Header `ej-webapi-client: ThirdParty` wird bei Authentifizierung, JSON-
und Dokumentabrufen mitgesendet. Die bestehende explizite HTTP-/TLS-Ausnahme
und das Zeitlimit bleiben erhalten. Die falsche Schreibweise `Job/Details/<id>`
wird nicht mehr verwendet. Endpunkte sind aus dem bestehenden Standalone-Client
und dessen dokumentierten Vertrag übernommen.

## Update und Prüfung

1. Das WordPress-Plugin beziehungsweise die TYPO3-Extension auf 3.10.0 oder neuer
   aktualisieren. Bei manueller Installation das vollständige Paket verwenden.
2. Die Kiosky-Oberfläche neu laden. Unter Schnittstellen → Easyjob müssen die
   gespeicherte Serveradresse und die Raumnutzungsarten erscheinen.
3. „Verbindung testen“ ausführen. Gespeicherte Zugangsdaten müssen nicht wegen
   dieser Korrektur geändert werden.
4. Bei Bedarf eine Importvorschau und einen ausgewählten Import prüfen.

Der derzeitige PHP-Verbindungstest prüft die Projektliste einschließlich
Tokenabruf. Jobliste, Details und Raumkalender, die in diesem Testlauf nicht
abgerufen werden, werden ausdrücklich als „nicht prüfbar“ dargestellt und
nicht als erfolgreich oder fehlgeschlagen behauptet. Detailaufrufe werden
separat beim Import ausgeführt. Die Standalone-Diagnose behält ihre bisherigen
zusätzlichen Prüfungen.

Ein weiterhin auftretender HTTP-Fehler beim Easyjob-Aufruf ist von der lokalen
Kiosky-Routenfehlermeldung zu unterscheiden. Eine erfolgreiche Prüfung mit
Testantworten ersetzt keinen Live-Test des kundenspezifischen Servers. Für eine
weitere Diagnose genügen installierte Kiosky-Version, betätigte Aktion und
Fehlermeldung; Passwörter und Tokens gehören nicht in Fehlerberichte.

## Weitere CMS-Korrekturen im selben Release

Die Importvorschau liefert neben Projekten auch die enthaltenen Jobs in dem vom
bestehenden Formular erwarteten Format. Details werden über den oben genannten
Job-Endpunkt aufgelöst. Ausschlüsse nach Projekt-/Jobtitel und Typ bleiben wirksam.
Ein expliziter `importKind` (Projekt, Job oder beides) und ausgewählte `jobIds`
werden beim Import berücksichtigt; eine leere Treffermenge darf nicht zu einem
Import sämtlicher Jobs werden. Veranstalter und Veranstaltungstyp können wie in
der bestehenden Oberfläche zugeordnet werden.

Der gemeinsame PHP-Kern ergänzt außerdem:

- `GET /api/dwd/warncells?q=…`: Suche ab zwei Zeichen in der offiziellen
  DWD-Warngebietsliste, mit Namens-/ID-/Abkürzungssuche, Umlautnormalisierung,
  maximal 30 Treffern, 24-Stunden-Cache und Weiterverwendung des letzten Caches
  bei Ausfall. Ohne vorhandenen Cache wird ein Abruffehler gemeldet.
- `GET/POST /api/events/:eventId/schedule` und
  `PATCH/DELETE /api/events/:eventId/schedule/:entryId`: manuelle Ablaufpunkte
  anzeigen, erstellen, bearbeiten und löschen. Datumswerte, Zeitreihenfolge und
  Kategorien werden geprüft. Importierte Einträge bleiben schreibgeschützt;
  Einträge eines anderen Events können nicht über eine fremde URL geändert
  werden. Beim endgültigen Löschen einer Veranstaltung werden ihre Ablaufpunkte
  mit entfernt. Vorhandene CMS-Berechtigungen und Modulfreigaben gelten weiter.

Die Routenprüfung berücksichtigt aktive Aufrufe der gemeinsamen Oberfläche.
Standalone-Anmeldung, Zugangswerk und lokale Benutzerverwaltung sind im CMS
absichtlich keine alternativen Anmeldewege. Native Update-Routen werden weiterhin
von den CMS-Adaptern ausgeführt. Diese Korrektur behauptet keine vollständige
Gleichheit sämtlicher interner Standalone-Diagnose- und Raumkalenderfunktionen.

## Nachweise und Grenzen

Regressionstests prüfen den echten Frontend-Einstellungsloader mit Antworten des
PHP-Kerns, den vollständigen Vorgabenkatalog, gespeicherte Regeln, Rechte,
Detailpfade, Clientheader, Jobauswahl und Import. Weitere PHP-Tests laufen für
beide Plattformkennungen und prüfen DWD-Suche/Cache/Ausfall sowie Ablauf-CRUD,
Teilupdates, fehlerhafte Zeitangaben, Import-Schreibschutz und fremde Event-IDs.
Die nativen WordPress-/TYPO3-Tests verwenden die tatsächlichen CMS-Factories und
State-Stores für Einstellungsrouten, persistierte Regeln, gecachte DWD-Suche und
Ablauf-CRUD. Providerantworten stammen aus kontrollierten Fixtures; es werden
keine Kundenpasswörter benötigt und keine Live-Importe auf Kundenservern ausgelöst.
