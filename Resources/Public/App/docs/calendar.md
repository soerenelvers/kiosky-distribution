# Website-Kalender in Standalone, WordPress und TYPO3

## Zentrale Einstellungen

In Kiosky unter Einstellungen → Website-Kalender werden Titel, Suche,
Zusatzliste kommender Veranstaltungen sowie Darstellung und Farben festgelegt.
Das Veranstaltungsmodul und seine Lizenzfreigabe müssen aktiv sein. Öffentliche
Kalender enthalten weiterhin ausschließlich freigegebene Veranstaltungen und
keine internen Notizen.

Es stehen fünf Ansichten zur Auswahl:

| Einstellung | Darstellung und Bedienung |
| --- | --- |
| Agenda (`agenda`, Vorgabe) | Monatsweise Veranstaltungsliste, Datumssprung und Suche über alle Termine. |
| Monatskalender (`month`) | Monatsraster, Tagesauswahl, Monats-/Jahreswechsel und Tagesliste. |
| Kategorien (`categories`) | Kategorie-Schaltflächen mit kombinierbarer Textsuche über alle Termine. |
| Woche (`week`) | Verschiebbares Zeitraster, Ganztagstermine und nebeneinander platzierte Überschneidungen. |
| Tag (`day`) | Großes Tagesdatum und Tagesliste mit wochenweise verschiebbarer Siebentageleiste. |

„Heute“ und der Datumssprung funktionieren in allen Ansichten. Zeiten werden
wie bisher in Europe/Berlin angezeigt, auch bei einer anderen Browserzeitzone.
Auf schmalen Geräten werden Listenspalten gestapelt; das Wochenraster lässt sich
innerhalb des Kalenders verschieben. Suchfelder, Navigation und Details bleiben
mit Tastatur bedienbar.

## Farben und Theme

„Automatisch aus dem Theme“ übernimmt Schrift und Textfarbe der Einbettung sowie
Akzentfarben in dieser Reihenfolge: `--kiosky-theme-accent`,
`--wp--preset--color--primary`, `--bs-primary`, `--primary-color`, sichtbare
Website-Linkfarbe, schließlich Blau. Die Steuerelemente orientieren sich an
sichtbaren Website-Buttons. Themes ohne passende Variablen können
`--kiosky-theme-accent` am umgebenden Element setzen. Änderungen der Theme-Klasse
oder Farben an `html`/`body` werden übernommen. Da Themes unterschiedlich aufgebaut
sind, sollte die konkrete Website nach dem Update visuell geprüft werden.

„Eigene Farben“ erlaubt fünf einzelne Hex-Farben: Akzent, Akzenttext,
Steuerelement-Hintergrund, Steuerelement-Text und Steuerelement-Rahmen. Ein
manuell gefärbter Kalender bleibt von der automatischen Farbwahl anderer
Einbettungen unabhängig. Auf ausreichenden Textkontrast achten.

## Spalten und Optionen

Einzeln ein-/ausblendbar sind Untertitel, Bild, Uhrzeit, Ende, Einlass, Pause,
Wiederbeginn, Aufbau, Abendkasse, Veranstaltungsnummer, Ort/Raum, Kategorie,
Veranstalter, Beschreibung, öffentliche Hinweise, Status, Ticketlinks,
Restkartenhinweis und Detail-Schaltflächen. Datum und Titel bleiben sichtbar.
Dieselben Vorgaben gelten für Zusatzliste und Details. Interne Notizen werden
nie ausgegeben. Archivierte und gelöschte Veranstaltungen werden ausgeschlossen.
Abgesagte Veranstaltungen erhalten keinen Ticketlink.

Ab 3.23.0 werden ausgeblendete Felder auch aus den eingebetteten JSON-Daten und
der öffentlichen Kalender-API entfernt. Berechtigte Redakteure können einzelne
Felder je Einbettung ausdrücklich freigeben. Vertrauliche Informationen gehören
weiterhin ausschließlich in interne Felder. Bisherige Einstellungen für Uhrzeit
und Beschreibung gelten auch für die zugehörigen neuen Unterfelder.

„Ansicht wechseln“ erlaubt Besuchern, zwischen allen fünf Layouts umzuschalten.
Die Suche findet Termine auch außerhalb des gerade sichtbaren Tages oder der
Woche. Lange Ergebnislisten laden jeweils bis zu 100 weitere Termine über
„Weitere Veranstaltungen laden“ nach; Suche und Filter berücksichtigen alle
freigegebenen Termine. Bilder werden erst bei Bedarf geladen.

## WordPress: je Kalender überschreiben

Ohne Attribute übernimmt `[kiosky_event_calendar]` die zentralen Vorgaben.
Beispiel einer eigenen Agenda ohne Ort und Veranstalter:

```text
[kiosky_event_calendar view="agenda" show_location="false" show_organizer="false"]
```

Weitere Beispiele:

```text
[kiosky_event_calendar view="week" color_mode="theme"]
[kiosky_event_calendar view="day" color_mode="custom" accent_color="#1968d2" accent_text_color="#ffffff" control_background="#ffffff" control_text_color="#183153" control_border_color="#ccd8e6"]
```

Optionen: `show_time`, `show_location`, `show_category`, `show_organizer`,
`show_description`, `show_status`, `show_tickets`, `show_remaining_tickets`,
`show_details`, `show_subtitle`, `show_image`, `show_end_time`,
`show_admission`, `show_break`, `show_setup_time`, `show_box_office`,
`show_event_number`, `show_public_notes`, `allow_view_switch`, außerdem die bisherigen `title`, `upcoming_title`,
`upcoming_count`, `show_search` und `show_upcoming`. Boolesche Angaben sind
`true`/`false`; weggelassene Angaben erben die zentrale Einstellung. Eine
Einbettung verändert weder die zentrale Konfiguration noch andere Kalender.

## TYPO3: Inhaltselement und Update

Im Inhaltselement „Kiosky Veranstaltungskalender“ stehen die Reiter Darstellung
und Spalten und Optionen bereit. „Zentrale Vorgabe“ übernimmt die Einstellung
von Kiosky. Jede Ansicht, jeder Farbwert und jede Sichtbarkeit können je
Inhaltselement überschrieben werden. Leere Farben und Anzahl 0 bedeuten erben.

Nach dem vollständigen Extension-Update TYPO3s Datenbankschema-Aktualisierung
(z. B. `vendor/bin/typo3 extension:setup`) und Cache-Leerung ausführen. Die
Erweiterung fügt Felder zu `tt_content` hinzu. Bestehende Such-/Zusatzlistenwerte
0/1 und bisherige Anzahlen werden erhalten; neue Elemente verwenden die
Vererbungswerte -1 beziehungsweise 0. Ein Rollback entfernt diese zusätzlichen
Daten nicht automatisch; vorherige Sicherung und übliche CMS-Updateprozesse
verwenden. Inhalte, Rollen und Veranstaltungsdaten werden nicht gelöscht.

## Prüfung

PHP-Tests prüfen Defaults, Validierung, sichere HTML-/JSON-Ausgabe, zentrale und
lokale Vorgaben, Teilupdates und Zeitzonen. Native CMS-Tests prüfen echte
Shortcode-/Inhaltselementausgabe und getrennte Überschreibungen. Chromium-Tests
bedienen alle fünf Ansichten unter WordPress-/TYPO3-Themebedingungen, prüfen
Suche, Kategorieauswahl, Wochenwechsel, Überschneidungen, Dialoge, Ticketlinks,
Spaltenausblendung, Farbwechsel, zwei unabhängige Kalender und mobile Breiten.

## Detailansicht und neue Gestaltung (3.27.0)

„Details“ öffnet die freigegebenen Veranstaltungsangaben in einem eigenen Dialog:
Bild, Titel, Untertitel, Datum, Zeiten, Ort, Kategorie, Veranstalter, Beschreibung,
öffentliche Hinweise, Ticketstatus und Ticket-Link entsprechend den aktivierten
Sichtbarkeiten. Bei fehlender Beschreibung werden die übrigen Daten und ein
entsprechender Hinweis angezeigt. Suche und Filter bleiben beim Schließen
erhalten. Escape, Schließen und Tastaturfokus sind unterstützt. Der Dialog wird
auch bei üblichen überschreibenden Theme-Styles sichtbar; nachträglich eingefügte
Kalender werden neu initialisiert. Veranstaltungskarten, Datumsmarken und der
Dialog verwenden weiterhin Website-Schriften und zentrale beziehungsweise pro
Einbindung gewählte Farben. Smartphone und Tablet erhalten passende Anordnungen.

## Standalone einbetten

Unter **Einstellungen → Website-Kalender** lässt sich „Öffentlichen Website-
Kalender freigeben“ einschalten. Die Voreinstellung ist ausgeschaltet. Nach der
Freigabe ist `/calendar` erreichbar; das Textfeld liefert einen iframe-Code für
die eigene Website. `?view=month`, `?view=week`, `?view=day` oder `?view=categories`
kann die Ansicht je Einbindung ändern. Die zentrale Feldauswahl wird dadurch
nicht erweitert. Kalender-Freigabe, Modul und Lizenz müssen aktiv sein.

Nur freigegebene Status (Bereit, Veröffentlicht, abgesagte/beendete beziehungsweise
ausverkaufte öffentliche Termine) werden ausgegeben. Entwürfe, archivierte Daten,
interne Notizen und abgeschaltete Felder gelangen nicht in die öffentliche HTML-/
JSON-Ausgabe. Die Freigabe kann wieder abgeschaltet werden. Änderungen benötigen
keine Datenbankmigration und werden in den vorhandenen Integrationseinstellungen
gespeichert. Für WordPress und TYPO3 bleiben Shortcode und Inhaltselement bestehen.

Prüfungen: `tests/calendar-details-regression.py` deckt Theme-Styles, dynamische
Kopien, wenig Eventdaten, Tastatur und responsive Dialoge ab.
`tests/calendar-standalone-browser.py` prüft öffentliche Freigabe, geschützte
Einstellungen, iframe-Ausgabe, Feldschutz und Abschalten über echtes HTTP.
`tests/public-calendar-standalone.test.ts` vergleicht Feldprojektion und
Einstellungen mit dem nativen PHP-Kalender.

## Veranstaltungs-/Jobtypen

Beim Anlegen und Bearbeiten einer Veranstaltung zeigt **Veranstaltungs-/Jobtyp**
vorhandene Typen manueller und importierter Veranstaltungen im Dropdown.
**Neuen Typ hinzufügen** öffnet ein Eingabefeld; mit **Typ übernehmen** oder
Enter auswählen. Speichern übernimmt auch einen noch nicht bestätigten Typ.
Erst die gespeicherte Veranstaltung macht den neuen Typ dauerhaft verfügbar.
Abbrechen verändert den bisherigen Typ nicht; **Kein Typ** entfernt die Zuordnung.
Dies gilt in Standalone, WordPress und TYPO3.

## Felder im Veranstaltungseditor

Unter **Einstellungen → Veranstaltungen** wählen Administratoren per Checkbox,
welche Eingabefelder beim Anlegen und Bearbeiten angeboten werden, einschließlich
Veranstaltungs-/Jobtyp, Ticketdaten, Bild und Ablauf. Der Titel bleibt verpflichtend.
Standardmäßig sind alle Felder aktiv. **Alle Felder anzeigen** setzt die Auswahl
zurück; mit **Veranstaltungseinstellungen speichern** dauerhaft übernehmen.

Ausgeblendete Felder sind im Formular deaktiviert; bestehende Werte werden beim
Bearbeiten erhalten. Die Auswahl ist eine Editor-Konfiguration, keine Löschung
oder Zugriffsregel für Import, API, bestehende Slides oder öffentliche Kalender.
Öffentliche Freigaben werden unter **Website-Kalender** separat gesteuert.
Redakteure bekommen dieselbe Formularkonfiguration über die Eventliste; nur
Administratoren dürfen sie ändern. Die Einstellung wird serverseitig gespeichert
und gilt über Geräte und Browser hinweg in allen drei Varianten.

Vorlagen und unabhängige Kopien manueller Veranstaltungen sind in
[event-templates.md](event-templates.md) beschrieben. Neue Kopien starten als
Entwurf und werden erst nach Freigabe in Kalender und Werbung sichtbar.
