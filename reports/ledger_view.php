<?php
declare(strict_types=1);
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/session.php';
require_login();
require __DIR__ . '/../includes/LedgerEngine.php';
$cid = current_company_id();
$engine = new LedgerEngine($pdo, $cid);
$ledgerId = (int)($_GET['id'] ?? 0);

$l = $pdo->prepare("SELECT * FROM ledger_accounts WHERE id=? AND company_id=?");
$l->execute([$ledgerId, $cid]);
$ledger = $l->fetch();
if (!$ledger) { die('Ledger not found'); }

$entries = $pdo->prepare(
    "SELECT v.voucher_no, v.voucher_date, vt.name AS type_name, ve.dr_cr, ve.amount, v.narration
     FROM voucher_entries ve JOIN vouchers v ON v.id = ve.voucher_id JOIN voucher_types vt ON vt.id=v.voucher_type_id
     WHERE ve.ledger_id=? AND v.status='posted' ORDER BY v.voucher_date, v.id"
);
$entries->execute([$ledgerId]);
$rows = $entries->fetchAll();

$running = $ledger['opening_balance_type'] === 'debit' ? (float)$ledger['opening_balance'] : -(float)$ledger['opening_balance'];

$pageTitle = 'Ledger: ' . $ledger['name'];
require __DIR__ . '/../includes/header.php';
?>
<div class="card-panel">
  <table class="acct-table">
    <thead><tr><th>Date</th><th>Voucher</th><th>Type</th><th>Narration</th><th class="num">Debit</th><th class="num">Credit</th><th class="num">Balance</th></tr></thead>
    <tbody>
      <tr class="fw-bold"><td colspan="6">Opening Balance</td><td class="num"><?= number_format(abs($running),2) ?> <?= $running>=0?'DR':'CR' ?></td></tr>
      <?php foreach ($rows as $r):
        $running += $r['dr_cr'] === 'debit' ? (float)$r['amount'] : -(float)$r['amount']; ?>
        <tr>
          <td><?= htmlspecialchars($r['voucher_date']) ?></td>
          <td><?= htmlspecialchars($r['voucher_no']) ?></td>
          <td><?= htmlspecialchars($r['type_name']) ?></td>
          <td><?= htmlspecialchars($r['narration'] ?? '') ?></td>
          <td class="num"><?= $r['dr_cr']==='debit' ? number_format($r['amount'],2) : '' ?></td>
          <td class="num"><?= $r['dr_cr']==='credit' ? number_format($r['amount'],2) : '' ?></td>
          <td class="num"><?= number_format(abs($running),2) ?> <?= $running>=0?'DR':'CR' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
