<?php
declare(strict_types=1);
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/session.php';
require_login();
$cid = current_company_id();

$typeMap = ['sales'=>'Sales','purchase'=>'Purchase','receipt'=>'Receipt','payment'=>'Payment','contra'=>'Contra','journal'=>'Journal','debit_note'=>'Debit Note','credit_note'=>'Credit Note'];
$typeKey = $_GET['type'] ?? '';
$where = "v.company_id = ?";
$params = [$cid];
if ($typeKey && isset($typeMap[$typeKey])) { $where .= " AND vt.name = ?"; $params[] = $typeMap[$typeKey]; }

$stmt = $pdo->prepare(
    "SELECT v.id, v.voucher_no, v.voucher_date, v.narration, vt.name AS type_name,
            (SELECT SUM(amount) FROM voucher_entries WHERE voucher_id=v.id AND dr_cr='debit') AS total
     FROM vouchers v JOIN voucher_types vt ON vt.id = v.voucher_type_id
     WHERE $where ORDER BY v.voucher_date DESC, v.id DESC LIMIT 200"
);
$stmt->execute($params);
$vouchers = $stmt->fetchAll();

$pageTitle = 'Voucher Register' . ($typeKey ? ' — ' . $typeMap[$typeKey] : '');
require __DIR__ . '/../includes/header.php';
?>
<div class="card-panel">
  <table class="acct-table">
    <thead><tr><th>Date</th><th>Type</th><th>Voucher No.</th><th>Narration</th><th class="num">Amount</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($vouchers as $v): ?>
      <tr>
        <td><?= htmlspecialchars($v['voucher_date']) ?></td>
        <td><?= htmlspecialchars($v['type_name']) ?></td>
        <td><?= htmlspecialchars($v['voucher_no']) ?></td>
        <td><?= htmlspecialchars($v['narration'] ?? '') ?></td>
        <td class="num"><?= number_format((float)$v['total'], 2) ?></td>
        <td>
          <?php $slug = array_search($v['type_name'], $typeMap); ?>
          <a href="/vouchers/voucher_entry.php?type=<?= $slug ?>&clone=<?= $v['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Clone this entry"><i class="bi bi-files"></i> Clone</a>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$vouchers): ?><tr><td colspan="6" class="text-center text-muted py-4">No vouchers yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
