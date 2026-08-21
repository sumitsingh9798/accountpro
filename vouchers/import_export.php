<?php
declare(strict_types=1);
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/session.php';
require_login();
require __DIR__ . '/../includes/LedgerEngine.php';
$cid = current_company_id();
$engine = new LedgerEngine($pdo, $cid);

// ---- EXPORT: ?export=vouchers | ledgers | trial_balance ----
if (!empty($_GET['export'])) {
    $type = $_GET['export'];
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $type . '_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');

    if ($type === 'ledgers') {
        fputcsv($out, ['Name','Group','Opening Balance','Type','GSTIN','State','Email']);
        $stmt = $pdo->prepare("SELECT la.*, ag.name grp FROM ledger_accounts la JOIN account_groups ag ON ag.id=la.group_id WHERE la.company_id=?");
        $stmt->execute([$cid]);
        foreach ($stmt->fetchAll() as $r) {
            fputcsv($out, [$r['name'], $r['grp'], $r['opening_balance'], $r['opening_balance_type'], $r['gstin'], $r['state'], $r['email']]);
        }
    } elseif ($type === 'trial_balance') {
        $tb = $engine->trialBalance($_GET['as_on'] ?? date('Y-m-d'));
        fputcsv($out, ['Ledger','Group','Debit','Credit']);
        foreach ($tb['rows'] as $r) fputcsv($out, [$r['name'], $r['group_name'], $r['debit'], $r['credit']]);
    } else { // vouchers
        fputcsv($out, ['Voucher No','Date','Type','Ledger','Dr/Cr','Amount','Narration']);
        $stmt = $pdo->prepare(
            "SELECT v.voucher_no, v.voucher_date, vt.name AS type_name, la.name AS ledger_name, ve.dr_cr, ve.amount, v.narration
             FROM vouchers v JOIN voucher_types vt ON vt.id=v.voucher_type_id
             JOIN voucher_entries ve ON ve.voucher_id = v.id
             JOIN ledger_accounts la ON la.id = ve.ledger_id
             WHERE v.company_id=? ORDER BY v.voucher_date DESC"
        );
        $stmt->execute([$cid]);
        foreach ($stmt->fetchAll() as $r) {
            fputcsv($out, [$r['voucher_no'], $r['voucher_date'], $r['type_name'], $r['ledger_name'], $r['dr_cr'], $r['amount'], $r['narration']]);
        }
    }
    fclose($out);
    exit;
}

// ---- IMPORT: ledgers CSV (Name,Group,OpeningBalance,Type) ----
$importMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['csv_file'])) {
    csrf_check();
    $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
    $header = fgetcsv($handle); // skip header row
    $groupCache = [];
    $count = 0;
    while (($row = fgetcsv($handle)) !== false) {
        [$name, $groupName, $ob, $obType] = array_pad($row, 4, null);
        if (!$name) continue;
        if (!isset($groupCache[$groupName])) {
            $g = $pdo->prepare("SELECT id FROM account_groups WHERE company_id=? AND name=?");
            $g->execute([$cid, $groupName]);
            $gid = $g->fetchColumn();
            if (!$gid) continue; // group must exist first
            $groupCache[$groupName] = $gid;
        }
        $ins = $pdo->prepare("INSERT INTO ledger_accounts (company_id, group_id, name, opening_balance, opening_balance_type) VALUES (?,?,?,?,?)");
        $ins->execute([$cid, $groupCache[$groupName], $name, $ob ?: 0, $obType === 'credit' ? 'credit' : 'debit']);
        $count++;
    }
    fclose($handle);
    $importMessage = "Imported {$count} ledger accounts.";
}

$pageTitle = 'Excel / CSV Import & Export';
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-4">
  <div class="col-md-6">
    <div class="card-panel">
      <h6 class="fw-bold mb-3">Export</h6>
      <div class="d-flex flex-column gap-2">
        <a href="?export=ledgers" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download"></i> Export Chart of Accounts (CSV)</a>
        <a href="?export=vouchers" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download"></i> Export All Vouchers (CSV)</a>
        <a href="?export=trial_balance" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download"></i> Export Trial Balance (CSV)</a>
      </div>
      <p class="small text-muted mt-2 mb-0">CSV opens natively in Excel/Google Sheets. Swap to PhpSpreadsheet for native .xlsx formatting if needed.</p>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card-panel">
      <h6 class="fw-bold mb-3">Import Chart of Accounts</h6>
      <?php if ($importMessage): ?><div class="alert alert-success py-2"><?= htmlspecialchars($importMessage) ?></div><?php endif; ?>
      <p class="small text-muted">CSV columns: <code>Name, Group, OpeningBalance, Type(debit/credit)</code>. The Group must already exist under Masters → Account Groups.</p>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="file" name="csv_file" accept=".csv" class="form-control form-control-sm mb-2" required>
        <button class="btn btn-brand btn-sm">Import</button>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
