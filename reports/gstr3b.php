<?php
declare(strict_types=1);
require __DIR__ . "/../config/db.php";
require __DIR__ . "/../config/session.php";
require_login();
$pageTitle = "GSTR-3B Report";
require __DIR__ . "/../includes/header.php";
?>
<div class="card-panel">
  <h6 class="fw-bold mb-2"><i class="bi bi-cone-striped"></i> GSTR-3B Report — Phase 2</h6>
  <p class="text-muted mb-2">Not built yet in this scaffold. Implementation plan (schema already supports it):</p>
  <p>Auto-computed from Sales (output tax) vs Purchase (input tax) vouchers for the period, netting CGST/SGST/IGST to show tax payable - the auto adjustment entry output vs input, difference payable logic you described.</p>
</div>
<?php require __DIR__ . "/../includes/footer.php"; ?>
