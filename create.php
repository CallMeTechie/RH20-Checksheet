<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';

// Nur per POST: ein GET-Link würde beim Vorausladen durch den Browser oder
// bei einem Reload ungewollt leere Prüfungen anlegen.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
if (isCrossSiteRequest($_SERVER)) {
    http_response_code(403);
    die('Anfrage von einer fremden Seite abgewiesen.');
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        "INSERT INTO inspections (head_model, serial_number, inspection_date, technician)
         VALUES ('RH20', '', :dt, '')"
    );
    $stmt->execute([':dt' => date('Y-m-d')]);
    $id = (int)$pdo->lastInsertId();

    // Nozzle Cleaning Pressure wird mit 100 kPa vorbelegt: das Messinstrument
    // zeigt nicht mehr als 100 kPa an, der tatsächliche Druck liegt deutlich
    // darüber. Ein niedrigerer Messwert ist die seltene Ausnahme und wird dann
    // von Hand überschrieben.
    $insSyr = $pdo->prepare(
        'INSERT INTO syringe_readings (inspection_id, syringe, clean_pressure) VALUES (:id, :sy, :cp)'
    );
    foreach (syringeLetters() as $letter) {
        $insSyr->execute([':id' => $id, ':sy' => $letter, ':cp' => CLEAN_PRESSURE_DEFAULT]);
    }
    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('rh20-inspection create: ' . $e->getMessage());
    http_response_code(500);
    die('Prüfung konnte nicht angelegt werden. <a href="index.php">Zurück zur Übersicht</a>');
}

header('Location: edit.php?id=' . $id);
exit;
