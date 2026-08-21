<?php
declare(strict_types=1);
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/session.php';
require_login();
require __DIR__ . '/../includes/LedgerEngine.php';
$cid = current_company_id();
$engine = new LedgerEngine($pdo, $cid);
$asOn = $_GET['as_on'] ?? date('Y-m-d');
$tb = $engine->trialBalance($asOn);

$pageTitle = 'Trial Balance';
require __DIR__ . '/../includes/header.php';
?>
<div class="card-panel">
  <form method="get" class="d-flex gap-2 align-items-end mb-3">
    <div><label class="form-label small">As on</label><input type="date" name="as_on" value="<?= htmlspecialchars($asOn) ?>" class="form-control form-control-sm"></div>
    <button class="btn btn-sm btn-brand">Refresh</button>
    <a href="/vouchers/import_export.php?export=trial_balance&as_on=<?= $asOn ?>" class="btn btn-sm btn-outline-secondary ms-auto"><i class="bi bi-file-earmark-excel"></i> Export Excel</a>
  </form>
  <table class="acct-table">
    <thead><tr><th>Ledger</th><th>Group</th><th class="num">Debit</th><th class="num">Credit</th></tr></thead>
    <tbody>
    <?php foreach ($tb['rows'] as $r): ?>
      <tr>
        <td><?= htmlspecialchars($r['name']) ?></td>
        <td><?= htmlspecialchars($r['group_name']) ?></td>
        <td class="num"><?= $r['debit'] ? number_format($r['debit'], 2) : '' ?></td>
        <td class="num"><?= $r['credit'] ? number_format($r['credit'], 2) : '' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr class="fw-bold border-top">
        <td colspan="2">Total</td>
        <td class="num"><?= number_format($tb['total_debit'], 2) ?></td>
        <td class="num"><?= number_format($tb['total_credit'], 2) ?></td>
      </tr>
    </tfoot>
  </table>
  <?php if (round($tb['total_debit'],2) !== round($tb['total_credit'],2)): ?>
    <div class="alert alert-danger mt-2">Trial balance does not tally — check for unposted / partial entries.</div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
