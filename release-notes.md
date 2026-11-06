## [3.22.2] – 2026-11-06

- Zeitzuweisungen mit ISO-Zeitzonenoffset in Standalone korrekt mit UTC vergleichen, einschließlich bestehender Einträge.
- CMS-Gruppenmitgliedschaften bei Display-Änderungen und Gruppen-Umbenennungen erhalten und beidseitig abgleichen.
- CMS-Kanal-/Slide-Verweise nach Zeitplanänderungen abgleichen und ältere widersprüchliche Verweise beim Ausspielen berücksichtigen.
- Warnungen oberhalb von Presets, Presets oberhalb aktiver Zeitzuweisungen und diese oberhalb des Display-Standards ausspielen. Warnungen beenden Presets beim Aktivieren nicht mehr automatisch.
- CMS-Warnungs-Presets übernehmen die konfigurierte Laufzeit auch beim Start aus der Oberfläche.
- API-Reset und Preset-Stopp erhalten aktive Warnungen standardmäßig; ausdrückliches Beenden bleibt möglich.
- Nicht mehr spielbare Einzel-Slides/Kanäle geben die folgende Ausspielebene frei. Standardkanäle und Standardslides bleiben als Rückfall erhalten.
- Gleichzeitige Display-/Gruppen-Zeitzuweisungen eindeutig auflösen und dieselbe Vertragsprüfung für Standalone sowie beide nativen CMS-Adapter ergänzen.
