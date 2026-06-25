<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

class DuplicateDetectionService
{
    private $db;
    private int $unit_id;

    public function __construct(int $unit_id)
    {
        $this->db = db_connect();
        $this->unit_id = $unit_id;
    }

    public function accounts(array $data, int $exclude_id = 0): array
    {
        $table = $this->db->prefixTable("gd_customer_accounts");
        $candidates = [];
        $exact_fields = ["document_number_normalized", "email_normalized", "phone_normalized", "whatsapp_normalized", "normalized_name"];
        $signals = array_filter(array_intersect_key($data, array_flip($exact_fields)), static fn($value) => $value !== null && $value !== "");

        if ($signals) {
            $builder = $this->db->table($table)->where("unit_id", $this->unit_id)->where("deleted", 0);
            if ($exclude_id) {
                $builder->where("id !=", $exclude_id);
            }
            $builder->groupStart();
            $first = true;
            foreach ($signals as $field => $value) {
                $first ? $builder->where($field, $value) : $builder->orWhere($field, $value);
                $first = false;
            }
            $builder->groupEnd()->limit(100);
            foreach ($builder->get()->getResult() as $row) {
                $candidates[(int) $row->id] = $row;
            }
        }

        // Similaridade é assistiva e deliberadamente limitada para evitar scan ilimitado.
        $recent = $this->db->table($table)->where("unit_id", $this->unit_id)->where("deleted", 0);
        if ($exclude_id) {
            $recent->where("id !=", $exclude_id);
        }
        foreach ($recent->orderBy("id", "DESC")->limit(100)->get()->getResult() as $row) {
            if (self::similar((string) ($data["normalized_name"] ?? ""), (string) $row->normalized_name)) {
                $candidates[(int) $row->id] = $row;
            }
        }

        $result = [];
        foreach ($candidates as $row) {
            $matched = [];
            $confidence = "low";
            if (!empty($data["document_number_normalized"]) && $data["document_number_normalized"] === $row->document_number_normalized) {
                $matched[] = "document";
                $confidence = "exact";
            }
            foreach (["email_normalized" => "email", "phone_normalized" => "phone", "whatsapp_normalized" => "whatsapp"] as $field => $label) {
                if (!empty($data[$field]) && $data[$field] === $row->$field) {
                    $matched[] = $label;
                    if ($confidence !== "exact") {
                        $confidence = "high";
                    }
                }
            }
            if (($data["normalized_name"] ?? "") === $row->normalized_name) {
                $matched[] = "name";
                if ($confidence === "low") {
                    $confidence = "medium";
                }
            } elseif (self::similar((string) ($data["normalized_name"] ?? ""), (string) $row->normalized_name)) {
                $matched[] = "similar_name";
            }
            if ($matched) {
                $result[] = ["record_id" => (int) $row->id, "entity_type" => "customer_account", "confidence" => $confidence, "matched_fields" => $matched, "display_summary" => $row->display_name];
            }
        }
        return $result;
    }

    public function people(array $data, int $exclude_id = 0): array
    {
        $people = $this->db->prefixTable("gd_people");
        $contacts = $this->db->prefixTable("gd_contact_methods");
        $candidates = [];
        $normalized_name = (string) ($data["normalized_name"] ?? "");
        $contact_values = array_values(array_filter((array) ($data["contact_values"] ?? [])));

        if ($normalized_name !== "") {
            $builder = $this->db->table($people)->where("unit_id", $this->unit_id)->where("deleted", 0)->where("normalized_name", $normalized_name);
            if ($exclude_id) {
                $builder->where("id !=", $exclude_id);
            }
            foreach ($builder->limit(100)->get()->getResult() as $row) {
                $candidates[(int) $row->id] = $row;
            }
        }

        if ($contact_values) {
            $ids = $this->db->table($contacts)->select("person_id")->distinct()->where("unit_id", $this->unit_id)->where("deleted", 0)->whereIn("normalized_value", $contact_values)->limit(100)->get()->getResultArray();
            $person_ids = array_map("intval", array_column($ids, "person_id"));
            if ($exclude_id) {
                $person_ids = array_values(array_diff($person_ids, [$exclude_id]));
            }
            if ($person_ids) {
                foreach ($this->db->table($people)->where("unit_id", $this->unit_id)->where("deleted", 0)->whereIn("id", $person_ids)->get()->getResult() as $row) {
                    $candidates[(int) $row->id] = $row;
                }
            }
        }

        $recent = $this->db->table($people)->where("unit_id", $this->unit_id)->where("deleted", 0);
        if ($exclude_id) {
            $recent->where("id !=", $exclude_id);
        }
        foreach ($recent->orderBy("id", "DESC")->limit(100)->get()->getResult() as $row) {
            if (self::similar($normalized_name, (string) $row->normalized_name)) {
                $candidates[(int) $row->id] = $row;
            }
        }

        $result = [];
        foreach ($candidates as $row) {
            $matched = [];
            $confidence = "low";
            $same_name = $normalized_name !== "" && $normalized_name === $row->normalized_name;
            if ($same_name) {
                $matched[] = "name";
                $confidence = "medium";
            } elseif (self::similar($normalized_name, (string) $row->normalized_name)) {
                $matched[] = "similar_name";
            }
            if ($same_name && !empty($data["birth_date"]) && $data["birth_date"] === $row->birth_date) {
                $matched[] = "birth_date";
                $confidence = "high";
            }
            if ($contact_values) {
                $matches = $this->db->table($contacts)->select("contact_type")->where("unit_id", $this->unit_id)->where("person_id", $row->id)->where("deleted", 0)->whereIn("normalized_value", $contact_values)->get()->getResult();
                foreach ($matches as $match) {
                    $matched[] = $match->contact_type;
                    $confidence = "high";
                }
            }
            if ($matched) {
                $result[] = ["record_id" => (int) $row->id, "entity_type" => "person", "confidence" => $confidence, "matched_fields" => array_values(array_unique($matched)), "display_summary" => $row->full_name];
            }
        }
        return $result;
    }

    private static function similar(string $a, string $b): bool
    {
        if ($a === "" || $b === "") {
            return false;
        }
        similar_text($a, $b, $percentage);
        return $percentage >= 85.0 || levenshtein($a, $b) <= 2;
    }
}
