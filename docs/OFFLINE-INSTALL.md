# Installation ohne Internetzugang

Für eine NAS oder einen Server, der keine Registry erreichen kann. Die drei
Dateien dieses Releases auf einem Rechner **mit** Internet herunterladen und
dann auf das Zielsystem übertragen.

| Datei | Zweck |
|---|---|
| `rh20-checksheet-1.3.2-image.tar.gz` | Das fertige Container-Image (164 MB) |
| `rh20-checksheet-1.3.2-image.tar.gz.sha256` | Prüfsumme dazu |
| `docker-compose.yml` | Startkonfiguration |

Das Image ist für **linux/amd64** gebaut — passend für Synology-Modelle mit
Intel-/AMD-Prozessor (DS918+, DS920+, DS923+ …). Auf ARM-basierten Modellen
läuft es nicht.

## 1. Datei prüfen

Nach dem Herunterladen, noch auf dem Rechner mit Internet:

```
sha256sum -c rh20-checksheet-1.3.2-image.tar.gz.sha256
```

Erwartet: `rh20-checksheet-1.3.2-image.tar.gz: OK`

## 2. Auf die NAS übertragen

Über File Station oder eine SMB-Freigabe, zum Beispiel nach
`/volume1/docker/`. Die Datei muss nicht entpackt werden.

## 3. Image einspielen

**Container Manager → Abbild → Aktion → Importieren → Von Datei hinzufügen**,
die `.tar.gz` auswählen.

**Danach unbedingt den Namen prüfen.** Unter **Abbild** muss genau das stehen:

```
ghcr.io/callmetechie/rh20-checksheet    1.3.2
```

Steht dort stattdessen der **Dateiname** (`rh20-checksheet-1.3.2-image.tar.gz`)
oder `<none>`, hat der Importer die Metadaten nicht gelesen. Dann findet
anschließend nichts das Image unter dem erwarteten Namen — Compose und der
Container Manager versuchen stattdessen, es aus dem Internet zu laden, was ohne
Internetzugang in einen Timeout läuft.

Abhilfe in diesem Fall, über SSH:

```
# tatsächlichen Namen und ID anzeigen
sudo /usr/local/bin/docker images

# unter dem erwarteten Namen zusätzlich eintragen (ID aus der Liste oben)
sudo /usr/local/bin/docker tag <IMAGE-ID> ghcr.io/callmetechie/rh20-checksheet:1.3.2
```

Ein `docker tag` kopiert nichts, es vergibt nur einen zweiten Namen für
dasselbe Image — kostet keinen Speicher und ist sofort fertig.

> **Zum Archivformat:** Die Image-Datei dieses Releases wird bewusst im
> klassischen Docker-Format erzeugt (`<layer-id>/layer.tar`), nicht im
> neueren OCI-Layout (`blobs/sha256/…`). Neuere Docker-Versionen schreiben
> standardmäßig OCI; damit kam der Importer des Container Managers in einem
> Fall nicht zurecht und benannte das Abbild nach der Datei.

## 4. Container anlegen

### 4a. Direkt aus dem Abbild — empfohlen ohne Internetzugang

Dieser Weg **kann keinen Registry-Zugriff auslösen**, weil er von einem bereits
vorhandenen Abbild ausgeht. Er braucht die `docker-compose.yml` nicht.

1. **Container Manager → Abbild**, das importierte Abbild markieren,
   **Ausführen**.
2. Containername: `rh20-checksheet`. **Automatischen Neustart aktivieren**
   anhaken.
3. **Erweiterte Einstellungen → Port-Einstellungen:**
   lokaler Port `8090` → Container-Port `80` (TCP).
4. **Speicherort / Volume:** *Ordner hinzufügen*, das vorbereitete
   Datenverzeichnis wählen, Mount-Pfad `/var/www/html/data`, Schreibrecht.
5. **Umgebung:** drei Variablen setzen —

   | Variable | Wert |
   |---|---|
   | `TZ` | `Europe/Berlin` |
   | `PUID` | UID des Datenverzeichnisses |
   | `PGID` | GID des Datenverzeichnisses |

6. Fertigstellen und starten.

### 4b. Über SSH

```
sudo /usr/local/bin/docker load -i /volume1/docker/rh20-checksheet-1.3.2-image.tar.gz

sudo /usr/local/bin/docker run -d --name rh20-checksheet \
  -p 8090:80 \
  -v /volume1/docker/rh20-checksheet/data:/var/www/html/data \
  -e TZ=Europe/Berlin -e PUID=1026 -e PGID=100 \
  --restart unless-stopped \
  ghcr.io/callmetechie/rh20-checksheet:1.3.2
```

`PUID`/`PGID` vorher ermitteln mit
`stat -c '%u %g' /volume1/docker/rh20-checksheet/data`.

Auf Synology liegt das Docker-Binary unter `/usr/local/bin/docker` und ist
nicht im `PATH` von `sudo` — daher der vollständige Pfad.

Alternativ mit der mitgelieferten `docker-compose.yml`:

```
cd /volume1/docker/rh20-checksheet && sudo /usr/local/bin/docker compose up -d
```

Die Datei enthält `pull_policy: never`; nachgemessen mit Compose 2.26.1 meldet
`docker compose pull` damit `Skipped` statt eines Ladeversuchs.

### 4c. Als Projekt im Container Manager — nicht ohne Internetzugang

Legt man die `docker-compose.yml` als **Projekt** an, versucht der Container
Manager das Image trotz `pull_policy` zu ziehen. Die Angabe wirkt für
`docker compose` auf der Kommandozeile, nicht für das Projekt-UI. Der Versuch
scheitert in jedem Fall: die Registry hinter dem Bildnamen ist nicht
öffentlich, das Image wird ausschließlich als Datei ausgeliefert.

Auf einem abgeschotteten System daher 4a oder 4b verwenden.

## 5. Was einzustellen ist

Gilt für alle drei Wege — im Assistenten des Container Managers, als
`-v`/`-e`-Angaben beim `docker run` oder in der `docker-compose.yml`:

- **Datenverzeichnis:** muss auf dem Zielsystem existieren und beschreibbar
  sein; Mount-Pfad im Container ist `/var/www/html/data`. Dort liegt später die
  einzige Datenbankdatei `inspections.sqlite`.
- **`PUID` / `PGID`:** auf Besitzer und Gruppe dieses Verzeichnisses setzen,
  damit die erzeugte Datenbank außerhalb des Containers handhabbar bleibt.
  Ermitteln mit `stat -c '%u %g' <Datenverzeichnis>`. **Nicht 0** — Apache
  verweigert den Start als root, der Container bricht mit einer entsprechenden
  Meldung ab.
- **Port:** der Port auf dem Host, Container-Port ist `80`. 8080 ist auf DSM
  häufig belegt.
- **Zeitzone:** `TZ` bestimmt die Zeitstempel in den Prüfprotokollen.

## 6. Aufrufen

`http://<NAS-IP>:8090`

Die Datenbank wird beim ersten Aufruf automatisch angelegt.

**Die Anwendung hat keinen Login.** Nur in einem vertrauenswürdigen Netz
betreiben, keine Portweiterleitung aus dem Internet.

## Spätere Aktualisierung

Neue Image-Datei einspielen, den Image-Namen in der `docker-compose.yml` auf
die neue Version setzen und `docker compose up -d` ausführen. Das
Datenverzeichnis liegt außerhalb des Containers und bleibt dabei erhalten —
trotzdem vorher eine Kopie von `inspections.sqlite` anlegen.
