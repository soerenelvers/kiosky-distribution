# Veranstaltungsbilder zuschneiden

In einer Veranstaltung einen **Bild-Link** eingeben oder **Bild auswählen /
hochladen** verwenden. Anschließend **Bild bearbeiten** öffnen.

- Den Ausschnittrahmen mit Maus oder Finger verschieben, an der Ecke vergrößern
  oder verkleinern; alternativ Links/Oben/Breite/Höhe in Prozent eingeben.
- Frei zuschneiden oder Original, Quadrat, 16:9, 9:16 bzw. 4:3 wählen.
- Mit **Zoom** den Ausschnitt vergrößern; **Original wiederherstellen** setzt ihn
  zurück. Die Vorschau zeigt das Ergebnis.
- **Ausschnitt übernehmen** übernimmt in den Veranstaltungsdialog. Erst
  **Veranstaltung speichern** speichert dauerhaft. **Abbrechen** verwirft den
  aktuellen Bildeditor-Entwurf.

Die Originaldatei, Mediathek und URL bleiben unverändert. Kiosky speichert nur
den Ausschnitt und die Bildabmessungen an der Veranstaltung. Auch externe
Bild-Links funktionieren ohne Canvas-Export oder Freigabe fremder Server für
CORS; der Link muss im Browser als Bild erreichbar sein. Gesperrte, fehlerhafte
oder abgelaufene Bild-URLs kann der Editor nicht reparieren.

Der Ausschnitt gilt für Veranstaltungsbilder in Event-Details/-Tabellen,
Veranstaltungsübersichten, dynamischen Bild-Elementen der Werbung und im
Website-Kalender, sofern dort Bilder freigegeben sind. Ein fest am Slide
gewähltes anderes Bild bleibt unabhängig. Die Medienplatzierung eines dynamischen
Slide-Bildes kann zusätzlich angepasst werden.

Kopien und Veranstaltungsvorlagen übernehmen die Bearbeitung. Beim Wechsel oder
Entfernen des Bildes im Veranstaltungsdialog wird der alte Ausschnitt verworfen.
Es werden keine Medienkopien erzeugt und keine Bildbytes in Veranstaltungsdaten
abgelegt; ein Zuschnitt reduziert daher nicht die Downloadgröße des Originals.
Keine automatische zusätzliche Bildabfrage vom Server.

Standalone erhält die additive Migration `045_event_image_edit.sql`; WordPress
und TYPO3 speichern dieselben Metadaten im bestehenden Kiosky-Zustand. Vor dem
Update regulär Datenbank und Medien sichern. Ältere Ausgaben ignorieren die
Bearbeitung und zeigen das Originalbild; Originaldateien bleiben erhalten.
