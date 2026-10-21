# Kanalvorschau

In **Slides & Kanäle** einen Kanal bearbeiten und unten **Kanalvorschau** öffnen.
Die Vorschau ist in Standalone, WordPress und TYPO3 identisch verfügbar.

Sie zeigt den aktuellen Bearbeitungsstand der Reihenfolge, individuellen Laufzeiten,
Übergänge, Ausrichtung und Wiederholungsoption – auch vor dem nächsten Autosave.
Slides und Veranstaltungsdaten werden beim Öffnen über die bestehende angemeldete
API frisch gelesen. Es werden nur Slides geladen, auf die das Benutzerkonto Zugriff
hat. Die Vorschau speichert oder veröffentlicht selbst keine Änderungen.

Die Wiedergabe beginnt mit der ersten aktuell gültigen Slide. Start- und Endzeiten
der Slides werden während der geöffneten Vorschau berücksichtigt. Bei einer Änderung
der gültigen Zusammenstellung startet die Rotation mit der ersten gültigen Slide neu.
Ohne Wiederholung bleibt nach dem letzten Durchlauf die letzte Slide stehen.

**Pause/Fortsetzen**, **Zurück**, **Weiter** und **Von vorn** steuern die Vorschau.
Uhren und datumsbezogene Countdowns zeigen weiterhin die aktuelle Zeit. Beim
Schließen (auch mit Escape) enden Timer, Videos und eingebettete Websites.
Inhalte oder Medien, die in der Zwischenzeit anderswo bearbeitet wurden, werden
beim erneuten Öffnen aktualisiert.

Die Vorschau verwendet den gemeinsamen Display-Renderer mit einer verkleinerten
1920 × 1080 bzw. 1080 × 1920 großen Bühne. „Automatisch“ verwendet in der
Kanalvorschau Querformat; bei festem Hochformat werden automatische Slide-Layouts
in Hochformat dargestellt. Bereits vorhandene feste Slide-Ausrichtungen bleiben erhalten.

Die Vorschau prüft den Kanal selbst. Display-Zeitpläne, standortabhängige Auswahl,
Matrixausschnitte, synchronisierte Display-Gruppen und aktive Warnmeldungen hängen
vom jeweiligen Display ab; diese über dessen **Player öffnen** prüfen.
Es wird kein Display angelegt, kein Heartbeat gesendet und kein Offline-Cache befüllt.
Externe Websites müssen das Einbetten erlauben; Browser können die automatische
Tonwiedergabe blockieren, wie im regulären Player.

Das Update benötigt keine Datenbankmigration. Ein Rückwechsel auf das vorherige
Paket verändert keine gespeicherten Kanäle oder Slides.
