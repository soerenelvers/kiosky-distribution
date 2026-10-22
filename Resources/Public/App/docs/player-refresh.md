# Automatische Player-Aktualisierung und Browser-Refresh

Ab Kiosky **3.7.1** in Standalone, WordPress und TYPO3.

## Änderungen an laufenden Inhalten

Aktive Web-Player prüfen ihren aufgelösten Abspielstand alle **fünf Sekunden**.
Gespeicherte Änderungen an zugewiesenen Slides, Kanalinhalt, Reihenfolge,
Laufzeiten und Übergängen sowie geänderte Zuweisungen, Zeitpläne und ausgeführte
Kanal-Presets werden dadurch übernommen. Entscheidend ist die tatsächliche
Ausspielung; ein geänderter, unzugewiesener Entwurf unterbricht den Player nicht.
Ein gespeichertes Preset, das noch nicht ausgeführt wurde, verändert seine bereits
angewendete Zuweisung nicht rückwirkend.

Der Browser lädt dabei **keine neue Seite** und zeigt keinen Ladebildschirm.
Die bestehende Player-Oberfläche bleibt erhalten, die geänderten Slides werden
neu gerendert und die Rotation eines gewöhnlichen Displays beginnt bei der
ersten Slide mit voller Laufzeit. Das gilt auch für eine einzelne zugewiesene
Slide und für den Austausch von Speisekarten-/PDF-Inhalten.

Unveränderte Statusantworten setzen weder die Rotation zurück noch ersetzen sie
die laufenden Medien. Heartbeats und Serverzeit gehören nicht zur Inhaltskennung.
Für Matrix-Displays bleibt der gemeinsame Takt erhalten: Eine relevante
Inhaltsänderung setzt die gemeinsame Zeitbasis zurück; weitere Mitglieder steigen
beim Abruf in diesen Takt ein. Ihre Ausschnitte und gemeinsame Rotation bleiben
synchronisiert.

Warnmeldungen werden weiterhin über ihre separate Überlagerung aktualisiert.
Die gültige Priorität von Warnungen, Zeitplänen und externen Zuweisungen bleibt
maßgeblich. Eine Änderung in einem aktuell durch eine höhere Priorität verdeckten
Kanal wird sichtbar, sobald dieser Kanal wieder zur Ausspielung ausgewählt wird.

## Player aus der Display-Verwaltung neu laden

Unter **Displays → gewünschtes Display → Player neu laden (F5)** den Browser-
Refresh anfordern. Der Befehl richtet sich an dieses Display. Er bleibt vorgemerkt,
solange der Player nicht erreichbar ist. Aktive Player fragen Befehle alle
**drei Sekunden** ab und bestätigen die empfangenen Befehls-IDs vor dem Neuladen.
Ein regelmäßiger Heartbeat bestätigt keine noch ungelesenen neuen Befehle.

Dies entspricht einem vollständigen Browser-Neuladen. Anders als bei einer
normalen Inhaltsänderung wird hierbei die Seite neu geöffnet; eine kurze
Startanzeige ist möglich. Der Player-Prozess, das Betriebssystem und der Monitor
werden dadurch nicht neu gestartet. Die Player-Adresse und der Geräteschlüssel
bleiben erhalten. Bei mehreren geöffneten Browsern derselben Display-Adresse
wird die gemeinsame Befehlswarteschlange verwendet; getrennte physische Displays
sollten eigene Display-Einträge haben.

Die vorhandenen Aktionen **Synchronisieren**, **Neu laden** und
**Identifizieren** unter **Presets & Warnhinweise** bleiben verfügbar.
Synchronisieren fordert neue Daten und einen Neustart der Rotation ohne
Seitennavigation an. Identifizieren blendet weiterhin die Display-Informationen ein.

Die Aktion verwendet die vorhandenen Backend-Berechtigungen für Player-Befehle.
Die öffentliche Befehls-API ist in allen drei Ausgaben ausschließlich mit dem
zugehörigen Geräteschlüssel erreichbar; sie eröffnet keinen Verwaltungszugang.

## Netzwerk, Offline-Betrieb und Installation

Bei einem fehlgeschlagenen Statusabruf spielt der Player den zuletzt erfolgreich
empfangenen Stand weiter. Ein vorhandener Offline-Cache dient zusätzlich dem
Neustart ohne Verbindung. Fehlgeschlagenes Schreiben in den Browser-Cache
verwirft keine bereits empfangenen neuen Daten. Sobald die Verbindung wieder
besteht, werden Änderungen beim nächsten Abruf übernommen. Das Aktualitätsfenster
setzt einen erreichbaren Server und einen aktiv laufenden Browser voraus;
angehaltene oder vom Betriebssystem gedrosselte Player können später reagieren.

Nach dem Software-Update auf 3.7.1 bestehende Player einmal neu laden, damit sie
auch den neuen JavaScript-Player verwenden. Künftig sind Inhaltsänderungen ohne
manuellen Refresh wirksam. Der separate native TYPO3-Tabellenplayer aktualisiert
seine Playlist ebenfalls im Fünfsekundentakt und startet bei einer Änderung von
vorne; unveränderte Abrufe lassen seine Rotation weiterlaufen.

Es gibt keine neue Datenbankmigration. Die letzte Inhaltskennung und Zeitbasis
liegen in Standalone unter `integration_settings` mit Präfix `player_playback:`,
im CMS-Zustand unter `playbackRevisions`. Zustellinformationen für ältere Player
liegen unter `player_delivered:` bzw. `deliveredPlayerCommands`. Diese Laufzeitdaten
werden nicht in übertragbare Inhalts-/Systempakete exportiert.
