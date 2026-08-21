<?php
declare(strict_types=1);
require __DIR__ . "/../config/db.php";
require __DIR__ . "/../config/session.php";
require_login();
$pageTitle = "Ratio Analysis";
require __DIR__ . "/../includes/header.php";
?>
<div class="card-panel">
  <h6 class="fw-bold mb-2"><i class="bi bi-cone-striped"></i> Ratio Analysis — Phase 2</h6>
  <p class="text-muted mb-2">Not built yet in this scaffold. Implementation plan (schema already supports it):</p>
  <p>Compute standard ratios (Current Ratio, Quick Ratio, Debt-Equity, Gross/Net Profit %, ROCE, Inventory/Debtor Turnover) directly from LedgerEngine::trialBalance / profitAndLoss / balanceSheet outputs - no new tables needed.</p>
</div>
<?php require __DIR__ . "/../includes/footer.php"; ?>
