# Veranstaltungen duplizieren und Vorlagen verwenden

Ab 3.29.0 stehen dieselben Aktionen in Standalone, WordPress und TYPO3 bereit.

## Veranstaltung duplizieren

Eine bestehende Veranstaltung öffnen und **Duplizieren** wählen. Titel und Datum
für die Kopie eingeben, dann **Entwurf erstellen**. Die Kopie öffnet sich zur
Bearbeitung. Die aktuelle Formulareingabe und die gespeicherten Ablaufpunkte
werden übernommen, ohne das Original zu ändern. Abbrechen im Kopierdialog legt
keinen Datensatz an.

Die neue Veranstaltung hat eine eigene ID, eigene bearbeitbare Ablaufpunkte und
einen eigenen Veranstaltungskanal wie bei der normalen manuellen Anlage. Sie
startet als **Entwurf**. Damit erscheint sie erst nach ausdrücklicher Freigabe
in Werbung und öffentlichem Kalender. Import-IDs und Synchronisationsbindungen,
Display-/Zeitplanzuweisungen, Presets sowie Inhalte vorhandener Kanäle werden
nicht kopiert.

## Veranstaltungsvorlage erstellen

Eine bestehende Veranstaltung öffnen und **Als Veranstaltungsvorlage** wählen.
Einen Namen eingeben und speichern. Übernommen werden die aktuellen Eingaben,
einschließlich Bild, Ticketdaten, öffentlicher/interner Hinweise und der bereits
gespeicherten Ablaufpunkte. Die Vorlage ist eine unabhängige Momentaufnahme und
bleibt nach Änderungen oder Löschung der Ursprungsveranstaltung verfügbar.

## Vorlagen verwalten

Im Bereich **Veranstaltungen → Veranstaltungsvorlagen**:

- **Verwenden:** Titel und Datum wählen; daraus entsteht eine neue manuelle
  Veranstaltung als Entwurf. Danach alle Angaben prüfen und anpassen.
- **Bearbeiten:** Vorlagenname, Veranstaltungsangaben und Ablaufpunkte ändern.
  Ablaufänderungen werden hier erst mit **Vorlage speichern** dauerhaft übernommen.
- **Löschen:** Entfernt die Vorlage nach Bestätigung. Bereits erzeugte
  Veranstaltungen behalten ihre eigenen Daten.

Datum und Veröffentlichungsstatus werden bei der Verwendung gewählt. Der
Vorlageneditor verwendet intern das Referenzdatum der Momentaufnahme, um
mehrtägige Abläufe beizubehalten. Die zentralen Feldoptionen unter
**Einstellungen → Veranstaltungen** gelten auch für diese Formulare.

## Uhrzeiten und mehrtägige Abläufe

Beim Übertragen auf ein anderes Datum bleiben die Uhrzeiten in **Europe/Berlin**
und die Kalender-Tagesabstände erhalten. Beispielsweise bleibt ein Ende nach
Mitternacht am Folgetag; beim Wechsel zwischen Sommer- und Winterzeit ändert
sich der UTC-Wert entsprechend. Die Browser-Zeitzone verändert diese Angaben
nicht. Unveränderte Zeitfelder behalten auch Sekunden und ihre vorhandene
Zeitzonenauflösung. Nicht existierende lokale Uhrzeiten bei der Zeitumstellung
werden mit einer Fehlermeldung abgewiesen; es entsteht keine unvollständige Kopie.
Bei mehrdeutigen neuen Uhrzeiten wird das frühere Vorkommen gewählt.

## Daten, Rechte und API

Vorlagen werden serverseitig gespeichert und im Systemexport/-import der
Veranstaltungen mitgeführt. Import erzeugt unabhängige Vorlagen mit neuen IDs.
Es gelten dieselben Schreibrechte wie bei Veranstaltungen: Administratoren und
Redakteure können Vorlagen und Kopien bearbeiten; Betrachter sind lesend.
Öffentliche Kalender und Player erhalten keine Vorlagenverwaltung.

Die internen, authentifizierten Oberflächen-Endpunkte sind:

- `POST /api/events/:id/duplicate` mit optionalem `title`, `date` und einer
  vollständigen `input`-Momentaufnahme aus dem Editor.
- `GET/POST /api/event-templates`; Anlegen benötigt `name`, `eventId` und optional
  die vollständige aktuelle `input`-Momentaufnahme.
- `GET/PUT/DELETE /api/event-templates/:id`; Aktualisieren benötigt `name`,
  `input` und `schedule`.
- `POST /api/event-templates/:id/create-event` mit optionalem `title` und `date`.

`date` akzeptiert `YYYY-MM-DD` oder `YYYYMMDD`. Titel und Daten werden serverseitig
validiert und auf bearbeitbare Felder begrenzt. Eine Kopie wird einschließlich
Veranstaltungskanal und Ablaufpunkten atomar angelegt. Die Sammlung ist auf
200 Vorlagen, 500 Ablaufpunkte je Vorlage und insgesamt 5 MB Vorlagendaten
begrenzt. Die Liste lädt nur beim Öffnen der Verwaltung; Player benötigen keine
zusätzliche Abfrage. Keine Datenbankmigration oder Laufzeitabhängigkeit ist nötig.

Diese internen Endpunkte erweitern nicht die externe Companion-Control-API.
