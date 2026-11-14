# Veranstaltungsstatus ab 3.38.0

| Status | Verwaltung | Kalender, Werbung und automatische Event-Auswahl |
|---|---|---|
| Entwurf | Vollständig bearbeitbar | Unsichtbar |
| Veröffentlicht | Vollständig bearbeitbar | Sichtbar, bestehende Feld-/Typ-/Standortfreigaben gelten zusätzlich |
| Abgesagt | Vollständig bearbeitbar | Sichtbar mit Status „Abgesagt“; virtuelle Werbung erhält einen Stempel, öffentliche Kalender verbergen den Ticket-Link. Die Absagekennzeichnung bleibt auch bei ausgeschalteter allgemeiner Statusanzeige sichtbar |
| Archiviert | Bleibt in der Liste; bearbeitbar und als Vorlage/Kopie nutzbar | Unsichtbar |
| Gelöscht | Nur Name, Datum, Kennung/Herkunft und technische Zeitstempel; schreibgeschützt | Unsichtbar |

Neue manuelle Veranstaltungen, Importe, Kopien und Vorlagenverwendungen beginnen
immer als Entwurf. Erst ein anschließendes Speichern als veröffentlicht oder
abgesagt oder die Sammelaktion „Gewählte Entwürfe veröffentlichen“ gibt sie frei. Wiederholte Importe behalten die lokale Veröffentlichung;
ein gelöschter Datensatz wird nicht erneut befüllt. Die kleine Herkunftskennung
verhindert das erneute Anlegen derselben externen Veranstaltung.

Das explizite Veranstaltungsende archiviert beim Erreichen der Endzeit. Ohne
gültige Endzeit bleibt der gesamte Veranstaltungstag in Europe/Berlin erhalten.
Ohne gültiges Datum gibt es keine automatische Archivierung. Eine geplante
Veranstaltung mit bereits vergangener Endzeit bleibt damit nicht veröffentlicht.
Standalone prüft minütlich und beim Listenabruf, CMS bei API-Aufrufen. Öffentliche
Selektoren prüfen den Zeitablauf zusätzlich, auch bei direkter Kalendereinbindung
ohne vorherigen Backendaufruf. Ohne Aufruf/Scheduler gibt es in PHP keine ständig
laufende Hintergrundausführung.

Die Migration übernimmt „Bereit“ als veröffentlicht, „Zu prüfen“ als Entwurf und
„Beendet“ als archiviert. Bisheriger Status „Ausverkauft“ wird veröffentlicht;
der Ticketstatus wird separat erhalten. Bestehende Papierkorb-Veranstaltungen
werden ebenfalls zu reduzierten gelöschten Datensätzen. Vor dem Update deshalb
eine vollständige Datensicherung erstellen, falls alte Papierkorbdaten noch
benötigt werden.

Löschen ist weiterhin administrativ und irreversibel: Veranstaltungstexte,
URLs, Bildbearbeitung, Rohdaten, interne Notizen und zugehörige Ablaufpunkte
werden entfernt. Verknüpfte Slides und Kanäle bleiben erhalten, ihre feste
Veranstaltungszuordnung wird gelöst. Gemeinsam nutzbare Medien und bewusst
gespeicherte Veranstaltungsvorlagen werden nicht pauschal gelöscht. SQLite gibt
gelöschte Seiten zur Wiederverwendung frei; eine Dateiverkleinerung per VACUUM
läuft nicht während einer Benutzeranfrage. Backups behalten ihren eigenen
Aufbewahrungszeitraum; die Reduktion ist keine sichere Löschung aller Backups.

## Sammelfreigabe und Ansichten ab 3.40.5

In der Programmverwaltung mehrere Veranstaltungen über ihre Checkboxen oder
„Alle sichtbaren Veranstaltungen auswählen“ markieren und **Gewählte Entwürfe
veröffentlichen** anklicken. Nach Bestätigung werden manuelle und importierte
Entwürfe gemeinsam freigegeben. Bestehende Veröffentlichungen, Absagen, archivierte,
abgelaufene oder gelöschte Veranstaltungen werden dabei nicht umgestellt. Die
Veranstaltungsdaten und gesperrten Importfelder bleiben erhalten. Es gelten die
normalen Veranstaltungs-Bearbeitungsrechte, keine neuen Benutzerrechte.

Der aufklappbare **Statusfilter** erlaubt mehrere Status gleichzeitig. Standardmäßig
sind Entwurf, Veröffentlicht und Abgesagt ausgewählt; Archiviert und Gelöscht bleiben
ausgeblendet. Für ausschließlich Entwürfe und Veröffentlichte „Abgesagt“ abwählen.
„Aktuelle“, „Alle“ und „Keine“ vereinfachen die Auswahl. Der Browser merkt sich die
gewählten Status. Ohne Auswahl ist die Ergebnisliste leer. Suche, Herkunftsfilter
und Sortierung gelten weiterhin zusätzlich. Auch außerhalb des Filters ausgewählte
Veranstaltungen werden im Auswahlhinweis kenntlich gemacht.
