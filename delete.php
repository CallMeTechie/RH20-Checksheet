<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';

// Nur per POST: Löschen darf nicht durch einen aufgerufenen Link,
// einen Reload oder einen Link-Prefetch des Browsers ausgelöst werden.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Location: index.php');
    exit;
}
if (isCrossSiteRequest($_SERVER)) {
    http_response_code(403);
    die('Anfrage von einer fremden Seite abgewiesen.');
}

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    // Die Shaft-Zeilen werden ausdrücklich mitgelöscht und nicht dem
    // ON DELETE CASCADE überlassen. Der Cascade greift nur, wenn
    // PRAGMA foreign_keys aktiv ist — das ist pro Verbindung einzustellen und
    // scheitert auf manchen SQLite-Builds still. Ohne diese Zeile bleiben dann
    // verwaiste Messwerte in der Datenbank zurück.
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM syringe_readings WHERE inspection_id = :id')->execute([':id' => $id]);
        $pdo->prepare('DELETE FROM inspections WHERE id = :id')->execute([':id' => $id]);
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('rh20-inspection delete: ' . $e->getMessage());
        http_response_code(500);
        die('Löschen fehlgeschlagen. <a href="index.php">Zurück zur Übersicht</a>');
    }
}

header('Location: index.php');
exit;
