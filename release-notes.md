## [3.3.3] – 2026-10-15

- Ausgewählte Slides, einzelne Kanäle und Vorlagen lassen sich direkt in den jeweiligen Übersichten und im Slide-Editor als Inhaltspaket exportieren und mit Vorschau importieren. Auch Entwürfe und unfertige Kanäle bleiben erhalten.
- Inhaltspakete nehmen nur benötigte Abhängigkeiten mit: Kanal-Slides, Veranstaltungsdaten, Layouts, Standorte und Medien. Eingebettete Dateien werden pro Paket dedupliziert; Bilder, Logos, Pfeile, Videos samt Zuschnitt und CMS-Dateianhänge werden zwischen Standalone, WordPress und TYPO3 übertragen.
- Native CMS-Dateien werden eingebettet und beim Import mit neuen IDs in der Ziel-Medienverwaltung angelegt. Externe Webseiten und Medienlinks bleiben als externe Verweise erkennbar. Imports ergänzen Kopien und verändern keine bestehenden System-Einstellungen oder Benutzer.
- Medienimporte lesen nicht mehr nach jeder Datei die komplette Medienbibliothek ein. Der Export verzichtet auf nicht benötigte Medien und die gesamte Versionshistorie.
- Medien mit unterschiedlichen Trimm-, Zuschnitt- oder anderen Metadaten bleiben getrennte Einträge, auch wenn dieselbe Originaldatei verwendet wird.
- Neue Vertrags-, Berechtigungs- und Übertragungstests sowie erweiterte native CMS-Smoke-Tests prüfen Inhaltspakete einschließlich Medien-Dateien und Verweisen.

- WordPress und TYPO3 speichern die Sichtbarkeit und Reihenfolge des Werbemoduls zuverlässig und unterstützen jetzt auch Werbekonfiguration, automatische Werbeslides und Werbekanäle einschließlich Standardbildern.
