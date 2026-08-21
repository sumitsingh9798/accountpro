<?php
declare(strict_types=1);
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/session.php';
require_login();
$cid = current_company_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $stmt = $pdo->prepare("INSERT INTO stock_items (company_id,name,unit,hsn_code,gst_rate,opening_qty,opening_rate,reorder_level) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([$cid, trim($_POST['name']), trim($_POST['unit']), trim($_POST['hsn_code']), $_POST['gst_rate'] ?: 0, $_POST['opening_qty'] ?: 0, $_POST['opening_rate'] ?: 0, $_POST['reorder_level'] ?: 0]);
    header('Location: /masters/stock_items.php');
    exit;
}

$items = $pdo->prepare("SELECT * FROM stock_items WHERE company_id=? ORDER BY name");
$items->execute([$cid]);
$items = $items->fetchAll();

$pageTitle = 'Stock Items';
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-4">
  <div class="col-lg-4">
    <div class="card-panel">
      <h6 class="fw-bold mb-3">New Stock Item</h6>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <div class="mb-2"><label class="form-label small">Name</label><input name="name" class="form-control form-control-sm" required></div>
        <div class="row g-2 mb-2">
          <div class="col-6"><label class="form-label small">Unit</label><input name="unit" class="form-control form-control-sm" value="Nos"></div>
          <div class="col-6"><label class="form-label small">HSN Code</label><input name="hsn_code" class="form-control form-control-sm"></div>
        </div>
        <div class="row g-2 mb-2">
          <div class="col-6"><label class="form-label small">GST Rate %</label><input type="number" step="0.01" name="gst_rate" class="form-control form-control-sm" value="0"></div>
          <div class="col-6"><label class="form-label small">Reorder Level</label><input type="number" step="0.01" name="reorder_level" class="form-control form-control-sm" value="0"></div>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-6"><label class="form-label small">Opening Qty</label><input type="number" step="0.001" name="opening_qty" class="form-control form-control-sm" value="0"></div>
          <div class="col-6"><label class="form-label small">Opening Rate</label><input type="number" step="0.01" name="opening_rate" class="form-control form-control-sm" value="0"></div>
        </div>
        <button class="btn btn-brand btn-sm w-100">Create Item</button>
      </form>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card-panel">
      <table class="acct-table">
        <thead><tr><th>Name</th><th>Unit</th><th>HSN</th><th class="num">GST %</th><th class="num">Opening Qty</th><th class="num">Opening Value</th></tr></thead>
        <tbody>
        <?php foreach ($items as $i): ?>
          <tr>
            <td><?= htmlspecialchars($i['name']) ?></td>
            <td><?= htmlspecialchars($i['unit']) ?></td>
            <td><?= htmlspecialchars($i['hsn_code']) ?></td>
            <td class="num"><?= number_format((float)$i['gst_rate'],2) ?></td>
            <td class="num"><?= number_format((float)$i['opening_qty'],3) ?></td>
            <td class="num"><?= number_format((float)$i['opening_qty'] * (float)$i['opening_rate'],2) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
