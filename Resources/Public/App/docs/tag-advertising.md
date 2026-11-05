# Bulk-Import und Werbung mit Tags

Ab 3.16.0 gelten dieselben Funktionen in Standalone, WordPress und TYPO3.

## Import

In **Slides & Kanäle → Bulk-Slide-Import** mehrere Bilder oder Videos wählen.
WordPress öffnet seinen nativen Medienbrowser. Nach der Dateiauswahl Tags auswählen
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


## Werbung aus manuell angelegten Veranstaltungen

Nach dem Aktivieren unter Einstellungen → Werbung erzeugt Kiosky die Kanäle
„Werbung quer“ und „Werbung Hochkant“. Manuelle Veranstaltungen ohne
Veranstaltungsart werden automatisch berücksichtigt. Ist eine Veranstaltungsart
hinterlegt, muss diese in den Werbeeinstellungen ausgewählt sein. Der bisherige Statusfilter bleibt erhalten: Auch Veranstaltungen „Zu prüfen“
werden berücksichtigt; abgesagte, archivierte, gelöschte und abgelaufene
Veranstaltungen bleiben ausgeschlossen.

Beim ersten Aktivieren wird „Werbung · Veranstaltung“ als bearbeitbare Vorlage
in der Vorlagenbibliothek gespeichert und ausgewählt. Über „Werbevorlage
bearbeiten“ öffnest du den Slide-Editor. Bild und Eventdaten stammen aus der
jeweiligen Veranstaltung; zusätzliche Eventfelder kannst du im Editor ergänzen.
Speichere über „Vorlage aktualisieren“. Änderungen werden in die automatisch
generierten Werbeslides übernommen. Eine selbst ausgewählte Vorlage bleibt
maßgeblich. Synchronisieren erzeugt keine doppelten Kanäle oder Vorlagen.

Die Werbung endet mit dem Veranstaltungsende, ersatzweise mit dem Beginn.
Die Begrenzungen für nächste Veranstaltungen und Slides je Kanal gelten weiter.
Diese Funktion ist in Standalone, WordPress und TYPO3 identisch.
