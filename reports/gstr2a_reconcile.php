<?php
declare(strict_types=1);
require __DIR__ . "/../config/db.php";
require __DIR__ . "/../config/session.php";
require_login();
$pageTitle = "GSTR-2A Auto-Reconciliation";
require __DIR__ . "/../includes/header.php";
?>
<div class="card-panel">
  <h6 class="fw-bold mb-2"><i class="bi bi-cone-striped"></i> GSTR-2A Auto-Reconciliation — Phase 2</h6>
  <p class="text-muted mb-2">Not built yet in this scaffold. Implementation plan (schema already supports it):</p>
  <p>Table gstr2a_upload already exists: (1) upload the GSTR-2A Excel/JSON from the GST portal into this table, (2) auto-match each row to a Purchase voucher by supplier GSTIN + invoice_no + amount (tolerance), (3) flag mismatched/not_in_books rows for follow-up - exactly the workflow requested.</p>
</div>
<?php require __DIR__ . "/../includes/footer.php"; ?>
