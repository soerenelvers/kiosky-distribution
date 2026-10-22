# Website-Kalender in WordPress und TYPO3 ab 3.10.0

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

Einzeln ein-/ausblendbar sind Uhrzeit einschließlich Einlass, Ort/Raum, Kategorie,
Veranstalter, Beschreibung/öffentliche Hinweise, Veranstaltungsstatus,
Ticketlinks, Restkartenhinweis und Detail-Schaltflächen. Datum und Titel bleiben
sichtbar. Dieselben Vorgaben gelten für die Zusatzliste und die Detailansicht.
Ausgeblendete Beschreibung oder Einlasszeit können keinen entsprechenden
Detail-Button erzeugen. Abgesagte Veranstaltungen erhalten keinen Ticketlink.

Diese Optionen steuern die Darstellung; sie sind keine Zugriffssperre für
bereits öffentliche Veranstaltungsdaten. Vertrauliche Informationen gehören
weiterhin ausschließlich in interne Felder.

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
`show_details`, außerdem die bisherigen `title`, `upcoming_title`,
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
