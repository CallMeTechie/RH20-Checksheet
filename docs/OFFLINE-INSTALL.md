# Installation ohne Internetzugang

Für eine NAS oder einen Server, der keine Registry erreichen kann. Die drei
Dateien dieses Releases auf einem Rechner **mit** Internet herunterladen und
dann auf das Zielsystem übertragen.

| Datei | Zweck |
|---|---|
| `rh20-checksheet-1.3.0-image.tar.gz` | Das fertige Container-Image (164 MB) |
| `rh20-checksheet-1.3.0-image.tar.gz.sha256` | Prüfsumme dazu |
| `docker-compose.yml` | Startkonfiguration |

Das Image ist für **linux/amd64** gebaut — passend für Synology-Modelle mit
Intel-/AMD-Prozessor (DS918+, DS920+, DS923+ …). Auf ARM-basierten Modellen
läuft es nicht.

## 1. Datei prüfen

Nach dem Herunterladen, noch auf dem Rechner mit Internet:

```
sha256sum -c rh20-checksheet-1.3.0-image.tar.gz.sha256
```

Erwartet: `rh20-checksheet-1.3.0-image.tar.gz: OK`

## 2. Auf die NAS übertragen

Über File Station oder eine SMB-Freigabe, zum Beispiel nach
`/volume1/docker/`. Die Datei muss nicht entpackt werden.

## 3a. Einspielen über den Container Manager

1. **Container Manager → Abbild → Aktion → Importieren → Von Datei hinzufügen**
2. `rh20-checksheet-1.3.0-image.tar.gz` auswählen.
3. Anschließend **Projekt → Erstellen**, das Verzeichnis mit der
   `docker-compose.yml` wählen und das Projekt starten.

> Akzeptiert der Dateidialog die `.gz`-Datei nicht, vorher entpacken
> (`gunzip rh20-checksheet-1.3.0-image.tar.gz`) und die entstandene `.tar`
> auswählen.

## 3b. Alternativ über SSH

```
sudo /usr/local/bin/docker load -i /volume1/docker/rh20-checksheet-1.3.0-image.tar.gz
cd /volume1/docker/rh20-checksheet && sudo /usr/local/bin/docker compose up -d
```

Auf Synology liegt das Docker-Binary unter `/usr/local/bin/docker` und ist
nicht im `PATH` von `sudo` — daher der vollständige Pfad.

## 4. Vor dem ersten Start anpassen

In der `docker-compose.yml`:

- **Datenverzeichnis:** Der Pfad links vom Doppelpunkt muss auf dem Zielsystem
  existieren und beschreibbar sein. Er enthält später die einzige
  Datenbankdatei `inspections.sqlite`.
- **`PUID` / `PGID`:** auf Besitzer und Gruppe dieses Verzeichnisses setzen,
  damit die erzeugte Datenbank außerhalb des Containers handhabbar bleibt.
  Ermitteln mit `stat -c '%u %g' <Datenverzeichnis>`. **Nicht 0** — der
  Webserver verweigert den Start als root.
- **Port:** links steht der Port auf dem Host. 8080 ist auf DSM häufig belegt.
- **Zeitzone:** `TZ` bestimmt die Zeitstempel in den Prüfprotokollen.

## 5. Aufrufen

`http://<NAS-IP>:8090`

Die Datenbank wird beim ersten Aufruf automatisch angelegt.

**Die Anwendung hat keinen Login.** Nur in einem vertrauenswürdigen Netz
betreiben, keine Portweiterleitung aus dem Internet.

## Spätere Aktualisierung

Neue Image-Datei einspielen, den Image-Namen in der `docker-compose.yml` auf
die neue Version setzen und `docker compose up -d` ausführen. Das
Datenverzeichnis liegt außerhalb des Containers und bleibt dabei erhalten —
trotzdem vorher eine Kopie von `inspections.sqlite` anlegen.
