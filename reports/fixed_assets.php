<?php
declare(strict_types=1);
require __DIR__ . "/../config/db.php";
require __DIR__ . "/../config/session.php";
require_login();
$pageTitle = "Fixed Asset Register";
require __DIR__ . "/../includes/header.php";
?>
<div class="card-panel">
  <h6 class="fw-bold mb-2"><i class="bi bi-cone-striped"></i> Fixed Asset Register — Phase 2</h6>
  <p class="text-muted mb-2">Not built yet in this scaffold. Implementation plan (schema already supports it):</p>
  <p>Asset-wise register with WDV/SLM depreciation schedule. Tables fixed_assets + fixed_asset_depreciation already exist - write a yearly depreciation-run job (dep = (opening_wdv-salvage) x rate% for WDV) and list assets with accumulated depreciation and net block.</p>
</div>
<?php require __DIR__ . "/../includes/footer.php"; ?>
