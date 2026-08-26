/* RH20 Shaft Inspection — Auto-Save, Tastatur-Navigation, Bestätigungsdialoge
 *
 * Eingabephilosophie: vertikal, Spalte für Spalte.
 * Reihenfolge beim Drücken von Enter:
 *   Kopf-Felder (Head Model -> Seriennummer -> Datum -> Techniker)
 *   -> einmalige Kopfmessungen (Contact Detection, IHS Flow Rate)
 *   -> je Messspalte A..T durch, dann zur nächsten Spalte
 * Die Spaltenreihenfolge kommt aus data-field-order am <body> und stammt damit
 * aus measurementColumns() in functions.php — sie kann hier nicht auseinanderlaufen.
 *
 * Jedes Feld speichert automatisch beim Verlassen (blur), sofern es sich
 * geändert hat. Enter speichert und springt zusätzlich zum nächsten Feld.
 */
(function () {
    'use strict';

    /* ---- Bestätigung für schreibende Aktionen (Anlegen/Löschen per POST) ---- */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (form && form.dataset && form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
            e.preventDefault();
        }
    });

    var body = document.body;
    var inspectionId = body.dataset.inspectionId;
    if (!inspectionId) return; // Übersicht / Berichtsansicht: nur der Dialog oben

    var GRID_FIELD_ORDER = (body.dataset.fieldOrder || '').split(',').filter(Boolean);
    // Einmalige Kopfmessungen in Eingabereihenfolge (aus headMeasurements()).
    var HEAD_CHAIN = (body.dataset.headOrder || '').split(',').filter(Boolean);
    var HEADER_CHAIN = ['head_model', 'serial_number', 'inspection_date', 'technician'];
    var LAST_ROW = 19; // Shaft T

    // Oberflächentexte kommen übersetzt aus dem Markup — hier steht kein
    // sprachgebundener Text, damit EN und DE nicht auseinanderlaufen.
    var T = {
        saving:     body.dataset.tSaving     || 'Saving …',
        saved:      body.dataset.tSaved      || 'Saved {time}',
        saveError:  body.dataset.tSaveError  || 'Save failed',
        invalid:    body.dataset.tInvalid    || 'Not saved: {value}',
        tipInvalid: body.dataset.tTipInvalid || 'Not a valid number.',
        tipFailed:  body.dataset.tTipFailed  || 'Save failed.',
        pending:    body.dataset.tPending    || 'pending'
    };

    var statusEl = document.getElementById('save-status');

    function setStatus(text, cls) {
        if (!statusEl) return;
        statusEl.textContent = text;
        statusEl.className = 'save-status ' + cls;
    }

    function savedNow() {
        var d = new Date();
        var p = function (n) { return String(n).padStart(2, '0'); };
        setStatus(T.saved.replace('{time}', p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds())), 'ok');
    }

    function flash(el, ok) {
        el.classList.remove('flash-ok', 'flash-bad');
        void el.offsetWidth; // Reflow, damit die Animation neu startet
        el.classList.add(ok ? 'flash-ok' : 'flash-bad');
        window.setTimeout(function () {
            el.classList.remove('flash-ok', 'flash-bad');
        }, 700);
    }

    function applyMeasurementClass(el, cellClass) {
        el.classList.remove('ok', 'warn', 'bad');
        if (cellClass) el.classList.add(cellClass);
    }

    function updateSummary(s) {
        if (!s) return;
        var total = s.total || 1;
        ['pass', 'warn', 'fail', 'pending'].forEach(function (key) {
            var count = document.getElementById('stat-' + key);
            var seg = document.getElementById('seg-' + key);
            if (count) count.textContent = s[key];
            if (seg) seg.style.width = (s[key] / total * 100) + '%';
        });
        var pill = document.getElementById('status-pill');
        if (pill) {
            pill.textContent = s.status;
            pill.className = 'pill ' + s.status;
        }
        var text = document.getElementById('summary-text');
        if (text) text.textContent = s.text || '';
    }

    function updateHeadResults(results) {
        if (!results) return;
        Object.keys(results).forEach(function (field) {
            var el = document.getElementById('head-result-' + field);
            if (!el) return;
            el.textContent = results[field] || T.pending;
            el.className = 'result-' + (results[field] || 'blank');
        });
    }

    function updateRowResult(letter, result) {
        var cell = document.getElementById('overall-' + letter);
        if (!cell) return;
        cell.textContent = result || '—';
        cell.className = 'col-overall result-' + (result || 'blank');
    }

    /* Zähler laufender Speichervorgänge: eine verspätet eintreffende Antwort
       darf die Zusammenfassung nicht auf einen älteren Stand zurücksetzen. */
    var saveSeq = 0;
    var lastAppliedSeq = 0;

    async function save(el) {
        var scope = el.dataset.scope;
        var field = el.dataset.field;
        var syringe = el.dataset.syringe || '';
        var value = el.value;

        if (el.dataset.numeric === '1') {
            value = value.replace(',', '.'); // Komma -> Punkt, wie serverseitig
            el.value = value;
        }

        // Nur speichern, wenn sich tatsächlich etwas geändert hat — sonst löst
        // reines Durchtabben der 200 Felder ebenso viele Schreibvorgänge aus.
        if (value === el.dataset.savedValue) return;

        var seq = ++saveSeq;
        setStatus(T.saving, '');

        try {
            var resp = await fetch('autosave.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    id: inspectionId, scope: scope, field: field, syringe: syringe, value: value
                }).toString()
            });
            var data = await resp.json();
            if (!data.ok) throw new Error(data.error || 'save_failed');

            el.classList.remove('invalid');
            el.removeAttribute('title');
            el.dataset.savedValue = typeof data.value === 'string' ? data.value : value;
            if (typeof data.value === 'string' && data.value !== value && document.activeElement !== el) {
                el.value = data.value;
            }

            if (el.dataset.numeric === '1') applyMeasurementClass(el, data.cell_class);
            if (scope === 'syringe') updateRowResult(syringe, data.row_result);
            updateHeadResults(data.head_results);

            if (seq >= lastAppliedSeq) {
                lastAppliedSeq = seq;
                updateSummary(data.summary);
            }
            flash(el, true);
            savedNow();
        } catch (err) {
            var invalidNumber = String(err && err.message) === 'invalid_number';
            el.classList.add('invalid');
            el.title = invalidNumber ? T.tipInvalid : T.tipFailed;
            flash(el, false);
            setStatus(invalidNumber ? T.invalid.replace('{value}', value) : T.saveError, 'error');
            console.error('Autosave fehlgeschlagen:', err);
        }
    }

    function findHeadField(field) {
        return field ? document.querySelector('[data-scope="head"][data-field="' + field + '"]') : null;
    }

    function findGridField(field, row) {
        return document.querySelector(
            '[data-scope="syringe"][data-field="' + field + '"][data-row="' + row + '"]'
        );
    }

    function nextAfter(el) {
        var scope = el.dataset.scope;
        var field = el.dataset.field;
        var row;

        if (scope === 'header') {
            var idx = HEADER_CHAIN.indexOf(field);
            if (idx >= 0 && idx < HEADER_CHAIN.length - 1) {
                return document.querySelector('[data-scope="header"][data-field="' + HEADER_CHAIN[idx + 1] + '"]');
            }
            return findHeadField(HEAD_CHAIN[0]);
        }

        if (scope === 'head') {
            var h = HEAD_CHAIN.indexOf(field);
            if (h >= 0 && h < HEAD_CHAIN.length - 1) return findHeadField(HEAD_CHAIN[h + 1]);
            return findGridField(GRID_FIELD_ORDER[0], 0);
        }

        if (scope === 'syringe') {
            row = parseInt(el.dataset.row, 10);
            if (row < LAST_ROW) return findGridField(field, row + 1);
            // Spaltenende (Shaft T) erreicht -> nächste Kategorie, wieder bei A
            var col = GRID_FIELD_ORDER.indexOf(field);
            if (col >= 0 && col < GRID_FIELD_ORDER.length - 1) {
                return findGridField(GRID_FIELD_ORDER[col + 1], 0);
            }
            return null; // komplette Tabelle durch
        }

        return null;
    }

    document.querySelectorAll('.autosave').forEach(function (el) {
        el.dataset.savedValue = el.value;
        el.addEventListener('blur', function () { save(el); });
        el.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            var next = nextAfter(el);
            if (next) {
                next.focus();
                if (typeof next.select === 'function') next.select();
            } else {
                el.blur();
            }
        });
    });
})();
