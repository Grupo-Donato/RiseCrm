<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

final class ReceivableGenerationService extends CustomerDataService
{
    private ?object $login_user;

    public function __construct(int $unit_id, int $actor_id = 0, ?object $login_user = null)
    {
        parent::__construct($unit_id, $actor_id, $login_user);
        $this->login_user = $login_user;
    }

    public function preview(string $month): array
    {
        $this->month($month);
        $first = $month . '-01';
        $last = (new \DateTimeImmutable($first))->format('Y-m-t');
        $out = [];
        $enrollments = $this->db->query(
            "SELECT e.id,e.product_id,e.preferred_due_day,c.name class_name,p.full_name,sp.family_account_id,a.display_name customer_name
               FROM `{$this->db->prefixTable('gd_enrollments')}` e
               JOIN `{$this->db->prefixTable('gd_school_profiles')}` sp ON sp.id=e.school_profile_id AND sp.unit_id=e.unit_id AND sp.deleted=0
               JOIN `{$this->db->prefixTable('gd_people')}` p ON p.id=sp.person_id AND p.deleted=0
               JOIN `{$this->db->prefixTable('gd_customer_accounts')}` a ON a.id=sp.family_account_id AND a.deleted=0
               JOIN `{$this->db->prefixTable('gd_classes')}` c ON c.id=e.class_id AND c.deleted=0
              WHERE e.unit_id=? AND e.status='active' AND e.deleted=0 AND e.starts_on<=? AND (e.ends_on IS NULL OR e.ends_on>=?)",
            [$this->unit_id, $last, $first]
        )->getResult();

        foreach ($enrollments as $row) {
            $amount = null;
            if ($row->product_id) {
                $price = (new PricingService($this->unit_id, $this->actor_id, $this->login_user))->resolve([
                    'product_id' => (int) $row->product_id, 'reference_date' => $first, 'quantity' => '1',
                ]);
                if (!empty($price['found'])) { $amount = $price['amount']; }
            }
            $out[] = [
                'key' => 'enrollment:' . $row->id, 'source_type' => 'enrollment', 'source_id' => (int) $row->id,
                'customer_account_id' => (int) $row->family_account_id, 'customer_name' => $row->customer_name,
                'description' => 'Mensalidade ' . $row->class_name . ' — ' . $row->full_name,
                'product_id' => (int) $row->product_id, 'amount' => $amount,
                'due_date' => $this->due($month, (int) ($row->preferred_due_day ?: 10)), 'ready' => $amount !== null,
            ];
        }

        $this->appendRentalPreview($out, 'gd_court_rentals', 'court_rental', 'Mensalista', $first, $last, $month);
        $this->appendRentalPreview($out, 'gd_barbecue_rentals', 'barbecue_rental', 'Mensalista churrasqueira', $first, $last, $month);
        return $out;
    }

    /** Garante a cobrança de uma competência sem criar duplicatas. */
    public function ensureMonth(string $month, ?string $source_type = null): array
    {
        $rows = $this->preview($month);
        $result = ['created' => 0, 'duplicates' => 0, 'pending' => [], 'errors' => []];
        $finance = new FinanceService($this->unit_id, $this->actor_id, $this->login_user);
        foreach ($rows as $row) {
            if ($source_type !== null && ($row['source_type'] ?? '') !== $source_type) { continue; }
            if (($row['amount'] ?? null) === null || ($row['amount'] ?? '') === '') {
                $result['pending'][] = $row['key'] ?? '';
                continue;
            }
            try {
                $due = (string) ($row['due_date'] ?? gmdate('Y-m-d'));
                $saved = $finance->createReceivable($row + [
                    'reference_month' => $month, 'issue_date' => min(gmdate('Y-m-d'), $due),
                    'due_date' => $due, 'original_amount' => $row['amount'],
                    'unit_amount' => $row['amount'], 'quantity' => '1',
                ]);
                if (!empty($saved['created'])) { $result['created']++; }
                else { $result['duplicates']++; }
            } catch (\Throwable $e) {
                $result['errors'][] = ['key' => $row['key'] ?? '', 'error' => $e->getMessage()];
                log_message('critical', 'GD rental receivable generation failed [' . ($row['key'] ?? '') . ']: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            }
        }
        return $result;
    }

    public function generateMonth(string $month, array $adjustments = []): array
    {
        $rows = $this->preview($month);
        $result = ['generated' => [], 'ignored' => [], 'errors' => []];
        $finance = new FinanceService($this->unit_id, $this->actor_id, $this->login_user);
        foreach ($rows as $row) {
            $adjustment = (array) ($adjustments[$row['key']] ?? []);
            $amount = $adjustment['amount'] ?? $row['amount'];
            $due = $adjustment['due_date'] ?? $row['due_date'];
            if ($amount === null || $amount === '') {
                $result['errors'][] = ['key' => $row['key'], 'error' => 'gd_finance_amount_required'];
                continue;
            }
            try {
                $saved = $finance->createReceivable($row + [
                    'reference_month' => $month, 'issue_date' => min(gmdate('Y-m-d'), $due),
                    'due_date' => $due, 'original_amount' => $amount,
                    'unit_amount' => $amount, 'quantity' => '1',
                ]);
                if (!empty($saved['created'])) { $result['generated'][] = $saved; }
                else { $result['ignored'][] = $row['key']; }
            } catch (\Throwable $e) {
                $result['errors'][] = ['key' => $row['key'], 'error' => $e->getMessage()];
            }
        }
        return $result;
    }

    public function generateCourtRental(int $id, array $override = []): array
    {
        return $this->generateSingleRental($id, 'gd_court_rentals', 'court_rental', 'Locação avulsa', $override);
    }

    public function generateBarbecueRental(int $id, array $override = []): array
    {
        return $this->generateSingleRental($id, 'gd_barbecue_rentals', 'barbecue_rental', 'Aluguel de churrasqueira', $override);
    }

    private function appendRentalPreview(array &$out, string $table, string $source, string $descriptionPrefix, string $first, string $last, string $month): void
    {
        $rentals = $this->db->table($this->db->prefixTable($table) . ' r')
            ->select('r.id,r.customer_account_id,r.title,r.preferred_due_day,r.negotiated_amount,r.extra_time_amount,r.extra_time_notes,r.product_id,r.metadata,a.display_name customer_name')
            ->join($this->db->prefixTable('gd_customer_accounts') . ' a', 'a.id=r.customer_account_id AND a.unit_id=r.unit_id AND a.deleted=0', 'inner', false)
            ->where('r.unit_id', $this->unit_id)->where('r.rental_type', 'recurring')->where('r.status', 'active')->where('r.deleted', 0)
            ->groupStart()->where('r.effective_from IS NULL', null, false)->orWhere('r.effective_from <=', $last)->groupEnd()
            ->groupStart()->where('r.effective_until IS NULL', null, false)->orWhere('r.effective_until >=', $first)->groupEnd()
            ->get()->getResult();
        foreach ($rentals as $row) {
            if ($this->isExempt($row->metadata ?? null)) { continue; }
            $amount = $row->negotiated_amount === null ? null : $this->addMoney((string) $row->negotiated_amount, (string) ($row->extra_time_amount ?? '0.00'));
            $out[] = [
                'key' => $source . ':' . $row->id, 'source_type' => $source, 'source_id' => (int) $row->id,
                'customer_account_id' => (int) $row->customer_account_id, 'customer_name' => $row->customer_name,
                'description' => $descriptionPrefix . ' — ' . $row->title, 'product_id' => (int) $row->product_id,
                'amount' => $amount, 'notes' => (string) ($row->extra_time_notes ?? ''),
                'due_date' => $this->due($month, (int) ($row->preferred_due_day ?: 10)), 'ready' => $amount !== null,
            ];
        }
    }

    private function generateSingleRental(int $id, string $table, string $source, string $prefix, array $override): array
    {
        $row = $this->db->table($this->db->prefixTable($table))->where('id', $id)->where('unit_id', $this->unit_id)->where('deleted', 0)->get(1)->getRow();
        if (!$row || $row->rental_type !== 'single') { throw new \DomainException('gd_record_not_found'); }
        if ($this->isExempt($row->metadata ?? null)) { throw new \DomainException('gd_finance_exempt_rental'); }
        $amount = $override['amount'] ?? ($row->negotiated_amount === null ? null : $this->addMoney((string) $row->negotiated_amount, (string) ($row->extra_time_amount ?? '0.00')));
        if ($amount === null) { throw new \DomainException('gd_finance_amount_required'); }
        $due = (string) ($override['due_date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) {
            $due = preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $row->effective_from) ? substr((string) $row->effective_from, 0, 10) : gmdate('Y-m-d');
        }
        return (new FinanceService($this->unit_id, $this->actor_id, $this->login_user))->createReceivable([
            'source_type' => $source, 'source_id' => $id, 'description' => $prefix . ' — ' . $row->title,
            'issue_date' => min(gmdate('Y-m-d'), $due), 'due_date' => $due,
            'original_amount' => $amount, 'unit_amount' => $amount, 'quantity' => '1',
            'product_id' => (int) $row->product_id, 'notes' => (string) ($row->extra_time_notes ?? ''),
        ]);
    }

    private function isExempt($metadata): bool
    {
        $data = json_decode((string) $metadata, true);
        return is_array($data) && (string) ($data['financial_status'] ?? '') === 'exempt';
    }

    private function month(string $month): void
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) { throw new \DomainException('gd_finance_invalid_reference'); }
    }

    private function due(string $month, int $day): string
    {
        $this->month($month);
        $max = (int) (new \DateTimeImmutable($month . '-01'))->format('t');
        return sprintf('%s-%02d', $month, min(max($day, 1), $max));
    }

    private function addMoney(string $left, string $right): string
    {
        $left = DataNormalizationService::decimal($left, 2);
        $right = DataNormalizationService::decimal($right, 2);
        [$leftInt, $leftFraction] = explode('.', $left);
        [$rightInt, $rightFraction] = explode('.', $right);
        $cents = ((int) $leftInt * 100 + (int) $leftFraction) + ((int) $rightInt * 100 + (int) $rightFraction);
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
