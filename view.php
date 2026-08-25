<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';
require __DIR__ . '/icons.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM inspections WHERE id = :id');
$stmt->execute([':id' => $id]);
$insp = $stmt->fetch();

if (!$insp) {
    http_response_code(404);
    die(h(t('not_found')) . ' <a href="index.php">' . h(t('back_to_overview')) . '</a>');
}

$stmt2 = $pdo->prepare('SELECT * FROM syringe_readings WHERE inspection_id = :id');
$stmt2->execute([':id' => $id]);
$byLetter = [];
foreach ($stmt2->fetchAll() as $r) {
    $byLetter[$r['syringe']] = $r;
}

$eval      = evaluateInspection($insp, $byLetter);
$columns   = measurementColumns();
$groups    = measurementGroups();
$headMeas  = headMeasurements();
$serial    = $insp['serial_number'] !== '' ? $insp['serial_number'] : '—';
?>
<!DOCTYPE html>
<html lang="<?= h(currentLang()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h(t('report_title', (int)$insp['id'])) ?> — <?= h($serial) ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="report">

<div class="topbar no-print">
    <div>
        <h1><?= h(t('report_title', (int)$insp['id'])) ?></h1>
        <div class="sub">S/N <?= h($serial) ?> — <?= h($insp['inspection_date']) ?></div>
    </div>
    <div class="actions">
        <?= langSwitcher() ?>
        <?= iconLinkBtn('index.php', 'back', t('btn_back'), 'ghost-onnavy') ?>
        <?= iconLinkBtn('edit.php?id=' . (int)$insp['id'], 'pencil', t('btn_edit'), 'ghost-onnavy') ?>
        <button type="button" class="icon-btn primary" title="<?= h(t('btn_print')) ?>"
                aria-label="<?= h(t('btn_print')) ?>" onclick="window.print()"><?= svgIcon('printer') ?></button>
    </div>
</div>

<div class="wrap wide sheet">

    <div class="card page-block">
        <h2><?= h(t('report_heading')) ?></h2>
        <dl class="head-info">
            <div><dt><?= h(t('label_head_model')) ?></dt><dd><?= h($insp['head_model']) ?></dd></div>
            <div><dt><?= h(t('label_serial')) ?></dt><dd><?= h($serial) ?></dd></div>
            <div><dt><?= h(t('label_date')) ?></dt><dd><?= h($insp['inspection_date']) ?></dd></div>
            <div><dt><?= h(t('label_technician')) ?></dt><dd><?= h($insp['technician'] ?: '—') ?></dd></div>
        </dl>
    </div>

    <div class="card page-block">
        <h2 class="teal"><?= h(t('section_head_meas')) ?></h2>
        <table class="spec compact">
            <thead>
                <tr>
                    <th class="label"><?= h(t('th_parameter')) ?></th>
                    <th><?= h(t('th_standard')) ?></th>
                    <th><?= h(t('th_reading')) ?></th>
                    <th><?= h(t('th_result')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($headMeas as $field => $m):
                    $val = $insp[$field] !== null ? (float)$insp[$field] : null;
                    $res = $eval['head_results'][$field];
                ?>
                <tr>
                    <td class="label"><?= h(t($m['label'])) ?></td>
                    <td><?= h($m['spec']) ?></td>
                    <td class="mono <?= h(cellClass($val, $field)) ?>"><?= h(fmtCell($val)) ?></td>
                    <td class="result-<?= $res ?: 'blank' ?>"><?= $res ?: h(t('pending')) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card table-card">
        <h2 class="teal"><?= h(t('report_shaft_table')) ?></h2>
        <div class="table-scroll">
        <table class="spec grid">
            <thead>
                <tr>
                    <th rowspan="2" class="col-syringe"><?= h(t('th_shaft')) ?></th>
                    <?php foreach ($groups as $group => $span): ?>
                        <th colspan="<?= (int)$span ?>"><?= h($group) ?></th>
                    <?php endforeach; ?>
                    <th rowspan="2" class="col-overall"><?= h(t('th_overall')) ?></th>
                </tr>
                <tr>
                    <?php foreach ($columns as $c): ?>
                        <th><?= h($c['label']) ?><br><span class="spec-note"><?= h($c['spec']) ?></span></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach (syringeLetters() as $letter):
                    $r   = $byLetter[$letter] ?? [];
                    $res = evaluateSyringe($r, $letter);
                ?>
                <tr>
                    <td class="col-syringe syringe<?= $letter === IHS_REF_SHAFT ? ' ref-shaft' : '' ?>"><?= h($letter) ?></td>
                    <?php foreach ($columns as $key => $c):
                        $val = isset($r[$key]) && $r[$key] !== null ? (float)$r[$key] : null;
                    ?>
                    <td class="mono <?= h(cellClass($val, $key, $letter)) ?>"><?= h(fmtCell($val)) ?></td>
                    <?php endforeach; ?>
                    <td class="col-overall result-<?= $res ?: 'blank' ?>"><?= $res ?: '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <div class="legend">
            <span class="legend-ok"><span class="swatch swatch-ok"></span><?= h(t('legend_ok')) ?></span>
            <span><span class="swatch swatch-warn"></span><?= h(t('legend_warn')) ?></span>
            <span><span class="swatch swatch-bad"></span><?= h(t('legend_bad')) ?></span>
            <span>—&nbsp;<?= h(t('legend_empty')) ?></span>
        </div>
    </div>

    <!-- Die Zusammenfassung bleibt im Ausdruck als Einheit zusammen -->
    <div class="card page-block summary-card">
        <h2><?= h(t('summary')) ?></h2>
        <?= renderSummary($eval) ?>
    </div>

</div>

<script src="assets/app.js"></script>
</body>
</html>
