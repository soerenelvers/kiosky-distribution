# Bulk-Import und Werbung mit Tags

Ab 3.16.0 gelten dieselben Funktionen in Standalone, WordPress und TYPO3.

## Import

In **Slides & Kanäle → Bulk-Slide-Import** mehrere Bilder oder Videos wählen.
WordPress und TYPO3 öffnen ihren nativen Medienbrowser; Standalone die eigene
Medienauswahl. Nach der Dateiauswahl Tags auswählen
oder neue Tags eingeben und übernehmen. Alle gewählten Dateien erhalten dieselben
Tags, jeweils als eigene Slide mit Referenz auf die Originaldatei. Abbrechen vor
„Übernehmen“ erstellt keine Slides. Ein fehlerhafter Import wird vollständig
zurückgerollt; die Tag-Auswahl bleibt zur Korrektur offen.

Die Slides bleiben Entwürfe. Zum Ausspielen im Werbekanal anschließend
veröffentlichen. Tags lassen sich auch im Editor oder über die Tag-Stapelzuweisung
an bestehenden Slides ergänzen.

## Tags und Ablauf

- **Werbung quer** ordnet die Slide dem Kanal **Werbung quer** zu.
- **Werbung Hochkant** ordnet sie dem Kanal **Werbung Hochkant** zu.
- Beide Format-Tags ordnen sie beiden Kanälen zu.
- **Werbung** ohne Format-Tag verwendet das Slideformat: Querformat, Hochkant oder
  bei automatischem Format beide Kanäle. Explizite Format-Tags haben Vorrang.

Bei einem Werbe-Tag ohne bestehendes Ablaufdatum wird „Sichtbar bis“ einmalig
auf sieben Tage gesetzt. Bei einem zukünftigen Sichtbarkeitsbeginn beginnt diese
Frist mit diesem Beginn. Bearbeiten, erneut Taggen und Synchronisieren verlängern
eine vorhandene Frist nicht. Das Datum kann im Editor gezielt geändert werden.
Bestehende getaggte Slides ohne Enddatum erhalten beim ersten Abgleich ebenfalls
sieben Tage. Event-Slides verwenden ihr Veranstaltungsende beziehungsweise den
Beginn, wenn keine Endzeit vorhanden ist.

Zum Ablauf blendet der Player die Slide bei seiner nächsten Inhaltsabfrage aus
(regulär alle fünf Sekunden). Die Kanalzuordnung wird beim nächsten automatischen
Abgleich entfernt (regulär spätestens nach fünf Minuten oder sofort nach Speichern/
manuellem Synchronisieren). Die Slide wird nicht gelöscht. Entwürfe, archivierte,
gelöschte, noch nicht begonnene oder abgelaufene manuelle Slides werden nicht
in die automatische Wiedergabe aufgenommen.

## Werbeautomatik

Unter **Einstellungen → Werbung** die Automatik aktivieren. Der bisherige
Veranstaltungsgenerator bleibt verfügbar. **Nächste Veranstaltungen** begrenzt
seinen Beitrag; **Maximale Slides je Werbekanal** begrenzt die Gesamtzahl aus
getaggten Slides und Veranstaltungen je Format auf 1 bis 500.

Getaggte manuelle Slides haben Vorrang, geordnet nach Ablaufdatum und bei gleicher
Frist stabil nach ID. Die übrigen Plätze werden mit den nächsten Veranstaltungen
gefüllt. Standortauswahl und Anzeigedauer gelten für beide Kanäle. Bei Deaktivierung
werden beide automatischen Kanäle pausiert.

Der bisherige automatische Werbekanal behält seine ID und wird zum Querkanal.
Vorhandene Zuweisungen bleiben erhalten. Für Hochkant-Displays den neuen
Hochkantkanal auswählen. Ein geänderter Basisname ersetzt „Werbung“ in beiden
Kanalnamen. Eigene manuelle Kanäle werden nicht umbenannt oder gelöscht.

Rollback entfernt bereits vergebene Tags/Ablaufdaten nicht. Die neue Einstellung
und der zusätzliche Kanal benötigen keine Datenbankschemaänderung.


## Werbung aus manuell angelegten Veranstaltungen ab 3.23.0

Alle manuell angelegten Veranstaltungen werden unabhängig von ihrer Kategorie
berücksichtigt. Die Auswahl der Veranstaltungsarten begrenzt weiterhin importierte
Veranstaltungen. Der bisherige Statusfilter bleibt erhalten: Auch „Zu prüfen“ wird
berücksichtigt; abgesagte, archivierte, gelöschte und abgelaufene Termine entfallen.

Unter Einstellungen → Werbung stehen getrennte, editierbare Vorlagen für Quer-
und Hochformat bereit. „Vorlage bearbeiten“ öffnet den Slide-Editor; „Vorlage
aktualisieren“ speichert die Änderungen. Bestehende Vorlagen werden übernommen;
die beiden neuen Standardvorlagen können unabhängig angepasst werden. Eventdaten
und Bild werden aus der jeweiligen Veranstaltung eingesetzt.

Die Kanäle erzeugen ihre Veranstaltungsinhalte erst beim Abruf im Hintergrund.
Es entstehen keine zusätzlichen Bibliotheks-Slides oder Slide-Versionen.
„Nächste Veranstaltungen“ begrenzt die Zahl der Event-Inhalte je Kanal;
„Maximale Slides je Werbekanal“ begrenzt die Summe aus manuellen getaggten Slides
und Event-Inhalten. Manuelle Slides haben Vorrang, danach folgen die nächsten
Veranstaltungen bis zur Grenze. Nach Ablauf rücken weitere passende Termine nach.
Die Werbung endet mit dem Veranstaltungsende, ersatzweise mit dem Beginn.

Automatische Inhalte werden über den Werbekanal zugewiesen. Beim ausdrücklichen
Duplizieren eines Werbekanals entstehen normale Entwurfs-Slides als bearbeitbare
Momentaufnahme. Bestehende ältere automatisch erzeugte Slides bleiben für ihre
alten Referenzen erhalten, sind aber aus der normalen Bibliothek ausgeblendet.

## Veranstaltungsbild

Im Veranstaltungsdialog entweder eine externe Bild-URL eintragen oder
„Bild auswählen / hochladen“ verwenden. WordPress öffnet die native Mediathek;
TYPO3 öffnet seinen nativen Dateibrowser mit Upload und Mehrfachauswahl.
Standalone verwendet die eigene Medienauswahl mit Upload.
Die Vorschau zeigt das ausgewählte Bild. „Bild entfernen“ löscht nur die Referenz
im Formular, nicht die Mediendatei. Erst „Speichern“ übernimmt die Änderung.

Die Funktion gilt für alle drei Varianten. Standalone-Migration 042 ergänzt eine
optionale virtuelle Inhaltskennung im Player-Status, ohne vorhandene Slide-
Referenzen zu ändern. Vorhandene Veranstaltungs-, Kanal- und Vorlagendaten werden
nicht gelöscht. Vor einem Downgrade eine Sicherung verwenden; ältere Versionen
können Event-Werbung wieder als gespeicherte Slides erzeugen.
