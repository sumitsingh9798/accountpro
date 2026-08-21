<?php
declare(strict_types=1);
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/session.php';
require_login();
require __DIR__ . '/../includes/OutstandingEngine.php';
$cid = current_company_id();
$engine = new OutstandingEngine($pdo, $cid);

$side = $_GET['side'] ?? 'debtors'; // debtors | creditors
$groupBy = $_GET['group_by'] ?? 'ledger'; // ledger | invoice | group
$asOn = $_GET['as_on'] ?? date('Y-m-d');

$data = $engine->outstandingReport($side, $groupBy, $asOn);

$pageTitle = ($side === 'debtors' ? 'Receivable' : 'Payable') . ' Outstanding & Aging';
require __DIR__ . '/../includes/header.php';

function agingClass(string $bucket): string {
    return match($bucket) {
        'Not Due' => 'badge-aging-0',
        '1-30 Days' => 'badge-aging-1',
        '31-60 Days' => 'badge-aging-2',
        default => 'badge-aging-3',
    };
}
?>
<div class="card-panel">
  <form method="get" class="d-flex gap-2 align-items-end mb-3 flex-wrap">
    <input type="hidden" name="side" value="<?= htmlspecialchars($side) ?>">
    <div><label class="form-label small">As on</label><input type="date" name="as_on" value="<?= htmlspecialchars($asOn) ?>" class="form-control form-control-sm"></div>
    <div><label class="form-label small">Group By</label>
      <select name="group_by" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="ledger" <?= $groupBy==='ledger'?'selected':'' ?>>Vendor / Customer wise</option>
        <option value="invoice" <?= $groupBy==='invoice'?'selected':'' ?>>Invoice wise</option>
        <option value="group" <?= $groupBy==='group'?'selected':'' ?>>Group wise</option>
      </select>
    </div>
    <button class="btn btn-sm btn-brand">Refresh</button>
    <div class="ms-auto btn-group btn-group-sm">
      <a href="?side=debtors&group_by=<?= $groupBy ?>" class="btn btn-outline-secondary <?= $side==='debtors'?'active':'' ?>">Receivable</a>
      <a href="?side=creditors&group_by=<?= $groupBy ?>" class="btn btn-outline-secondary <?= $side==='creditors'?'active':'' ?>">Payable</a>
    </div>
  </form>

  <?php if ($groupBy === 'invoice'): ?>
    <table class="acct-table">
      <thead><tr><th>Party</th><th>Voucher No.</th><th>Date</th><th>Due Date</th><th class="num">Balance</th><th>Aging</th></tr></thead>
      <tbody>
      <?php foreach ($data as $inv): ?>
        <tr>
          <td><?= htmlspecialchars($inv['ledger_name']) ?></td>
          <td><?= htmlspecialchars($inv['voucher_no']) ?></td>
          <td><?= htmlspecialchars($inv['voucher_date']) ?></td>
          <td><?= htmlspecialchars($inv['due_date'] ?? $inv['voucher_date']) ?></td>
          <td class="num"><?= number_format($inv['balance'],2) ?></td>
          <td><span class="badge <?= agingClass($inv['aging_bucket']) ?>"><?= htmlspecialchars($inv['aging_bucket']) ?> (<?= $inv['days_overdue'] ?>d)</span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$data): ?><tr><td colspan="6" class="text-center text-muted py-3">No outstanding.</td></tr><?php endif; ?>
      </tbody>
    </table>
  <?php else: ?>
    <table class="acct-table">
      <thead><tr><th><?= $groupBy==='group'?'Group':'Party' ?></th><th class="num">Not Due</th><th class="num">1-30</th><th class="num">31-60</th><th class="num">61-90</th><th class="num">91-120</th><th class="num">120+</th><th class="num">Total</th></tr></thead>
      <tbody>
      <?php $grandTotal = 0; foreach ($data as $row): $grandTotal += $row['total']; ?>
        <tr>
          <td><?= htmlspecialchars($row['name']) ?></td>
          <td class="num"><?= number_format($row['buckets']['Not Due'],2) ?></td>
          <td class="num"><?= number_format($row['buckets']['1-30 Days'],2) ?></td>
          <td class="num"><?= number_format($row['buckets']['31-60 Days'],2) ?></td>
          <td class="num"><?= number_format($row['buckets']['61-90 Days'],2) ?></td>
          <td class="num"><?= number_format($row['buckets']['91-120 Days'],2) ?></td>
          <td class="num text-credit"><?= number_format($row['buckets']['120+ Days'],2) ?></td>
          <td class="num fw-bold"><?= number_format($row['total'],2) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$data): ?><tr><td colspan="8" class="text-center text-muted py-3">No outstanding.</td></tr><?php endif; ?>
      </tbody>
      <?php if ($data): ?><tfoot><tr class="fw-bold border-top"><td colspan="7">Grand Total</td><td class="num"><?= number_format($grandTotal,2) ?></td></tr></tfoot><?php endif; ?>
    </table>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
