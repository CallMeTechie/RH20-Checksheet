<?php
$h = ['HTTP_HOST' => 'nas.firma.local:8090'];

t_is('eigene Seite (Sec-Fetch-Site)',        isCrossSiteRequest($h + ['HTTP_SEC_FETCH_SITE' => 'same-origin']), false);
t_is('direkt aufgerufen (none)',              isCrossSiteRequest($h + ['HTTP_SEC_FETCH_SITE' => 'none']), false);
t_is('fremde Webseite (cross-site)',          isCrossSiteRequest($h + ['HTTP_SEC_FETCH_SITE' => 'cross-site']), true);
t_is('anderer Dienst auf der NAS (same-site)', isCrossSiteRequest($h + ['HTTP_SEC_FETCH_SITE' => 'same-site']), true);
t_is('Sec-Fetch-Site hat Vorrang vor Origin',
    isCrossSiteRequest($h + ['HTTP_SEC_FETCH_SITE' => 'cross-site', 'HTTP_ORIGIN' => 'http://nas.firma.local:8090']), true);

t_is('Origin passt',                 isCrossSiteRequest($h + ['HTTP_ORIGIN' => 'http://nas.firma.local:8090']), false);
t_is('Origin passt, Großschreibung', isCrossSiteRequest($h + ['HTTP_ORIGIN' => 'http://NAS.firma.local:8090']), false);
t_is('Origin fremde Domain',         isCrossSiteRequest($h + ['HTTP_ORIGIN' => 'https://evil.example']), true);
t_is('Origin gleicher Host, anderer Port', isCrossSiteRequest($h + ['HTTP_ORIGIN' => 'http://nas.firma.local:5000']), true);
t_is('Origin: null',                 isCrossSiteRequest($h + ['HTTP_ORIGIN' => 'null']), true);
t_is('Origin mit IP-Adresse',
    isCrossSiteRequest(['HTTP_HOST' => '10.0.0.5:8090', 'HTTP_ORIGIN' => 'http://10.0.0.5:8090']), false);

t_is('kein Browser-Kontext (curl)',  isCrossSiteRequest($h), false);
