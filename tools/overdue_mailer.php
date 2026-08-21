<?php
declare(strict_types=1);
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/session.php';
require_login();
require __DIR__ . '/../includes/OutstandingEngine.php';
$cid = current_company_id();
$engine = new OutstandingEngine($pdo, $cid);
$overdue = $engine->outstandingReport('debtors', 'ledger');

// keep only parties with something past due, and an email on file
$sent = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($_POST['send_to'] ?? [] as $ledgerId) {
        $l = $pdo->prepare("SELECT name, email FROM ledger_accounts WHERE id=? AND company_id=?");
        $l->execute([$ledgerId, $cid]);
        $ledger = $l->fetch();
        if (!$ledger || !$ledger['email']) continue;

        $row = null;
        foreach ($overdue as $r) if ($r['name'] === $ledger['name']) $row = $r;
        if (!$row) continue;

        $subject = "Payment Reminder — Outstanding Balance";
        $body = "Dear {$ledger['name']},\n\nThis is a friendly reminder that you have an outstanding balance of "
              . number_format($row['total'], 2) . " on our books as of " . date('Y-m-d') . ".\n\n"
              . "Kindly arrange payment at your earliest convenience.\n\nRegards,\nAccounts Team";

        $ok = @mail($ledger['email'], $subject, $body, "From: accounts@yourcompany.com");
        $log = $pdo->prepare("INSERT INTO overdue_mail_log (ledger_id, amount_due, status) VALUES (?,?,?)");
        $log->execute([$ledgerId, $row['total'], $ok ? 'sent' : 'failed']);
        $sent[] = $ledger['name'] . ($ok ? ' ✓' : ' ✗ (mail server not configured)');
    }
}

$pageTitle = 'Overdue Follow-up Mailer';
require __DIR__ . '/../includes/header.php';
?>
<div class="card-panel">
  <p class="small text-muted">Sends a payment reminder to every customer whose ledger has an email on file. Wire the <code>mail()</code> call to your SMTP relay (PHPMailer/SendGrid) in production — this uses PHP's built-in mail() as a working baseline. Set this page up as a daily cron via <code>php overdue_mailer.php --cron</code> for full automation.</p>
  <?php if ($sent): ?><div class="alert alert-info"><strong>Result:</strong> <?= implode(', ', array_map('htmlspecialchars', $sent)) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <table class="acct-table">
      <thead><tr><th></th><th>Party</th><th class="num">Outstanding</th></tr></thead>
      <tbody>
      <?php foreach ($overdue as $r): if ($r['total'] <= 0) continue; ?>
        <tr>
          <td><input type="checkbox" name="send_to[]" value="<?= array_key_first(array_column($r['invoices'],'ledger_id')) !== null ? $r['invoices'][0]['ledger_id'] : '' ?>"></td>
          <td><?= htmlspecialchars($r['name']) ?></td>
          <td class="num"><?= number_format($r['total'], 2) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <button class="btn btn-brand btn-sm">Send Reminders to Selected</button>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
