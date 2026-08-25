<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';
require __DIR__ . '/icons.php';

$inspections = $pdo->query('SELECT * FROM inspections ORDER BY id DESC')->fetchAll();

$statusById = [];
if ($inspections) {
    $ids = array_column($inspections, 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM syringe_readings WHERE inspection_id IN ($in)");
    $stmt->execute($ids);
    $grouped = [];
    foreach ($stmt->fetchAll() as $s) {
        $grouped[$s['inspection_id']][$s['syringe']] = $s;
    }
    foreach ($inspections as $insp) {
        $statusById[$insp['id']] = evaluateInspection($insp, $grouped[$insp['id']] ?? []);
    }
}
?>
<!DOCTYPE html>
<html lang="<?= h(currentLang()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h(t('app_title')) ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="topbar">
    <div>
        <h1><?= h(t('app_title')) ?></h1>
    </div>
    <div class="actions">
        <?= langSwitcher() ?>
        <?= iconFormBtn('create.php', 'plus', t('btn_new'), 'primary') ?>
    </div>
</div>

<div class="wrap">
    <div class="card">
        <h2><?= h(t('saved_inspections')) ?></h2>

        <?php if (!$inspections): ?>
            <div class="empty-state">
                <p><?= h(t('none_saved')) ?></p>
                <form method="post" action="create.php">
                    <button type="submit" class="text-btn primary"><?= h(t('create_first')) ?></button>
                </form>
            </div>
        <?php else: ?>
        <table class="list">
            <thead>
                <tr>
                    <th><?= h(t('col_id')) ?></th>
                    <th><?= h(t('col_serial')) ?></th>
                    <th><?= h(t('col_head')) ?></th>
                    <th><?= h(t('col_date')) ?></th>
                    <th><?= h(t('col_technician')) ?></th>
                    <th><?= h(t('col_result')) ?></th>
                    <th><?= h(t('col_saved_at')) ?></th>
                    <th class="no-print"><?= h(t('col_actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($inspections as $insp): $st = $statusById[$insp['id']]; ?>
                <tr>
                    <td class="mono">#<?= (int)$insp['id'] ?></td>
                    <td><strong><?= h($insp['serial_number'] ?: '—') ?></strong></td>
                    <td><?= h($insp['head_model']) ?></td>
                    <td class="mono"><?= h($insp['inspection_date']) ?></td>
                    <td><?= h($insp['technician'] ?: '—') ?></td>
                    <td>
                        <span class="pill <?= h($st['status']) ?>"><?= h($st['status']) ?></span>
                        <span class="spec-note"><?= h(summaryVerdict($st)) ?></span>
                    </td>
                    <td class="mono spec-note"><?= h($insp['updated_at'] ?: $insp['created_at']) ?></td>
                    <td class="no-print">
                        <div class="actions">
                            <?= iconLinkBtn('edit.php?id=' . (int)$insp['id'], 'pencil', t('btn_edit'), 'secondary') ?>
                            <?= iconLinkBtn('view.php?id=' . (int)$insp['id'], 'eye', t('btn_view'), 'secondary') ?>
                            <?= iconFormBtn('delete.php', 'trash', t('btn_delete'), 'danger',
                                    ['id' => (int)$insp['id']],
                                    t('confirm_delete', (int)$insp['id'], $insp['serial_number'] ?: t('no_serial'))) ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<script src="assets/app.js"></script>
</body>
</html>
