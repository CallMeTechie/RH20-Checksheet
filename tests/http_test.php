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
    return [(int)($m[1] ?? 0), $body];
};
$db = fn() => new PDO('sqlite:' . $tmp . '/data/inspections.sqlite');
$own = ["Origin: http://127.0.0.1:$port", 'Sec-Fetch-Site: same-origin'];

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
} finally {
    proc_terminate($proc);
    proc_close($proc);
    exec('rm -rf ' . escapeshellarg($tmp));
}
