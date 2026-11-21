## [3.51.5]

- Raspberry-Pi-Images für alle vier Modelle um eine Mehrschirm-Sitzung erweitert: pro aktivem Ausgang ein eigenständiger Vollbildplayer mit getrenntem Profil und Kopplung, automatischem Hotplug und erhaltenem primären Profil bei Inplace-Updates. Alte Cage-Images laufen ohne Zusatzpaket weiter als Einzelplayer; für Mehrschirm das neue Beta-4-Image verwenden.
- Wegweisungs-Pfeile in Slides und Vorlagen erhalten einen Zoom-Regler von 10 bis 250 Prozent. Editor, Vorschau und Wiedergabe verwenden denselben begrenzten Wert; bestehende Pfeile bleiben bei 100 Prozent.

- WordPress speichert gewöhnliche Player-Statusmeldungen getrennt vom großen Inhaltsdatensatz. Frische Anzeigeinformationen bleiben für Backend und API sichtbar; Änderungen an Inhalten, Player-Schlüsseln und Befehlsbestätigungen verwenden weiterhin den abgesicherten Vergleichs-/Speicherweg.
- Player verarbeiten Steuerbefehle aus dem Zustandsabruf alle fünf Sekunden. Der zusätzliche Drei-Sekunden-Abruf entfällt; die ersten Folgeabrufe werden zeitlich verteilt, um Lastspitzen bei vielen Playern zu vermindern. Offline werden keine gespeicherten Befehle ausgeführt.
- CMS-Status-/Befehlsabrufe überspringen unnötige Veranstaltungs-Lebenszyklusberechnungen und Anzeigeprojektionen. Inhaltsabruf, Zeitplanung, Warnungen und Scheduler behalten die bisherigen Auswertungen.
- Regressionen mit 15 Browser-Playern und 60 WordPress-Statusmeldungen ergänzt; native WordPress-Installations-/Upgrade-Prüfung kontrolliert den separaten Speicherweg.
