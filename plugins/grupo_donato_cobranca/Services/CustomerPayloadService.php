<?php

declare(strict_types=1);

namespace grupo_donato_cobranca\Services;

final class CustomerPayloadService
{
    private $db;
    private int $unitId;

    public function __construct(int $unitId)
    {
        $this->db = db_connect();
        $this->unitId = $unitId;
    }

    public function build(int $accountId): array
    {
        $accountTable = $this->db->prefixTable('gd_customer_accounts');
        $account = $this->db->table($accountTable)->where('id', $accountId)->where('unit_id', $this->unitId)->where('deleted', 0)->get(1)->getRow();
        if (!$account) {
            throw new \DomainException('gdc_customer_not_found');
        }

        $links = $this->db->prefixTable('gd_account_people');
        $people = $this->db->prefixTable('gd_people');
        $contacts = $this->db->prefixTable('gd_contact_methods');
        $person = $this->db->table($links)
            ->select("$people.id,$people.full_name", false)
            ->join($people, "$people.id=$links.person_id AND $people.unit_id=$links.unit_id AND $people.deleted=0", 'inner', false)
            ->where("$links.unit_id", $this->unitId)->where("$links.account_id", $accountId)
            ->where("$links.deleted", 0)->where("$links.status", 'active')
            ->orderBy("$links.is_financial_responsible", 'DESC')->orderBy("$links.is_primary", 'DESC')
            ->get(1)->getRow();

        $email = (string) ($account->email ?? '');
        $phone = (string) (($account->whatsapp ?? '') ?: ($account->phone ?? ''));
        if ($person) {
            $contactRows = $this->db->table($contacts)
                ->select('contact_type,value,is_primary')
                ->where('unit_id', $this->unitId)->where('person_id', (int) $person->id)
                ->where('deleted', 0)->where('status', 'active')
                ->orderBy('is_primary', 'DESC')->get()->getResult();
            foreach ($contactRows as $contact) {
                if ($email === '' && (string) $contact->contact_type === 'email') {
                    $email = (string) $contact->value;
                }
                if ($phone === '' && in_array((string) $contact->contact_type, ['whatsapp', 'phone'], true)) {
                    $phone = (string) $contact->value;
                }
            }
        }

        return [
            'local_customer_id' => (string) $accountId,
            'name' => (string) (($person->full_name ?? '') ?: $account->display_name),
            'document_type' => (string) ($account->document_type ?? 'none'),
            'document' => preg_replace('/\D+/', '', (string) ($account->document_number ?? '')),
            'email' => $email,
            'phone' => preg_replace('/\D+/', '', $phone),
            'metadata' => ['unit_id' => $this->unitId, 'customer_account_id' => $accountId],
        ];
    }
}
