<?php
t_is('gemessene 0 ist nicht "nicht erfasst"', fmtCell(0.0), '0');
t_is('nicht erfasst', fmtCell(null), '—');
t_is('negativer Wert', fmtCell(-0.06), '-0.06');
t_is('gewöhnlicher Messwert', fmtCell(88.0), '88');

// Der Fehler in seiner ursprünglichen Form — als Beleg, dass er real war
t_is('alte Schreibweise lieferte fälschlich —', fmtNum(0.0) ?: '—', '—');
t_is('fmtNum(0.0) selbst ist korrekt', fmtNum(0.0), '0');

// Die Bemerkungsmechanik ist entfernt
t_is('remarkFootnotes() existiert nicht mehr', function_exists('remarkFootnotes'), false);
t_is('textLength() existiert nicht mehr',      function_exists('textLength'),      false);
t_is('REMARK_INLINE_MAX ist weg',              defined('REMARK_INLINE_MAX'),       false);
t_is('remarks ist kein Messfeld',              in_array('remarks', syringeFields(), true), false);
