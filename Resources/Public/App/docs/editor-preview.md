# Große Slide-Vorschau (ab 3.46.2)

Im Slide- oder Vorlageneditor **▶ Vorschau** wählen. Die Vorschau nutzt die gesamte
Arbeitsfläche. Werkzeuge, Ebenen und Eigenschaften sind ausgeblendet; oben bleiben
**Editor anzeigen** und **Schließen** erreichbar, auch auf Smartphones und Tablets.

Die vollständige Slide wird proportional eingepasst: Querformat 16:9, Hochformat
9:16. Responsive Slides verwenden die aktuell im Editor gewählte Designausrichtung.
Die Vorschau wird aus einer Arbeitsfläche mit 1920 × 1080 beziehungsweise
1080 × 1920 Pixeln gleichmäßig skaliert. Dadurch bleiben auch ältere feste
Pixelschriften im Verhältnis zur Slide korrekt. Die Anpassung berücksichtigt Breite und Höhe und reagiert auf Fenstergrößenänderungen.
Ränder außerhalb der Slide sind normal, wenn Fläche und Slide unterschiedliche
Proportionen haben. Inhalte werden dafür weder gestreckt noch beschnitten.

**Editor anzeigen** stellt das normale Layout wieder her. Nach dem Schließen startet
der Editor wieder im Bearbeitungsmodus. Die Vorschau verändert keine gespeicherten
Inhalte oder Abmessungen und übernimmt keinen neuen Slide-Status.

Die gemeinsame Oberfläche gilt für Standalone, WordPress und TYPO3.
`python3 tests/editor-preview-browser.py` prüft alle drei UI-Transporte, beide festen
Ausrichtungen und ein responsives Hochkantdesign bei 1600/1280/820/390/320 Pixeln,
maximale proportional passende Abmessungen, Rückkehr und Wiederöffnen sowie
unveränderte Dokumente. `KIOSKY_BROWSER_ENGINE=webkit` prüft zusätzlich WebKit.
