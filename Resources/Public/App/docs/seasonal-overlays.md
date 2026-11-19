# Animierte Deko (3.50.0)

Im Slide- oder Vorlagen-Editor **Animierte Deko** hinzufügen und rechts ein Motiv
wählen. Es handelt sich um ein gewöhnliches positionierbares Element; mehrere
Motive dürfen auf derselben Slide stehen.

- **Schneefall:** transparente, fallende Schneepartikel, etwa über einem
  Veranstaltungsplakat. Neue Deko beginnt großflächig über der ganzen Slide.
- **Weihnachtsbaum:** geschmückter Vektorbaum, leichtes Schaukeln und langsam
  wechselnde Lichter. Beispielsweise klein am Rand eines Begrüßungsbildschirms.
- **Schneemann:** winkende Figur mit farblich anpassbarem Schal und Hutband.
- **Halloween-Kürbis:** sanft leuchtendes Gesicht und leichte Bewegung.
- **Funkelnde Sterne:** verstreute Lichtpunkte für Winter, Silvester oder festliche
  Veranstaltungen.

## Einstellungen

Motiv, **Animation aktiv**, **Akzentfarbe** und **Geschwindigkeit** (0,25× bis 2×)
stehen direkt am Element. Bei Schnee und Sternen sind 8–40 Partikel einstellbar.
Die Farbe betrifft Schnee/Sterne beziehungsweise Schmuck, Schal oder Kürbis;
andere charakteristische Motivfarben bleiben erhalten.

Ziehen, numerische Position und Größe (Prozent/Pixel), Drehung und Deckkraft
verwenden die bestehenden Editorfunktionen. Figuren behalten im Elementrahmen
ihre natürlichen Proportionen. Die Ebenenliste bestimmt, welche Inhalte darüber
oder darunter liegen. Zum Bearbeiten verdeckter Inhalte das Overlay sperren oder
vorübergehend über die Ebenenliste wechseln. Im Player lässt die Dekoration
Zeiger-/Touch-Eingaben zu darunterliegenden Bedienelementen durch.

Die Einstellungen werden mit der Slide beziehungsweise Vorlage gespeichert und
in Editor, Vorschau und tatsächlicher Ausgabe verwendet. Unter
**Einstellungen → Slide-Editor** kann das Werkzeug ausgeblendet werden; vorhandene
Inhalte werden dadurch nicht gelöscht. Die vorhandenen Rollen- und Kanalrechte
sowie die Lizenz für Inhalts-/Kanalbearbeitung bleiben maßgeblich.

## Ressourcen und Bewegung

Motive bestehen aus lokalen Vektoren beziehungsweise einfachen Partikeln. Keine
Fremdserver, Bilddownloads, Videos, Canvas, JavaScript-Intervalle oder externen
Schriften sind nötig. Animationen ändern ausschließlich Transformation/Deckkraft.
Miniaturen in der Bibliothek bleiben statisch. Die Betriebssystemvorgabe
**reduzierte Bewegung** schaltet diese Animationen aus und lässt eine statische
Dekoration sichtbar. Auch der Haken **Animation aktiv** erlaubt eine statische
Darstellung. Schnee wird beim Pausieren über die Fläche verteilt angezeigt.

Die Partikelgrenze gilt je Overlay. Für schwächere Player wenige gleichzeitige
Effekte verwenden und die gewünschte Kombination mit Videos/UHD am Zielgerät
prüfen. Dies ist keine allgemeine Bildratenzusage für jedes Gerät.

## Prüfung und Update

Node-Tests prüfen Grenzwerte, fehlerhafte Einstellungen, sichere/deterministische
Markup-Ausgabe und alle fünf Motive. PHP-Prüfungen sichern Speicherung in Slides
und Vorlagen, Player-Ausgabe sowie die Werkzeug-Einstellung beider CMS ab.
Browserprüfungen in Chromium/WebKit verwenden isolierte Daten und alle drei
Oberflächentransporte: Motivwechsel, Animation/Pause, Verschieben, Größe,
Farben/Tempo/Anzahl, Vorschau, Player, statische Miniaturen, reduzierte Bewegung,
Smartphone/Tablet, Hochkant und UHD. Native CMS-Installation/Upgrades werden
zusätzlich in CI geprüft; echte Android-/iOS-/TV-Hardware wurde dadurch nicht
vollständig abgenommen.

Nach dem Systemupdate Verwaltung und Player einmal neu laden. Kopplungen bleiben
erhalten. Es gibt keine neue SQL-Tabelle, Datenmigration oder Abhängigkeit.
Ältere Versionen kennen den Elementtyp nicht und zeigen diese Dekoration nicht;
vorhandene andere Inhalte und Einstellungen benötigen keine Rückmigration.
