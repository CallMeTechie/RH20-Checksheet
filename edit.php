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

$eval     = evaluateInspection($insp, $byLetter);
$columns  = measurementColumns();
$groups   = measurementGroups();
$headMeas = headMeasurements();
?>
<!DOCTYPE html>
<html lang="<?= h(currentLang()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h(t('edit_title', (int)$insp['id'])) ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body data-inspection-id="<?= (int)$insp['id'] ?>"
      data-field-order="<?= h(implode(',', syringeFields())) ?>"
      data-head-order="<?= h(implode(',', array_keys($headMeas))) ?>"
      data-t-saving="<?= h(t('status_saving')) ?>"
      data-t-saved="<?= h(t('status_saved', '{time}')) ?>"
      data-t-save-error="<?= h(t('status_save_error')) ?>"
      data-t-invalid="<?= h(t('status_invalid', '{value}')) ?>"
      data-t-tip-invalid="<?= h(t('tip_invalid')) ?>"
      data-t-tip-failed="<?= h(t('tip_save_failed')) ?>"
      data-t-pending="<?= h(t('pending')) ?>">

<div class="topbar no-print">
    <div>
        <h1><?= h(t('edit_title', (int)$insp['id'])) ?> <span class="sub-inline"><?= h($insp['serial_number'] ?: t('no_serial')) ?></span></h1>
        <div class="sub" id="save-status"><?= h(t('status_ready')) ?></div>
    </div>
    <div class="actions">
        <?= langSwitcher() ?>
        <?= iconLinkBtn('index.php', 'back', t('btn_back'), 'ghost-onnavy') ?>
        <?= iconLinkBtn('view.php?id=' . (int)$insp['id'], 'eye', t('btn_view'), 'ghost-onnavy') ?>
        <?= iconFormBtn('delete.php', 'trash', t('btn_delete'), 'ghost-onnavy',
                ['id' => (int)$insp['id']],
                t('confirm_delete_short', (int)$insp['id'])) ?>
    </div>
</div>

<div class="wrap wide">

    <div class="card">
        <h2><?= h(t('head_information')) ?></h2>
        <div class="field-grid">
            <div>
                <label for="f_head_model"><?= h(t('label_head_model')) ?></label>
                <input type="text" id="f_head_model" class="autosave" data-scope="header" data-field="head_model"
                       value="<?= h($insp['head_model']) ?>">
            </div>
            <div>
                <label for="f_serial"><?= h(t('label_serial')) ?></label>
                <input type="text" id="f_serial" class="autosave" data-scope="header" data-field="serial_number"
                       value="<?= h($insp['serial_number']) ?>">
            </div>
            <div>
                <label for="f_date"><?= h(t('label_date')) ?></label>
                <input type="date" id="f_date" class="autosave" data-scope="header" data-field="inspection_date"
                       value="<?= h($insp['inspection_date']) ?>">
            </div>
            <div>
                <label for="f_tech"><?= h(t('label_technician')) ?></label>
                <input type="text" id="f_tech" class="autosave" data-scope="header" data-field="technician"
                       value="<?= h($insp['technician']) ?>">
            </div>
        </div>
    </div>

    <div class="card">
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
                    <td>
                        <input type="text" inputmode="decimal" aria-label="<?= h(t($m['label'])) ?>"
                               class="autosave <?= h(cellClass($val, $field)) ?>"
                               data-scope="head" data-field="<?= h($field) ?>" data-numeric="1"
                               value="<?= h(fmtNum($val)) ?>">
                    </td>
                    <td id="head-result-<?= h($field) ?>" class="result-<?= $res ?: 'blank' ?>"><?= $res ?: h(t('pending')) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2 class="teal"><?= h(t('section_shaft_meas')) ?></h2>
        <p class="hint"><?= h(t('entry_hint', IHS_REF_SHAFT, (int)IHS_TARGET,
                fmtNum(IHS_TOL_REF), fmtNum(IHS_TOL_MAX), fmtNum(IHS_TOL_WARN))) ?></p>
        <div class="table-scroll">
        <table class="spec grid">
            <thead>
                <tr>
                    <th rowspan="2" class="col-syringe"><?= h(t('th_shaft')) ?></th>
                    <?php foreach ($groups as $group => $span): ?>
                        <th colspan="<?= (int)$span ?>"><?= h($group) ?></th>
                    <?php endforeach; ?>
                    <th rowspan="2" class="col-overall"><?= h(t('th_overall')) ?></th>
                    <th rowspan="2" class="col-remarks"><?= h(t('th_remarks')) ?></th>
                </tr>
                <tr>
                    <?php foreach ($columns as $c): ?>
                        <th><?= h($c['label']) ?><br><span class="spec-note"><?= h($c['spec']) ?></span></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach (syringeLetters() as $row => $letter):
                    $r   = $byLetter[$letter] ?? [];
                    $res = evaluateSyringe($r, $letter);
                ?>
                <tr>
                    <td class="col-syringe syringe<?= $letter === IHS_REF_SHAFT ? ' ref-shaft' : '' ?>"
                        <?= $letter === IHS_REF_SHAFT ? 'title="' . h(t('ref_shaft_tooltip')) . '"' : '' ?>><?= h($letter) ?></td>
                    <?php foreach ($columns as $key => $c):
                        $val = isset($r[$key]) && $r[$key] !== null ? (float)$r[$key] : null;
                    ?>
                    <td>
                        <input type="text" inputmode="decimal"
                               aria-label="<?= h($c['group'] . ' ' . $c['label'] . ' ' . t('th_shaft') . ' ' . $letter) ?>"
                               class="autosave <?= h(cellClass($val, $key, $letter)) ?>"
                               data-scope="syringe" data-field="<?= h($key) ?>" data-syringe="<?= h($letter) ?>"
                               data-row="<?= (int)$row ?>" data-numeric="1"
                               value="<?= h(fmtNum($val)) ?>">
                    </td>
                    <?php endforeach; ?>
                    <td id="overall-<?= h($letter) ?>" class="col-overall result-<?= $res ?: 'blank' ?>"><?= $res ?: '—' ?></td>
                    <td class="col-remarks">
                        <input type="text" class="autosave remarks"
                               aria-label="<?= h(t('th_remarks') . ' ' . t('th_shaft') . ' ' . $letter) ?>"
                               data-scope="syringe" data-field="remarks"
                               data-syringe="<?= h($letter) ?>" data-row="<?= (int)$row ?>"
                               value="<?= h($r['remarks'] ?? '') ?>">
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <div class="legend">
            <span><span class="swatch swatch-ok"></span><?= h(t('legend_ok')) ?></span>
            <span><span class="swatch swatch-warn"></span><?= h(t('legend_warn')) ?></span>
            <span><span class="swatch swatch-bad"></span><?= h(t('legend_bad')) ?></span>
        </div>
    </div>

    <div class="card">
        <h2><?= h(t('summary_section')) ?></h2>
        <?= renderSummary($eval) ?>
    </div>

    <div class="actions no-print" style="margin-bottom:40px">
        <?= iconLinkBtn('index.php', 'check', t('btn_done'), 'primary') ?>
        <?= iconLinkBtn('view.php?id=' . (int)$insp['id'], 'printer', t('btn_view'), 'secondary') ?>
    </div>

</div>

<script src="assets/app.js"></script>
</body>
</html>
