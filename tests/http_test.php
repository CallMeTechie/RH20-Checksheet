<?php
// Durchlauf über HTTP gegen den eingebauten PHP-Server, auf einer Wegwerfkopie
// der Anwendung mit eigener, leerer Datenbank.
$root = dirname(__DIR__);
$tmp  = sys_get_temp_dir() . '/rh20-http-' . bin2hex(random_bytes(4));
mkdir($tmp . '/assets', 0777, true);
foreach (glob($root . '/*.php') as $f)        { copy($f, $tmp . '/' . basename($f)); }
foreach (glob($root . '/assets/*') as $f)     { copy($f, $tmp . '/assets/' . basename($f)); }

$port = 18300 + random_int(0, 600);
$proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $tmp],
    [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) { usleep(100000); }

$post = function (string $path, array $data, array $headers = []) use ($port): array {
    $ctx = stream_context_create(['http' => [
        'method' => 'POST', 'ignore_errors' => true, 'follow_location' => 0,
        'header' => array_merge(['Content-Type: application/x-www-form-urlencoded'], $headers),
        'content' => http_build_query($data),
    ]]);
    $body = file_get_contents("http://127.0.0.1:$port/$path", false, $ctx);
    preg_match('#^HTTP/\S+ (\d+)#', $http_response_header[0] ?? '', $m);
    return [(int)($m[1] ?? 0), $body, $http_response_header];
};
$get = function (string $path) use ($port): array {
    $ctx = stream_context_create(['http' => ['ignore_errors' => true, 'follow_location' => 0]]);
    $body = file_get_contents("http://127.0.0.1:$port/$path", false, $ctx);
    preg_match('#^HTTP/\S+ (\d+)#', $http_response_header[0] ?? '', $m);
    return [(int)($m[1] ?? 0), $http_response_header, $body];
};
$db = fn() => new PDO('sqlite:' . $tmp . '/data/inspections.sqlite');
$own = ["Origin: http://127.0.0.1:$port", 'Sec-Fetch-Site: same-origin'];
$location = function (array $headers): string {
    foreach ($headers as $h) {
        if (stripos($h, 'Location:') === 0) return trim(substr($h, 9));
    }
    return '';
};

try {
    [$code] = $post('create.php', [], $own);
    t_is('Anlegen von der eigenen Seite', $code, 302);

    // Fehler 2: Seriennummer und Datum leeren
    $post('autosave.php', ['id' => 1, 'scope' => 'header', 'field' => 'serial_number', 'value' => 'ABC'], $own);
    [$code, $body] = $post('autosave.php', ['id' => 1, 'scope' => 'header', 'field' => 'serial_number', 'value' => ''], $own);
    t_is('Seriennummer leeren wird gespeichert', $code, 200);
    t_is('Seriennummer ist danach wirklich leer',
        $db()->query('SELECT serial_number FROM inspections WHERE id = 1')->fetchColumn(), '');
    [$code] = $post('autosave.php', ['id' => 1, 'scope' => 'header', 'field' => 'inspection_date', 'value' => ''], $own);
    t_is('Datum leeren wird gespeichert', $code, 200);

    // Geleertes Head Model wird als RH20 gespeichert — die Antwort muss den
    // gespeicherten Wert nennen, sonst bleibt das Feld in der Maske leer.
    [$code, $body] = $post('autosave.php', ['id' => 1, 'scope' => 'header', 'field' => 'head_model', 'value' => ''], $own);
    t_is('Head Model leeren: Antwort nennt den gespeicherten Wert', json_decode($body, true)['value'] ?? null, 'RH20');
    [$code, $body] = $post('autosave.php', ['id' => 1, 'scope' => 'header', 'field' => 'technician', 'value' => '  Meier '], $own);
    t_is('Kopffeld: Antwort nennt den getrimmten Wert', json_decode($body, true)['value'] ?? null, 'Meier');

    // Ein aufgerufener Lösch-Link führt zurück zur Übersicht statt auf eine leere Seite.
    [$code, $headers] = $get('delete.php?id=1');
    t_is('GET auf delete.php leitet weiter', $code, 302);
    t_is('GET auf delete.php löscht nichts',
        (int)$db()->query('SELECT COUNT(*) FROM inspections')->fetchColumn(), 1);

    // Fehler 1: Anfragen fremder Seiten
    $evil = ['Origin: https://evil.example', 'Sec-Fetch-Site: cross-site'];
    [$code] = $post('delete.php', ['id' => 1], $evil);
    t_is('Löschen von fremder Seite abgewiesen', $code, 403);
    t_is('Prüfung existiert danach noch',
        (int)$db()->query('SELECT COUNT(*) FROM inspections')->fetchColumn(), 1);

    [$code, $body] = $post('autosave.php', ['id' => 1, 'scope' => 'header', 'field' => 'technician', 'value' => 'X'], $evil);
    t_is('Überschreiben von fremder Seite abgewiesen', $code, 403);
    t_is('Antwort nennt den Grund', json_decode($body, true)['error'] ?? null, 'cross_site_request');

    [$code] = $post('create.php', [], $evil);
    t_is('Anlegen von fremder Seite abgewiesen', $code, 403);
    t_is('keine zusätzliche Prüfung angelegt',
        (int)$db()->query('SELECT COUNT(*) FROM inspections')->fetchColumn(), 1);

    [$code] = $post('delete.php', ['id' => 1], $own);
    t_is('Löschen von der eigenen Seite funktioniert weiter', $code, 302);
    t_is('Prüfung ist gelöscht',
        (int)$db()->query('SELECT COUNT(*) FROM inspections')->fetchColumn(), 0);

    // --- Valve Air Stick überspringen ---
    $post('create.php', [], $own);
    $iid = (int)$db()->query('SELECT MAX(id) FROM inspections')->fetchColumn();
    $valve = fn(int $id) => $db()->query("SELECT valve_slide FROM syringe_readings WHERE inspection_id = $id ORDER BY syringe")
        ->fetchAll(PDO::FETCH_COLUMN);
    t_is('neue Prüfung: Valve Air Stick nicht übersprungen',
        (int)$db()->query("SELECT valve_skipped FROM inspections WHERE id = $iid")->fetchColumn(), 0);

    [$code] = $post('autosave.php', ['id' => $iid, 'scope' => 'syringe', 'syringe' => 'C', 'field' => 'valve_slide', 'value' => '0.05'], $own);
    t_is('Valve-Messwert ohne Überspringen wird gespeichert', [$code, $valve($iid)[2]], [200, 0.05]);
    [$code, $body] = $post('autosave.php', ['id' => $iid, 'scope' => 'valve_skip', 'value' => '1'], $own);
    t_is('Überspringen wird gespeichert', $code, 200);
    t_is('Merker ist gesetzt',
        (int)$db()->query("SELECT valve_skipped FROM inspections WHERE id = $iid")->fetchColumn(), 1);
    t_is('alle 20 Felder tragen den Standardwert 0.03', array_unique($valve($iid)), [0.03]);
    t_is('es sind genau 20 Felder', count($valve($iid)), 20);
    $resp = json_decode($body, true);
    t_is('Antwort liefert das Ergebnis aller 20 Zeilen', count($resp['row_results'] ?? []), 20);
    t_is('Antwort nennt den Anzeigewert', $resp['value'] ?? null, '0.03');
    t_is('Antwort nennt die Zellbewertung', $resp['cell_class'] ?? null, 'ok');

    [$code, $body] = $post('autosave.php', ['id' => $iid, 'scope' => 'syringe', 'syringe' => 'C', 'field' => 'valve_slide', 'value' => '0.07'], $own);
    t_is('Messwert bei übersprungener Messung abgewiesen', $code, 409);
    t_is('Antwort nennt den Grund (Überspringen)', json_decode($body, true)['error'] ?? null, 'valve_skipped');
    t_is('Standardwert bleibt stehen', $valve($iid)[2], 0.03);

    [$code] = $post('autosave.php', ['id' => $iid, 'scope' => 'valve_skip', 'value' => '0'], $own);
    t_is('Zurücknehmen wird gespeichert', $code, 200);
    t_is('Zurücknehmen leert alle 20 Felder', array_unique($valve($iid)), [null]);
    t_is('Merker ist zurückgesetzt',
        (int)$db()->query("SELECT valve_skipped FROM inspections WHERE id = $iid")->fetchColumn(), 0);
    [$code] = $post('autosave.php', ['id' => $iid, 'scope' => 'valve_skip', 'value' => 'ja'], $own);
    t_is('ungültiger Wert für Überspringen abgewiesen', $code, 400);
    [$code] = $post('autosave.php', ['id' => $iid, 'scope' => 'valve_skip', 'value' => '1'], $evil);
    t_is('Überspringen von fremder Seite abgewiesen', $code, 403);

    // --- Kopie als neue Prüfung ---
    $hdr = ['serial_number' => 'SN-4711', 'technician' => 'Meier', 'inspection_date' => '2020-01-02', 'head_model' => 'RH20X'];
    foreach ($hdr as $f => $v) {
        $post('autosave.php', ['id' => $iid, 'scope' => 'header', 'field' => $f, 'value' => $v], $own);
    }
    $post('autosave.php', ['id' => $iid, 'scope' => 'head', 'field' => 'contact_pressure', 'value' => '70.5'], $own);
    $post('autosave.php', ['id' => $iid, 'scope' => 'head', 'field' => 'ihs_flow', 'value' => '3.9'], $own);
    $post('autosave.php', ['id' => $iid, 'scope' => 'syringe', 'syringe' => 'A', 'field' => 'ihs_pressure', 'value' => '64.8'], $own);
    $post('autosave.php', ['id' => $iid, 'scope' => 'syringe', 'syringe' => 'T', 'field' => 'vac_flow_z2', 'value' => '2.25'], $own);
    $post('autosave.php', ['id' => $iid, 'scope' => 'valve_skip', 'value' => '1'], $own);

    $countInsp = fn() => (int)$db()->query('SELECT COUNT(*) FROM inspections')->fetchColumn();
    $before = $countInsp();
    [$code, , $headers] = $post('copy.php', ['id' => $iid], $own);
    $newId = (int)$db()->query('SELECT MAX(id) FROM inspections')->fetchColumn();
    t_is('Kopieren leitet weiter', $code, 302);
    t_is('Kopie ist eine zusätzliche Prüfung', $countInsp(), $before + 1);
    t_is('Weiterleitung in die Erfassung der Kopie', $location($headers), 'edit.php?id=' . $newId);

    $insp = fn(int $id) => $db()->query("SELECT head_model, serial_number, technician, contact_pressure, ihs_flow, valve_skipped
                                         FROM inspections WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
    t_is('Kopf- und Kopfmessdaten übernommen', $insp($newId), $insp($iid));
    t_is('Kopie trägt das heutige Datum',
        $db()->query("SELECT inspection_date FROM inspections WHERE id = $newId")->fetchColumn(), date('Y-m-d'));
    $cols = 'syringe, ' . implode(', ', syringeFields());
    $rows = fn(int $id) => $db()->query("SELECT $cols FROM syringe_readings WHERE inspection_id = $id ORDER BY syringe")->fetchAll(PDO::FETCH_ASSOC);
    t_is('alle 20 Shaft-Zeilen samt Messwerten übernommen', $rows($newId), $rows($iid));
    t_is('Original bleibt unverändert erhalten',
        $db()->query("SELECT inspection_date FROM inspections WHERE id = $iid")->fetchColumn(), '2020-01-02');

    [$code] = $post('copy.php', ['id' => $iid], $evil);
    t_is('Kopieren von fremder Seite abgewiesen', $code, 403);
    [$code] = $post('copy.php', ['id' => 99999], $own);
    t_is('Kopieren einer unbekannten Prüfung: 404', $code, 404);
    [$code] = $get('copy.php?id=' . $iid);
    t_is('GET auf copy.php legt nichts an', [$code, $countInsp()], [302, $before + 1]);

    // --- Seitenaufbau ---
    [, , $view] = $get('view.php?id=' . $iid);
    [, , $edit] = $get('edit.php?id=' . $iid);
    t_is('Bericht bietet das Kopiermenü an', str_contains($view, 'action="copy.php"'), true);
    t_is('Erfassung bietet kein Kopiermenü an', str_contains($edit, 'action="copy.php"'), false);
    t_is('Bearbeiten-Knopf im Bericht ohne Hinweis aufs Kopieren',
        (bool)preg_match('#<a href="edit\.php\?id=' . $iid . '"[^>]*title="Edit"[^>]*data-copy-menu#', $view), true);
    t_is('Bericht vermerkt die übersprungene Messung', str_contains($view, 'id="valve-skip-note"'), true);
    t_is('Erfassung: Checkbox ist gesetzt', (bool)preg_match('/id="valve-skip"[^>]*\bchecked\b/', $edit), true);
    t_is('Erfassung: Valve-Felder sind schreibgeschützt',
        preg_match_all('/data-field="valve_slide"[^>]*\breadonly\b/', $edit), 20);
    t_is('Erfassung: kein Done-Knopf mehr', str_contains($edit, 'btn-done') || str_contains($edit, 'Done — back'), false);
    t_is('Erfassung: Drucken steht in der Kopfleiste',
        (bool)preg_match('#class="topbar.*?view\.php\?id=' . $iid . '&amp;print=1#s', $edit), true);
    [, , $view2] = $get('view.php?id=' . $iid . '&print=1');
    t_is('Bericht mit print=1 startet den Druck', str_contains($view2, 'data-autoprint="1"'), true);
    t_is('Bericht ohne print=1 startet keinen Druck', str_contains($view, 'data-autoprint'), false);
    // Der Bericht selbst verlinkt nirgends mit print=1 — ein Treffer stammt aus dem Sprachumschalter.
    t_is('Sprachumschalter öffnet den Druck nicht erneut', preg_match('#href="[^"]*print=1#', $view2), 0);

    // --- Footer und Asset-Versionen auf allen Seiten ---
    [, , $index] = $get('index.php');
    $hash = fn(string $f) => substr(md5_file($root . '/assets/' . $f), 0, 10);
    foreach (['Übersicht' => $index, 'Erfassung' => $edit, 'Bericht' => $view] as $name => $html) {
        t_is("$name: style.css mit Inhalts-Version", str_contains($html, 'assets/style.css?v=' . $hash('style.css')), true);
        t_is("$name: app.js mit Inhalts-Version", str_contains($html, 'assets/app.js?v=' . $hash('app.js')), true);
        t_is("$name: Footer vorhanden", substr_count($html, '<footer class="site-footer'), 1);
        t_is("$name: Footer verlinkt LinkedIn",
            str_contains($html, 'href="https://www.linkedin.com/in/ma-backes/"'), true);
        t_is("$name: Footer zeigt die Version", str_contains($html, 'v' . APP_VERSION), true);
        t_is("$name: Footer zeigt das Fuji-Logo",
            str_contains($html, 'src="assets/fuji_logo_rgb.webp?v=' . $hash('fuji_logo_rgb.webp') . '"'), true);
    }
} finally {
    proc_terminate($proc);
    proc_close($proc);
    exec('rm -rf ' . escapeshellarg($tmp));
}
