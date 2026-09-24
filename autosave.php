<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';

function fail(int $code, string $msg): never
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail(405, 'method_not_allowed');
}
if (isCrossSiteRequest($_SERVER)) {
    fail(403, 'cross_site_request');
}

$id    = (int)($_POST['id'] ?? 0);
$scope = (string)($_POST['scope'] ?? '');
$field = (string)($_POST['field'] ?? '');
$value = $_POST['value'] ?? '';

if ($id <= 0) {
    fail(400, 'invalid_id');
}

$stmtInsp = $pdo->prepare('SELECT * FROM inspections WHERE id = :id');
$stmtInsp->execute([':id' => $id]);
if (!$stmtInsp->fetch()) {
    fail(404, 'not_found');
}

// Messfelder einer Syringe-Zeile: siehe measurementColumns().
$numericFields = syringeFields();

/**
 * Numerische Eingabe prüfen und in float|null wandeln.
 * Ein nicht interpretierbarer Wert wird NICHT als Leerwert gespeichert,
 * sondern abgewiesen — sonst verschwindet ein Tippfehler wie "8O" still
 * aus dem Protokoll, während die Oberfläche "Gespeichert" meldet.
 */
function numericOrFail(mixed $value): ?float
{
    $state = numericInputState($value);
    if ($state === 'invalid') {
        fail(422, 'invalid_number');
    }
    return $state === 'empty' ? null : nz($value);
}

$letter = '';

try {
    if ($scope === 'header') {
        $allowed = ['head_model', 'serial_number', 'inspection_date', 'technician'];
        if (!in_array($field, $allowed, true)) {
            fail(400, 'invalid_field');
        }
        $val = trim((string)$value);
        if ($field === 'head_model' && $val === '') {
            $val = 'RH20';
        }
        $stmt = $pdo->prepare("UPDATE inspections SET {$field} = :v, updated_at = datetime('now','localtime') WHERE id = :id");
        // Leer als '' speichern, nicht als NULL: serial_number und
        // inspection_date sind NOT NULL, ein NULL scheiterte mit db_error und
        // ließ den alten Wert stehen, während das Feld leer aussah.
        $stmt->execute([':v' => $val, ':id' => $id]);

    } elseif ($scope === 'head') {
        // Einmalige Messungen je Kopf: Contact Detection Pressure und die
        // Durchflussmessung des Internal Head Sensors. Der IHS-*Druck* liegt
        // dagegen je Shaft in syringe_readings.
        if (!array_key_exists($field, headMeasurements())) {
            fail(400, 'invalid_field');
        }
        $num = numericOrFail($value);
        $stmt = $pdo->prepare("UPDATE inspections SET {$field} = :v, updated_at = datetime('now','localtime') WHERE id = :id");
        $stmt->execute([':v' => $num, ':id' => $id]);

    } elseif ($scope === 'syringe') {
        $letter = (string)($_POST['syringe'] ?? '');
        if (!in_array($letter, syringeLetters(), true)) {
            fail(400, 'invalid_syringe');
        }
        if (in_array($field, $numericFields, true)) {
            $num = numericOrFail($value);
            $stmt = $pdo->prepare("UPDATE syringe_readings SET {$field} = :v WHERE inspection_id = :id AND syringe = :sy");
            $stmt->execute([':v' => $num, ':id' => $id, ':sy' => $letter]);
        } else {
            fail(400, 'invalid_field');
        }
        $pdo->prepare("UPDATE inspections SET updated_at = datetime('now','localtime') WHERE id = :id")->execute([':id' => $id]);

    } else {
        fail(400, 'invalid_scope');
    }
} catch (PDOException $e) {
    // Interne Details ins PHP-Error-Log, nicht in die Antwort (kein Pfad-Leak).
    error_log('rh20-inspection autosave: ' . $e->getMessage());
    fail(500, 'db_error');
}

// Aktuellen Zustand neu laden und Gesamtergebnis + betroffene Zelle
// zurückgeben, damit die Seite ohne Reload aktuell bleibt.
$stmtInsp->execute([':id' => $id]);
$insp = $stmtInsp->fetch();

$stmt2 = $pdo->prepare('SELECT * FROM syringe_readings WHERE inspection_id = :id');
$stmt2->execute([':id' => $id]);
$byLetter = [];
foreach ($stmt2->fetchAll() as $r) {
    $byLetter[$r['syringe']] = $r;
}

$eval = evaluateInspection($insp, $byLetter);

$response = [
    'ok'      => true,
    'summary' => [
        'status'  => $eval['status'],
        'pass'    => $eval['pass'],
        'warn'    => $eval['warn'],
        'fail'    => $eval['fail'],
        'pending' => $eval['pending'],
        'total'   => $eval['total'],
        'text'    => summaryVerdict($eval),
    ],
    'head_results' => $eval['head_results'],
];

if ($scope === 'syringe') {
    $row = $byLetter[$letter] ?? [];
    $response['row_result'] = evaluateSyringe($row, $letter);
    $response['syringe']    = $letter;
    $response['field']      = $field;
    // Die Prüfung ist hier defensiv, nicht tragend: scope=syringe akzeptiert
    // ohnehin nur Felder aus $numericFields, alles andere scheitert oben schon
    // mit 400. Der eigentliche Grund für die Fallunterscheidung ist der
    // head-Zweig unten, der Value/Class eigenständig behandelt.
    if (in_array($field, $numericFields, true)) {
        $num = ($row[$field] ?? null) !== null ? (float)$row[$field] : null;
        $response['cell_class'] = cellClass($num, $field, $letter);
        $response['value']      = fmtNum($num);
    }
} elseif ($scope === 'head') {
    $v = ($insp[$field] ?? null) !== null ? (float)$insp[$field] : null;
    $response['cell_class'] = cellClass($v, $field);
    $response['field']      = $field;
    $response['value']      = fmtNum($v);
}

echo json_encode($response);
