<?php
declare(strict_types=1);
require __DIR__ . "/../config/db.php";
require __DIR__ . "/../config/session.php";
require_login();
$pageTitle = "TDS Working Report";
require __DIR__ . "/../includes/header.php";
?>
<div class="card-panel">
  <h6 class="fw-bold mb-2"><i class="bi bi-cone-striped"></i> TDS Working Report — Phase 2</h6>
  <p class="text-muted mb-2">Not built yet in this scaffold. Implementation plan (schema already supports it):</p>
  <p>Sum vouchers.tds_amount grouped by ledger_accounts.tds_section for the quarter/period, cross-check against ledger_accounts.tds_applicable - outputs the TDS payable/deducted working for Form 26Q filing.</p>
</div>
<?php require __DIR__ . "/../includes/footer.php"; ?>
