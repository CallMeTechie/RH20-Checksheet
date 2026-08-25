<?php
declare(strict_types=1);

// Die SQLite-Datei liegt in data/. Dieses Verzeichnis wird per data/.htaccess
// gegen direkte HTTP-Zugriffe gesperrt; auf nginx-basierten Setups muss die
// entsprechende location-Regel gesetzt werden (siehe README.md, Abschnitt 4).
$dbDir  = __DIR__ . '/data';
$dbFile = $dbDir . '/inspections.sqlite';

if (!is_dir($dbDir)) {
    mkdir($dbDir, 0775, true);
}

try {
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
} catch (PDOException $e) {
    http_response_code(500);
    die('Datenbankverbindung fehlgeschlagen. Prüfen Sie, ob das Verzeichnis "data/" '
        . 'für den Webserver-Benutzer beschreibbar ist. ('
        . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . ')');
}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS inspections (
        id               INTEGER PRIMARY KEY AUTOINCREMENT,
        head_model       TEXT    NOT NULL DEFAULT 'RH20',
        serial_number    TEXT    NOT NULL,
        inspection_date  TEXT    NOT NULL,
        technician       TEXT,
        ihs_pressure     REAL,
        ihs_flow         REAL,
        contact_pressure REAL,
        created_at       TEXT    NOT NULL DEFAULT (datetime('now','localtime')),
        updated_at       TEXT
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS syringe_readings (
        id                 INTEGER PRIMARY KEY AUTOINCREMENT,
        inspection_id      INTEGER NOT NULL,
        syringe            TEXT    NOT NULL,
        ihs_pressure       REAL,
        vac_pressure_z1    REAL,
        vac_flow_z1        REAL,
        vac_pressure_z2    REAL,
        vac_flow_z2        REAL,
        vacbreak_pressure  REAL,
        vacbreak_flow      REAL,
        clean_pressure     REAL,
        clean_flow         REAL,
        remarks            TEXT,
        FOREIGN KEY (inspection_id) REFERENCES inspections(id) ON DELETE CASCADE
    )
");

$syringeCols = array_column($pdo->query('PRAGMA table_info(syringe_readings)')->fetchAll(), 'name');

// Migration 1: Datenbanken mit dem alten Schema (eine einzelne Vacuum-Spalte
// statt Z1/Z2) — neue Spalten ergänzen und bisherige Werte als Z1 übernehmen.
// Z2 bleibt zunächst leer und ist nachzutragen.
if (in_array('vac_pressure', $syringeCols, true) && !in_array('vac_pressure_z1', $syringeCols, true)) {
    $pdo->exec('ALTER TABLE syringe_readings ADD COLUMN vac_pressure_z1 REAL');
    $pdo->exec('ALTER TABLE syringe_readings ADD COLUMN vac_flow_z1 REAL');
    $pdo->exec('ALTER TABLE syringe_readings ADD COLUMN vac_pressure_z2 REAL');
    $pdo->exec('ALTER TABLE syringe_readings ADD COLUMN vac_flow_z2 REAL');
    $pdo->exec('UPDATE syringe_readings SET vac_pressure_z1 = vac_pressure, vac_flow_z1 = vac_flow');
    $syringeCols[] = 'vac_pressure_z1';
}

// Migration 2: Internal Head Sensor Pressure wird nicht mehr nur einmalig an
// Shaft A gemessen, sondern an jedem Shaft A–T. Die Spalte wandert deshalb von
// inspections nach syringe_readings; der bisher einmalig erfasste Wert war die
// Messung an Shaft A und wird genau dorthin übernommen. Die alte Spalte
// inspections.ihs_pressure bleibt unangetastet stehen (SQLite kann Spalten in
// älteren Versionen nicht löschen), wird aber nicht mehr gelesen.
if (!in_array('ihs_pressure', $syringeCols, true)) {
    $pdo->exec('ALTER TABLE syringe_readings ADD COLUMN ihs_pressure REAL');
    $pdo->exec("
        UPDATE syringe_readings
           SET ihs_pressure = (SELECT i.ihs_pressure FROM inspections i WHERE i.id = syringe_readings.inspection_id)
         WHERE syringe = 'A'
    ");
}

// Migration 3: Contact Detection Pressure (70 ± 4 kPa) kam später dazu und
// wird einmal je Kopf über den Internal Head Sensor gemessen. Bestehende
// Prüfungen bekommen die Spalte leer und sind damit als unvollständig
// markiert, bis der Wert nachgetragen ist — es geht nichts verloren.
$inspCols = array_column($pdo->query('PRAGMA table_info(inspections)')->fetchAll(), 'name');
if (!in_array('contact_pressure', $inspCols, true)) {
    $pdo->exec('ALTER TABLE inspections ADD COLUMN contact_pressure REAL');
}

$pdo->exec("CREATE INDEX IF NOT EXISTS idx_syringe_inspection ON syringe_readings(inspection_id)");
