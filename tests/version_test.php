<?php
// Die Version steht im Footer (APP_VERSION) und als Image-Tag in Compose und Doku.
// Beim Release müssen alle Stellen zusammen angehoben werden.
$root = dirname(__DIR__);
t_is('APP_VERSION ist gesetzt', defined('APP_VERSION') && preg_match('/^\d+\.\d+\.\d+$/', APP_VERSION) === 1, true);
preg_match('#image:\s*ghcr\.io/callmetechie/rh20-checksheet:(\S+)#', file_get_contents($root . '/docker-compose.yml'), $m);
t_is('docker-compose.yml nennt dieselbe Version', $m[1] ?? null, defined('APP_VERSION') ? APP_VERSION : 'APP_VERSION fehlt');
foreach (['README.md', 'docs/OFFLINE-INSTALL.md'] as $doc) {
    preg_match_all('#rh20-checksheet[-:](\d+\.\d+\.\d+)#', file_get_contents($root . '/' . $doc), $all);
    t_is("$doc nennt nur diese Version", array_values(array_unique($all[1])), defined('APP_VERSION') ? [APP_VERSION] : []);
}
