# RH20 Kopf-Prüfprotokoll (Web-App)

PHP/SQLite-Webanwendung zur Erfassung der Fuji-RH20-Kopfprüfung
(Internal Head Sensor / Vacuum / Vacuum Break Down / Nozzle Cleaning, alle 20
Shafts A–T). Kein Login, keine externen Abhängigkeiten, läuft direkt über
Synology Web Station. **Jedes Feld speichert sofort beim Verlassen (Auto-Save) —
es gibt keinen „Speichern"-Button.**

## 1. Voraussetzungen auf der Synology

1. **Web Station** (Paket-Zentrum) installieren, falls noch nicht vorhanden.
2. Ein **PHP-Profil** anlegen/verwenden (Web Station → PHP-Einstellungen →
   Profil bearbeiten). **Erforderlich ist PHP 8.1 oder neuer** (die App nutzt
   `match`, `mixed` und den Rückgabetyp `never`). Wichtig: In den Erweiterungen
   müssen **`pdo_sqlite`** und **`sqlite3`** aktiviert sein. Weitere
   Erweiterungen — auch `mbstring` — werden nicht benötigt.
3. Einen virtuellen Host / Webordner anlegen, der auf dieses PHP-Profil zeigt.

> **Alternativ als Container:** Das Repository enthält ein `Dockerfile` und eine
> `docker-compose.yml` für den Betrieb als Container (z. B. im Container Manager
> einer Synology). Die folgende Anleitung beschreibt die Installation direkt
> unter Web Station.

## 2. Installation

1. Diesen Ordner (`rh20-inspection/`) auf die NAS kopieren, z. B. nach
   `/web/rh20-inspection/`.
2. `data/`-Ordner für den Webserver-Benutzer (auf Synology i. d. R. `http`)
   beschreibbar machen:
   ```
   chown -R http:http /web/rh20-inspection/data
   chmod 775 /web/rh20-inspection/data
   ```
3. Im Browser aufrufen: `http://<NAS-IP>/rh20-inspection/index.php`
4. Die SQLite-Datenbank `data/inspections.sqlite` wird beim ersten Aufruf
   automatisch angelegt.

## 3. Nutzung

- **+ (Neue Prüfung anlegen)** – legt sofort eine neue Prüfung mit 20 leeren
  Shaft-Zeilen an und öffnet direkt die Erfassungsmaske.
- **Erfassungsmaske (Stift-Icon „Bearbeiten")** – hier werden alle Werte
  eingetragen:
  - Jedes Feld speichert **automatisch beim Verlassen** (Klick woanders hin
    oder Tab) — aber nur, wenn sich der Wert tatsächlich geändert hat.
  - **Enter** speichert das aktuelle Feld und springt automatisch weiter.
  - Reihenfolge beim Drücken von Enter: Kopf-Felder → Internal Head Sensor
    Flow Rate → dann **spaltenweise**: erst Internal Head Sensor Pressure A bis
    T komplett, danach Vacuum Pressure Z1 A–T, dann Vacuum Flow Z1 A–T usw. —
    immer erst eine Kategorie komplett A–T, dann zur nächsten.
  - Farbige Hinterlegung (grün/gelb/rot) je Zelle sowie die Zusammenfassung
    unten aktualisieren sich live nach jedem Speichern, ohne Neuladen.
  - **Vacuum Pressure** akzeptiert Werte mit oder ohne Minuszeichen — `88`
    und `-88` werden identisch bewertet (Betragsprüfung ≥ 80 kPa), da
    Vakuummeter den Wert oft ohne Vorzeichen anzeigen.
  - Dezimalwerte funktionieren mit **Punkt oder Komma** (`3.2` oder `3,2`).
  - Eine Eingabe, die keine gültige Zahl ist (z. B. `8O` statt `80`), wird
    **nicht** gespeichert: das Feld wird rot umrandet, oben erscheint
    „Nicht gespeichert …", und der zuletzt gültige Messwert bleibt in der
    Datenbank erhalten. Ein Feld bewusst leeren geht weiterhin, indem man es
    leer verlässt.
- **Augen-Icon (Ansehen / Drucken)** – schreibgeschützte Berichtsansicht.
  Drucker-Icon öffnet den Druckdialog (`Strg+P`) — siehe Abschnitt 4.
- **Papierkorb-Icon** – löscht eine Prüfung inkl. aller 20 Shaft-Zeilen
  dauerhaft (mit Bestätigungsabfrage).

Alle Aktions-Buttons sind Icons mit Tooltip (Maus kurz draufhalten zeigt die
Beschriftung) statt Textbuttons.

## 3a. Sprache

Die Oberfläche gibt es auf **Englisch (Standard)** und Deutsch. Umgeschaltet wird
über **EN / DE** oben rechts; die Wahl merkt sich ein Cookie (`rh20_lang`, ein
Jahr) und gilt für alle Seiten. `?lang=en` bzw. `?lang=de` in der URL setzt sie
ebenfalls.

Übersetzt sind Erklärungstexte, Überschriften und Beschriftungen. Die Messgrößen
selbst (Vacuum, Nozzle Cleaning, Pressure, Flow …) stehen in beiden Sprachen
englisch, weil sie so auf dem Fuji-Beleg und an den Messgeräten stehen. Alle
Texte liegen in `lang.php`; ein fehlender Schlüssel fällt auf Englisch zurück.

## 4. Drucken / PDF

Die Berichtsansicht (`view.php`) ist auf **DIN A4 Querformat** ausgelegt und
passt im Regelfall auf **eine**, spätestens auf **zwei** Seiten.

Im Druckdialog einstellen:

| Einstellung | Wert |
|---|---|
| Ausrichtung | **Querformat** |
| Papierformat | A4 |
| Ränder | Standard |
| **Hintergrundgrafiken** | **aktiviert** (sonst fehlen die Farbmarkierungen) |
| Sprache | folgt der Anzeige — vor dem Drucken EN/DE wählen |
| Drucker | „Microsoft Print to PDF" für eine PDF-Datei |

**Farben im Ausdruck:** Die Messwerttabelle wird nicht grün hinterlegt — Grün
für PASS trägt nur die Spalte „Overall". Gelb (grenzwertig) und Rot (außerhalb
der Spezifikation) bleiben auch bei den Einzelwerten erhalten, damit der
auffällige Messwert auf dem Papier auffindbar bleibt. Am Bildschirm ist die
Tabelle unverändert dreifarbig.

Aufteilung: Kopfdaten, die einmaligen Kopfmessungen und die komplette Messtabelle
A–T stehen zusammen; die Zusammenfassung samt Unterschriftenzeilen bleibt als
Block zusammen und rutscht nur dann komplett auf Seite 2, wenn sie auf Seite 1
nicht mehr passt. Reißt die Messtabelle über den Seitenrand, wird ihr
Tabellenkopf auf der Folgeseite wiederholt und keine Zeile aufgetrennt.

## 5. Prüfkriterien (Referenz)

| Test | Ort | Standard |
|---|---|---|
| **Contact Detection – Pressure** | einmalig je Kopf, über den Internal Head Sensor | 70 ± 4 kPa |
| **Nozzle Cleaning – Internal Head Sensor, Pressure — Shaft A** | je Shaft, Referenz-Shaft | 65 ± 2.5 kPa (Einmessung) |
| **Nozzle Cleaning – Internal Head Sensor, Pressure — Shafts B–T** | je Shaft | 65 ± 10 kPa zulässig; ab ± 5 kPa Abweichung grenzwertig (gelb) |
| Nozzle Cleaning (Internal Head Sensor) – Flow Rate | einmalig je Kopf | ≥ 3.5 L/min |
| Vacuum – Pressure (Z1) | Nozzle Tip, Seite Z1 | \|Wert\| ≥ 80 kPa (Vorzeichen egal) |
| Vacuum – Flow Rate (Z1) | Nozzle Tip, Seite Z1 | ≥ 2.0 L/min |
| Vacuum – Pressure (Z2) | Nozzle Tip, Seite Z2 | \|Wert\| ≥ 80 kPa (Vorzeichen egal) |
| Vacuum – Flow Rate (Z2) | Nozzle Tip, Seite Z2 | ≥ 2.0 L/min |
| Vacuum Break Down – Pressure | Nozzle Tip | 10–15 kPa |
| Vacuum Break Down – Flow Rate | Nozzle Tip | ≥ 0.5 L/min |
| Nozzle Cleaning – Pressure | Nozzle Tip | ≥ 100 kPa (mit 100 vorbelegt, siehe unten) |
| Nozzle Cleaning – Flow Rate | Nozzle Tip | ≥ 0.8 L/min |
| **Valve Air Stick – Repeated Sliding** | je Shaft, Messuhr | \|Wert\| ≤ 0.08 mm |

**Vorbelegung Nozzle Cleaning Pressure:** Neue Prüfungen starten mit 100 kPa in
allen 20 Shafts. Das Messinstrument zeigt nicht mehr als 100 kPa an, der reale
Druck liegt deutlich darüber — ein niedrigerer Messwert ist die seltene Ausnahme
und wird dann von Hand überschrieben. Der Vorgabewert steht als
`CLEAN_PRESSURE_DEFAULT` in `functions.php`.

Quelle: Prüfvorgabe des Herstellers (Fuji Corporation).
Die Grenzwerte stehen gebündelt als Konstanten und in `specState()` in
`functions.php` — dort werden sie geändert, nicht in den Seiten.

### Bewertung

| Zustand | Bedeutung |
|---|---|
| **PASS** (grün) | Alle Messwerte des Shafts innerhalb der Spezifikation |
| **WARN** (gelb) | Alle Messwerte zulässig, aber der Internal-Head-Sensor-Druck weicht um mehr als ± 5 kPa vom Zielwert ab |
| **FAIL** (rot) | Mindestens ein Messwert außerhalb der Spezifikation |
| **–** | Noch nicht alle Messwerte des Shafts erfasst |

Das Gesamtergebnis der Prüfung ist FAIL, sobald ein Shaft oder die Flow-Messung
durchfällt; sonst INCOMPLETE, solange Werte fehlen; sonst WARN, wenn mindestens
ein Shaft grenzwertig ist; sonst PASS.

## 6. Sicherheit / Hinweise

- **Kein Login** (wie gewünscht) — die App sollte daher nur im internen LAN
  erreichbar sein (kein Port-Forward). Für Fernzugriff eher VPN oder den
  Synology-Login-Portal-Schutz davorschalten.
- Alle Daten liegen in **einer Datei**: `data/inspections.sqlite`. Für
  Backups genügt es, diese Datei in eine bestehende Hyper-Backup-Aufgabe
  aufzunehmen.
- Das Verzeichnis `data/` enthält eine **`.htaccess`**, die den direkten
  HTTP-Zugriff auf die Datenbankdatei sperrt. Das greift nur bei Apache.
  **Läuft der virtuelle Host unter nginx**, muss die Sperre dort ergänzt
  werden, sonst ist `http://<NAS-IP>/rh20-inspection/data/inspections.sqlite`
  herunterladbar:
  ```
  location ~ /data/ { deny all; return 404; }
  ```
- Anlegen und Löschen laufen über **POST**, damit ein Reload oder ein
  Link-Prefetch des Browsers keine Prüfung anlegt oder löscht.
- Da jedes Feld sofort speichert, gibt es keinen „ungespeicherten Zustand"
  mehr, der beim Schließen des Tabs verloren gehen könnte.
- Es gibt keinen Schutz gegen gleichzeitiges Bearbeiten: Öffnen zwei Personen
  dieselbe Prüfung, gewinnt der zuletzt gespeicherte Wert.

## 7. Dateiübersicht

```
rh20-inspection/
├── index.php          Übersicht aller gespeicherten Prüfungen
├── create.php          legt neue Prüfung an (20 leere Zeilen), nur per POST
├── edit.php             Live-Erfassungsmaske (Auto-Save, Tastatur-Navigation)
├── autosave.php          AJAX-Endpunkt: speichert ein einzelnes Feld sofort
├── view.php               druckbare Berichtsansicht (A4 quer)
├── delete.php              löscht eine Prüfung, nur per POST
├── db.php                   DB-Verbindung, Schema und Migrationen (SQLite)
├── functions.php             Grenzwert-/PASS-WARN-FAIL-Logik, Spaltendefinition
├── lang.php                   Übersetzungen EN/DE, Sprachwahl (Standard Englisch)
├── tests/                      abhängigkeitsfreie Tests, Aufruf: php tests/run.php
├── icons.php                  SVG-Icon-Set + Icon-Button-Helfer
├── assets/style.css            Layout (Bildschirm + Druck)
├── assets/app.js                Auto-Save + Enter-Tastatur-Navigation
├── data/.htaccess                sperrt den HTTP-Zugriff auf die Datenbank
└── data/inspections.sqlite        (wird automatisch erzeugt)
```

## 8. Z1 / Z2 (zwei V-Achsen-Seiten)

Vacuum Pressure und Vacuum Flow werden **komplett getrennt für beide Seiten**
erfasst: alle 20 Shafts A–T einmal für Z1 und einmal für Z2 (vier Spalten
statt zwei). Ein Shaft ergibt nur dann PASS, wenn **beide** Seiten die
Spezifikation erfüllen — so wird gleichzeitig sichtbar, ob eine der beiden
Z-Achsen falsch justiert ist.

## 9. Migration bestehender Datenbanken

Beide Umstellungen laufen beim ersten Aufruf automatisch und ohne Datenverlust;
sie können gefahrlos mehrfach ausgeführt werden.

1. **Eine Vacuum-Spalte → Z1/Z2:** Die neuen Spalten werden ergänzt und die
   bisherigen Werte als Z1 übernommen. Z2 ist danach leer und nachzutragen.
2. **Internal Head Sensor Pressure einmalig → je Shaft:** Die Spalte wandert
   von der Prüfung zu den Shaft-Zeilen. Der bisher einmalig erfasste Wert war
   die Messung am Referenz-Shaft A und wird genau dorthin übernommen; für die
   Shafts B–T ist der Druck nachzutragen. Die alte Spalte bleibt unangetastet
   in der Datenbank stehen, wird aber nicht mehr gelesen.

3. **Contact Detection Pressure kam hinzu:** Die Spalte wird auf Prüfungsebene
   ergänzt und ist für bestehende Prüfungen leer. Diese stehen dadurch so lange
   auf INCOMPLETE, bis der Wert nachgetragen ist — kein Messwert geht verloren.

4. **Valve Air Stick kam hinzu:** Die Spalte `valve_slide` wird in den Shaft-Zeilen
   ergänzt und ist für bestehende Prüfungen leer. Diese stehen dadurch so lange auf
   INCOMPLETE, bis die 20 Werte nachgetragen sind — kein Messwert geht verloren. Die
   Bemerkungsspalte entfällt in der Oberfläche; die Datenbankspalte `remarks` bleibt
   unangetastet stehen und wird nicht mehr gelesen.

Vor dem Update der Dateien empfiehlt sich trotzdem eine Kopie von
`data/inspections.sqlite`.
