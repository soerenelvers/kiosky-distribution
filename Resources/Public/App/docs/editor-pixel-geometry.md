# Position und Größe in Pixeln oder Prozent (ab 3.34.0)

Im Slide- und Vorlageneditor ein Element auswählen. Unter **Position und Größe**
die Einheit **Prozent** oder **Pixel (Entwurfsfläche)** wählen. X, Y, Breite und
Höhe gelten für alle Elementtypen. Querformat verwendet 1920 × 1080, Hochformat
1080 × 1920 Entwurfspixel. 100 px Breite entsprechen bei einer halb so großen
Ausgabe 50 Bildschirmpixeln. Dezimalwerte sind möglich, die Mindestgröße beträgt
einen Entwurfspixel. Gesperrte Elemente können nicht verschoben oder skaliert werden.

Der Einheitenwechsel verändert weder Position noch Größe. Die Einheit wird pro
Element gespeichert und mit Vorlagen und Kopien übernommen. Intern bleiben die
Geometriewerte präzise Prozentwerte; Vorschau und Player skalieren sie zusammen
mit der gesamten Slide. Die bestehende automatische Anpassung beim Wechsel
zwischen Hoch- und Querformat bleibt erhalten. Die Pixelangaben beziehen sich
immer auf die im Editor angezeigte Entwurfsfläche, nicht auf die Browsergröße.
Bestehende Slides öffnen sich weiterhin in Prozent. Keine Datenbankmigration.

Gilt für Standalone, WordPress und TYPO3. Bei einem Downgrade bleiben die
prozentualen Positionen erhalten; ältere Editoren zeigen keine Pixeleingabe an.
