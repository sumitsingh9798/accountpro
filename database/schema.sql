-- ============================================================
-- AccountPro - Open Source PHP Accounting Software
-- Core Schema (Phase 1: Masters, Vouchers, Reports)
-- Engine: InnoDB (needed for FK + transactions)
-- ============================================================

CREATE DATABASE IF NOT EXISTS accountpro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE accountpro;

-- ---------- COMPANY / SETTINGS ----------
CREATE TABLE companies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  address TEXT,
  gstin VARCHAR(20),
  pan VARCHAR(20),
  fin_year_start DATE NOT NULL,
  fin_year_end DATE NOT NULL,
  entry_mode ENUM('double','single') DEFAULT 'double', -- global default, overridable per voucher
  base_currency VARCHAR(10) DEFAULT 'INR',
  logo_path VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','accountant','auditor','viewer') DEFAULT 'accountant',
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (company_id) REFERENCES companies(id)
) ENGINE=InnoDB;

-- ---------- CHART OF ACCOUNTS ----------
-- Primary groups mirror Tally's structure so P&L / Balance Sheet classify automatically
CREATE TABLE account_groups (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  parent_id INT NULL,
  nature ENUM('asset','liability','income','expense') NOT NULL,
  affects_gross_profit TINYINT(1) DEFAULT 0, -- Direct Income/Expense flag for trading account
  is_system TINYINT(1) DEFAULT 0, -- system groups can't be deleted
  schedule6_head VARCHAR(100) NULL, -- maps group to Schedule III/VI head for BS notes
  FOREIGN KEY (company_id) REFERENCES companies(id),
  FOREIGN KEY (parent_id) REFERENCES account_groups(id)
) ENGINE=InnoDB;

CREATE TABLE ledger_accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_id INT NOT NULL,
  group_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  alias VARCHAR(100),
  opening_balance DECIMAL(18,2) DEFAULT 0,
  opening_balance_type ENUM('debit','credit') DEFAULT 'debit',
  as_on_date DATE NULL,
  address TEXT,
  gstin VARCHAR(20),
  pan VARCHAR(20),
  state VARCHAR(50),
  contact_person VARCHAR(100),
  phone VARCHAR(30),
  email VARCHAR(150),
  credit_period_days INT DEFAULT 0, -- for aging/overdue mails
  bank_account_no VARCHAR(50) NULL,
  bank_ifsc VARCHAR(20) NULL,
  is_bank_cash TINYINT(1) DEFAULT 0, -- flags Cash/Bank ledgers (used in Contra, BRS, Cash Flow)
  tds_applicable TINYINT(1) DEFAULT 0,
  tds_section VARCHAR(20) NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (company_id) REFERENCES companies(id),
  FOREIGN KEY (group_id) REFERENCES account_groups(id),
  INDEX idx_ledger_company (company_id, name)
) ENGINE=InnoDB;

-- Opening balance adjustment audit trail (separate dr/cr adjustment entries requested)
CREATE TABLE opening_balance_adjustments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ledger_id INT NOT NULL,
  adjustment_type ENUM('debit','credit') NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  reason VARCHAR(255),
  adjusted_by INT NOT NULL,
  adjusted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ledger_id) REFERENCES ledger_accounts(id),
  FOREIGN KEY (adjusted_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------- STOCK / INVENTORY (for Stock Statement) ----------
CREATE TABLE stock_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  unit VARCHAR(20) DEFAULT 'Nos',
  hsn_code VARCHAR(20),
  gst_rate DECIMAL(5,2) DEFAULT 0,
  opening_qty DECIMAL(18,3) DEFAULT 0,
  opening_rate DECIMAL(18,2) DEFAULT 0,
  reorder_level DECIMAL(18,3) DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1,
  FOREIGN KEY (company_id) REFERENCES companies(id)
) ENGINE=InnoDB;

-- ---------- FIXED ASSETS ----------
CREATE TABLE fixed_assets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_id INT NOT NULL,
  asset_name VARCHAR(150) NOT NULL,
  ledger_id INT NOT NULL, -- linked asset ledger
  purchase_date DATE NOT NULL,
  purchase_value DECIMAL(18,2) NOT NULL,
  dep_method ENUM('SLM','WDV') DEFAULT 'WDV',
  dep_rate DECIMAL(5,2) NOT NULL,
  salvage_value DECIMAL(18,2) DEFAULT 0,
  location VARCHAR(100),
  status ENUM('active','sold','disposed') DEFAULT 'active',
  disposal_date DATE NULL,
  disposal_value DECIMAL(18,2) NULL,
  FOREIGN KEY (company_id) REFERENCES companies(id),
  FOREIGN KEY (ledger_id) REFERENCES ledger_accounts(id)
) ENGINE=InnoDB;

CREATE TABLE fixed_asset_depreciation (
  id INT AUTO_INCREMENT PRIMARY KEY,
  asset_id INT NOT NULL,
  fin_year VARCHAR(9) NOT NULL, -- e.g. 2025-2026
  opening_wdv DECIMAL(18,2) NOT NULL,
  dep_amount DECIMAL(18,2) NOT NULL,
  closing_wdv DECIMAL(18,2) NOT NULL,
  FOREIGN KEY (asset_id) REFERENCES fixed_assets(id)
) ENGINE=InnoDB;

-- ---------- VOUCHER TYPES (Tally-style) ----------
CREATE TABLE voucher_types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_id INT NOT NULL,
  name VARCHAR(50) NOT NULL, -- Sales, Purchase, Receipt, Payment, Contra, Journal, Debit Note, Credit Note
  code VARCHAR(10) NOT NULL, -- SAL, PUR, REC, PAY, CON, JRN, DRN, CRN
  prefix VARCHAR(10) DEFAULT '',
  next_number INT DEFAULT 1,
  is_system TINYINT(1) DEFAULT 1,
  FOREIGN KEY (company_id) REFERENCES companies(id)
) ENGINE=InnoDB;

-- ---------- VOUCHERS (header) ----------
CREATE TABLE vouchers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_id INT NOT NULL,
  voucher_type_id INT NOT NULL,
  voucher_no VARCHAR(30) NOT NULL,
  voucher_date DATE NOT NULL,
  entry_mode ENUM('double','single') DEFAULT 'double',
  reference_no VARCHAR(50),
  narration TEXT,
  party_ledger_id INT NULL,          -- quick reference for reports (debtor/creditor)
  due_date DATE NULL,                 -- for receivable/payable aging
  gst_taxable_value DECIMAL(18,2) DEFAULT 0,
  gst_cgst DECIMAL(18,2) DEFAULT 0,
  gst_sgst DECIMAL(18,2) DEFAULT 0,
  gst_igst DECIMAL(18,2) DEFAULT 0,
  tds_amount DECIMAL(18,2) DEFAULT 0,
  cloned_from_id INT NULL,            -- set when created via "Clone Entry"
  status ENUM('draft','posted','cancelled') DEFAULT 'posted',
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (company_id) REFERENCES companies(id),
  FOREIGN KEY (voucher_type_id) REFERENCES voucher_types(id),
  FOREIGN KEY (party_ledger_id) REFERENCES ledger_accounts(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  UNIQUE KEY uniq_voucher (company_id, voucher_type_id, voucher_no)
) ENGINE=InnoDB;

-- ---------- VOUCHER ENTRIES (double-entry lines) ----------
-- Single-entry mode just uses 2 auto-generated lines behind the scenes (see logic layer)
CREATE TABLE voucher_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  voucher_id INT NOT NULL,
  ledger_id INT NOT NULL,
  dr_cr ENUM('debit','credit') NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  narration VARCHAR(255),
  cost_center VARCHAR(100) NULL,
  bill_ref VARCHAR(50) NULL,          -- "Against Ref" for bill-wise (invoice-wise outstanding)
  bill_type ENUM('new','against_ref','advance','on_account') DEFAULT 'new',
  FOREIGN KEY (voucher_id) REFERENCES vouchers(id) ON DELETE CASCADE,
  FOREIGN KEY (ledger_id) REFERENCES ledger_accounts(id),
  INDEX idx_ve_ledger (ledger_id)
) ENGINE=InnoDB;

-- ---------- STOCK ITEM LINES (for Sales/Purchase vouchers) ----------
CREATE TABLE voucher_stock_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  voucher_id INT NOT NULL,
  stock_item_id INT NOT NULL,
  qty DECIMAL(18,3) NOT NULL,
  rate DECIMAL(18,2) NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  FOREIGN KEY (voucher_id) REFERENCES vouchers(id) ON DELETE CASCADE,
  FOREIGN KEY (stock_item_id) REFERENCES stock_items(id)
) ENGINE=InnoDB;

-- ---------- BANK RECONCILIATION ----------
CREATE TABLE bank_reconciliation (
  id INT AUTO_INCREMENT PRIMARY KEY,
  voucher_entry_id INT NOT NULL,
  bank_ledger_id INT NOT NULL,
  bank_date DATE NULL,          -- date as per bank statement (null = not yet reconciled)
  is_reconciled TINYINT(1) DEFAULT 0,
  FOREIGN KEY (voucher_entry_id) REFERENCES voucher_entries(id),
  FOREIGN KEY (bank_ledger_id) REFERENCES ledger_accounts(id)
) ENGINE=InnoDB;

-- ---------- GST WORKING (GSTR-1 / 2A / 3B) ----------
CREATE TABLE gstr2a_upload (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_id INT NOT NULL,
  period VARCHAR(7) NOT NULL, -- MM-YYYY
  supplier_gstin VARCHAR(20),
  supplier_name VARCHAR(150),
  invoice_no VARCHAR(50),
  invoice_date DATE,
  taxable_value DECIMAL(18,2),
  cgst DECIMAL(18,2) DEFAULT 0,
  sgst DECIMAL(18,2) DEFAULT 0,
  igst DECIMAL(18,2) DEFAULT 0,
  matched_voucher_id INT NULL, -- set once auto-reconciliation finds a match
  match_status ENUM('matched','mismatched','not_in_books') DEFAULT 'not_in_books',
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (company_id) REFERENCES companies(id),
  FOREIGN KEY (matched_voucher_id) REFERENCES vouchers(id)
) ENGINE=InnoDB;

-- ---------- EMAIL REMINDER LOG (auto follow-up for overdue) ----------
CREATE TABLE overdue_mail_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ledger_id INT NOT NULL,
  voucher_id INT NULL,
  sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  amount_due DECIMAL(18,2),
  status ENUM('sent','failed') DEFAULT 'sent',
  FOREIGN KEY (ledger_id) REFERENCES ledger_accounts(id)
) ENGINE=InnoDB;

-- ---------- SEED: standard Tally-style groups ----------
INSERT INTO companies (name, address, fin_year_start, fin_year_end, entry_mode)
VALUES ('My Company', '', '2026-04-01', '2027-03-31', 'double');

SET @cid = LAST_INSERT_ID();

INSERT INTO account_groups (company_id, name, parent_id, nature, is_system) VALUES
(@cid,'Capital Account',NULL,'liability',1),
(@cid,'Loans (Liability)',NULL,'liability',1),
(@cid,'Current Liabilities',NULL,'liability',1),
(@cid,'Sundry Creditors',NULL,'liability',1),
(@cid,'Fixed Assets',NULL,'asset',1),
(@cid,'Investments',NULL,'asset',1),
(@cid,'Current Assets',NULL,'asset',1),
(@cid,'Sundry Debtors',NULL,'asset',1),
(@cid,'Cash-in-Hand',NULL,'asset',1),
(@cid,'Bank Accounts',NULL,'asset',1),
(@cid,'Stock-in-Hand',NULL,'asset',1),
(@cid,'Sales Accounts',NULL,'income',1),
(@cid,'Purchase Accounts',NULL,'expense',1),
(@cid,'Direct Income',NULL,'income',1),
(@cid,'Indirect Income',NULL,'income',1),
(@cid,'Direct Expenses',NULL,'expense',1),
(@cid,'Indirect Expenses',NULL,'expense',1),
(@cid,'Duties & Taxes',NULL,'liability',1);

UPDATE account_groups SET affects_gross_profit=1 WHERE name IN ('Sales Accounts','Purchase Accounts','Direct Income','Direct Expenses');

INSERT INTO voucher_types (company_id, name, code, prefix) VALUES
(@cid,'Sales','SAL','SAL-'),
(@cid,'Purchase','PUR','PUR-'),
(@cid,'Receipt','REC','REC-'),
(@cid,'Payment','PAY','PAY-'),
(@cid,'Contra','CON','CON-'),
(@cid,'Journal','JRN','JRN-'),
(@cid,'Debit Note','DRN','DRN-'),
(@cid,'Credit Note','CRN','CRN-');

-- default cash ledger
INSERT INTO ledger_accounts (company_id, group_id, name, is_bank_cash)
SELECT @cid, id, 'Cash', 1 FROM account_groups WHERE company_id=@cid AND name='Cash-in-Hand';
