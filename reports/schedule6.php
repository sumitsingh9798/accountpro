<?php
declare(strict_types=1);
require __DIR__ . "/../config/db.php";
require __DIR__ . "/../config/session.php";
require_login();
$pageTitle = "Schedule VI / III Balance Sheet with Notes";
require __DIR__ . "/../includes/header.php";
?>
<div class="card-panel">
  <h6 class="fw-bold mb-2"><i class="bi bi-cone-striped"></i> Schedule VI / III Balance Sheet with Notes — Phase 2</h6>
  <p class="text-muted mb-2">Not built yet in this scaffold. Implementation plan (schema already supports it):</p>
  <p>account_groups.schedule6_head already tags each group to its Schedule III head. This report groups LedgerEngine::balanceSheet() output by that head and auto-generates the supporting Notes to Accounts (e.g. Note 1: Share Capital, Note 2: Reserves) purely from ledger data - no manual re-entry.</p>
</div>
<?php require __DIR__ . "/../includes/footer.php"; ?>
