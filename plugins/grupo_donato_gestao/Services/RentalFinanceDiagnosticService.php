<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

/**
 * Auditoria somente leitura do ledger de locações.
 *
 * O serviço não corrige, cancela, recalcula nem marca nenhum registro. Ele
 * apenas retorna evidências para que uma correção de dados seja decidida com
 * segurança e executada por uma rotina específica, se necessário.
 */
final class RentalFinanceDiagnosticService extends CustomerDataService
{
    public function run(?string $reference_month = null): array
    {
        $reference_month = $reference_month ?: date('Y-m');
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $reference_month)) {
            throw new \DomainException('gd_finance_invalid_reference');
        }

        $first = $reference_month . '-01';
        $last = (new \DateTimeImmutable($first))->format('Y-m-t');
        $r = $this->db->prefixTable('gd_receivables');
        $items = $this->db->prefixTable('gd_receivable_items');
        $allocations = $this->db->prefixTable('gd_payment_allocations');
        $payments = $this->db->prefixTable('gd_payments');
        $court = $this->db->prefixTable('gd_court_rentals');
        $barbecue = $this->db->prefixTable('gd_barbecue_rentals');
        $enrollments = $this->db->prefixTable('gd_enrollments');
        $accounts = $this->db->prefixTable('gd_customer_accounts');

        $findings = [];
        $findings['active_monthly_without_charge'] = $this->rows(
            "SELECT 'court_rental' source_type, cr.id source_id, cr.rental_number, cr.title, cr.negotiated_amount
               FROM `$court` cr
              WHERE cr.unit_id=? AND cr.rental_type='recurring' AND cr.status='active' AND cr.deleted=0
                AND (cr.effective_from IS NULL OR cr.effective_from<=?)
                AND (cr.effective_until IS NULL OR cr.effective_until>=?)
                AND NOT EXISTS (SELECT 1 FROM `$r` x WHERE x.unit_id=cr.unit_id AND x.source_type='court_rental' AND x.source_id=cr.id AND x.reference_month=? AND x.deleted=0)
             UNION ALL
             SELECT 'barbecue_rental' source_type, br.id source_id, br.rental_number, br.title, br.negotiated_amount
               FROM `$barbecue` br
              WHERE br.unit_id=? AND br.rental_type='recurring' AND br.status='active' AND br.deleted=0
                AND (br.effective_from IS NULL OR br.effective_from<=?)
                AND (br.effective_until IS NULL OR br.effective_until>=?)
                AND NOT EXISTS (SELECT 1 FROM `$r` x WHERE x.unit_id=br.unit_id AND x.source_type='barbecue_rental' AND x.source_id=br.id AND x.reference_month=? AND x.deleted=0)",
            [$this->unit_id, $last, $first, $reference_month, $this->unit_id, $last, $first, $reference_month]
        );

        $findings['single_rental_without_charge'] = $this->rows(
            "SELECT 'court_rental' source_type, cr.id source_id, cr.rental_number, cr.title
               FROM `$court` cr
              WHERE cr.unit_id=? AND cr.rental_type='single' AND cr.deleted=0
                AND NOT EXISTS (SELECT 1 FROM `$r` x WHERE x.unit_id=cr.unit_id AND x.source_type='court_rental' AND x.source_id=cr.id AND x.reference_month='' AND x.deleted=0)
             UNION ALL
             SELECT 'barbecue_rental' source_type, br.id source_id, br.rental_number, br.title
               FROM `$barbecue` br
              WHERE br.unit_id=? AND br.rental_type='single' AND br.deleted=0
                AND NOT EXISTS (SELECT 1 FROM `$r` x WHERE x.unit_id=br.unit_id AND x.source_type='barbecue_rental' AND x.source_id=br.id AND x.reference_month='' AND x.deleted=0)",
            [$this->unit_id, $this->unit_id]
        );

        $findings['orphan_receivables'] = $this->rows(
            "SELECT r.id,r.receivable_number,r.source_type,r.source_id,r.reference_month,r.balance_amount
               FROM `$r` r
              WHERE r.unit_id=? AND r.deleted=0 AND (
                    (r.source_type='court_rental' AND NOT EXISTS (SELECT 1 FROM `$court` cr WHERE cr.id=r.source_id AND cr.unit_id=r.unit_id AND cr.deleted=0))
                 OR (r.source_type='barbecue_rental' AND NOT EXISTS (SELECT 1 FROM `$barbecue` br WHERE br.id=r.source_id AND br.unit_id=r.unit_id AND br.deleted=0))
                 OR (r.source_type='enrollment' AND NOT EXISTS (SELECT 1 FROM `$enrollments` e WHERE e.id=r.source_id AND e.unit_id=r.unit_id AND e.deleted=0))
                 OR (r.customer_account_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `$accounts` a WHERE a.id=r.customer_account_id AND a.unit_id=r.unit_id AND a.deleted=0))
              )
              ORDER BY r.id",
            [$this->unit_id]
        );

        $findings['orphan_receivable_items'] = $this->rows(
            "SELECT i.id,i.receivable_id,i.total_amount
               FROM `$items` i
              WHERE i.unit_id=? AND i.deleted=0
                AND NOT EXISTS (SELECT 1 FROM `$r` r WHERE r.id=i.receivable_id AND r.unit_id=i.unit_id AND r.deleted=0)",
            [$this->unit_id]
        );
        $findings['orphan_payment_allocations'] = $this->rows(
            "SELECT a.id,a.payment_id,a.receivable_id,a.allocated_amount
               FROM `$allocations` a
              WHERE a.unit_id=? AND a.status='active'
                AND (NOT EXISTS (SELECT 1 FROM `$r` r WHERE r.id=a.receivable_id AND r.unit_id=a.unit_id AND r.deleted=0)
                  OR NOT EXISTS (SELECT 1 FROM `$payments` p WHERE p.id=a.payment_id AND p.unit_id=a.unit_id AND p.deleted=0))",
            [$this->unit_id]
        );

        $findings['duplicate_competence'] = $this->rows(
            "SELECT source_type,source_id,reference_month,COUNT(*) quantity,GROUP_CONCAT(id ORDER BY id) receivable_ids
               FROM `$r`
              WHERE unit_id=? AND deleted=0 AND source_type IN ('court_rental','barbecue_rental','enrollment')
              GROUP BY source_type,source_id,reference_month HAVING COUNT(*)>1",
            [$this->unit_id]
        );
        $findings['zero_or_negative_charge'] = $this->rows(
            "SELECT id,receivable_number,source_type,source_id,reference_month,original_amount,balance_amount
               FROM `$r`
              WHERE unit_id=? AND deleted=0 AND status<>'cancelled' AND (original_amount<=0 OR balance_amount<0)
              ORDER BY id",
            [$this->unit_id]
        );
        $findings['invalid_due_date'] = $this->rows(
            "SELECT id,receivable_number,source_type,source_id,reference_month,issue_date,due_date,status
               FROM `$r`
              WHERE unit_id=? AND deleted=0 AND (
                    due_date<issue_date
                 OR (reference_month<>'' AND DATE_FORMAT(due_date,'%Y-%m')<>reference_month)
                 OR (status='overdue' AND due_date>=CURDATE())
              )
              ORDER BY id",
            [$this->unit_id]
        );
        $findings['paid_still_overdue'] = $this->rows(
            "SELECT id,receivable_number,source_type,source_id,reference_month,status,paid_amount,balance_amount,due_date
               FROM `$r`
              WHERE unit_id=? AND deleted=0 AND status='overdue' AND balance_amount<=0
              ORDER BY id",
            [$this->unit_id]
        );
        $findings['open_past_due_projection'] = $this->rows(
            "SELECT id,receivable_number,source_type,source_id,reference_month,status,balance_amount,due_date
               FROM `$r`
              WHERE unit_id=? AND deleted=0 AND status IN ('open','partial') AND balance_amount>0 AND due_date<CURDATE()
              ORDER BY due_date,id",
            [$this->unit_id]
        );
        $findings['payment_button_invalid_context'] = $this->rows(
            "SELECT r.id,r.receivable_number,r.source_type,r.source_id,r.reference_month,r.status,r.balance_amount
               FROM `$r` r
              WHERE r.unit_id=? AND r.deleted=0 AND r.status IN ('open','partial','overdue') AND r.balance_amount>0 AND (
                    (r.source_type='court_rental' AND (NOT EXISTS (SELECT 1 FROM `$court` cr WHERE cr.id=r.source_id AND cr.unit_id=r.unit_id AND cr.deleted=0)
                        OR NOT EXISTS (SELECT 1 FROM `$accounts` a JOIN `$court` cr2 ON cr2.customer_account_id=a.id AND cr2.unit_id=a.unit_id WHERE cr2.id=r.source_id AND cr2.unit_id=r.unit_id AND cr2.deleted=0 AND a.deleted=0)))
                 OR (r.source_type='barbecue_rental' AND (NOT EXISTS (SELECT 1 FROM `$barbecue` br WHERE br.id=r.source_id AND br.unit_id=r.unit_id AND br.deleted=0)
                        OR NOT EXISTS (SELECT 1 FROM `$accounts` a JOIN `$barbecue` br2 ON br2.customer_account_id=a.id AND br2.unit_id=a.unit_id WHERE br2.id=r.source_id AND br2.unit_id=r.unit_id AND br2.deleted=0 AND a.deleted=0)))
              )",
            [$this->unit_id]
        );

        $summary = [];
        foreach ($findings as $name => $rows) { $summary[$name] = count($rows); }
        return [
            'read_only' => true,
            'unit_id' => $this->unit_id,
            'reference_month' => $reference_month,
            'checked_at' => gmdate('c'),
            'summary' => $summary,
            'findings' => $findings,
        ];
    }

    private function rows(string $sql, array $params): array
    {
        return array_map(static fn($row): array => (array) $row, $this->db->query($sql, $params)->getResult());
    }
}
