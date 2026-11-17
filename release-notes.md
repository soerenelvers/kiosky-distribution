## [3.46.3]

- Verwaltungsantworten sind an die aktuelle Sitzung gebunden. Überholte Katalogantworten nach Schreibzugriffen oder Ansichtswechseln werden verworfen; Standalone-Abmeldung und Sitzungsablauf erneuern den privaten Oberflächenzustand.
- Parallele identische Leseabrufe werden zusammengefasst; abgeschlossene Antworten werden nicht dauerhaft zwischengespeichert. Lesefristen, klare Fehler bei ungültigen Antworten und Erhalt des bisherigen Datenstands verbessern langsame/gestörte Verbindungen. Schreibzugriffe werden nicht automatisch wiederholt.
- Video-Miniaturen laden keine Videometadaten mehr auf Vorrat. Die echte Video-Vorschau und Metadatenermittlung bleiben im Editor erhalten. Große Mehrfachauswahlen benötigen nur noch lineare Katalogarbeit.
- Größere Auswahlflächen, lesbarere Aktionsbuttons und mindestens 44-Pixel-Touch-Ziele in der Slide-/Kanalbibliothek für Smartphones und Tablets.
