# AccountPro — Open Source PHP Accounting Software

A Tally-style accounting engine built on plain PHP 8 + PDO/MySQL (no framework
lock-in), with a premium Bootstrap 5 UI.

## What's fully implemented (Phase 1 — working now)

| Area | File(s) |
|---|---|
| Double-entry engine (also supports single-entry mode) | `includes/LedgerEngine.php` |
| Chart of Accounts + hierarchical Groups | `masters/chart_of_accounts.php`, `masters/groups.php` |
| Voucher entry: Sales, Purchase, Receipt, Payment, Contra, Journal, Debit Note, Credit Note | `vouchers/voucher_entry.php` |
| Clone Entry (duplicate any past voucher as a starting point) | `vouchers/voucher_list.php` → "Clone" |
| Opening balance + separate Dr/Cr adjustment audit trail | `masters/opening_balance_adjustment.php` |
| Trial Balance | `reports/trial_balance.php` |
| Profit & Loss (direct/indirect split, gross & net profit) | `reports/profit_loss.php` |
| Balance Sheet | `reports/balance_sheet.php` |
| Ledger statement (running balance) | `reports/ledger_view.php` |
| Receivable / Payable outstanding — vendor-wise, invoice-wise, group-wise, with aging buckets | `includes/OutstandingEngine.php`, `reports/outstanding.php` |
| Excel/CSV import (Chart of Accounts) & export (ledgers, vouchers, trial balance) | `vouchers/import_export.php` |
| Overdue payment follow-up mailer | `tools/overdue_mailer.php` |
| Stock item master | `masters/stock_items.php` |
| Login/session/CSRF, multi-user, role field | `auth/`, `config/session.php` |

## What's scaffolded (Phase 2 — schema ready, page is a stub with the exact build plan)

Stock Statement, Fixed Asset Register, Receipt & Payment report, Cash Flow,
Fund Flow, Bank Reconciliation, Ratio Analysis, TDS Working, GSTR-1,
GSTR-2A auto-reconciliation, GSTR-3B, Schedule VI/III with auto-notes.

Every one of these reads from tables **already in `database/schema.sql`**
(`fixed_assets`, `bank_reconciliation`, `gstr2a_upload`, `stock_items`,
`voucher_stock_items`, `account_groups.schedule6_head`, etc.) — nothing about
the data model needs to change, they just need their query + view built.
Open any stub page for its specific implementation notes.

Why these were scaffolded instead of built out fully: each one (especially
GSTR-2A reconciliation, Schedule VI note generation, and depreciation
schedules) is a substantial sub-project in its own right — building all of
them correctly, with real GST portal JSON formats and Companies Act Schedule
III formatting rules, is well beyond a single pass. The engine underneath
(`LedgerEngine`, `OutstandingEngine`) is the hard part and is done — these
reports are now "just" SQL + a view on top of it.

## Setup

```bash
mysql -u root -p < database/schema.sql
cp config/db.php.example config/db.php   # or just set env vars below
php setup.php                             # creates your first admin user
php -S localhost:8000                     # or point Apache/Nginx docroot here
```

Environment variables (or edit `config/db.php` directly):
`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.

Requires PHP 8.1+, PDO MySQL extension, MySQL/MariaDB with InnoDB.

## Architecture notes

- **Single source of truth**: every report (Trial Balance, P&L, Balance
  Sheet, Outstanding/Aging) is derived live from `voucher_entries` — nothing
  is pre-aggregated or cached, so books always reconcile by construction.
- **Single-entry mode** isn't a separate data path — the UI collects one
  ledger + amount and `LedgerEngine::postVoucher()` auto-generates the
  contra leg (e.g. against Cash) so the underlying ledger stays properly
  double-entry for every report. This mirrors how Tally's "single entry
  mode" works internally.
- **Bill-wise outstanding**: `voucher_entries.bill_ref` / `bill_type` let
  a Receipt/Payment net off against a specific Sales/Purchase invoice
  instead of just running down a ledger total — this is what makes
  invoice-wise aging possible.
- **Clone Entry**: `LedgerEngine::cloneVoucher()` returns a header+lines
  payload that `voucher_entry.php` pre-fills into a fresh form (new
  voucher number gets allocated on save, not on clone).
- **Schedule VI mapping**: `account_groups.schedule6_head` tags each
  group to its Companies Act head once, at master-creation time — Phase 2's
  Schedule VI report is then a GROUP BY on data you already have.

## Security baseline included
CSRF tokens on all POST forms, prepared statements everywhere (PDO,
`ATTR_EMULATE_PREPARES=false`), password hashing via `password_hash()`,
session-gated pages via `require_login()`. **Before production**: add rate
limiting on login, HTTPS enforcement, role-based access checks per page
(the `role` column exists on `users` but isn't yet enforced page-by-page),
and swap PHP's `mail()` in the overdue mailer for a real SMTP library
(PHPMailer / Symfony Mailer) with DKIM/SPF configured.
