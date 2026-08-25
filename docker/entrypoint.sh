#!/bin/sh
# Startvorbereitung des RH20-Containers.
#
# Der Knackpunkt auf einer Synology: das eingehängte Verzeichnis gehört dem
# DSM-Benutzer (typischerweise UID 1026+), Apache läuft im Container aber als
# www-data (UID 33). Ohne Anpassung kann die App die SQLite-Datei nicht
# anlegen. Entweder wird www-data auf die passende UID gesetzt (PUID/PGID)
# oder das Verzeichnis wird auf www-data umgeschrieben.
set -e

DATA_DIR=/var/www/html/data

# Apache verweigert den Dienst, wenn es als root laufen soll. Ein versehentlich
# gesetztes PUID=0 würde sonst zu einem kryptischen Apache-Syntaxfehler führen.
if [ "${PUID:-}" = "0" ] || [ "${PGID:-}" = "0" ]; then
    echo "[rh20] FEHLER: PUID/PGID darf nicht 0 sein — Apache läuft nicht als root." >&2
    echo "[rh20] UID/GID des Datenverzeichnisses auf der NAS ermitteln:" >&2
    echo "[rh20]   stat -c '%u %g' /volume1/docker/rh20-inspection/data" >&2
    exit 1
fi

if [ -n "$PGID" ]; then
    if [ "$PGID" != "$(id -g www-data)" ]; then
        echo "[rh20] Setze Gruppe www-data auf GID $PGID"
        groupmod -o -g "$PGID" www-data
    fi
fi

if [ -n "$PUID" ]; then
    if [ "$PUID" != "$(id -u www-data)" ]; then
        echo "[rh20] Setze Benutzer www-data auf UID $PUID"
        usermod -o -u "$PUID" www-data
    fi
    # Die Anwendungsdateien müssen dem neuen Benutzer weiterhin gehören.
    chown -R www-data:www-data /var/www/html/assets /var/www/html/*.php 2>/dev/null || true
fi

mkdir -p "$DATA_DIR"

# Besitzverhältnisse nur anfassen, wenn der Webserver sonst nicht schreiben
# kann. Auf einer Synology gehört das eingehängte Verzeichnis dem DSM-Benutzer
# und ist meist ohnehin gruppen-/weltschreibbar — ein vorsorgliches chown würde
# es dann grundlos aus der File Station herauslösen.
if ! su -s /bin/sh -c "test -w '$DATA_DIR'" www-data; then
    echo "[rh20] $DATA_DIR ist nicht beschreibbar — korrigiere Besitzer/Rechte"
    chown -R www-data:www-data "$DATA_DIR" 2>/dev/null \
        || echo "[rh20] Hinweis: chown nicht möglich (Bind-Mount?)."
    chmod 775 "$DATA_DIR" 2>/dev/null || true
fi

# Schreibtest als www-data: lieber jetzt mit klarer Meldung abbrechen, als die
# Techniker später vor einem 500er stehen zu lassen.
if ! su -s /bin/sh -c "test -w '$DATA_DIR'" www-data; then
    echo "[rh20] FEHLER: $DATA_DIR ist für den Webserver nicht beschreibbar." >&2
    echo "[rh20] Auf der Synology das eingehängte Verzeichnis prüfen oder den" >&2
    echo "[rh20] Container mit PUID/PGID des Besitzers starten, z. B.:" >&2
    echo "[rh20]   PUID=\$(stat -c '%u' /volume1/docker/rh20-inspection/data)" >&2
    echo "[rh20]   PGID=\$(stat -c '%g' /volume1/docker/rh20-inspection/data)" >&2
    exit 1
fi

echo "[rh20] Datenverzeichnis bereit: $DATA_DIR (UID $(id -u www-data):$(id -g www-data)), TZ=${TZ:-unset}"

exec "$@"
