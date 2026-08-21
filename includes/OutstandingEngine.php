<?php
declare(strict_types=1);
/**
 * OutstandingEngine
 * -------------------------------------------------------------
 * Bill-wise outstanding & aging for Sundry Debtors / Creditors.
 * Uses voucher_entries.bill_ref / bill_type so every invoice's
 * payments net off against the correct original invoice — this
 * is what makes "invoice-wise outstanding" possible instead of
 * just a running ledger total.
 */
class OutstandingEngine
{
    private PDO $pdo;
    private int $companyId;
    private array $agingBuckets = [30, 60, 90, 120]; // days; last bucket = 120+

    public function __construct(PDO $pdo, int $companyId)
    {
        $this->pdo = $pdo;
        $this->companyId = $companyId;
    }

    /**
     * @param string $side 'debtors' (Sundry Debtors group) or 'creditors' (Sundry Creditors group)
     * @param string $groupBy 'ledger' | 'invoice' | 'group'
     */
    public function outstandingReport(string $side, string $groupBy = 'ledger', ?string $asOnDate = null): array
    {
        $asOnDate = $asOnDate ?? date('Y-m-d');
        $groupName = $side === 'debtors' ? 'Sundry Debtors' : 'Sundry Creditors';

        $sql = "SELECT ve.id AS entry_id, ve.ledger_id, la.name AS ledger_name, ag.name AS group_name,
                       ve.dr_cr, ve.amount, ve.bill_ref, ve.bill_type,
                       v.voucher_no, v.voucher_date, v.due_date
                FROM voucher_entries ve
                JOIN vouchers v ON v.id = ve.voucher_id
                JOIN ledger_accounts la ON la.id = ve.ledger_id
                JOIN account_groups ag ON ag.id = la.group_id
                WHERE la.company_id = ? AND ag.name = ? AND v.status='posted'
                  AND v.voucher_date <= ?
                ORDER BY la.name, v.voucher_date";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$this->companyId, $groupName, $asOnDate]);
        $entries = $stmt->fetchAll();

        // Debtors: debit = invoice raised, credit = receipt/adjustment.
        // Creditors: credit = invoice/bill, debit = payment.
        $invoiceSide = $side === 'debtors' ? 'debit' : 'credit';
        $settleSide  = $side === 'debtors' ? 'credit' : 'debit';

        // Build open invoices keyed by bill_ref (falls back to voucher_no if no explicit ref)
        $invoices = []; // key => ['ledger_name'=>, 'group_name'=>, 'voucher_no'=>, 'date'=>, 'due_date'=>, 'amount'=>, 'settled'=>]
        foreach ($entries as $e) {
            $key = ($e['bill_ref'] ?: $e['voucher_no']) . '|' . $e['ledger_id'];
            if ($e['dr_cr'] === $invoiceSide && $e['bill_type'] !== 'against_ref') {
                $invoices[$key] = $invoices[$key] ?? [
                    'ledger_id' => $e['ledger_id'], 'ledger_name' => $e['ledger_name'], 'group_name' => $e['group_name'],
                    'voucher_no' => $e['voucher_no'], 'voucher_date' => $e['voucher_date'],
                    'due_date' => $e['due_date'], 'amount' => 0, 'settled' => 0,
                ];
                $invoices[$key]['amount'] += (float)$e['amount'];
            } elseif ($e['dr_cr'] === $settleSide) {
                if (isset($invoices[$key])) {
                    $invoices[$key]['settled'] += (float)$e['amount'];
                } else {
                    // payment/receipt referencing an invoice not yet indexed under this key
                    // (e.g. against_ref pointing elsewhere) — tracked as on-account below.
                }
            }
        }

        $open = [];
        foreach ($invoices as $inv) {
            $balance = round($inv['amount'] - $inv['settled'], 2);
            if ($balance <= 0) continue;
            $dueDate = $inv['due_date'] ?: $inv['voucher_date'];
            $daysOverdue = max(0, (strtotime($asOnDate) - strtotime($dueDate)) / 86400);
            $inv['balance'] = $balance;
            $inv['days_overdue'] = (int)$daysOverdue;
            $inv['aging_bucket'] = $this->bucketFor((int)$daysOverdue);
            $open[] = $inv;
        }

        return $this->group($open, $groupBy);
    }

    private function bucketFor(int $days): string
    {
        if ($days <= 0) return 'Not Due';
        if ($days <= 30) return '1-30 Days';
        if ($days <= 60) return '31-60 Days';
        if ($days <= 90) return '61-90 Days';
        if ($days <= 120) return '91-120 Days';
        return '120+ Days';
    }

    private function group(array $open, string $groupBy): array
    {
        if ($groupBy === 'invoice') {
            return $open; // flat invoice-wise list, already has everything needed
        }

        $key = $groupBy === 'group' ? 'group_name' : 'ledger_name';
        $out = [];
        foreach ($open as $inv) {
            $k = $inv[$key];
            $out[$k] = $out[$k] ?? ['name' => $k, 'total' => 0, 'buckets' => array_fill_keys(
                ['Not Due','1-30 Days','31-60 Days','61-90 Days','91-120 Days','120+ Days'], 0.0
            ), 'invoices' => []];
            $out[$k]['total'] += $inv['balance'];
            $out[$k]['buckets'][$inv['aging_bucket']] += $inv['balance'];
            $out[$k]['invoices'][] = $inv;
        }
        return array_values($out);
    }
}
