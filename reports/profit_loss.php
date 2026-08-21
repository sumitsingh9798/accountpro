<?php
declare(strict_types=1);
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/session.php';
require_login();
require __DIR__ . '/../includes/LedgerEngine.php';
$cid = current_company_id();
$engine = new LedgerEngine($pdo, $cid);

$comp = $pdo->prepare("SELECT fin_year_start, fin_year_end FROM companies WHERE id=?");
$comp->execute([$cid]);
$fy = $comp->fetch();

$from = $_GET['from'] ?? $fy['fin_year_start'];
$to   = $_GET['to'] ?? date('Y-m-d');
$pl = $engine->profitAndLoss($from, $to);

$pageTitle = 'Profit & Loss Account';
require __DIR__ . '/../includes/header.php';

function plRows(array $rows): string {
    $html = '';
    foreach ($rows as [$name, $amt]) {
        $html .= '<tr><td class="ps-4">' . htmlspecialchars($name) . '</td><td class="num">' . number_format($amt, 2) . '</td></tr>';
    }
    return $html;
}
?>
<div class="card-panel">
  <form method="get" class="d-flex gap-2 align-items-end mb-3">
    <div><label class="form-label small">From</label><input type="date" name="from" value="<?= htmlspecialchars($from) ?>" class="form-control form-control-sm"></div>
    <div><label class="form-label small">To</label><input type="date" name="to" value="<?= htmlspecialchars($to) ?>" class="form-control form-control-sm"></div>
    <button class="btn btn-sm btn-brand">Refresh</button>
  </form>

  <div class="row">
    <div class="col-md-6">
      <h6 class="fw-bold">Expenses</h6>
      <table class="acct-table">
        <?= plRows($pl['lines']['direct_expense']) ?>
        <tr class="fw-bold"><td>Gross Profit c/o</td><td class="num"><?= number_format($pl['grossProfit'],2) ?></td></tr>
        <?= plRows($pl['lines']['indirect_expense']) ?>
        <tr class="fw-bold border-top"><td>Net Profit</td><td class="num"><?= number_format($pl['netProfit'],2) ?></td></tr>
      </table>
    </div>
    <div class="col-md-6">
      <h6 class="fw-bold">Income</h6>
      <table class="acct-table">
        <?= plRows($pl['lines']['direct_income']) ?>
        <tr class="fw-bold border-top"><td>Total (Sales/Direct Income)</td><td class="num"><?= number_format($pl['directIncome'],2) ?></td></tr>
        <?= plRows($pl['lines']['indirect_income']) ?>
        <tr class="fw-bold border-top"><td>Gross Profit b/f + Other Income</td><td class="num"><?= number_format($pl['grossProfit'] + $pl['indirectIncome'],2) ?></td></tr>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
