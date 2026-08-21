<?php
// Expects $pageTitle to be set by the including page.
$pageTitle = $pageTitle ?? 'AccountPro';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> · AccountPro</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <div class="brand"><i class="bi bi-graph-up-arrow"></i> AccountPro</div>
    <nav class="nav flex-column">
      <div class="nav-section">Masters</div>
      <a class="nav-link" href="/masters/groups.php"><i class="bi bi-diagram-3"></i> Account Groups</a>
      <a class="nav-link" href="/masters/chart_of_accounts.php"><i class="bi bi-journal-bookmark"></i> Chart of Accounts</a>
      <a class="nav-link" href="/masters/stock_items.php"><i class="bi bi-box-seam"></i> Stock Items</a>

      <div class="nav-section">Transactions</div>
      <a class="nav-link" href="/vouchers/voucher_entry.php?type=sales"><i class="bi bi-cart-check"></i> Sales</a>
      <a class="nav-link" href="/vouchers/voucher_entry.php?type=purchase"><i class="bi bi-bag-plus"></i> Purchase</a>
      <a class="nav-link" href="/vouchers/voucher_entry.php?type=receipt"><i class="bi bi-arrow-down-circle"></i> Receipt</a>
      <a class="nav-link" href="/vouchers/voucher_entry.php?type=payment"><i class="bi bi-arrow-up-circle"></i> Payment</a>
      <a class="nav-link" href="/vouchers/voucher_entry.php?type=contra"><i class="bi bi-arrow-left-right"></i> Contra</a>
      <a class="nav-link" href="/vouchers/voucher_entry.php?type=journal"><i class="bi bi-journal-text"></i> Journal</a>
      <a class="nav-link" href="/vouchers/voucher_entry.php?type=debit_note"><i class="bi bi-file-earmark-minus"></i> Debit Note</a>
      <a class="nav-link" href="/vouchers/voucher_entry.php?type=credit_note"><i class="bi bi-file-earmark-plus"></i> Credit Note</a>
      <a class="nav-link" href="/vouchers/voucher_list.php"><i class="bi bi-list-ul"></i> Voucher Register</a>

      <div class="nav-section">Reports</div>
      <a class="nav-link" href="/reports/trial_balance.php"><i class="bi bi-clipboard-data"></i> Trial Balance</a>
      <a class="nav-link" href="/reports/profit_loss.php"><i class="bi bi-graph-up"></i> Profit &amp; Loss</a>
      <a class="nav-link" href="/reports/balance_sheet.php"><i class="bi bi-bank"></i> Balance Sheet</a>
      <a class="nav-link" href="/reports/schedule6.php"><i class="bi bi-file-earmark-text"></i> Schedule VI + Notes</a>
      <a class="nav-link" href="/reports/stock_statement.php"><i class="bi bi-boxes"></i> Stock Statement</a>
      <a class="nav-link" href="/reports/fixed_assets.php"><i class="bi bi-building"></i> Fixed Asset Register</a>
      <a class="nav-link" href="/reports/receipt_payment.php"><i class="bi bi-receipt"></i> Receipt &amp; Payment</a>
      <a class="nav-link" href="/reports/outstanding.php?side=debtors"><i class="bi bi-person-check"></i> Receivable / Aging</a>
      <a class="nav-link" href="/reports/outstanding.php?side=creditors"><i class="bi bi-person-dash"></i> Payable / Aging</a>
      <a class="nav-link" href="/reports/cash_flow.php"><i class="bi bi-cash-stack"></i> Cash Flow</a>
      <a class="nav-link" href="/reports/fund_flow.php"><i class="bi bi-arrow-down-up"></i> Fund Flow</a>
      <a class="nav-link" href="/reports/bank_reconciliation.php"><i class="bi bi-bank2"></i> Bank Reconciliation</a>
      <a class="nav-link" href="/reports/ratio_analysis.php"><i class="bi bi-percent"></i> Ratio Analysis</a>

      <div class="nav-section">Compliance</div>
      <a class="nav-link" href="/reports/tds_working.php"><i class="bi bi-file-earmark-ruled"></i> TDS Working</a>
      <a class="nav-link" href="/reports/gstr1.php"><i class="bi bi-filetype-json"></i> GSTR-1</a>
      <a class="nav-link" href="/reports/gstr2a_reconcile.php"><i class="bi bi-arrow-repeat"></i> GSTR-2A Reconciliation</a>
      <a class="nav-link" href="/reports/gstr3b.php"><i class="bi bi-file-earmark-check"></i> GSTR-3B</a>

      <div class="nav-section">Tools</div>
      <a class="nav-link" href="/masters/opening_balance_adjustment.php"><i class="bi bi-sliders"></i> Opening Balance Adj.</a>
      <a class="nav-link" href="/vouchers/import_export.php"><i class="bi bi-file-earmark-excel"></i> Excel Import / Export</a>
      <a class="nav-link" href="/tools/overdue_mailer.php"><i class="bi bi-envelope-exclamation"></i> Overdue Follow-up Mail</a>
    </nav>
  </aside>
  <div class="main">
    <header class="topbar">
      <div class="page-title"><?= htmlspecialchars($pageTitle) ?></div>
      <div class="topbar-right">
        <span class="entry-mode-badge"><?= htmlspecialchars(strtoupper($_SESSION['entry_mode'] ?? 'DOUBLE')) ?> ENTRY</span>
        <span><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></span>
        <a href="/auth/logout.php" class="btn btn-sm btn-outline-secondary">Logout</a>
      </div>
    </header>
    <main class="content">
