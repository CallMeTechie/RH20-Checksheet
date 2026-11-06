/* RH20 Shaft Inspection — Auto-Save, Tastatur-Navigation, Bestätigungsdialoge,
 * Valve Air Stick überspringen, Druckstart und Kopiermenü der Berichtsansicht
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

    /* ---- Berichtsansicht: Druckdialog sofort öffnen (Drucken-Knopf der Erfassung) ---- */
    if (body.dataset.autoprint === '1') {
        // print=1 aus der Adresse nehmen, damit Neuladen den Dialog nicht erneut öffnet.
        if (window.history && history.replaceState) {
            var url = new URL(window.location.href);
            url.searchParams.delete('print');
            history.replaceState(null, '', url.toString());
        }
        window.addEventListener('load', function () { window.print(); });
    }

    /* ---- Berichtsansicht: Kontextmenü am Bearbeiten-Knopf (Kopie anlegen) ----
       Rechtsklick (oder Kontextmenü-Taste) auf den Knopf öffnet statt des
       Browsermenüs ein eigenes Menü; ein normaler Klick bearbeitet wie gewohnt. */
    document.querySelectorAll('[data-copy-menu]').forEach(function (trigger) {
        var menu = document.getElementById(trigger.dataset.copyMenu);
        if (!menu) return;
        function close() { menu.hidden = true; }

        trigger.addEventListener('contextmenu', function (e) {
            e.preventDefault();
            menu.hidden = false;
            // An der Mausposition; über die Tastatur ausgelöst (keine Koordinaten) unter dem Knopf.
            var r = trigger.getBoundingClientRect();
            var x = e.clientX || r.left;
            var y = e.clientY || r.bottom;
            menu.style.left = Math.max(8, Math.min(x, window.innerWidth - menu.offsetWidth - 8)) + 'px';
            menu.style.top = Math.max(8, Math.min(y, window.innerHeight - menu.offsetHeight - 8)) + 'px';
            var item = menu.querySelector('[role="menuitem"]');
            if (item) item.focus();
        });
        document.addEventListener('click', function (e) {
            if (!menu.contains(e.target)) close();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !menu.hidden) {
                close();
                trigger.focus();
            }
        });
        window.addEventListener('resize', close);
        window.addEventListener('blur', close);
        window.addEventListener('scroll', close, true);
    });

    var inspectionId = body.dataset.inspectionId;
    if (!inspectionId) return; // Übersicht / Berichtsansicht: ab hier nur die Erfassung

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
        pending:    body.dataset.tPending    || 'pending',
        ready:      body.dataset.tReady      || 'Ready',
        confirmSkip: body.dataset.tConfirmSkip || 'Replace the Valve Air Stick readings with the default value?'
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
       darf Feld, Zeilenergebnis und Zusammenfassung nicht auf einen älteren
       Stand zurücksetzen. Das Feld merkt sich seinen letzten Vorgang in
       data-save-seq, die Zeilen und die Zusammenfassung je einen eigenen Stand. */
    var saveSeq = 0;
    var lastAppliedSeq = 0;
    var lastRowSeq = {};
    var inflight = []; // laufende Speichervorgänge (Promises), siehe Klick auf Links unten
    var sending = [];  // Startzeiten gerade unterwegs befindlicher Anfragen (ms)
    var SAVE_TIMEOUT = 10000; // ms, danach gilt die Anfrage als fehlgeschlagen
    var SLOW_SAVE = 2000;     // ms, ab hier warnt das Verlassen der Seite

    function track(promise) {
        inflight.push(promise);
        var done = function () { inflight.splice(inflight.indexOf(promise), 1); };
        promise.then(done, done);
        return promise;
    }

    // keepalive: die Anfrage überlebt das Verlassen der Seite (siehe unten).
    // Nach SAVE_TIMEOUT wird abgebrochen — eine hängende Anfrage (Funkloch, NAS
    // blockiert) hielte sonst die Speicherkette ihres Felds unbegrenzt auf.
    async function post(params) {
        var started = Date.now();
        sending.push(started);
        var controller = window.AbortController ? new AbortController() : null;
        var timer = controller ? window.setTimeout(function () { controller.abort(); }, SAVE_TIMEOUT) : 0;
        try {
            var resp = await fetch('autosave.php', {
                method: 'POST',
                keepalive: true,
                signal: controller ? controller.signal : undefined,
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(params).toString()
            });
            return await resp.json();
        } finally {
            window.clearTimeout(timer);
            sending.splice(sending.indexOf(started), 1);
        }
    }

    /* Speichervorgänge eines Felds laufen nacheinander: der nächste startet erst,
       wenn die Antwort des vorigen da ist. So kann ein älterer Wert einen neueren
       auf dem Server nicht überholen, und "unverändert" wird gegen den zuletzt
       bestätigten Wert geprüft. Nur beim Verlassen der Seite (immediate) geht der
       Wert sofort hinaus — danach gibt es keine Seite mehr, die warten könnte. */
    function save(el, immediate) {
        var value = el.value;
        if (el.dataset.numeric === '1') {
            value = value.replace(',', '.'); // Komma -> Punkt, wie serverseitig
            el.value = value;
        }
        el.queued = (el.queued || 0) + 1; // für das Verlassen der Seite, siehe flushPending()
        var run = function () { el.queued--; return send(el, value); };
        var job = immediate ? run() : (el.saving || Promise.resolve()).then(run, run);
        el.saving = job;
        return track(job);
    }

    async function send(el, value) {
        // Inzwischen gesperrt (Valve Air Stick übersprungen): ein noch eingereihter
        // Messwert ist hinfällig, der Server würde ihn ohnehin abweisen.
        if (el.readOnly) return;
        var scope = el.dataset.scope;
        var field = el.dataset.field;
        var syringe = el.dataset.syringe || '';

        // Nur speichern, wenn sich tatsächlich etwas geändert hat — sonst löst
        // reines Durchtabben der 200 Felder ebenso viele Schreibvorgänge aus.
        // Eine zurückgenommene Fehleingabe ist damit erledigt — die rote Markierung
        // muss weg, sonst warnt auch das Verlassen der Seite grundlos.
        if (value === el.dataset.savedValue) {
            if (el.classList.contains('invalid')) {
                el.classList.remove('invalid');
                el.removeAttribute('title');
                setStatus(T.ready, '');
            }
            return;
        }

        var seq = ++saveSeq;
        el.dataset.saveSeq = String(seq);
        setStatus(T.saving, '');

        // Überholt durch "Valve Air Stick überspringen" (setzt saveSeq aller Valve-
        // Felder) oder ein sofortiges Speichern beim Verlassen der Seite.
        function superseded() { return el.dataset.saveSeq !== String(seq); }

        try {
            var data = await post({
                id: inspectionId, scope: scope, field: field, syringe: syringe, value: value
            });
            if (!data.ok) throw new Error(data.error || 'save_failed');

            if (scope === 'syringe' && seq >= (lastRowSeq[syringe] || 0)) {
                lastRowSeq[syringe] = seq;
                updateRowResult(syringe, data.row_result);
            }
            if (seq >= lastAppliedSeq) {
                lastAppliedSeq = seq;
                updateHeadResults(data.head_results);
                updateSummary(data.summary);
            }
            if (superseded()) return;

            el.classList.remove('invalid');
            el.removeAttribute('title');
            el.dataset.savedValue = typeof data.value === 'string' ? data.value : value;
            if (typeof data.value === 'string' && data.value !== value && document.activeElement !== el) {
                el.value = data.value;
            }

            if (el.dataset.numeric === '1') applyMeasurementClass(el, data.cell_class);
            flash(el, true);
            savedNow();
        } catch (err) {
            if (superseded()) return;
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
            // Spaltenende (Shaft T) erreicht -> nächste Kategorie, wieder bei A.
            // Gesperrte Spalten (Valve Air Stick übersprungen) werden übergangen.
            var col = GRID_FIELD_ORDER.indexOf(field);
            for (var c = col + 1; col >= 0 && c < GRID_FIELD_ORDER.length; c++) {
                var first = findGridField(GRID_FIELD_ORDER[c], 0);
                if (first && !first.readOnly) return first;
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

    /* ---- Valve Air Stick überspringen: einmal für alle 20 Shafts ----
       Der Server trägt überall den Standardwert ein (bzw. leert die Felder beim
       Zurücknehmen) und meldet Wert, Bewertung und alle Zeilenergebnisse zurück. */
    var skipBox = document.getElementById('valve-skip');
    if (skipBox) {
        skipBox.addEventListener('change', function () { track(toggleSkip()); });

        async function toggleSkip() {
            var skip = skipBox.checked;
            var fields = Array.prototype.slice.call(
                document.querySelectorAll('[data-scope="syringe"][data-field="valve_slide"]'));
            var hasReadings = fields.some(function (el) { return el.value.trim() !== ''; });
            if (skip && hasReadings && !window.confirm(T.confirmSkip)) {
                skipBox.checked = false;
                return;
            }

            // Noch ausstehende Antworten einzelner Valve-Felder gelten damit als überholt,
            // noch eingereihte Messwerte werden durch die sofortige Sperre verworfen (send()).
            var seq = ++saveSeq;
            fields.forEach(function (el) {
                el.dataset.saveSeq = String(seq);
                el.readOnly = true;
            });
            skipBox.disabled = true;
            setStatus(T.saving, '');
            try {
                var data = await post({ id: inspectionId, scope: 'valve_skip', value: skip ? '1' : '0' });
                if (!data.ok) throw new Error(data.error || 'save_failed');

                fields.forEach(function (el) {
                    el.value = data.value;
                    el.dataset.savedValue = data.value;
                    el.readOnly = data.skipped;
                    el.classList.remove('invalid');
                    el.removeAttribute('title');
                    applyMeasurementClass(el, data.cell_class);
                });
                Object.keys(data.row_results || {}).forEach(function (letter) {
                    if (seq >= (lastRowSeq[letter] || 0)) {
                        lastRowSeq[letter] = seq;
                        updateRowResult(letter, data.row_results[letter]);
                    }
                });
                if (seq >= lastAppliedSeq) {
                    lastAppliedSeq = seq;
                    updateHeadResults(data.head_results);
                    updateSummary(data.summary);
                }
                skipBox.checked = data.skipped;
                savedNow();
            } catch (err) {
                skipBox.checked = !skip;
                fields.forEach(function (el) { el.readOnly = !skip; });
                setStatus(T.saveError, 'error');
                console.error('Valve Air Stick überspringen fehlgeschlagen:', err);
            } finally {
                skipBox.disabled = false;
            }
        }
    }

    /* ---- Seite verlassen: offenes Feld noch speichern, Ungespeichertes melden ----
       Gespeichert wird sonst beim Verlassen eines Felds (blur). Neu laden, Tab
       schließen, Browser-Zurück oder Tastenkürzel verlassen die Seite aber, ohne
       dass das Feld vorher den Fokus verliert — der zuletzt getippte Wert ginge
       verloren. save() schickt die Anfrage mit keepalive, sie überlebt das
       Entladen. */
    var NUMBER_RE = /^[+-]?(\d+(\.\d*)?|\.\d+)$/; // wie numericInputState() in functions.php

    function activeAutosave() {
        var el = document.activeElement;
        return el && el.classList && el.classList.contains('autosave') ? el : null;
    }

    // Nicht gespeichert werden rot markierte Felder (Speichern fehlgeschlagen) und
    // ungültige Zahlen. Letztere werden am Feldinhalt erkannt, nicht an der roten
    // Markierung: Beim Klick auf einen Link verliert das Feld zwar vorher den Fokus,
    // die Antwort "ungültig" ist beim Verlassen der Seite aber noch nicht da.
    function hasUnsaved() {
        var now = Date.now();
        if (sending.some(function (t) { return now - t > SLOW_SAVE; })) return true; // hängt womöglich
        return Array.prototype.some.call(document.querySelectorAll('.autosave'), function (el) {
            if (el.classList.contains('invalid')) return true;
            if (el.dataset.numeric !== '1') return false;
            var v = el.value.replace(',', '.').trim();
            return v !== '' && !NUMBER_RE.test(v);
        });
    }

    // Das fokussierte Feld und jedes Feld mit eingereihtem, noch nicht gesendetem
    // Speichervorgang gehen sofort hinaus — warten kann die Seite nicht mehr.
    function flushPending() {
        var active = activeAutosave();
        document.querySelectorAll('.autosave').forEach(function (el) {
            if (el === active || el.queued > 0) save(el, true);
        });
    }

    // pagehide nur als Ersatz, wo beforeunload fehlt (mobile Browser) — sonst
    // ginge derselbe Wert zweimal hinaus.
    var flushedBeforeUnload = false;
    window.addEventListener('beforeunload', function (e) {
        var warn = hasUnsaved();
        flushPending();
        flushedBeforeUnload = true;
        if (warn) {
            e.preventDefault();
            e.returnValue = ''; // ältere Browser; der Text selbst wird nicht angezeigt
        }
    });
    window.addEventListener('pagehide', function () {
        if (!flushedBeforeUnload) flushPending();
    });
    window.addEventListener('pageshow', function () { flushedBeforeUnload = false; });

    /* ---- Kurz nach dem Laden einmal mit dem Server abgleichen ----
       Der beim Verlassen abgeschickte Wert (Neu laden, Zurück und wieder hin) kann
       erst gespeichert werden, nachdem die neue Seite schon ausgeliefert ist — sie
       zeigte dann den alten Stand, obwohl der neue in der Datenbank steht. Deshalb
       wird die Seite nach 1 s und nach 4 s still neu abgerufen und neuere Werte
       übernommen. Nur solange hier noch nichts gespeichert wurde (sonst ist der
       eigene Stand aktueller) und nur für Felder, die niemand angefasst hat. */
    function fieldSelector(el) {
        return '.autosave[data-scope="' + el.dataset.scope + '"][data-field="' + el.dataset.field + '"]'
            + (el.dataset.syringe ? '[data-syringe="' + el.dataset.syringe + '"]' : '');
    }

    async function resync() {
        if (saveSeq !== 0) return;
        try {
            var resp = await fetch(window.location.href, { cache: 'no-store' });
            if (!resp.ok) return;
            var doc = new DOMParser().parseFromString(await resp.text(), 'text/html');
            if (saveSeq !== 0) return;

            var changed = false;
            document.querySelectorAll('.autosave').forEach(function (el) {
                var fresh = doc.querySelector(fieldSelector(el));
                if (!fresh || fresh.value === el.value) return;
                if (el === document.activeElement || el.value !== el.dataset.savedValue) return;
                el.value = fresh.value;
                el.dataset.savedValue = fresh.value;
                el.className = fresh.className;
                el.readOnly = fresh.readOnly;
                changed = true;
            });
            if (!changed) return;

            var freshBox = doc.getElementById('valve-skip');
            if (freshBox && skipBox) skipBox.checked = freshBox.checked;

            doc.querySelectorAll('[id^="overall-"], [id^="head-result-"]').forEach(function (fresh) {
                var cell = document.getElementById(fresh.id);
                if (cell) {
                    cell.textContent = fresh.textContent;
                    cell.className = fresh.className;
                }
            });
            var freshSummary = doc.querySelector('.summary');
            var summary = document.querySelector('.summary');
            if (freshSummary && summary) summary.replaceWith(document.importNode(freshSummary, true));
        } catch (e) {
            // Nur eine Absicherung — ohne Abgleich bleibt der geladene Stand stehen.
        }
    }
    window.setTimeout(resync, 1000);
    window.setTimeout(resync, 4000);

    /* ---- Links erst nach laufenden Speichervorgängen folgen ----
       Ein Klick auf einen Link nimmt dem Feld schon bei mousedown den Fokus und
       stößt das Speichern an; die Folgeseite (Bericht, Drucken, Sprachwechsel)
       würde sonst womöglich vor dem Speichern ausgeliefert und den alten Wert
       zeigen. Höchstens 3 s warten, dann trotzdem weiter. */
    document.addEventListener('click', function (e) {
        var a = e.target.closest ? e.target.closest('a[href]') : null;
        if (!a || !inflight.length || e.defaultPrevented || e.button !== 0
            || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey || a.target) return;
        e.preventDefault();
        var timeout = new Promise(function (resolve) { window.setTimeout(resolve, 3000); });
        Promise.race([Promise.all(inflight.slice()), timeout]).then(function () {
            window.location.href = a.href;
        });
    });
})();
