<?php
declare(strict_types=1);
require __DIR__ . "/../config/db.php";
require __DIR__ . "/../config/session.php";
require_login();
$pageTitle = "Bank Reconciliation (BRS)";
require __DIR__ . "/../includes/header.php";
?>
<div class="card-panel">
  <h6 class="fw-bold mb-2"><i class="bi bi-cone-striped"></i> Bank Reconciliation (BRS) — Phase 2</h6>
  <p class="text-muted mb-2">Not built yet in this scaffold. Implementation plan (schema already supports it):</p>
  <p>Table bank_reconciliation already models this: mark each bank-ledger voucher_entry with bank_date once it appears on the statement; unreconciled rows are the BRS differences. Build a screen to (a) import bank statement CSV, (b) auto-match by amount+date, (c) manually tick remaining entries.</p>
</div>
<?php require __DIR__ . "/../includes/footer.php"; ?>
