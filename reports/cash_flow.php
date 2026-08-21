<?php
declare(strict_types=1);
require __DIR__ . "/../config/db.php";
require __DIR__ . "/../config/session.php";
require_login();
$pageTitle = "Cash Flow Statement";
require __DIR__ . "/../includes/header.php";
?>
<div class="card-panel">
  <h6 class="fw-bold mb-2"><i class="bi bi-cone-striped"></i> Cash Flow Statement — Phase 2</h6>
  <p class="text-muted mb-2">Not built yet in this scaffold. Implementation plan (schema already supports it):</p>
  <p>Indirect-method cash flow: start from Net Profit (LedgerEngine::profitAndLoss), add back non-cash items, adjust for working-capital changes (Debtors/Creditors/Stock deltas) computed from two Trial Balance snapshots (opening vs closing date).</p>
</div>
<?php require __DIR__ . "/../includes/footer.php"; ?>
