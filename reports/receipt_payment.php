<?php
declare(strict_types=1);
require __DIR__ . "/../config/db.php";
require __DIR__ . "/../config/session.php";
require_login();
$pageTitle = "Receipt and Payment Report";
require __DIR__ . "/../includes/header.php";
?>
<div class="card-panel">
  <h6 class="fw-bold mb-2"><i class="bi bi-cone-striped"></i> Receipt and Payment Report — Phase 2</h6>
  <p class="text-muted mb-2">Not built yet in this scaffold. Implementation plan (schema already supports it):</p>
  <p>Cash-basis summary of all Receipt/Payment vouchers for a period, grouped by ledger - a straight filter on the vouchers/voucher_entries tables by voucher_type_id IN (Receipt,Payment).</p>
</div>
<?php require __DIR__ . "/../includes/footer.php"; ?>
