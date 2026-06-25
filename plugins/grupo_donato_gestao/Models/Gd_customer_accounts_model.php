<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

class Gd_customer_accounts_model extends Gd_Model
{
    public function __construct() { parent::__construct("gd_customer_accounts"); }

    public function get_scoped(int $id, int $unit_id, bool $include_deleted = false): ?object
    {
        $builder = $this->db->table($this->table)->where("id", $id)->where("unit_id", $unit_id);
        if (!$include_deleted) { $builder->where("deleted", 0); }
        return $builder->get(1)->getRow();
    }

    public function get_details(array $options = []): array
    {
        $unit_id = (int) get_array_value($options, "unit_id");
        $accounts = $this->table;
        $links = $this->db->prefixTable("gd_account_people");
        $people = $this->db->prefixTable("gd_people");

        $base = function () use ($options, $unit_id, $accounts, $links, $people) {
            $b = $this->db->table($accounts)
                ->join($links, "$links.account_id=$accounts.id AND $links.unit_id=$accounts.unit_id AND $links.deleted=0 AND $links.status='active'", "left", false)
                ->join($people, "$people.id=$links.person_id AND $people.unit_id=$accounts.unit_id AND $people.deleted=0", "left", false)
                ->where("$accounts.unit_id", $unit_id)->where("$accounts.deleted", 0);
            foreach (["account_type", "status"] as $field) {
                $value = get_array_value($options, $field);
                if ($value) { $b->where("$accounts.$field", $value); }
            }
            $search = trim((string) get_array_value($options, "search_by"));
            if ($search !== "") {
                $digits = preg_replace('/\D+/', '', $search) ?: $search;
                $b->groupStart()->like("$accounts.display_name", $search)->orLike("$accounts.legal_name", $search)
                    ->orLike("$accounts.trade_name", $search)->orLike("$accounts.document_number_normalized", $digits)
                    ->orLike("$accounts.email_normalized", mb_strtolower($search))->orLike("$accounts.phone_normalized", $digits)
                    ->orLike("$accounts.whatsapp_normalized", $digits)->orLike("$people.full_name", $search)->groupEnd();
            }
            return $b;
        };

        $records_total = $this->db->table($accounts)->where("unit_id", $unit_id)->where("deleted", 0)->countAllResults();
        $count_row = $base()->select("COUNT(DISTINCT $accounts.id) AS total", false)->get()->getRow();
        $builder = $base()->select("$accounts.*, COUNT(DISTINCT $links.person_id) AS people_count", false)->groupBy("$accounts.id");
        $order_map = ["display_name" => "$accounts.display_name", "account_type" => "$accounts.account_type", "status" => "$accounts.status", "updated_at" => "$accounts.updated_at", "id" => "$accounts.id"];
        $order = $order_map[(string) get_array_value($options, "order_by")] ?? "$accounts.display_name";
        $dir = get_array_value($options, "order_dir") === "DESC" ? "DESC" : "ASC";
        $builder->orderBy($order, $dir);
        $limit = max(1, min(100, (int) (get_array_value($options, "limit") ?: 25)));
        $builder->limit($limit, max(0, (int) get_array_value($options, "skip")));
        return ["data" => $builder->get()->getResult(), "recordsTotal" => $records_total, "recordsFiltered" => (int) ($count_row->total ?? 0)];
    }

    public function active_relation_count(int $id, int $unit_id): int
    {
        $table = $this->db->prefixTable("gd_account_people");
        return $this->db->table($table)->where("account_id", $id)->where("unit_id", $unit_id)->where("deleted", 0)->where("status", "active")->countAllResults();
    }
}
