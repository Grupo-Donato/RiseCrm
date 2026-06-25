<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

class Gd_people_model extends Gd_Model
{
    public function __construct() { parent::__construct("gd_people"); }

    public function get_scoped(int $id, int $unit_id, bool $include_deleted = false): ?object
    {
        $b = $this->db->table($this->table)->where("id", $id)->where("unit_id", $unit_id);
        if (!$include_deleted) { $b->where("deleted", 0); }
        return $b->get(1)->getRow();
    }

    public function get_details(array $options = []): array
    {
        $unit_id = (int) get_array_value($options, "unit_id");
        $people = $this->table;
        $contacts = $this->db->prefixTable("gd_contact_methods");
        $links = $this->db->prefixTable("gd_account_people");
        $accounts = $this->db->prefixTable("gd_customer_accounts");
        $base = function () use ($options, $unit_id, $people, $contacts, $links, $accounts) {
            $b = $this->db->table($people)
                ->join($contacts, "$contacts.person_id=$people.id AND $contacts.unit_id=$people.unit_id AND $contacts.deleted=0 AND $contacts.status='active'", "left", false)
                ->join($links, "$links.person_id=$people.id AND $links.unit_id=$people.unit_id AND $links.deleted=0 AND $links.status='active'", "left", false)
                ->join($accounts, "$accounts.id=$links.account_id AND $accounts.unit_id=$people.unit_id AND $accounts.deleted=0", "left", false)
                ->where("$people.unit_id", $unit_id)->where("$people.deleted", 0);
            $status = get_array_value($options, "status");
            if ($status) { $b->where("$people.status", $status); }
            $search = trim((string) get_array_value($options, "search_by"));
            if ($search !== "") {
                $digits = preg_replace('/\D+/', '', $search) ?: $search;
                $b->groupStart()->like("$people.full_name", $search)->orLike("$people.preferred_name", $search)
                    ->orLike("$contacts.normalized_value", mb_strtolower($digits))->orLike("$contacts.value", $search)
                    ->orLike("$accounts.display_name", $search)->groupEnd();
            }
            return $b;
        };
        $records_total = $this->db->table($people)->where("unit_id", $unit_id)->where("deleted", 0)->countAllResults();
        $count = $base()->select("COUNT(DISTINCT $people.id) total", false)->get()->getRow();
        $builder = $base()->select("$people.*, COUNT(DISTINCT $links.account_id) account_count, MAX(CASE WHEN $contacts.is_primary=1 THEN $contacts.value ELSE NULL END) primary_contact, MAX(CASE WHEN $contacts.is_primary=1 THEN $contacts.contact_type ELSE NULL END) primary_contact_type", false)->groupBy("$people.id");
        $order_map = ["full_name" => "$people.full_name", "birth_date" => "$people.birth_date", "status" => "$people.status", "updated_at" => "$people.updated_at", "id" => "$people.id"];
        $builder->orderBy($order_map[(string) get_array_value($options, "order_by")] ?? "$people.full_name", get_array_value($options, "order_dir") === "DESC" ? "DESC" : "ASC");
        $builder->limit(max(1, min(100, (int) (get_array_value($options, "limit") ?: 25))), max(0, (int) get_array_value($options, "skip")));
        return ["data" => $builder->get()->getResult(), "recordsTotal" => $records_total, "recordsFiltered" => (int) ($count->total ?? 0)];
    }
}
