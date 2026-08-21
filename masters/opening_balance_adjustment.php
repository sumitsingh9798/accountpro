<?php
declare(strict_types=1);
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/session.php';
require_login();
require __DIR__ . '/../includes/LedgerEngine.php';
$cid = current_company_id();
$engine = new LedgerEngine($pdo, $cid);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $stmt = $pdo->prepare("INSERT INTO opening_balance_adjustments (ledger_id, adjustment_type, amount, reason, adjusted_by) VALUES (?,?,?,?,?)");
    $stmt->execute([$_POST['ledger_id'], $_POST['adjustment_type'], $_POST['amount'], trim($_POST['reason'] ?? ''), current_user_id()]);
    header('Location: /masters/opening_balance_adjustment.php');
    exit;
}

$ledgers = $pdo->prepare("SELECT id, name FROM ledger_accounts WHERE company_id=? ORDER BY name");
$ledgers->execute([$cid]);
$ledgers = $ledgers->fetchAll();

$history = $pdo->prepare(
    "SELECT oba.*, la.name AS ledger_name, u.name AS user_name FROM opening_balance_adjustments oba
     JOIN ledger_accounts la ON la.id = oba.ledger_id
     JOIN users u ON u.id = oba.adjusted_by
     WHERE la.company_id = ? ORDER BY oba.adjusted_at DESC LIMIT 100"
);
$history->execute([$cid]);
$history = $history->fetchAll();

$pageTitle = 'Opening Balance Adjustment';
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-4">
  <div class="col-lg-4">
    <div class="card-panel">
      <h6 class="fw-bold mb-3">New Adjustment</h6>
      <p class="small text-muted">Corrects an opening balance without touching the original master entry — keeps a full audit trail.</p>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <div class="mb-2"><label class="form-label small">Ledger</label>
          <select name="ledger_id" class="form-select form-select-sm" required>
            <?php foreach ($ledgers as $l): ?><option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="row g-2 mb-2">
          <div class="col-6"><label class="form-label small">Type</label>
            <select name="adjustment_type" class="form-select form-select-sm">
              <option value="debit">Debit</option><option value="credit">Credit</option>
            </select>
          </div>
          <div class="col-6"><label class="form-label small">Amount</label><input type="number" step="0.01" name="amount" class="form-control form-control-sm" required></div>
        </div>
        <div class="mb-3"><label class="form-label small">Reason</label><input name="reason" class="form-control form-control-sm" placeholder="e.g. correcting migration error"></div>
        <button class="btn btn-brand btn-sm w-100">Apply Adjustment</button>
      </form>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card-panel">
      <h6 class="fw-bold mb-3">Adjustment History</h6>
      <table class="acct-table">
        <thead><tr><th>Date</th><th>Ledger</th><th>Type</th><th class="num">Amount</th><th>Reason</th><th>By</th></tr></thead>
        <tbody>
        <?php foreach ($history as $h): ?>
          <tr>
            <td><?= htmlspecialchars($h['adjusted_at']) ?></td>
            <td><?= htmlspecialchars($h['ledger_name']) ?></td>
            <td class="text-capitalize"><?= htmlspecialchars($h['adjustment_type']) ?></td>
            <td class="num"><?= number_format((float)$h['amount'],2) ?></td>
            <td><?= htmlspecialchars($h['reason']) ?></td>
            <td><?= htmlspecialchars($h['user_name']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
