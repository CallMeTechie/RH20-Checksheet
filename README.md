# RH20 Kopf-Prüfprotokoll

Werkzeug zur Erfassung und Bewertung der Fuji-RH20-Kopfprüfung — Contact
Detection, Internal Head Sensor, Vacuum (Z1/Z2), Vacuum Break Down, Nozzle
Cleaning und Valve Air Stick über alle 20 Shafts A–T, druckbar als Prüfbericht
auf A4 quer.

PHP/SQLite, kein Framework, kein Build-Schritt, keine externen Abhängigkeiten.
Läuft als Container. Kein Login. **Jedes Feld speichert sofort beim Verlassen
(Auto-Save) — es gibt keinen „Speichern"-Button.**

## 1. Voraussetzungen

Eine Docker-Umgebung — auf einer Synology der **Container Manager** aus dem
Paket-Zentrum (DSM 7.2+), sonst Docker oder Podman.

Das Image ist für **linux/amd64** gebaut und läuft damit auf Synology-Modellen
mit Intel-/AMD-Prozessor (DS918+, DS920+, DS923+ …), **nicht** auf
ARM-basierten Modellen.

Alles Weitere bringt das Image mit: PHP 8.3 mit `pdo_sqlite` und `sqlite3`,
Apache, die Zeitzone. Es gibt nichts zu konfigurieren außer dem
Datenverzeichnis und dem Port.

## 2. Installation

Das fertige Image liegt **jedem Release als Datei bei** — es gibt keine
öffentliche Registry, aus der es gezogen werden könnte. Der Bildname beginnt
zwar mit `ghcr.io/…`, das ist aber nur ein Bezeichner; die zugehörige Registry
ist nicht öffentlich.

### Image einspielen

Von der [Release-Seite](https://github.com/CallMeTechie/RH20-Checksheet/releases/latest)
`rh20-checksheet-<version>-image.tar.gz` herunterladen und einspielen:

```bash
docker load -i rh20-checksheet-1.3.2-image.tar.gz
```

Auf einer Synology stattdessen über **Container Manager → Abbild → Aktion →
Importieren → Von Datei hinzufügen**.

### Starten

```bash
docker run -d --name rh20-checksheet -p 1020:80 \
  -v /volume1/docker/rh20-checksheet/data:/var/www/html/data \
  -e TZ=Europe/Berlin -e PUID=1000 -e PGID=1000 \
  --restart unless-stopped \
  ghcr.io/callmetechie/rh20-checksheet:1.3.2
```

Oder mit der `docker-compose.yml` aus diesem Repository:

```bash
docker compose up -d
```

Die Compose-Datei enthält `pull_policy: never` — sie erwartet das Image also
lokal und versucht keinen Registry-Zugriff.

### Selbst bauen

Ebenso möglich, das Repository enthält alles Nötige:

```bash
docker build -t rh20-checksheet:local .
```

Dann in `docker-compose.yml` den Bildnamen auf `rh20-checksheet:local` setzen.

Schritt für Schritt, auch für Systeme ohne Internetzugang, in
[`docs/OFFLINE-INSTALL.md`](docs/OFFLINE-INSTALL.md).

### Was vor dem ersten Start anzupassen ist

| Einstellung | Bedeutung |
|---|---|
| **Datenverzeichnis** | Der Pfad links vom Doppelpunkt muss existieren und beschreibbar sein. Er enthält später die einzige Datenbankdatei. |
| **`PUID` / `PGID`** | Besitzer und Gruppe dieses Verzeichnisses, zu ermitteln mit `stat -c '%u %g' <Verzeichnis>`. Damit gehört die erzeugte Datenbank dem richtigen Benutzer und bleibt außerhalb des Containers handhabbar. **Nicht 0** — Apache verweigert den Start als root, der Container bricht mit einer entsprechenden Meldung ab. |
| **Port** | Links der Port auf dem Host. Auf DSM ist 8080 häufig belegt. |
| **`TZ`** | Bestimmt die Zeitstempel in den Prüfprotokollen. |

Der Pfad des Datenverzeichnisses ist frei wählbar; das obige Beispiel und die
mitgelieferte `docker-compose.yml` verwenden unterschiedliche Namen, weil die
Compose-Datei den Pfad einer bestehenden Installation beibehält. Maßgeblich ist
nur, dass der Pfad existiert und zu `PUID`/`PGID` passt.

Aufrufen unter `http://<HOST>:1020`. Die SQLite-Datenbank wird beim ersten
Aufruf automatisch angelegt.

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
  Drucker-Icon öffnet den Druckdialog (`Strg+P`) — siehe Abschnitt 5.
- **Papierkorb-Icon** – löscht eine Prüfung inkl. aller 20 Shaft-Zeilen
  dauerhaft (mit Bestätigungsabfrage).

Alle Aktions-Buttons sind Icons mit Tooltip (Maus kurz draufhalten zeigt die
Beschriftung) statt Textbuttons.

## 4. Sprache

Die Oberfläche gibt es auf **Englisch (Standard)** und Deutsch. Umgeschaltet wird
über **EN / DE** oben rechts; die Wahl merkt sich ein Cookie (`rh20_lang`, ein
Jahr) und gilt für alle Seiten. `?lang=en` bzw. `?lang=de` in der URL setzt sie
ebenfalls.

Übersetzt sind Erklärungstexte, Überschriften und Beschriftungen. Die Messgrößen
selbst (Vacuum, Nozzle Cleaning, Pressure, Flow …) stehen in beiden Sprachen
englisch, weil sie so auf dem Fuji-Beleg und an den Messgeräten stehen. Alle
Texte liegen in `lang.php`; ein fehlender Schlüssel fällt auf Englisch zurück.

## 5. Drucken / PDF

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

## 6. Prüfkriterien (Referenz)

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

## 7. Sicherheit / Hinweise

- **Kein Login** — die App sollte daher nur im internen Netz erreichbar sein
  (keine Portweiterleitung). Für Fernzugriff VPN oder einen Reverse Proxy mit
  vorgeschalteter Authentifizierung.
- Alle Daten liegen in **einer Datei**: `data/inspections.sqlite`. Für Backups
  genügt es, diese Datei in eine bestehende Sicherungsaufgabe aufzunehmen.
- Die Datenbank ist **nicht über HTTP erreichbar**. Die Sperre steht in der
  vhost-Konfiguration des Images (`docker/rh20.conf`) und nicht allein in
  `data/.htaccess` — im Basisimage gilt `AllowOverride None`, `.htaccess`
  würde also ignoriert. Ein Aufruf von `/data/inspections.sqlite` liefert 403.
- **Der PHP-Code läuft als `www-data`**, nicht als root. Nur der
  Apache-Masterprozess ist root, weil er Port 80 bindet — die Arbeitsprozesse,
  die Anfragen bearbeiten, laufen unprivilegiert. Ein versehentliches `PUID=0`
  wird beim Start mit einer verständlichen Meldung abgewiesen, statt Apache in
  einen kryptischen Fehler laufen zu lassen.
- Die erzeugte Datenbank gehört dem über `PUID`/`PGID` gesetzten Benutzer und
  ist damit außerhalb des Containers normal handhabbar (z. B. in der File
  Station).
- Anlegen und Löschen laufen über **POST**, damit ein Reload oder ein
  Link-Prefetch des Browsers keine Prüfung anlegt oder löscht.
- Da jedes Feld sofort speichert, gibt es keinen „ungespeicherten Zustand"
  mehr, der beim Schließen des Tabs verloren gehen könnte.
- Es gibt keinen Schutz gegen gleichzeitiges Bearbeiten: Öffnen zwei Personen
  dieselbe Prüfung, gewinnt der zuletzt gespeicherte Wert.

## 8. Dateiübersicht

```
.
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
├── Dockerfile                    Image-Definition (php:8.3-apache)
├── docker-compose.yml             Startkonfiguration
├── docker/rh20.conf                vhost; sperrt den Zugriff auf data/
├── docker/entrypoint.sh             setzt PUID/PGID, prüft das Datenverzeichnis
├── data/.htaccess                    zusätzliche Sperre außerhalb des Containers
└── data/inspections.sqlite             (wird automatisch erzeugt)
```

## 9. Z1 / Z2 (zwei V-Achsen-Seiten)

Vacuum Pressure und Vacuum Flow werden **komplett getrennt für beide Seiten**
erfasst: alle 20 Shafts A–T einmal für Z1 und einmal für Z2 (vier Spalten
statt zwei). Ein Shaft ergibt nur dann PASS, wenn **beide** Seiten die
Spezifikation erfüllen — so wird gleichzeitig sichtbar, ob eine der beiden
Z-Achsen falsch justiert ist.

## 10. Migration bestehender Datenbanken

Alle folgenden Umstellungen laufen beim ersten Aufruf automatisch und ohne
Datenverlust; sie können gefahrlos mehrfach ausgeführt werden.

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

Vor dem Einspielen einer neuen Version empfiehlt sich trotzdem eine Kopie von
`data/inspections.sqlite`. Das Datenverzeichnis liegt außerhalb des Containers
und überlebt ein `docker compose up -d` mit neuem Image unverändert.
