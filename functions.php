<?php
declare(strict_types=1);

require_once __DIR__ . '/lang.php';

/* ---------------------------------------------------------------------------
 * Prüfvorgaben Internal Head Sensor (Nozzle Cleaning, gemessen je Shaft)
 *
 * Shaft A ist der Referenz-Shaft: an ihm wird der Kopf auf 65 kPa eingemessen,
 * er muss die enge Justagetoleranz von ±2.5 kPa einhalten.
 *
 * Alle übrigen Shafts B–T dürfen vom Zielwert 65 kPa um bis zu ±10 kPa
 * abweichen. Ab einer Abweichung von mehr als ±5 kPa ist der Wert zwar noch
 * zulässig, aber grenzwertig — er wird am Bildschirm und im Ausdruck gelb
 * markiert. Über ±10 kPa gilt der Shaft als durchgefallen.
 * ------------------------------------------------------------------------- */
const IHS_TARGET     = 65.0;  // Zielwert in kPa
const IHS_TOL_REF    = 2.5;   // Justagetoleranz Referenz-Shaft
const IHS_TOL_WARN   = 5.0;   // bis hier unauffällig, darüber grenzwertig (gelb)
const IHS_TOL_MAX    = 10.0;  // darüber FAIL
const IHS_REF_SHAFT  = 'A';   // Shaft, an dem eingemessen wird

/* ---------------------------------------------------------------------------
 * Contact Detection Pressure — wird ebenfalls über den Internal Head Sensor
 * gemessen, aber nur einmal je Kopf (nicht je Shaft). Sollwert 70 kPa mit
 * einer Toleranz von ±4 kPa; keine Grenzwertzone, der Wert ist binär.
 * ------------------------------------------------------------------------- */
const CONTACT_TARGET = 70.0;
const CONTACT_TOL    = 4.0;

/* ---------------------------------------------------------------------------
 * Valve Air Stick — Repeated sliding test. Gemessen wird je Shaft, wie stark die
 * Rückkehrposition des Valve Air nach zehn Durchgängen streut. Bewertet wird der
 * Betrag; die Toleranz ist symmetrisch, das Vorzeichen wird nur mitgeschrieben,
 * weil die Messuhr es anzeigt. 0.00 ist der Normalfall, nicht die Ausnahme.
 * ------------------------------------------------------------------------- */
const VALVE_SLIDE_MAX = 0.08;  // mm, Betrag, Grenze eingeschlossen

/**
 * Vorbelegung für Nozzle Cleaning Pressure. Das Messinstrument zeigt maximal
 * 100 kPa an, der reale Druck liegt deutlich höher — der Wert ist deshalb in
 * aller Regel genau 100 und wird nur im Ausnahmefall überschrieben.
 */
const CLEAN_PRESSURE_DEFAULT = 100.0;

/** Toleranz für Fließkommavergleiche an den Grenzwerten (z. B. exakt 75.0 kPa). */
const SPEC_EPS = 1e-9;

/** Liste aller Syringe-/Shaft-Positionen A bis T (20 Stück). */
function syringeLetters(): array
{
    return range('A', 'T');
}

/**
 * Die Messspalten einer Syringe-Zeile — zugleich die Eingabe-Reihenfolge
 * (Enter läuft je Spalte A→T durch und springt dann zur nächsten Spalte).
 *
 * Einzige Quelle für Spaltenaufbau, Beschriftung und Spezifikationstext;
 * edit.php, view.php und assets/app.js leiten sich davon ab.
 */
function measurementColumns(): array
{
    return [
        'ihs_pressure' => [
            'group' => 'Internal Head Sensor',
            'label' => 'Pressure (kPa)',
            'spec'  => 'A: 65 ± 2.5 · B–T: 65 ± 10',
        ],
        'vac_pressure_z1' => ['group' => 'Vacuum — Z1', 'label' => 'Pressure (kPa)', 'spec' => '|Wert| ≥ 80'],
        'vac_flow_z1'     => ['group' => 'Vacuum — Z1', 'label' => 'Flow (L/min)',   'spec' => '≥ 2.0'],
        'vac_pressure_z2' => ['group' => 'Vacuum — Z2', 'label' => 'Pressure (kPa)', 'spec' => '|Wert| ≥ 80'],
        'vac_flow_z2'     => ['group' => 'Vacuum — Z2', 'label' => 'Flow (L/min)',   'spec' => '≥ 2.0'],
        'vacbreak_pressure' => ['group' => 'Vacuum Break Down', 'label' => 'Pressure (kPa)', 'spec' => '10 – 15'],
        'vacbreak_flow'     => ['group' => 'Vacuum Break Down', 'label' => 'Flow (L/min)',   'spec' => '≥ 0.5'],
        'clean_pressure'    => ['group' => 'Nozzle Cleaning',   'label' => 'Pressure (kPa)', 'spec' => '≥ 100'],
        'clean_flow'        => ['group' => 'Nozzle Cleaning',   'label' => 'Flow (L/min)',   'spec' => '≥ 0.8'],
        'valve_slide' => ['group' => 'Valve Air Stick', 'label' => 'Repeated Sliding (mm)',
                          'spec'  => '|Wert| ≤ 0.08', 'decimals' => 2],
    ];
}

/**
 * Messungen, die einmal je Kopf erfasst werden (nicht je Shaft) — in
 * Eingabereihenfolge. Sie stehen in Abschnitt 1 der Maske und des Berichts.
 */
function headMeasurements(): array
{
    return [
        'contact_pressure' => ['label' => 'meas_contact',  'spec' => '70 ± 4 kPa'],
        'ihs_flow'         => ['label' => 'meas_ihs_flow', 'spec' => '≥ 3.5 L/min'],
    ];
}

/** Gruppenüberschriften der Messtabelle als [Gruppenname => Spaltenanzahl]. */
function measurementGroups(): array
{
    $groups = [];
    foreach (measurementColumns() as $col) {
        $groups[$col['group']] = ($groups[$col['group']] ?? 0) + 1;
    }
    return $groups;
}

/** Die numerischen Messfelder einer Syringe-Zeile, in Eingabe-Reihenfolge. */
function syringeFields(): array
{
    return array_keys(measurementColumns());
}

/**
 * Formularwert (String) in float|null umwandeln.
 * Erlaubt Punkt und Komma als Dezimaltrennzeichen (3.2 oder 3,2), führendes
 * Vorzeichen sowie verkürzte Schreibweisen (.5 / 5.). Leer -> null.
 *
 * Achtung: Gibt auch bei ungültiger Eingabe null zurück. Wer zwischen "leer"
 * und "Tippfehler" unterscheiden muss (z. B. autosave.php), verwendet
 * numericInputState().
 */
function nz(mixed $v): ?float
{
    if ($v === null) return null;
    $v = str_replace(',', '.', trim((string)$v));
    if ($v === '') return null;
    if (!preg_match('/^[+-]?(\d+(\.\d*)?|\.\d+)$/', $v)) return null;
    return (float)$v;
}

/**
 * Zustand einer numerischen Eingabe: 'empty', 'invalid' oder 'ok'.
 * Damit ein Tippfehler ("8O" statt "80") nicht stillschweigend als
 * Leerwert gespeichert wird, sondern eine Fehlermeldung auslöst.
 */
function numericInputState(mixed $v): string
{
    $s = str_replace(',', '.', trim((string)($v ?? '')));
    if ($s === '') return 'empty';
    return preg_match('/^[+-]?(\d+(\.\d*)?|\.\d+)$/', $s) ? 'ok' : 'invalid';
}

/**
 * Zahl fürs Anzeigen/Eingabefeld formatieren (Punkt als Trennzeichen), leer wenn NULL.
 *
 * $decimals füllt auf eine feste Stellenzahl auf, rundet aber nie: hat der gespeicherte
 * Wert mehr Nachkommastellen, wird er ungekürzt ausgegeben. Ein rundendes
 * number_format($v, 2) würde aus -0.064 die Anzeige -0.06 machen — ein Wert, der laut
 * Spaltenkopf in der Toleranz liegt, in einer rot eingefärbten Zelle.
 * Die Nicht-Rundung gilt bis drei Nachkommastellen; für $decimals > 3 wird eine
 * bereits auf drei Stellen kollabierte Zahl aufgefüllt.
 */
function fmtNum(?float $v, ?int $decimals = null): string
{
    if ($v === null) return '';
    // 3 Nachkommastellen: genau genug für jedes Messgerät im Prüfablauf und
    // verlustfrei genug, dass ein erneutes Speichern den Wert nicht rundet.
    $s = rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');
    if ($s === '' || $s === '-' || $s === '-0') $s = '0';
    if ($decimals === null) return $s;

    $dot  = strpos($s, '.');
    $frac = $dot === false ? 0 : strlen($s) - $dot - 1;
    return $frac >= $decimals ? $s : number_format((float)$s, $decimals, '.', '');
}

/**
 * Anzeigetext einer Messzelle: der formatierte Wert, oder '—' wenn nicht erfasst.
 *
 * Bewusst eine eigene Funktion und keine Prüfung im Template: `fmtNum(0.0)` liefert
 * den String '0', und '0' ist in PHP falsy. Ein `h(fmtNum($v)) ?: '—'` im Template
 * zeigt deshalb für einen gemessenen Nullwert dasselbe Zeichen wie für einen gar
 * nicht erfassten. Bei Drücken und Durchflüssen fiel das nie auf, beim
 * Repeated-sliding-Test ist 0.00 der Normalfall.
 */
function fmtCell(?float $v, ?int $decimals = null): string
{
    return $v === null ? '—' : fmtNum($v, $decimals);
}

/**
 * Bewertet einen Messwert gegen die Fuji-Prüfvorgabe.
 * Rückgabe: 'ok' (grün), 'warn' (gelb, grenzwertig) oder 'bad' (rot).
 *
 * Vacuum Pressure wird als Betrag geprüft (|Wert| ≥ 80), damit es keine Rolle
 * spielt, ob der Techniker -88 oder 88 einträgt — Vakuummeter zeigen den
 * Betrag oft ohne Vorzeichen an.
 *
 * Vacuum Pressure/Flow werden auf beiden Seiten (Z1, Z2) gemessen, um
 * gleichzeitig die Justage der beiden Z-Achsen zu prüfen; beide Seiten müssen
 * die Spezifikation erfüllen.
 *
 * Internal Head Sensor Pressure hängt vom Shaft ab (siehe Konstanten oben) —
 * nur dieses Feld kann den Zustand 'warn' liefern.
 */
function specState(string $field, float $value, string $syringe = ''): string
{
    if ($field === 'ihs_pressure') {
        $dev = abs($value - IHS_TARGET);
        if ($syringe === IHS_REF_SHAFT) {
            return $dev <= IHS_TOL_REF + SPEC_EPS ? 'ok' : 'bad';
        }
        if ($dev <= IHS_TOL_WARN + SPEC_EPS) return 'ok';
        if ($dev <= IHS_TOL_MAX + SPEC_EPS)  return 'warn';
        return 'bad';
    }

    $ok = match ($field) {
        'contact_pressure'  => abs($value - CONTACT_TARGET) <= CONTACT_TOL + SPEC_EPS,
        'vac_pressure_z1', 'vac_pressure_z2' => abs($value) >= 80 - SPEC_EPS,
        'vac_flow_z1', 'vac_flow_z2'         => $value >= 2.0 - SPEC_EPS,
        'vacbreak_pressure' => $value >= 10 - SPEC_EPS && $value <= 15 + SPEC_EPS,
        'vacbreak_flow'     => $value >= 0.5 - SPEC_EPS,
        'clean_pressure'    => $value >= 100 - SPEC_EPS,
        'clean_flow'        => $value >= 0.8 - SPEC_EPS,
        'ihs_flow'          => $value >= 3.5 - SPEC_EPS,
        'valve_slide'       => abs($value) <= VALVE_SLIDE_MAX + SPEC_EPS,
        default             => true,
    };
    return $ok ? 'ok' : 'bad';
}

/** Erfüllt der Wert die Spezifikation? (grenzwertig zählt als erfüllt) */
function specOk(string $field, float $value, string $syringe = ''): bool
{
    return specState($field, $value, $syringe) !== 'bad';
}

/** CSS-Klasse für eine einzelne Messzelle: '', 'ok', 'warn' oder 'bad'. */
function cellClass(?float $value, string $field, string $syringe = ''): string
{
    if ($value === null) return '';
    return specState($field, $value, $syringe);
}

/**
 * Ergebnis einer Syringe-/Shaft-Zeile: '' (unvollständig), 'PASS', 'WARN'
 * oder 'FAIL'. Alle Messfelder der Zeile müssen erfasst sein.
 */
function evaluateSyringe(array $r, string $syringe): string
{
    foreach (syringeFields() as $f) {
        if (($r[$f] ?? null) === null || $r[$f] === '') return '';
    }
    $result = 'PASS';
    foreach (syringeFields() as $f) {
        $state = specState($f, (float)$r[$f], $syringe);
        if ($state === 'bad')  return 'FAIL';
        if ($state === 'warn') $result = 'WARN';
    }
    return $result;
}

/** Ergebnis einer einmaligen Kopfmessung: '', 'PASS' oder 'FAIL'. */
function evaluateHeadMeasurement(string $field, ?float $value): string
{
    if ($value === null) return '';
    return specOk($field, $value) ? 'PASS' : 'FAIL';
}

/** Ergebnisse aller Kopfmessungen als [Feld => '', 'PASS' oder 'FAIL']. */
function evaluateHeadMeasurements(array $insp): array
{
    $out = [];
    foreach (array_keys(headMeasurements()) as $field) {
        $v = ($insp[$field] ?? null) !== null ? (float)$insp[$field] : null;
        $out[$field] = evaluateHeadMeasurement($field, $v);
    }
    return $out;
}

/**
 * Gesamtstatus einer Prüfung.
 * Liefert ['status', 'pass', 'warn', 'fail', 'pending', 'total', 'flow_result'].
 */
function evaluateInspection(array $insp, array $syringesByLetter): array
{
    $headResults = evaluateHeadMeasurements($insp);
    $headFailed     = in_array('FAIL', $headResults, true);
    $headIncomplete = in_array('', $headResults, true);

    $pass = 0; $warn = 0; $fail = 0; $pending = 0;
    foreach (syringeLetters() as $letter) {
        switch (evaluateSyringe($syringesByLetter[$letter] ?? [], $letter)) {
            case 'PASS': $pass++;    break;
            case 'WARN': $warn++;    break;
            case 'FAIL': $fail++;    break;
            default:     $pending++; break;
        }
    }

    if ($fail > 0 || $headFailed) {
        $status = 'FAIL';
    } elseif ($pending > 0 || $headIncomplete) {
        $status = 'INCOMPLETE';
    } elseif ($warn > 0) {
        $status = 'WARN';
    } else {
        $status = 'PASS';
    }

    return [
        'status'       => $status,
        'pass'         => $pass,
        'warn'         => $warn,
        'fail'         => $fail,
        'pending'      => $pending,
        'total'        => count(syringeLetters()),
        'head_results' => $headResults,
    ];
}

/** Klartext-Einordnung des Gesamtergebnisses für die Zusammenfassung. */
function summaryVerdict(array $eval): string
{
    $n = $eval['total'];
    return match ($eval['status']) {
        'FAIL'       => $eval['fail'] > 0
            ? t('verdict_fail', $eval['fail'], $n)
            : t('verdict_fail_head'),
        'INCOMPLETE' => $eval['pending'] > 0
            ? t('verdict_incomplete', $eval['pending'], $n)
            : t('verdict_incomplete_head'),
        'WARN'       => t('verdict_warn', $n, $eval['warn']),
        default      => t('verdict_pass', $n),
    };
}

/**
 * Zusammenfassungsblock (Gesamturteil, Balken, Zähler).
 * Von edit.php und view.php gemeinsam genutzt; die IDs erlauben es app.js,
 * den Block nach jedem Auto-Save ohne Neuladen zu aktualisieren.
 */
function renderSummary(array $eval): string
{
    $n      = max(1, $eval['total']);
    $labels = ['pass' => t('cnt_pass'), 'warn' => t('cnt_warn'), 'fail' => t('cnt_fail'), 'pending' => t('cnt_pending')];

    $segs = '';
    foreach ($labels as $key => $label) {
        $pct   = round($eval[$key] / $n * 100, 4);
        $segs .= '<span class="seg ' . $key . '" id="seg-' . $key . '" style="width:' . $pct . '%"'
               . ' title="' . h($eval[$key] . ' ' . $label) . '"></span>';
    }

    $counts = '';
    foreach ($labels as $key => $label) {
        $counts .= '<li class="' . $key . '"><b id="stat-' . $key . '">' . (int)$eval[$key] . '</b> ' . h($label) . '</li>';
    }

    return '<div class="summary">'
         . '<div class="summary-verdict">'
         . '<span class="pill ' . h($eval['status']) . '" id="status-pill">' . h($eval['status']) . '</span>'
         . '<span class="summary-text" id="summary-text">' . h(summaryVerdict($eval)) . '</span>'
         . '</div>'
         . '<div class="summary-bar" role="img" aria-label="' . h(summaryVerdict($eval)) . '">' . $segs . '</div>'
         . '<ul class="summary-counts">' . $counts . '</ul>'
         . '<div class="summary-total">' . h(t('shafts_total', (int)$eval['total'])) . '</div>'
         . '</div>';
}

function h(mixed $v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Kommt eine schreibende Anfrage von einer fremden Webseite?
 *
 * Die Anwendung hat keine Anmeldung. Ohne diese Prüfung könnte jede Webseite,
 * die jemand im Firmennetz im Browser öffnet, per unsichtbarem Formular
 * Prüfungen löschen oder Messwerte überschreiben — der Browser schickt die
 * Anfrage an die NAS, als käme sie vom Benutzer.
 *
 * Maßgeblich ist Sec-Fetch-Site, das alle aktuellen Browser selbst setzen und
 * das eine Seite nicht fälschen kann. Nur ohne diesen Header wird auf Origin
 * zurückgegriffen. Fehlen beide, stammt die Anfrage nicht aus einem
 * Browser-Kontext (curl, Skript) und wird zugelassen.
 *
 * "same-site" wird ebenfalls abgewiesen: dazu zählen auch andere Dienste auf
 * derselben NAS unter einem anderen Port.
 */
function isCrossSiteRequest(array $server): bool
{
    $site = $server['HTTP_SEC_FETCH_SITE'] ?? null;
    if ($site !== null) {
        return !in_array($site, ['same-origin', 'none'], true);
    }

    $origin = $server['HTTP_ORIGIN'] ?? null;
    if ($origin === null) {
        return false;
    }
    $host = parse_url($origin, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return true; // u. a. "Origin: null" aus Sandbox-Frames
    }
    $port = parse_url($origin, PHP_URL_PORT);
    $originHost = strtolower($host . ($port !== null ? ':' . $port : ''));
    return $originHost !== strtolower((string)($server['HTTP_HOST'] ?? ''));
}
