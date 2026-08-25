<?php
declare(strict_types=1);

/**
 * Zweisprachigkeit (Englisch/Deutsch).
 *
 * Standardsprache ist Englisch. Die Wahl steckt in einem Cookie, damit sie
 * ohne Login und ohne Session über alle Seiten hinweg erhalten bleibt; ein
 * `?lang=` in der URL setzt sie um und gilt sofort.
 *
 * Übersetzt werden ausschließlich Erklärungstexte, Überschriften und
 * Beschriftungen. Die Messgrößen selbst (Vacuum, Nozzle Cleaning, Pressure,
 * Flow …) stehen in beiden Sprachen englisch, weil sie so auf dem Fuji-Beleg
 * und an den Messgeräten stehen.
 */

const LANG_DEFAULT = 'en';
const LANG_COOKIE  = 'rh20_lang';

/** Unterstützte Sprachen: Code => Anzeigename im Umschalter. */
function languages(): array
{
    return ['en' => 'EN', 'de' => 'DE'];
}

/**
 * Aktive Sprache bestimmen: ?lang= schlägt Cookie schlägt Standard.
 * Ein gültiges ?lang= wird als Cookie festgeschrieben.
 */
function currentLang(): string
{
    static $lang = null;
    if ($lang !== null) return $lang;

    $supported = array_keys(languages());
    $fromUrl = isset($_GET['lang']) ? strtolower(trim((string)$_GET['lang'])) : '';

    if (in_array($fromUrl, $supported, true)) {
        $lang = $fromUrl;
        if (!headers_sent()) {
            setcookie(LANG_COOKIE, $lang, [
                'expires'  => time() + 31536000,
                'path'     => '/',
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }
        $_COOKIE[LANG_COOKIE] = $lang;
        return $lang;
    }

    $fromCookie = isset($_COOKIE[LANG_COOKIE]) ? strtolower((string)$_COOKIE[LANG_COOKIE]) : '';
    $lang = in_array($fromCookie, $supported, true) ? $fromCookie : LANG_DEFAULT;
    return $lang;
}

/**
 * Übersetzten Text holen. Weitere Argumente werden per sprintf eingesetzt.
 * Fehlt ein Schlüssel, wird der Schlüssel selbst zurückgegeben — im Test
 * sofort sichtbar statt still leer.
 */
function t(string $key, mixed ...$args): string
{
    $all  = translations();
    $lang = currentLang();
    $text = $all[$lang][$key] ?? $all[LANG_DEFAULT][$key] ?? $key;
    return $args ? vsprintf($text, $args) : $text;
}

/** URL der aktuellen Seite mit gewechselter Sprache (übrige Parameter bleiben). */
function langUrl(string $lang): string
{
    $params = $_GET;
    $params['lang'] = $lang;
    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
    return $script . '?' . http_build_query($params);
}

function translations(): array
{
    return [
        'en' => [
            // --- Übersicht ---
            'app_title'          => 'RH20 Head Inspection Records',
            'saved_inspections'  => 'Saved Inspections',
            'none_saved'         => 'No inspection recorded yet.',
            'create_first'       => 'Create the first inspection →',
            'col_id'             => 'ID',
            'col_serial'         => 'Serial Number',
            'col_head'           => 'Head',
            'col_date'           => 'Date',
            'col_technician'     => 'Technician',
            'col_result'         => 'Result',
            'col_saved_at'       => 'Saved',
            'col_actions'        => 'Actions',

            // --- Schaltflächen und Tooltips ---
            'btn_new'            => 'New inspection',
            'btn_edit'           => 'Edit',
            'btn_view'           => 'View / Print',
            'btn_delete'         => 'Delete',
            'btn_back'           => 'Back to overview',
            'btn_done'           => 'Done — back to overview',
            'btn_print'          => 'Print / Save as PDF',
            'confirm_delete'     => 'Permanently delete inspection #%d (%s)?',
            'confirm_delete_short' => 'Permanently delete inspection #%d?',
            'no_serial'          => 'no serial number',

            // --- Erfassungsmaske ---
            'edit_title'         => 'Edit inspection #%d',
            'head_information'   => 'Head Information',
            'label_head_model'   => 'Head Model',
            'label_serial'       => 'Serial Number',
            'label_date'         => 'Date',
            'label_technician'   => 'Technician',
            'status_ready'       => 'Ready',
            'status_saving'      => 'Saving …',
            'status_saved'       => 'Saved %s',
            'status_save_error'  => 'Save failed — please reload the page',
            'status_invalid'     => 'Not saved: “%s” is not a valid number',
            'tip_invalid'        => 'Not a valid number — this value was NOT saved.',
            'tip_save_failed'    => 'Save failed — this value was NOT saved.',

            // --- Abschnitt 1: einmalige Messungen ---
            'section_head_meas'  => '1. Internal Head Sensor — single measurement per head',
            'th_parameter'       => 'Parameter',
            'th_standard'        => 'Standard',
            'th_reading'         => 'Reading',
            'th_result'          => 'Result',
            'meas_contact'       => 'Contact Detection — Pressure',
            'meas_ihs_flow'      => 'Internal Head Sensor — Flow Rate',
            'pending'            => 'pending',

            // --- Abschnitt 2: Messtabelle ---
            'section_shaft_meas' => '2. Readings per shaft (A–T)',
            'th_shaft'           => 'Shaft',
            'th_overall'         => 'Overall',
            'ref_shaft_tooltip'  => 'Reference shaft: the Internal Head Sensor is calibrated here',
            'entry_hint'         => 'Entry runs column by column: Enter steps from A through T within one column, then jumps to the top of the next. Vacuum Pressure accepts values with or without a minus sign (−88 and 88 are treated alike); the decimal separator may be a point or a comma. Internal Head Sensor Pressure: shaft %s is the reference shaft, calibrated to %d kPa (tolerance ± %s kPa). All other shafts are acceptable up to ± %s kPa; beyond ± %s kPa deviation the value is flagged as borderline in yellow.',

            // --- Legende ---
            'legend_ok'          => 'Within specification',
            'legend_warn'        => 'Borderline — acceptable but conspicuous',
            'legend_bad'         => 'Out of specification',
            'legend_empty'       => 'no reading recorded',

            // --- Zusammenfassung ---
            'summary'            => 'Summary',
            'summary_section'    => '3. Summary',
            'cnt_pass'           => 'PASS',
            'cnt_warn'           => 'borderline',
            'cnt_fail'           => 'FAIL',
            'cnt_pending'        => 'pending',
            'shafts_total'       => '%d shafts in total (A–T)',
            'verdict_fail'       => '%d of %d shafts out of specification',
            'verdict_fail_head'  => 'Single measurement per head out of specification',
            'verdict_incomplete' => '%d of %d shafts not yet fully recorded',
            'verdict_incomplete_head' => 'Single measurement per head not yet recorded',
            'verdict_warn'       => 'All %d shafts acceptable, %d of them borderline',
            'verdict_pass'       => 'All %d shafts within specification',

            // --- Berichtsansicht ---
            'report_title'       => 'Inspection Record #%d',
            'report_heading'     => 'RH20 Head Inspection Report',
            'report_shaft_table' => 'Readings per shaft (A–T)',

            // --- Fehlerseiten ---
            'not_found'          => 'Inspection not found.',
            'back_to_overview'   => 'Back to overview',
        ],

        'de' => [
            'app_title'          => 'RH20 Kopf-Prüfprotokolle',
            'saved_inspections'  => 'Gespeicherte Prüfungen',
            'none_saved'         => 'Noch keine Prüfung gespeichert.',
            'create_first'       => 'Jetzt erste Prüfung anlegen →',
            'col_id'             => 'ID',
            'col_serial'         => 'Seriennummer',
            'col_head'           => 'Kopf',
            'col_date'           => 'Datum',
            'col_technician'     => 'Techniker',
            'col_result'         => 'Ergebnis',
            'col_saved_at'       => 'Gespeichert am',
            'col_actions'        => 'Aktionen',

            'btn_new'            => 'Neue Prüfung anlegen',
            'btn_edit'           => 'Bearbeiten',
            'btn_view'           => 'Ansehen / Drucken',
            'btn_delete'         => 'Löschen',
            'btn_back'           => 'Zur Übersicht',
            'btn_done'           => 'Fertig — zur Übersicht',
            'btn_print'          => 'Drucken / Als PDF speichern',
            'confirm_delete'     => 'Prüfung #%d (%s) wirklich unwiderruflich löschen?',
            'confirm_delete_short' => 'Prüfung #%d wirklich unwiderruflich löschen?',
            'no_serial'          => 'ohne Seriennummer',

            'edit_title'         => 'Prüfung #%d bearbeiten',
            'head_information'   => 'Kopf-Informationen',
            'label_head_model'   => 'Head Model',
            'label_serial'       => 'Seriennummer',
            'label_date'         => 'Datum',
            'label_technician'   => 'Techniker',
            'status_ready'       => 'Bereit',
            'status_saving'      => 'Speichert …',
            'status_saved'       => 'Gespeichert %s',
            'status_save_error'  => 'Fehler beim Speichern — bitte Seite neu laden',
            'status_invalid'     => 'Nicht gespeichert: „%s“ ist keine gültige Zahl',
            'tip_invalid'        => 'Keine gültige Zahl — dieser Wert wurde NICHT gespeichert.',
            'tip_save_failed'    => 'Speichern fehlgeschlagen — dieser Wert wurde NICHT gespeichert.',

            'section_head_meas'  => '1. Internal Head Sensor — einmalige Messung je Kopf',
            'th_parameter'       => 'Parameter',
            'th_standard'        => 'Standard',
            'th_reading'         => 'Messwert',
            'th_result'          => 'Ergebnis',
            'meas_contact'       => 'Contact Detection — Pressure',
            'meas_ihs_flow'      => 'Internal Head Sensor — Flow Rate',
            'pending'            => 'ausstehend',

            'section_shaft_meas' => '2. Messwerte je Shaft (A–T)',
            'th_shaft'           => 'Shaft',
            'th_overall'         => 'Overall',
            'ref_shaft_tooltip'  => 'Referenz-Shaft: hier wird der Internal Head Sensor eingemessen',
            'entry_hint'         => 'Eingabe erfolgt spaltenweise: Enter läuft erst A → T einer Spalte durch, dann springt es an den Anfang der nächsten Spalte. Vacuum Pressure akzeptiert Werte mit oder ohne Minuszeichen (−88 und 88 werden gleich behandelt), Dezimaltrennzeichen darf Punkt oder Komma sein. Internal Head Sensor Pressure: Shaft %s ist der Referenz-Shaft, an dem auf %d kPa eingemessen wird (Toleranz ± %s kPa). Für alle übrigen Shafts sind bis ± %s kPa zulässig; ab mehr als ± %s kPa Abweichung wird der Wert als grenzwertig gelb markiert.',

            'legend_ok'          => 'Innerhalb der Spezifikation',
            'legend_warn'        => 'Grenzwertig — zulässig, aber auffällig',
            'legend_bad'         => 'Außerhalb der Spezifikation',
            'legend_empty'       => 'kein Messwert erfasst',

            'summary'            => 'Zusammenfassung',
            'summary_section'    => '3. Zusammenfassung',
            'cnt_pass'           => 'PASS',
            'cnt_warn'           => 'grenzwertig',
            'cnt_fail'           => 'FAIL',
            'cnt_pending'        => 'ausstehend',
            'shafts_total'       => '%d Shafts gesamt (A–T)',
            'verdict_fail'       => '%d von %d Shafts außerhalb der Spezifikation',
            'verdict_fail_head'  => 'Einmalige Messung je Kopf außerhalb der Spezifikation',
            'verdict_incomplete' => '%d von %d Shafts noch nicht vollständig erfasst',
            'verdict_incomplete_head' => 'Einmalige Messung je Kopf noch nicht erfasst',
            'verdict_warn'       => 'Alle %d Shafts zulässig, davon %d grenzwertig',
            'verdict_pass'       => 'Alle %d Shafts innerhalb der Spezifikation',

            'report_title'       => 'Prüfprotokoll #%d',
            'report_heading'     => 'RH20 Head Inspection Report',
            'report_shaft_table' => 'Messwerte je Shaft (A–T)',

            'not_found'          => 'Prüfung nicht gefunden.',
            'back_to_overview'   => 'Zurück zur Übersicht',
        ],
    ];
}
