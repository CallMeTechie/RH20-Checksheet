# RH20 Kopf-Prüfprotokoll — Container für Synology (DS918+, x86-64)
#
# Basis: offizielles PHP-Apache-Image. PHP 8.3 erfüllt die Mindestanforderung
# der App (8.1+, siehe README Abschnitt 1); pdo_sqlite und sqlite3 sind in
# diesem Image bereits eingebaut und werden im Build verifiziert.
FROM php:8.3-apache

# Zeitzone: die App schreibt Zeitstempel über datetime('now','localtime') in
# SQLite und date('Y-m-d') in PHP. Ohne gesetzte Zeitzone liefe beides in UTC
# und die Protokolle trügen die falsche Uhrzeit.
ENV TZ=Europe/Berlin
RUN set -eux; \
    ln -snf "/usr/share/zoneinfo/$TZ" /etc/localtime; \
    echo "$TZ" > /etc/timezone; \
    printf 'date.timezone=%s\n' "$TZ" > /usr/local/etc/php/conf.d/timezone.ini

# Produktionsnahe PHP-Einstellungen: Fehler ins Log, nicht in die Seite.
RUN set -eux; \
    mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"; \
    { \
      echo 'display_errors=Off'; \
      echo 'log_errors=On'; \
      echo 'error_log=/dev/stderr'; \
    } > "$PHP_INI_DIR/conf.d/rh20.ini"

# Die benötigten Erweiterungen müssen vorhanden sein — sonst soll der Build
# scheitern und nicht erst der erste Seitenaufruf auf der NAS.
RUN set -eux; \
    php -m | grep -qx 'pdo_sqlite'; \
    php -m | grep -qx 'sqlite3'; \
    php -r 'exit(version_compare(PHP_VERSION, "8.1", ">=") ? 0 : 1);'

# Eigene vhost-Konfiguration: sperrt data/ serverseitig. Die data/.htaccess der
# App greift hier nicht, weil AllowOverride im Basisimage auf None steht — die
# Sperre muss deshalb in der Serverkonfiguration stehen, sonst wäre die
# SQLite-Datei über HTTP herunterladbar.
COPY docker/rh20.conf /etc/apache2/sites-available/000-default.conf
RUN set -eux; \
    a2enmod headers; \
    apache2ctl configtest

COPY docker/entrypoint.sh /usr/local/bin/rh20-entrypoint
RUN chmod +x /usr/local/bin/rh20-entrypoint

# Anwendungsdateien (data/ ist über .dockerignore ausgeschlossen und wird zur
# Laufzeit als Volume eingehängt).
COPY --chown=www-data:www-data assets/ /var/www/html/assets/
COPY --chown=www-data:www-data *.php README.md /var/www/html/

# Abbruch, falls eine Anwendungsdatei fehlt oder leer ist. Greift beim Bauen;
# die Prüfung beim Start steht im Entrypoint, weil ein unvollständig
# importiertes Image sonst eine Seite ohne Stylesheet ausliefert, ohne dass ein
# Fehler sichtbar wird.
RUN test -s /var/www/html/assets/style.css \
 && test -s /var/www/html/assets/app.js \
 && test -s /var/www/html/index.php

# Fallback, falls jemand ohne Volume startet: das Verzeichnis existiert dann
# im Container und die App läuft (Daten sind allerdings flüchtig).
RUN set -eux; \
    mkdir -p /var/www/html/data; \
    chown www-data:www-data /var/www/html/data; \
    chmod 775 /var/www/html/data

VOLUME ["/var/www/html/data"]
EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD php -r '$c=stream_context_create(["http"=>["timeout"=>4,"ignore_errors"=>true]]); \
        $r=@file_get_contents("http://127.0.0.1/index.php",false,$c); \
        exit($r !== false && str_contains($r, "RH20") ? 0 : 1);'

LABEL org.opencontainers.image.title="RH20 Kopf-Prüfprotokoll" \
      org.opencontainers.image.description="Erfassung der Fuji-RH20-Kopfprüfung (Internal Head Sensor / Vacuum / Nozzle Cleaning, Shafts A-T)" \
      org.opencontainers.image.licenses="NONE"

ENTRYPOINT ["/usr/local/bin/rh20-entrypoint"]
CMD ["apache2-foreground"]
