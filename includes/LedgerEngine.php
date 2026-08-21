<?php
declare(strict_types=1);
/**
 * LedgerEngine
 * -------------------------------------------------------------
 * The accounting core. Every voucher (Sales, Purchase, Receipt,
 * Payment, Contra, Journal, Debit/Credit Note) is posted through
 * postVoucher(). It enforces:
 *   - Double entry: sum(debit) === sum(credit), else rejected.
 *   - Single entry mode: caller supplies ONE ledger + amount +
 *     dr/cr; engine auto-balances against a chosen "default"
 *     ledger (e.g. Cash) so the underlying ledger table always
 *     stays double-entry-consistent for reporting (Tally does
 *     the same under the hood for "single entry" convenience).
 * All monetary logic lives here so every report reads from one
 * source of truth instead of re-deriving balances ad hoc.
 */
class LedgerEngine
{
    private PDO $pdo;
    private int $companyId;

    public function __construct(PDO $pdo, int $companyId)
    {
        $this->pdo = $pdo;
        $this->companyId = $companyId;
    }

    /**
     * Post a voucher.
     * $header: voucher_type_id, voucher_no(optional-auto), voucher_date,
     *          entry_mode ('double'|'single'), reference_no, narration,
     *          party_ledger_id, due_date, gst_*, tds_amount, cloned_from_id
     * $lines (double mode): [['ledger_id'=>..,'dr_cr'=>'debit|credit','amount'=>..,'bill_ref'=>..,'bill_type'=>..], ...]
     * $lines (single mode): [['ledger_id'=>..,'dr_cr'=>..,'amount'=>..]] + $header['contra_ledger_id']
     *          (the other side, e.g. Cash/Bank) auto-generated.
     */
    public function postVoucher(array $header, array $lines): int
    {
        if (empty($lines)) throw new InvalidArgumentException('Voucher must have at least one line.');

        $mode = $header['entry_mode'] ?? 'double';

        if ($mode === 'single') {
            if (empty($header['contra_ledger_id'])) {
                throw new InvalidArgumentException('Single-entry mode requires a contra (default) ledger, e.g. Cash.');
            }
            $line = $lines[0];
            $opposite = $line['dr_cr'] === 'debit' ? 'credit' : 'debit';
            $lines = [
                $line,
                [
                    'ledger_id' => $header['contra_ledger_id'],
                    'dr_cr'     => $opposite,
                    'amount'    => $line['amount'],
                ],
            ];
        }

        // Enforce double-entry balance
        $debit = 0.0; $credit = 0.0;
        foreach ($lines as $l) {
            if ($l['dr_cr'] === 'debit') $debit += (float)$l['amount'];
            else $credit += (float)$l['amount'];
        }
        if (round($debit, 2) !== round($credit, 2)) {
            throw new RuntimeException("Voucher not balanced: Debit {$debit} != Credit {$credit}");
        }

        $this->pdo->beginTransaction();
        try {
            $voucherNo = $header['voucher_no'] ?? $this->nextVoucherNumber((int)$header['voucher_type_id']);

            $stmt = $this->pdo->prepare(
                "INSERT INTO vouchers
                 (company_id, voucher_type_id, voucher_no, voucher_date, entry_mode, reference_no,
                  narration, party_ledger_id, due_date, gst_taxable_value, gst_cgst, gst_sgst, gst_igst,
                  tds_amount, cloned_from_id, status, created_by)
                 VALUES (:cid,:vt,:vno,:vd,:mode,:ref,:narr,:party,:due,:gtv,:cgst,:sgst,:igst,:tds,:clone,'posted',:uid)"
            );
            $stmt->execute([
                ':cid'   => $this->companyId,
                ':vt'    => $header['voucher_type_id'],
                ':vno'   => $voucherNo,
                ':vd'    => $header['voucher_date'],
                ':mode'  => $mode,
                ':ref'   => $header['reference_no'] ?? null,
                ':narr'  => $header['narration'] ?? null,
                ':party' => $header['party_ledger_id'] ?? null,
                ':due'   => $header['due_date'] ?? null,
                ':gtv'   => $header['gst_taxable_value'] ?? 0,
                ':cgst'  => $header['gst_cgst'] ?? 0,
                ':sgst'  => $header['gst_sgst'] ?? 0,
                ':igst'  => $header['gst_igst'] ?? 0,
                ':tds'   => $header['tds_amount'] ?? 0,
                ':clone' => $header['cloned_from_id'] ?? null,
                ':uid'   => current_user_id(),
            ]);
            $voucherId = (int)$this->pdo->lastInsertId();

            $entryStmt = $this->pdo->prepare(
                "INSERT INTO voucher_entries (voucher_id, ledger_id, dr_cr, amount, narration, cost_center, bill_ref, bill_type)
                 VALUES (:vid,:lid,:drcr,:amt,:narr,:cc,:bref,:btype)"
            );
            foreach ($lines as $l) {
                $entryStmt->execute([
                    ':vid'   => $voucherId,
                    ':lid'   => $l['ledger_id'],
                    ':drcr'  => $l['dr_cr'],
                    ':amt'   => $l['amount'],
                    ':narr'  => $l['narration'] ?? null,
                    ':cc'    => $l['cost_center'] ?? null,
                    ':bref'  => $l['bill_ref'] ?? null,
                    ':btype' => $l['bill_type'] ?? 'new',
                ]);
            }

            // stock lines (optional, for Sales/Purchase)
            if (!empty($header['stock_items'])) {
                $siStmt = $this->pdo->prepare(
                    "INSERT INTO voucher_stock_items (voucher_id, stock_item_id, qty, rate, amount)
                     VALUES (:vid,:sid,:qty,:rate,:amt)"
                );
                foreach ($header['stock_items'] as $si) {
                    $siStmt->execute([
                        ':vid'  => $voucherId,
                        ':sid'  => $si['stock_item_id'],
                        ':qty'  => $si['qty'],
                        ':rate' => $si['rate'],
                        ':amt'  => $si['amount'],
                    ]);
                }
            }

            $this->incrementVoucherNumber((int)$header['voucher_type_id']);
            $this->pdo->commit();
            return $voucherId;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** Clone an existing voucher (header + lines) as a starting point for a new one. */
    public function cloneVoucher(int $voucherId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM vouchers WHERE id=? AND company_id=?");
        $stmt->execute([$voucherId, $this->companyId]);
        $header = $stmt->fetch();
        if (!$header) throw new RuntimeException('Voucher not found');

        $lineStmt = $this->pdo->prepare("SELECT * FROM voucher_entries WHERE voucher_id=?");
        $lineStmt->execute([$voucherId]);
        $lines = $lineStmt->fetchAll();

        $header['cloned_from_id'] = $voucherId;
        unset($header['id'], $header['voucher_no'], $header['created_at']);
        return ['header' => $header, 'lines' => $lines];
    }

    private function nextVoucherNumber(int $voucherTypeId): string
    {
        $stmt = $this->pdo->prepare("SELECT prefix, next_number FROM voucher_types WHERE id=? AND company_id=? FOR UPDATE");
        $stmt->execute([$voucherTypeId, $this->companyId]);
        $row = $stmt->fetch();
        if (!$row) throw new RuntimeException('Invalid voucher type');
        return $row['prefix'] . str_pad((string)$row['next_number'], 4, '0', STR_PAD_LEFT);
    }

    private function incrementVoucherNumber(int $voucherTypeId): void
    {
        $this->pdo->prepare("UPDATE voucher_types SET next_number = next_number + 1 WHERE id=? AND company_id=?")
                   ->execute([$voucherTypeId, $this->companyId]);
    }

    /**
     * Ledger closing balance as of a date (inclusive), net of opening balance
     * and any opening_balance_adjustments.
     * Returns ['balance'=>signed float (+ve = debit), 'dr_cr'=>'debit|credit']
     */
    public function ledgerBalance(int $ledgerId, ?string $asOnDate = null): array
    {
        $ledger = $this->pdo->prepare("SELECT opening_balance, opening_balance_type FROM ledger_accounts WHERE id=?");
        $ledger->execute([$ledgerId]);
        $l = $ledger->fetch();
        $signed = $l['opening_balance_type'] === 'debit' ? (float)$l['opening_balance'] : -(float)$l['opening_balance'];

        $adjStmt = $this->pdo->prepare("SELECT adjustment_type, SUM(amount) amt FROM opening_balance_adjustments WHERE ledger_id=? GROUP BY adjustment_type");
        $adjStmt->execute([$ledgerId]);
        foreach ($adjStmt->fetchAll() as $a) {
            $signed += $a['adjustment_type'] === 'debit' ? (float)$a['amt'] : -(float)$a['amt'];
        }

        $sql = "SELECT
                  SUM(CASE WHEN ve.dr_cr='debit' THEN ve.amount ELSE 0 END) d,
                  SUM(CASE WHEN ve.dr_cr='credit' THEN ve.amount ELSE 0 END) c
                FROM voucher_entries ve
                JOIN vouchers v ON v.id = ve.voucher_id
                WHERE ve.ledger_id = ? AND v.status='posted'";
        $params = [$ledgerId];
        if ($asOnDate) { $sql .= " AND v.voucher_date <= ?"; $params[] = $asOnDate; }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $r = $stmt->fetch();
        $signed += (float)($r['d'] ?? 0) - (float)($r['c'] ?? 0);

        return ['balance' => abs($signed), 'dr_cr' => $signed >= 0 ? 'debit' : 'credit'];
    }

    /** Trial balance: every ledger with its closing dr/cr balance. */
    public function trialBalance(?string $asOnDate = null): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT la.id, la.name, ag.name AS group_name, ag.nature
             FROM ledger_accounts la
             JOIN account_groups ag ON ag.id = la.group_id
             WHERE la.company_id = ? AND la.is_active = 1
             ORDER BY ag.name, la.name"
        );
        $stmt->execute([$this->companyId]);
        $rows = [];
        $totalDr = 0; $totalCr = 0;
        foreach ($stmt->fetchAll() as $row) {
            $bal = $this->ledgerBalance((int)$row['id'], $asOnDate);
            if ($bal['balance'] == 0) continue; // skip nil balances
            $row['debit']  = $bal['dr_cr'] === 'debit' ? $bal['balance'] : 0;
            $row['credit'] = $bal['dr_cr'] === 'credit' ? $bal['balance'] : 0;
            $totalDr += $row['debit'];
            $totalCr += $row['credit'];
            $rows[] = $row;
        }
        return ['rows' => $rows, 'total_debit' => $totalDr, 'total_credit' => $totalCr];
    }

    /**
     * Profit & Loss for a period, built purely from account_groups.nature
     * (income/expense) off the trial balance — Schedule VI notes reuse this.
     */
    public function profitAndLoss(string $fromDate, string $toDate): array
    {
        $sql = "SELECT la.id, la.name, ag.name AS group_name, ag.nature, ag.affects_gross_profit,
                  SUM(CASE WHEN ve.dr_cr='debit' THEN ve.amount ELSE 0 END) d,
                  SUM(CASE WHEN ve.dr_cr='credit' THEN ve.amount ELSE 0 END) c
                FROM voucher_entries ve
                JOIN vouchers v ON v.id = ve.voucher_id
                JOIN ledger_accounts la ON la.id = ve.ledger_id
                JOIN account_groups ag ON ag.id = la.group_id
                WHERE la.company_id = ? AND v.status='posted'
                  AND ag.nature IN ('income','expense')
                  AND v.voucher_date BETWEEN ? AND ?
                GROUP BY la.id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$this->companyId, $fromDate, $toDate]);

        $directIncome = 0; $directExpense = 0; $indirectIncome = 0; $indirectExpense = 0;
        $lines = ['direct_income'=>[], 'direct_expense'=>[], 'indirect_income'=>[], 'indirect_expense'=>[]];

        foreach ($stmt->fetchAll() as $row) {
            $net = $row['nature'] === 'income'
                ? (float)$row['c'] - (float)$row['d']
                : (float)$row['d'] - (float)$row['c'];
            if ($net == 0) continue;

            if ($row['nature'] === 'income' && $row['affects_gross_profit']) { $directIncome += $net; $lines['direct_income'][] = [$row['name'], $net]; }
            elseif ($row['nature'] === 'income') { $indirectIncome += $net; $lines['indirect_income'][] = [$row['name'], $net]; }
            elseif ($row['nature'] === 'expense' && $row['affects_gross_profit']) { $directExpense += $net; $lines['direct_expense'][] = [$row['name'], $net]; }
            else { $indirectExpense += $net; $lines['indirect_expense'][] = [$row['name'], $net]; }
        }

        $grossProfit = $directIncome - $directExpense;
        $netProfit = $grossProfit + $indirectIncome - $indirectExpense;

        return compact('lines', 'directIncome', 'directExpense', 'indirectIncome', 'indirectExpense', 'grossProfit', 'netProfit');
    }

    /** Balance Sheet as of date, grouped by nature (asset/liability) + P&L reserve rolled in. */
    public function balanceSheet(string $asOnDate, float $netProfitToDate): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT la.id, la.name, ag.name AS group_name, ag.nature, ag.schedule6_head
             FROM ledger_accounts la
             JOIN account_groups ag ON ag.id = la.group_id
             WHERE la.company_id = ? AND ag.nature IN ('asset','liability') AND la.is_active=1"
        );
        $stmt->execute([$this->companyId]);

        $assets = []; $liabilities = []; $totalAssets = 0; $totalLiabilities = 0;
        foreach ($stmt->fetchAll() as $row) {
            $bal = $this->ledgerBalance((int)$row['id'], $asOnDate);
            if ($bal['balance'] == 0) continue;
            $isAssetSide = ($row['nature'] === 'asset' && $bal['dr_cr'] === 'debit')
                        || ($row['nature'] === 'liability' && $bal['dr_cr'] === 'debit'); // contra balance flips side
            $entry = ['name' => $row['name'], 'group' => $row['group_name'], 'schedule6_head' => $row['schedule6_head'], 'amount' => $bal['balance']];
            if ($row['nature'] === 'asset') { $assets[] = $entry; $totalAssets += $bal['balance']; }
            else { $liabilities[] = $entry; $totalLiabilities += $bal['balance']; }
        }
        $liabilities[] = ['name' => 'Profit & Loss A/c (Current Year)', 'group' => 'Reserves & Surplus', 'schedule6_head' => 'Reserves and Surplus', 'amount' => $netProfitToDate];
        $totalLiabilities += $netProfitToDate;

        return compact('assets', 'liabilities', 'totalAssets', 'totalLiabilities');
    }
}
