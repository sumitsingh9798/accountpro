<?php
declare(strict_types=1);
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/session.php';
require_login();
$cid = current_company_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $stmt = $pdo->prepare("INSERT INTO account_groups (company_id,name,parent_id,nature,affects_gross_profit,schedule6_head) VALUES (?,?,?,?,?,?)");
    $stmt->execute([
        $cid,
        trim($_POST['name']),
        $_POST['parent_id'] ?: null,
        $_POST['nature'],
        isset($_POST['affects_gross_profit']) ? 1 : 0,
        trim($_POST['schedule6_head'] ?? '') ?: null,
    ]);
    header('Location: /masters/groups.php');
    exit;
}

$groups = $pdo->prepare("SELECT g.*, p.name AS parent_name FROM account_groups g LEFT JOIN account_groups p ON p.id=g.parent_id WHERE g.company_id=? ORDER BY g.nature, g.name");
$groups->execute([$cid]);
$allGroups = $groups->fetchAll();

$pageTitle = 'Account Groups';
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-4">
  <div class="col-lg-4">
    <div class="card-panel">
      <h6 class="fw-bold mb-3">New Group</h6>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <div class="mb-2"><label class="form-label small">Group Name</label><input name="name" class="form-control form-control-sm" required></div>
        <div class="mb-2"><label class="form-label small">Under (Parent Group)</label>
          <select name="parent_id" class="form-select form-select-sm">
            <option value="">— Primary Group —</option>
            <?php foreach ($allGroups as $g): ?><option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2"><label class="form-label small">Nature</label>
          <select name="nature" class="form-select form-select-sm" required>
            <option value="asset">Asset</option><option value="liability">Liability</option>
            <option value="income">Income</option><option value="expense">Expense</option>
          </select>
        </div>
        <div class="mb-2"><label class="form-label small">Schedule VI Head (optional)</label><input name="schedule6_head" class="form-control form-control-sm" placeholder="e.g. Trade Receivables"></div>
        <div class="form-check mb-3"><input type="checkbox" class="form-check-input" name="affects_gross_profit" id="agp"><label class="form-check-label small" for="agp">Direct Income/Expense (affects Gross Profit)</label></div>
        <button class="btn btn-brand btn-sm w-100">Create Group</button>
      </form>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card-panel">
      <h6 class="fw-bold mb-3">All Groups</h6>
      <table class="acct-table">
        <thead><tr><th>Name</th><th>Under</th><th>Nature</th><th>Schedule VI Head</th></tr></thead>
        <tbody>
        <?php foreach ($allGroups as $g): ?>
          <tr>
            <td><?= htmlspecialchars($g['name']) ?><?= $g['is_system'] ? ' <span class="badge bg-secondary">system</span>' : '' ?></td>
            <td><?= htmlspecialchars($g['parent_name'] ?? '—') ?></td>
            <td><span class="text-capitalize"><?= htmlspecialchars($g['nature']) ?></span></td>
            <td><?= htmlspecialchars($g['schedule6_head'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
