<?php
declare(strict_types=1);
require __DIR__ . "/../config/db.php";
require __DIR__ . "/../config/session.php";
require_login();
$pageTitle = "GSTR-1 Report";
require __DIR__ . "/../includes/header.php";
?>
<div class="card-panel">
  <h6 class="fw-bold mb-2"><i class="bi bi-cone-striped"></i> GSTR-1 Report — Phase 2</h6>
  <p class="text-muted mb-2">Not built yet in this scaffold. Implementation plan (schema already supports it):</p>
  <p>B2B/B2C invoice-wise outward supply summary sourced from vouchers where voucher_type=Sales, using gst_taxable_value/gst_cgst/gst_sgst/gst_igst - format matches the GSTR-1 JSON schema for direct portal upload.</p>
</div>
<?php require __DIR__ . "/../includes/footer.php"; ?>
