<?php
// Migration 5 auf einer Datenbank im Stand von 1.3.2 (ohne valve_skipped).
$tmp = sys_get_temp_dir() . '/rh20-mig-' . bin2hex(random_bytes(4));
mkdir($tmp . '/data', 0777, true);
copy(dirname(__DIR__) . '/db.php', $tmp . '/db.php');
$old = new PDO('sqlite:' . $tmp . '/data/inspections.sqlite');
$old->exec("CREATE TABLE inspections (id INTEGER PRIMARY KEY AUTOINCREMENT, head_model TEXT NOT NULL DEFAULT 'RH20',
            serial_number TEXT NOT NULL, inspection_date TEXT NOT NULL, technician TEXT, ihs_pressure REAL, ihs_flow REAL,
            contact_pressure REAL, created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')), updated_at TEXT)");
$old->exec("CREATE TABLE syringe_readings (id INTEGER PRIMARY KEY AUTOINCREMENT, inspection_id INTEGER NOT NULL, syringe TEXT NOT NULL,
            ihs_pressure REAL, vac_pressure_z1 REAL, vac_flow_z1 REAL, vac_pressure_z2 REAL, vac_flow_z2 REAL,
            vacbreak_pressure REAL, vacbreak_flow REAL, clean_pressure REAL, clean_flow REAL, valve_slide REAL, remarks TEXT)");
$old->exec("INSERT INTO inspections (serial_number, inspection_date) VALUES ('ALT', '2026-01-01')");
$old->exec("INSERT INTO syringe_readings (inspection_id, syringe, valve_slide) VALUES (1, 'A', 0.01)");
$old = null;

exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg('require ' . var_export($tmp . '/db.php', true) . ';') . ' 2>&1', $out, $rc);
t_is('Migration läuft fehlerfrei', [$rc, $out], [0, []]);
$db = new PDO('sqlite:' . $tmp . '/data/inspections.sqlite');
t_is('bestehende Prüfung gilt als nicht übersprungen',
    $db->query('SELECT valve_skipped FROM inspections WHERE id = 1')->fetchColumn(), 0);
t_is('bestehender Messwert bleibt', (float)$db->query('SELECT valve_slide FROM syringe_readings')->fetchColumn(), 0.01);
exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg('require ' . var_export($tmp . '/db.php', true) . ';') . ' 2>&1', $out2, $rc2);
t_is('zweiter Lauf ändert nichts', $rc2, 0);
$db = null;
exec('rm -rf ' . escapeshellarg($tmp));
