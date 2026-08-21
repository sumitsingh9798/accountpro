<?php
declare(strict_types=1);
require __DIR__ . "/../config/db.php";
require __DIR__ . "/../config/session.php";
require_login();
$pageTitle = "Stock Statement";
require __DIR__ . "/../includes/header.php";
?>
<div class="card-panel">
  <h6 class="fw-bold mb-2"><i class="bi bi-cone-striped"></i> Stock Statement — Phase 2</h6>
  <p class="text-muted mb-2">Not built yet in this scaffold. Implementation plan (schema already supports it):</p>
  <p>Item-wise stock valuation (qty x rate) driven by voucher_stock_items, with opening/inward/outward/closing. Data model (stock_items, voucher_stock_items) is already in schema.sql - build the query: closing_qty = opening_qty + SUM(purchase qty) - SUM(sales qty), valued at FIFO/Weighted Avg.</p>
</div>
<?php require __DIR__ . "/../includes/footer.php"; ?>
