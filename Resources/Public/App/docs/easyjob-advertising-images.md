# Veranstaltungsbilder aus easyjob

Kiosky importiert beim manuellen und automatischen easyjob-Veranstaltungsimport alle Bildanhänge mit der Dokumentenart `Veranstaltungsbild`.

## Zuordnung

- Bei einer als Job importierten Veranstaltung werden zuerst die Anhänge des Jobs gelesen.
- Besitzt der Job kein Veranstaltungsbild, verwendet Kiosky die Veranstaltungsbilder des übergeordneten Projekts.
- Bei einem Projektimport werden die Projektanhänge verwendet.
- Sind mehrere Veranstaltungsbilder vorhanden, werden alle importiert. Das zuletzt geänderte Bild wird der Veranstaltung als primäres Bild zugeordnet.

## Ablage und Aktualisierung

Die Medienordner werden automatisch nach folgendem Muster angelegt:

`Werbung / JJJJ / MM / JJJJ-MM-TT – Veranstaltungstitel`

Die easyjob-Dokument-ID und der Datei-Hash werden als interne Metadaten gespeichert. Eine geänderte Datei aktualisiert deshalb das vorhandene Kiosky-Medium. Wird ein Dokument in easyjob ersetzt, wird das bestehende Medienobjekt weiterverwendet. Nicht mehr vorhandene importierte Bilder werden in den Medien-Papierkorb verschoben.

Manuell gesetzte und dadurch gesperrte Veranstaltungsbilder überschreibt die Synchronisation nicht.

Unterstützt werden JPEG, PNG, GIF, WebP und AVIF bis 20 MB. Die easyjob-Zugangsdaten und Download-Tokens werden nicht in Kiosky gespeichert oder an Displays ausgegeben.

## Standardbild je Veranstaltungsart

Unter **Einstellungen → Werbung → Veranstaltungsarten und Standardbilder** kann für jede aktivierte Veranstaltungsart ein Bild aus der Mediathek ausgewählt oder direkt hochgeladen werden. Bei automatisch erzeugten Werbeslides gilt folgende Priorität:

1. das veranstaltungsspezifische Bild aus easyjob bzw. der Veranstaltung,
2. das Standardbild der zugehörigen Veranstaltungsart,
3. der konfigurierte Hintergrund der Werbevorlage.

Wird ein Standardbild in der Mediathek bearbeitet oder in den Werbeeinstellungen ersetzt, werden die automatisch verwalteten Slides bei der nächsten Synchronisierung aktualisiert.

## Massenaktionen für importierte Veranstaltungen

In der Veranstaltungsliste können mehrere Termine über die Checkboxen ausgewählt und anschließend über **Massenaktion …** gemeinsam bearbeitet werden. Unterstützt werden Anwarts-/Einlasszeit, Beginnzeit, Pause von/bis, Endzeit und Veranstaltungsbild. Uhrzeiten werden jeweils am individuellen Veranstaltungstag in der Zeitzone `Europe/Berlin` gesetzt.

Manuell angelegte Veranstaltungen werden dabei übersprungen. Die geänderten Felder importierter Veranstaltungen werden redaktionell gesperrt, damit ein späterer easyjob- oder CrewBrain-Import die Massenänderung nicht wieder überschreibt.
