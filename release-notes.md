## [3.48.1]

- Die Einstellungsübersicht lädt Werbung samt Mediathek und Kalender erst beim Öffnen des jeweiligen Bereichs.

- Standalone-Player lösen nur tatsächlich verwendete Medien-IDs auf; wiederholte Referenzen und fehlende IDs werden je Antwort einmal gelesen. Dateiänderungen bleiben beim nächsten Abruf sichtbar.
- CMS-Einstellungen für Module, Veranstaltungen, Kalender, Speisekarten und Player-Überwachung benötigen keine Mediathek-Vollabfrage und lösen keine Medienmigration aus. Rechte und Schreibkonfliktschutz bleiben erhalten.
- Die Live-Ausgabevorschau lädt nur sichtbare beziehungsweise nahe Displaykarten einschließlich sichtbarer Matrix-Mitglieder, mit höchstens zwei parallelen Abrufen. Scrollen lädt weitere Ausgaben nach.
- Hintergrundabfragen pausieren bei geschlossenen Überwachungspanels, in anderen Ansichten, versteckten Tabs und offenen Bearbeitungsdialogen. Player-Abfragefrequenzen und Warnungsreaktion bleiben unverändert.
