# Nakaryu Shortcode Audit — Anleitung

Dieses kostenlose Werkzeug prüft die Struktur gespeicherter WPBakery-Inhalte. Es zeigt beispielsweise fehlende Abschluss-Tags, falsche Verschachtelung und unvollständige Attribut-Anführungszeichen. Der Inhalt wird dabei weder ausgeführt noch verändert.

## WordPress

1. `nakaryu-shortcode-audit-0.1.1.zip` unter **Plugins → Installieren → Plugin hochladen** installieren und aktivieren.
2. **Werkzeuge → Shortcode Audit** öffnen.
3. Eine Seite, einen Beitrag oder ein Produkt auswählen; alternativ einen Shortcode-Ausschnitt einfügen.
4. Fundstellen anhand von Zeile und Spalte prüfen. Bei Bedarf den JSON-Bericht herunterladen.

Die Oberfläche der ersten Version ist englisch. Benötigt werden WordPress ab 6.3 und PHP ab 7.4. WPBakery muss zum einfachen Prüfen nicht aktiv sein. Wenn seine Elementdefinitionen verfügbar sind, werden zusätzliche Container erkannt.

## Ergebnisse verstehen

- **Error:** Ein struktureller Fehler wurde innerhalb der unterstützten Prüfung erkannt.
- **Warning:** Bei einem eigenen Builder-Element ist unklar, ob es einen Abschluss-Tag benötigt. Die Elementdefinition manuell prüfen oder die Klassifizierung konfigurieren.
- **Info:** Ein Textelelement ist leer; das kann beabsichtigt sein.
- **Scan incomplete:** Die Prüfung konnte nicht vollständig durchgeführt werden, zum Beispiel wegen eines Größenlimits oder beschädigter Syntax. Ein solcher Bericht ist keine erfolgreiche Gesamtprüfung.

Ein Bericht ohne Fehler ist kein Nachweis für korrektes Rendering. CSS, Bilder, Links, erlaubte Eltern-Kind-Kombinationen und die Ausführung eigener Shortcodes werden nicht geprüft. HTML-Attribute, Kommentare, Skript-/Style-Inhalte und rohe Builder-Payloads sind ausdrücklich ausgeschlossen.

## Datenschutz und Berechtigungen

Die Verwaltungsseite erfordert `manage_options`; für vorhandene Inhalte wird zusätzlich die Bearbeitungsberechtigung des Eintrags geprüft. Berichte enthalten Tag-Namen und Fundstellen, aber keine Originaltexte. Für gespeicherte Einträge werden außerdem ID und Inhaltstyp exportiert.

Das Plugin legt keine eigenen Tabellen, Berichte oder Hintergrundaufgaben an und sendet keine Inhalte an externe Dienste. Beim Löschen ist daher kein zusätzlicher Datenbestand aufzuräumen.

## Für Entwickler

```sh
php bin/shortcode-audit.php examples/valid.txt
php bin/shortcode-audit.php examples/broken.txt --format=json
wp shortcode-audit check 42 --format=json
wp shortcode-audit scan --post_type=page,product --limit=50 --offset=0
```

Die [englische README](../README.md) beschreibt zusätzliche Tag-Klassifizierungen, Grenzen und die Tests. Dieses Projekt enthält eigenständig neu geschriebenen Code und synthetische Beispiele. Es übernimmt keinen Code aus den kommerziellen Nakaryu-Plugins.
