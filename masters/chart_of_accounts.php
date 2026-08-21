<?php
declare(strict_types=1);
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/session.php';
require_login();
require __DIR__ . '/../includes/LedgerEngine.php';
$cid = current_company_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $stmt = $pdo->prepare(
        "INSERT INTO ledger_accounts
         (company_id, group_id, name, alias, opening_balance, opening_balance_type, as_on_date,
          address, gstin, pan, state, contact_person, phone, email, credit_period_days,
          bank_account_no, bank_ifsc, is_bank_cash, tds_applicable, tds_section)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
    );
    $stmt->execute([
        $cid, $_POST['group_id'], trim($_POST['name']), trim($_POST['alias'] ?? ''),
        $_POST['opening_balance'] ?: 0, $_POST['opening_balance_type'], $_POST['as_on_date'] ?: null,
        trim($_POST['address'] ?? ''), trim($_POST['gstin'] ?? ''), trim($_POST['pan'] ?? ''),
        trim($_POST['state'] ?? ''), trim($_POST['contact_person'] ?? ''), trim($_POST['phone'] ?? ''),
        trim($_POST['email'] ?? ''), $_POST['credit_period_days'] ?: 0,
        trim($_POST['bank_account_no'] ?? ''), trim($_POST['bank_ifsc'] ?? ''),
        isset($_POST['is_bank_cash']) ? 1 : 0, isset($_POST['tds_applicable']) ? 1 : 0,
        trim($_POST['tds_section'] ?? ''),
    ]);
    header('Location: /masters/chart_of_accounts.php');
    exit;
}

$groupsStmt = $pdo->prepare("SELECT id, name FROM account_groups WHERE company_id=? ORDER BY name");
$groupsStmt->execute([$cid]);
$groups = $groupsStmt->fetchAll();

$ledgersStmt = $pdo->prepare(
    "SELECT la.*, ag.name AS group_name FROM ledger_accounts la
     JOIN account_groups ag ON ag.id = la.group_id
     WHERE la.company_id=? ORDER BY la.name"
);
$ledgersStmt->execute([$cid]);
$ledgers = $ledgersStmt->fetchAll();
$engine = new LedgerEngine($pdo, $cid);

$pageTitle = 'Chart of Accounts';
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-4">
  <div class="col-lg-4">
    <div class="card-panel">
      <h6 class="fw-bold mb-3">New Ledger Account</h6>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <div class="mb-2"><label class="form-label small">Name</label><input name="name" class="form-control form-control-sm" required></div>
        <div class="mb-2"><label class="form-label small">Alias</label><input name="alias" class="form-control form-control-sm"></div>
        <div class="mb-2"><label class="form-label small">Under Group</label>
          <select name="group_id" class="form-select form-select-sm" required>
            <?php foreach ($groups as $g): ?><option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="row g-2 mb-2">
          <div class="col-7"><label class="form-label small">Opening Balance</label><input type="number" step="0.01" name="opening_balance" class="form-control form-control-sm" value="0"></div>
          <div class="col-5"><label class="form-label small">Type</label>
            <select name="opening_balance_type" class="form-select form-select-sm">
              <option value="debit">Debit</option><option value="credit">Credit</option>
            </select>
          </div>
        </div>
        <div class="mb-2"><label class="form-label small">As-on Date</label><input type="date" name="as_on_date" class="form-control form-control-sm"></div>
        <div class="mb-2"><label class="form-label small">GSTIN</label><input name="gstin" class="form-control form-control-sm"></div>
        <div class="mb-2"><label class="form-label small">State</label><input name="state" class="form-control form-control-sm"></div>
        <div class="mb-2"><label class="form-label small">Credit Period (days)</label><input type="number" name="credit_period_days" class="form-control form-control-sm" value="0"></div>
        <div class="mb-2"><label class="form-label small">Email (for overdue reminders)</label><input type="email" name="email" class="form-control form-control-sm"></div>
        <div class="form-check mb-2"><input type="checkbox" class="form-check-input" name="is_bank_cash" id="ibc"><label class="form-check-label small" for="ibc">This is a Cash / Bank ledger</label></div>
        <div class="form-check mb-3"><input type="checkbox" class="form-check-input" name="tds_applicable" id="tdsa"><label class="form-check-label small" for="tdsa">TDS Applicable</label></div>
        <input name="tds_section" class="form-control form-control-sm mb-3" placeholder="TDS Section e.g. 194C">
        <button class="btn btn-brand btn-sm w-100">Create Ledger</button>
      </form>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card-panel">
      <h6 class="fw-bold mb-3">All Ledgers (<?= count($ledgers) ?>)</h6>
      <input type="text" id="ledgerSearch" class="form-control form-control-sm mb-2" placeholder="Search ledger...">
      <table class="acct-table" id="ledgerTable">
        <thead><tr><th>Name</th><th>Group</th><th class="num">Opening Bal.</th><th class="num">Closing Bal.</th></tr></thead>
        <tbody>
        <?php foreach ($ledgers as $l):
            $closing = $engine->ledgerBalance((int)$l['id']); ?>
          <tr>
            <td><a href="/reports/ledger_view.php?id=<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></a></td>
            <td><?= htmlspecialchars($l['group_name']) ?></td>
            <td class="num"><?= number_format((float)$l['opening_balance'], 2) ?> <?= strtoupper(substr($l['opening_balance_type'],0,2)) ?></td>
            <td class="num <?= $closing['dr_cr']==='debit'?'text-debit':'text-credit' ?>"><?= number_format($closing['balance'], 2) ?> <?= strtoupper(substr($closing['dr_cr'],0,2)) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script>
document.getElementById('ledgerSearch').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('#ledgerTable tbody tr').forEach(tr => {
    tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
