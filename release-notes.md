## [3.40.4]

- Der Website-Kalender in WordPress und TYPO3 löst Typ-Standardbilder direkt aus der nativen Mediathek auf, auch wenn kein Kiosky-Medienkatalog gespeichert ist. Eigene Veranstaltungsbilder bleiben vorrangig.
- Detailfenster laden ihre Bilder sofort. Ausgeblendete Bilder bleiben auch im öffentlichen Datensatz ausgeblendet.
- Gezielte, deduplizierte Bildabfragen vermeiden einen vollständigen Medienscan; private, gelöschte oder nicht öffentlich lesbare native Standardbilder werden nicht veröffentlicht. Neue Regressionen prüfen die echten CMS-Ausgaben und geladene Bilder im Browser.
