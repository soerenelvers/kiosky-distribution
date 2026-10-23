## [3.11.1] – 2026-10-23

- WordPress-Menükollision zwischen „Kiosky → Updates“ und den nativen Zugangseinstellungen beheben: Die Updateübersicht erhält einen eigenen Seitenschlüssel und öffnet wieder die gemeinsame Oberfläche zum Prüfen und Installieren. Bestehende Einstellungs-URLs, gespeicherte Tokens und Berechtigungen bleiben erhalten.
- Die WordPress-Einstellungsseite als „Kiosky Update-Zugang“ kennzeichnen und mit „Updates suchen und installieren“ zur Updateübersicht verlinken. Direktaufruf und regulären WordPress-Updateweg für bereits betroffene Installationen dokumentieren.
- Regressionen prüfen beide Registrierungsreihenfolgen, getrennte WordPress-Elternseiten und Callbacks sowie die tatsächlich gerenderte Updateansicht und Zugangseinstellung in einer nativen WordPress-Installation.
