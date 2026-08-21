<?php
declare(strict_types=1);
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/session.php';
require_login();
require __DIR__ . '/../includes/LedgerEngine.php';
$cid = current_company_id();
$engine = new LedgerEngine($pdo, $cid);

$comp = $pdo->prepare("SELECT fin_year_start FROM companies WHERE id=?");
$comp->execute([$cid]);
$fyStart = $comp->fetchColumn();

$asOn = $_GET['as_on'] ?? date('Y-m-d');
$pl = $engine->profitAndLoss($fyStart, $asOn);
$bs = $engine->balanceSheet($asOn, $pl['netProfit']);

$pageTitle = 'Balance Sheet';
require __DIR__ . '/../includes/header.php';
?>
<div class="card-panel">
  <form method="get" class="d-flex gap-2 align-items-end mb-3">
    <div><label class="form-label small">As on</label><input type="date" name="as_on" value="<?= htmlspecialchars($asOn) ?>" class="form-control form-control-sm"></div>
    <button class="btn btn-sm btn-brand">Refresh</button>
  </form>
  <div class="row">
    <div class="col-md-6">
      <h6 class="fw-bold">Liabilities</h6>
      <table class="acct-table">
        <?php foreach ($bs['liabilities'] as $l): ?>
          <tr><td><?= htmlspecialchars($l['name']) ?><br><small class="text-muted"><?= htmlspecialchars($l['group']) ?></small></td><td class="num"><?= number_format($l['amount'],2) ?></td></tr>
        <?php endforeach; ?>
        <tr class="fw-bold border-top"><td>Total</td><td class="num"><?= number_format($bs['totalLiabilities'],2) ?></td></tr>
      </table>
    </div>
    <div class="col-md-6">
      <h6 class="fw-bold">Assets</h6>
      <table class="acct-table">
        <?php foreach ($bs['assets'] as $a): ?>
          <tr><td><?= htmlspecialchars($a['name']) ?><br><small class="text-muted"><?= htmlspecialchars($a['group']) ?></small></td><td class="num"><?= number_format($a['amount'],2) ?></td></tr>
        <?php endforeach; ?>
        <tr class="fw-bold border-top"><td>Total</td><td class="num"><?= number_format($bs['totalAssets'],2) ?></td></tr>
      </table>
    </div>
  </div>
  <?php if (round($bs['totalAssets'],2) !== round($bs['totalLiabilities'],2)): ?>
    <div class="alert alert-warning mt-2">Balance sheet difference: <?= number_format(abs($bs['totalAssets']-$bs['totalLiabilities']),2) ?> — review unposted entries or missing groups.</div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
