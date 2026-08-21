<?php
declare(strict_types=1);
require __DIR__ . "/../config/db.php";
require __DIR__ . "/../config/session.php";
require_login();
$pageTitle = "Fund Flow Statement";
require __DIR__ . "/../includes/header.php";
?>
<div class="card-panel">
  <h6 class="fw-bold mb-2"><i class="bi bi-cone-striped"></i> Fund Flow Statement — Phase 2</h6>
  <p class="text-muted mb-2">Not built yet in this scaffold. Implementation plan (schema already supports it):</p>
  <p>Sources and Application of Funds - compare Balance Sheet at two dates (LedgerEngine::balanceSheet) and bucket the deltas into Long-term Sources vs Application.</p>
</div>
<?php require __DIR__ . "/../includes/footer.php"; ?>
