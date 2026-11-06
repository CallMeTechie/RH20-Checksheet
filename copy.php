<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';

// Legt eine Kopie einer Prüfung als neue Prüfung an — Kopfdaten, einmalige
// Kopfmessungen, alle 20 Shaft-Zeilen und den Merker "Valve Air Stick
// übersprungen". Das Datum wird auf heute gesetzt: die Kopie ist ein neuer
// Vorgang. Aufgerufen über das Kontextmenü am Bearbeiten-Knopf der
// Berichtsansicht; nur per POST, aus denselben Gründen wie create.php.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
if (isCrossSiteRequest($_SERVER)) {
    http_response_code(403);
    die('Anfrage von einer fremden Seite abgewiesen.');
}

$id = (int)($_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM inspections WHERE id = :id');
$stmt->execute([':id' => $id]);
$src = $stmt->fetch();
if (!$src) {
    http_response_code(404);
    die(h(t('not_found')) . ' <a href="index.php">' . h(t('back_to_overview')) . '</a>');
}

$fields = syringeFields();
$pdo->beginTransaction();
try {
    $pdo->prepare(
        "INSERT INTO inspections (head_model, serial_number, inspection_date, technician,
                                  contact_pressure, ihs_flow, valve_skipped)
         VALUES (:hm, :sn, :dt, :te, :cp, :fl, :vs)"
    )->execute([
        ':hm' => $src['head_model'], ':sn' => $src['serial_number'], ':dt' => date('Y-m-d'),
        ':te' => $src['technician'], ':cp' => $src['contact_pressure'], ':fl' => $src['ihs_flow'],
        ':vs' => $src['valve_skipped'],
    ]);
    $newId = (int)$pdo->lastInsertId();

    // Spaltennamen stammen aus measurementColumns(), nicht aus der Anfrage.
    $cols = implode(', ', $fields);
    $pdo->prepare(
        "INSERT INTO syringe_readings (inspection_id, syringe, $cols)
         SELECT :new, syringe, $cols FROM syringe_readings WHERE inspection_id = :src"
    )->execute([':new' => $newId, ':src' => $id]);
    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('rh20-inspection copy: ' . $e->getMessage());
    http_response_code(500);
    die('Kopie konnte nicht angelegt werden. <a href="index.php">Zurück zur Übersicht</a>');
}

header('Location: edit.php?id=' . $newId);
exit;
