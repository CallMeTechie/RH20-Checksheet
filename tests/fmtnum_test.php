<?php
// Auffüllen
t_is('0.0 auf 2 Stellen',      fmtNum(0.0, 2),    '0.00');
t_is('-0.06 auf 2 Stellen',    fmtNum(-0.06, 2),  '-0.06');
t_is('0.30 auf 2 Stellen',     fmtNum(0.30, 2),   '0.30');
t_is('0.1 auf 2 Stellen',      fmtNum(0.1, 2),    '0.10');
t_is('-0.0 ergibt kein -0.00', fmtNum(-0.0, 2),   '0.00');
t_is('null bleibt leer',       fmtNum(null, 2),   '');

// Nie runden — der eigentliche Grund für diese Aufgabe
t_is('0.065 wird nicht auf 0.07 gerundet',   fmtNum(0.065, 2),  '0.065');
t_is('-0.064 wird nicht auf -0.06 gerundet', fmtNum(-0.064, 2), '-0.064');

// Altverhalten unverändert
t_is('88.0 ohne Angabe',   fmtNum(88.0),   '88');
t_is('3.456 ohne Angabe',  fmtNum(3.456),  '3.456');
t_is('-88.0 ohne Angabe',  fmtNum(-88.0),  '-88');
t_is('0.0 ohne Angabe',    fmtNum(0.0),    '0');
t_is('0.8 ohne Angabe',    fmtNum(0.8),    '0.8');
t_is('null ohne Angabe',   fmtNum(null),   '');

// fmtCell reicht den Parameter durch
t_is('fmtCell mit Stellen',        fmtCell(0.0, 2),  '0.00');
t_is('fmtCell null trotz Stellen', fmtCell(null, 2), '—');
