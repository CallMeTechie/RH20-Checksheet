<?php
// --- Bewertung: Randfälle sind der eigentliche Test, die Realdaten sind alle 0.00 ---
t_is('0.00 Normalfall',        specState('valve_slide',  0.00), 'ok');
t_is('0.08 obere Grenze',      specState('valve_slide',  0.08), 'ok');
t_is('-0.08 Betrag zählt',     specState('valve_slide', -0.08), 'ok');
t_is('0.09 knapp darüber',     specState('valve_slide',  0.09), 'bad');
t_is('-0.09 knapp darunter',   specState('valve_slide', -0.09), 'bad');
t_is('-0.14 deutlich außen',   specState('valve_slide', -0.14), 'bad');

// Absicherung gegen einen stillen Ausfall: specState() hat einen default => true-Zweig.
// Fehlt der match-Zweig, liefert jeder Wert 'ok' — und die Realdaten (alle 0.00) würden
// das nicht auffallen lassen.
t_is('vergessener match-Zweig fiele hier auf', specState('valve_slide', 0.09), 'bad');

// --- Spaltenaufbau ---
$cols = measurementColumns();
t_is('valve_slide ist definiert',  array_key_exists('valve_slide', $cols), true);
t_is('steht als letzte Spalte',    array_key_last($cols), 'valve_slide');
t_is('Spaltenzahl',                count($cols), 10);
t_is('zwei Nachkommastellen',      $cols['valve_slide']['decimals'], 2);
t_is('Gruppe',                     $cols['valve_slide']['group'], 'Valve Air Stick');
// syringeFields() ist eine Liste (array_keys) — array_key_last() lieferte hier den
// Index 9, nicht den Feldnamen. Deshalb über den letzten Eintrag prüfen:
$fields = syringeFields();
t_is('syringeFields folgt',        $fields[count($fields) - 1], 'valve_slide');

// --- Zeilenbewertung ---
$row = ['ihs_pressure' => 65, 'vac_pressure_z1' => -88, 'vac_flow_z1' => 2.4,
        'vac_pressure_z2' => -87, 'vac_flow_z2' => 2.3, 'vacbreak_pressure' => 12,
        'vacbreak_flow' => 0.9, 'clean_pressure' => 100, 'clean_flow' => 1.1,
        'valve_slide' => 0.0];
t_is('vollständige Zeile mit 0.00', evaluateSyringe($row, 'A'), 'PASS');
t_is('Zeile mit 0.09',              evaluateSyringe(['valve_slide' => 0.09] + $row, 'A'), 'FAIL');
t_is('valve_slide fehlt',           evaluateSyringe(array_diff_key($row, ['valve_slide' => 1]), 'A'), '');

// --- Anzeige ---
t_is('0.00 wird als 0.00 gezeigt', fmtCell(0.0, $cols['valve_slide']['decimals']), '0.00');
t_is('nicht erfasst',              fmtCell(null, $cols['valve_slide']['decimals']), '—');
