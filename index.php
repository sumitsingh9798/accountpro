<?php
declare(strict_types=1);
require __DIR__ . '/config/db.php';
require __DIR__ . '/config/session.php';
require_login();
require __DIR__ . '/includes/LedgerEngine.php';

$engine = new LedgerEngine($pdo, current_company_id());
$today = date('Y-m-d');
$fyStart = $pdo->prepare("SELECT fin_year_start, fin_year_end FROM companies WHERE id=?");
$fyStart->execute([current_company_id()]);
$fy = $fyStart->fetch();

$pl = $engine->profitAndLoss($fy['fin_year_start'] ?? date('Y-01-01'), $today);
$tb = $engine->trialBalance($today);

// Cash + Bank balance quick KPI
$cbStmt = $pdo->prepare("SELECT id FROM ledger_accounts WHERE company_id=? AND is_bank_cash=1");
$cbStmt->execute([current_company_id()]);
$cashBank = 0;
foreach ($cbStmt->fetchAll() as $r) {
    $b = $engine->ledgerBalance((int)$r['id'], $today);
    $cashBank += $b['dr_cr'] === 'debit' ? $b['balance'] : -$b['balance'];
}

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>
<div class="kpi-grid">
  <div class="kpi-card debit"><div class="label">Net Profit (YTD)</div><div class="value">₹<?= number_format($pl['netProfit'], 2) ?></div></div>
  <div class="kpi-card"><div class="label">Gross Profit</div><div class="value">₹<?= number_format($pl['grossProfit'], 2) ?></div></div>
  <div class="kpi-card credit"><div class="label">Cash &amp; Bank Balance</div><div class="value">₹<?= number_format($cashBank, 2) ?></div></div>
  <div class="kpi-card"><div class="label">Trial Balance Ledgers</div><div class="value"><?= count($tb['rows']) ?></div></div>
</div>

<div class="card-panel">
  <h6 class="mb-3 fw-bold">Quick Actions</h6>
  <div class="d-flex flex-wrap gap-2">
    <a href="/vouchers/voucher_entry.php?type=sales" class="btn btn-brand btn-sm"><i class="bi bi-cart-check"></i> New Sales</a>
    <a href="/vouchers/voucher_entry.php?type=purchase" class="btn btn-outline-secondary btn-sm">New Purchase</a>
    <a href="/vouchers/voucher_entry.php?type=receipt" class="btn btn-outline-secondary btn-sm">New Receipt</a>
    <a href="/vouchers/voucher_entry.php?type=payment" class="btn btn-outline-secondary btn-sm">New Payment</a>
    <a href="/reports/trial_balance.php" class="btn btn-outline-secondary btn-sm">Trial Balance</a>
    <a href="/reports/outstanding.php?side=debtors" class="btn btn-outline-secondary btn-sm">Receivable Aging</a>
  </div>
</div>

<div class="card-panel">
  <h6 class="mb-3 fw-bold">Trial Balance snapshot (as on <?= $today ?>)</h6>
  <table class="acct-table">
    <thead><tr><th>Ledger</th><th>Group</th><th class="num">Debit</th><th class="num">Credit</th></tr></thead>
    <tbody>
    <?php foreach (array_slice($tb['rows'], 0, 10) as $r): ?>
      <tr>
        <td><?= htmlspecialchars($r['name']) ?></td>
        <td><?= htmlspecialchars($r['group_name']) ?></td>
        <td class="num text-debit"><?= $r['debit'] ? number_format($r['debit'], 2) : '' ?></td>
        <td class="num text-credit"><?= $r['credit'] ? number_format($r['credit'], 2) : '' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <a href="/reports/trial_balance.php" class="small">View full trial balance →</a>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
