<?php
declare(strict_types=1);
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/session.php';
require_login();
require __DIR__ . '/../includes/LedgerEngine.php';
$cid = current_company_id();
$engine = new LedgerEngine($pdo, $cid);

$typeMap = [
    'sales' => 'Sales', 'purchase' => 'Purchase', 'receipt' => 'Receipt', 'payment' => 'Payment',
    'contra' => 'Contra', 'journal' => 'Journal', 'debit_note' => 'Debit Note', 'credit_note' => 'Credit Note',
];
$typeKey = $_GET['type'] ?? 'journal';
$typeName = $typeMap[$typeKey] ?? 'Journal';

$vtStmt = $pdo->prepare("SELECT * FROM voucher_types WHERE company_id=? AND name=?");
$vtStmt->execute([$cid, $typeName]);
$voucherType = $vtStmt->fetch();
if (!$voucherType) { http_response_code(404); die('Voucher type not configured.'); }

// Clone support: ?clone=<voucher_id>
$prefill = null;
if (!empty($_GET['clone'])) {
    $prefill = $engine->cloneVoucher((int)$_GET['clone']);
}

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $mode = $_POST['entry_mode'] ?? 'double';
        $lines = [];
        $ledgerIds = $_POST['ledger_id'] ?? [];
        $drCrs = $_POST['dr_cr'] ?? [];
        $amounts = $_POST['amount'] ?? [];
        $billRefs = $_POST['bill_ref'] ?? [];
        $billTypes = $_POST['bill_type'] ?? [];

        foreach ($ledgerIds as $i => $lid) {
            if ($lid === '' || $amounts[$i] === '') continue;
            $lines[] = [
                'ledger_id' => (int)$lid,
                'dr_cr'     => $drCrs[$i],
                'amount'    => (float)$amounts[$i],
                'bill_ref'  => $billRefs[$i] ?: null,
                'bill_type' => $billTypes[$i] ?: 'new',
            ];
        }

        $header = [
            'voucher_type_id'    => $voucherType['id'],
            'voucher_date'       => $_POST['voucher_date'],
            'entry_mode'         => $mode,
            'reference_no'       => $_POST['reference_no'] ?? null,
            'narration'          => $_POST['narration'] ?? null,
            'party_ledger_id'    => $_POST['party_ledger_id'] ?: null,
            'due_date'           => $_POST['due_date'] ?: null,
            'gst_taxable_value'  => $_POST['gst_taxable_value'] ?? 0,
            'gst_cgst'           => $_POST['gst_cgst'] ?? 0,
            'gst_sgst'           => $_POST['gst_sgst'] ?? 0,
            'gst_igst'           => $_POST['gst_igst'] ?? 0,
            'tds_amount'         => $_POST['tds_amount'] ?? 0,
            'contra_ledger_id'   => $_POST['contra_ledger_id'] ?? null,
            'cloned_from_id'     => $_POST['cloned_from_id'] ?: null,
        ];

        $voucherId = $engine->postVoucher($header, $lines);
        $message = "Voucher posted successfully (#{$voucherId}).";
    } catch (Throwable $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

$ledgersStmt = $pdo->prepare("SELECT id, name, is_bank_cash FROM ledger_accounts WHERE company_id=? AND is_active=1 ORDER BY name");
$ledgersStmt->execute([$cid]);
$allLedgers = $ledgersStmt->fetchAll();

$pageTitle = $typeName . ' Voucher';
require __DIR__ . '/../includes/header.php';
?>
<div class="card-panel">
  <?php if ($message): ?><div class="alert alert-<?= $messageType ?> py-2"><?= htmlspecialchars($message) ?></div><?php endif; ?>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-bold mb-0"><?= htmlspecialchars($typeName) ?> Voucher</h6>
    <div class="btn-group btn-group-sm">
      <input type="radio" class="btn-check" name="entry_mode_toggle" id="modeDouble" checked>
      <label class="btn btn-outline-secondary" for="modeDouble">Double Entry</label>
      <input type="radio" class="btn-check" name="entry_mode_toggle" id="modeSingle">
      <label class="btn btn-outline-secondary" for="modeSingle">Single Entry</label>
    </div>
  </div>

  <form method="post" id="voucherForm">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="entry_mode" id="entry_mode_field" value="double">
    <input type="hidden" name="cloned_from_id" value="<?= htmlspecialchars($_GET['clone'] ?? '') ?>">

    <div class="row g-2 mb-3">
      <div class="col-md-3"><label class="form-label small">Voucher Date</label>
        <input type="date" name="voucher_date" class="form-control form-control-sm" required
               value="<?= htmlspecialchars($prefill['header']['voucher_date'] ?? date('Y-m-d')) ?>"></div>
      <div class="col-md-3"><label class="form-label small">Reference No.</label>
        <input name="reference_no" class="form-control form-control-sm" value="<?= htmlspecialchars($prefill['header']['reference_no'] ?? '') ?>"></div>
      <div class="col-md-3"><label class="form-label small">Party Ledger (for outstanding tracking)</label>
        <select name="party_ledger_id" class="form-select form-select-sm">
          <option value="">—</option>
          <?php foreach ($allLedgers as $l): ?><option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="col-md-3"><label class="form-label small">Due Date (for aging)</label>
        <input type="date" name="due_date" class="form-control form-control-sm"></div>
    </div>

    <!-- Single-entry mode: pick the "default" contra ledger (e.g. Cash) the other leg posts against -->
    <div class="row g-2 mb-3 d-none" id="singleModeRow">
      <div class="col-md-4">
        <label class="form-label small">Default (Contra) Ledger</label>
        <select name="contra_ledger_id" class="form-select form-select-sm">
          <?php foreach ($allLedgers as $l): ?>
            <option value="<?= $l['id'] ?>" <?= $l['is_bank_cash'] ? 'selected' : '' ?>><?= htmlspecialchars($l['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="form-text">In single-entry mode you only pick ONE ledger + amount below; the system auto-posts the other side here — same convenience as Tally's single entry mode, while your books stay fully double-entry underneath.</div>
      </div>
    </div>

    <div id="entryRows">
      <div class="voucher-row-grid fw-bold small text-muted">
        <div>Ledger</div><div>Dr / Cr</div><div>Amount</div><div>Bill Ref (optional)</div><div></div>
      </div>
      <?php
      $prefillLines = $prefill['lines'] ?? [['ledger_id'=>'','dr_cr'=>'debit','amount'=>'','bill_ref'=>''], ['ledger_id'=>'','dr_cr'=>'credit','amount'=>'','bill_ref'=>'']];
      foreach ($prefillLines as $idx => $pl):
      ?>
      <div class="voucher-row-grid entry-row">
        <select name="ledger_id[]" class="form-select form-select-sm">
          <option value="">Select ledger...</option>
          <?php foreach ($allLedgers as $l): ?>
            <option value="<?= $l['id'] ?>" <?= (string)($pl['ledger_id'] ?? '') === (string)$l['id'] ? 'selected' : '' ?>><?= htmlspecialchars($l['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="dr_cr[]" class="form-select form-select-sm">
          <option value="debit" <?= ($pl['dr_cr'] ?? '') === 'debit' ? 'selected' : '' ?>>Debit</option>
          <option value="credit" <?= ($pl['dr_cr'] ?? '') === 'credit' ? 'selected' : '' ?>>Credit</option>
        </select>
        <input type="number" step="0.01" name="amount[]" class="form-control form-control-sm amount-input" value="<?= htmlspecialchars((string)($pl['amount'] ?? '')) ?>">
        <input type="text" name="bill_ref[]" class="form-control form-control-sm" placeholder="Invoice # if any" value="<?= htmlspecialchars($pl['bill_ref'] ?? '') ?>">
        <button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-x"></i></button>
      </div>
      <?php endforeach; ?>
    </div>
    <button type="button" id="addRow" class="btn btn-sm btn-outline-secondary mb-3"><i class="bi bi-plus"></i> Add Row</button>

    <div class="d-flex gap-4 mb-3 small">
      <div>Total Debit: <strong id="totalDebit" class="text-debit">0.00</strong></div>
      <div>Total Credit: <strong id="totalCredit" class="text-credit">0.00</strong></div>
      <div id="balanceWarning" class="text-danger fw-bold d-none">Not balanced!</div>
    </div>

    <?php if (in_array($typeKey, ['sales', 'purchase'])): ?>
    <div class="row g-2 mb-3 border-top pt-3">
      <div class="col-md-3"><label class="form-label small">Taxable Value</label><input type="number" step="0.01" name="gst_taxable_value" class="form-control form-control-sm" value="0"></div>
      <div class="col-md-2"><label class="form-label small">CGST</label><input type="number" step="0.01" name="gst_cgst" class="form-control form-control-sm" value="0"></div>
      <div class="col-md-2"><label class="form-label small">SGST</label><input type="number" step="0.01" name="gst_sgst" class="form-control form-control-sm" value="0"></div>
      <div class="col-md-2"><label class="form-label small">IGST</label><input type="number" step="0.01" name="gst_igst" class="form-control form-control-sm" value="0"></div>
      <div class="col-md-3"><label class="form-label small">TDS Amount</label><input type="number" step="0.01" name="tds_amount" class="form-control form-control-sm" value="0"></div>
    </div>
    <?php endif; ?>

    <div class="mb-3"><label class="form-label small">Narration</label>
      <textarea name="narration" class="form-control form-control-sm" rows="2"><?= htmlspecialchars($prefill['header']['narration'] ?? '') ?></textarea></div>

    <button class="btn btn-brand"><i class="bi bi-check-circle"></i> Post Voucher</button>
    <a href="/vouchers/voucher_list.php?type=<?= $typeKey ?>" class="btn btn-outline-secondary">View Register / Clone Past Entries</a>
  </form>
</div>

<template id="rowTemplate">
  <div class="voucher-row-grid entry-row">
    <select name="ledger_id[]" class="form-select form-select-sm">
      <option value="">Select ledger...</option>
      <?php foreach ($allLedgers as $l): ?><option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option><?php endforeach; ?>
    </select>
    <select name="dr_cr[]" class="form-select form-select-sm">
      <option value="debit">Debit</option><option value="credit">Credit</option>
    </select>
    <input type="number" step="0.01" name="amount[]" class="form-control form-control-sm amount-input" value="">
    <input type="text" name="bill_ref[]" class="form-control form-control-sm" placeholder="Invoice # if any">
    <button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-x"></i></button>
  </div>
</template>

<script>
const entryRows = document.getElementById('entryRows');
const rowTemplate = document.getElementById('rowTemplate');
const modeDouble = document.getElementById('modeDouble');
const modeSingle = document.getElementById('modeSingle');
const entryModeField = document.getElementById('entry_mode_field');
const singleModeRow = document.getElementById('singleModeRow');

document.getElementById('addRow').addEventListener('click', () => {
  entryRows.appendChild(rowTemplate.content.cloneNode(true));
  recalc();
});
entryRows.addEventListener('click', (e) => {
  if (e.target.closest('.remove-row')) { e.target.closest('.entry-row').remove(); recalc(); }
});
entryRows.addEventListener('input', recalc);

function recalc() {
  let dr = 0, cr = 0;
  document.querySelectorAll('#entryRows .entry-row').forEach(row => {
    const amt = parseFloat(row.querySelector('.amount-input').value) || 0;
    const type = row.querySelector('select[name="dr_cr[]"]').value;
    if (type === 'debit') dr += amt; else cr += amt;
  });
  document.getElementById('totalDebit').textContent = dr.toFixed(2);
  document.getElementById('totalCredit').textContent = cr.toFixed(2);
  document.getElementById('balanceWarning').classList.toggle('d-none', Math.abs(dr-cr) < 0.005 || modeSingle.checked);
}

function toggleMode() {
  const single = modeSingle.checked;
  entryModeField.value = single ? 'single' : 'double';
  singleModeRow.classList.toggle('d-none', !single);
  // In single mode, only the FIRST entry row is used; hide/disable the rest.
  document.querySelectorAll('#entryRows .entry-row').forEach((row, i) => {
    row.style.display = (single && i > 0) ? 'none' : '';
  });
  document.getElementById('addRow').style.display = single ? 'none' : '';
  recalc();
}
modeDouble.addEventListener('change', toggleMode);
modeSingle.addEventListener('change', toggleMode);
recalc();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
