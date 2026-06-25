<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use grupo_donato_gestao\Config\Constants;

class BookingSeriesService extends CustomerDataService
{
    private $series;
    private $series_resources;
    private $series_events;
    private $series_exceptions;
    private TemporalService $time;

    public function __construct(int $unit_id, int $actor_id = 0, ?object $login_user = null)
    {
        parent::__construct($unit_id, $actor_id, $login_user);
        $this->series = model("grupo_donato_gestao\\Models\\Gd_booking_series_model");
        $this->series_resources = model("grupo_donato_gestao\\Models\\Gd_booking_series_resources_model");
        $this->series_events = model("grupo_donato_gestao\\Models\\Gd_booking_series_events_model");
        $this->series_exceptions = model("grupo_donato_gestao\\Models\\Gd_booking_series_exceptions_model");
        $this->time = new TemporalService($unit_id);
    }

    public function get(int $id): ?object
    {
        $row = $this->series->get_scoped($id, $this->unit_id);
        if (!$row) { return null; }
        $row->resources = $this->series_resources->for_series($id, $this->unit_id);
        $row->events = $this->series_events->for_series($id, $this->unit_id);
        $row->exceptions = $this->series_exceptions->for_series($id, $this->unit_id);
        $row->occurrences = $this->db->table($this->db->prefixTable("gd_bookings"))->select("id,booking_number,title,status,starts_at_utc,ends_at_utc,series_occurrence_key,series_local_date,is_series_exception,detached_from_series,lock_version")->where("unit_id", $this->unit_id)->where("series_id", $id)->where("deleted", 0)->orderBy("series_local_date", "ASC")->limit(1000)->get()->getResult();
        $row->customer_name = null;
        if ($row->customer_account_id) {
            $account = $this->db->table($this->db->prefixTable("gd_customer_accounts"))->select("display_name")->where("id", $row->customer_account_id)->where("unit_id", $this->unit_id)->where("deleted", 0)->get(1)->getRow();
            $row->customer_name = $account->display_name ?? null;
        }
        return $row;
    }

    public function listPage(array $options): array
    {
        $table = $this->db->prefixTable("gd_booking_series");
        $accounts = $this->db->prefixTable("gd_customer_accounts");
        $series_resources = $this->db->prefixTable("gd_booking_series_resources");
        $resources = $this->db->prefixTable("gd_resources");
        $base = function () use ($options, $table, $accounts, $series_resources, $resources) {
            $q = $this->db->table($table)->join($accounts, "$accounts.id=$table.customer_account_id AND $accounts.unit_id=$table.unit_id AND $accounts.deleted=0", "left", false)->join($series_resources, "$series_resources.series_id=$table.id AND $series_resources.unit_id=$table.unit_id AND $series_resources.deleted=0", "left", false)->join($resources, "$resources.id=$series_resources.resource_id AND $resources.unit_id=$table.unit_id AND $resources.deleted=0", "left", false)->where("$table.unit_id", $this->unit_id)->where("$table.deleted", 0);
            if ($value = trim((string) ($options["status"] ?? ""))) { $q->where("$table.status", $value); }
            if ($value = (int) ($options["resource_id"] ?? 0)) { $q->where("$series_resources.resource_id", $value); }
            if ($value = (int) ($options["customer_account_id"] ?? 0)) { $q->where("$table.customer_account_id", $value); }
            if ($value = trim((string) ($options["date_from"] ?? ""))) { $q->where("COALESCE($table.ends_on,'9999-12-31') >=", $value); }
            if ($value = trim((string) ($options["date_to"] ?? ""))) { $q->where("$table.starts_on <=", $value); }
            if ($value = trim((string) ($options["search_by"] ?? ""))) { $q->groupStart()->like("$table.series_number", $value)->orLike("$table.title", $value)->orLike("$accounts.display_name", $value)->orLike("$resources.name", $value)->groupEnd(); }
            return $q;
        };
        $total = $this->db->table($table)->where("unit_id", $this->unit_id)->where("deleted", 0)->countAllResults();
        $filtered = $base()->select("COUNT(DISTINCT $table.id) total", false)->get()->getRow();
        $q = $base()->select("$table.*, $accounts.display_name AS customer_name, GROUP_CONCAT(DISTINCT CONCAT($resources.code,' — ',$resources.name) ORDER BY $resources.code SEPARATOR ', ') AS resource_names", false)->groupBy("$table.id");
        $map = ["series_number" => "$table.series_number", "title" => "$table.title", "frequency" => "$table.frequency", "starts_on" => "$table.starts_on", "status" => "$table.status", "updated_at" => "$table.updated_at"];
        $order = $map[(string) ($options["order_by"] ?? "")] ?? "$table.starts_on";
        $dir = ($options["order_dir"] ?? "") === "ASC" ? "ASC" : "DESC";
        $q->orderBy($order, $dir)->limit(max(1, min(100, (int) ($options["limit"] ?? 25))), max(0, (int) ($options["skip"] ?? 0)));
        return ["data" => $q->get()->getResult(), "recordsTotal" => $total, "recordsFiltered" => (int) ($filtered->total ?? 0)];
    }

    public function preview(array $input): array
    {
        $prepared = $this->normalize($input);
        return (new BookingSeriesOccurrenceService($this->unit_id, $this->actor_id))->preview($prepared["series"], $prepared["resources"]);
    }

    public function create(array $input, bool $generate = true): array
    {
        $prepared = $this->normalize($input);
        $lock = new BookingSeriesLockService();
        $in_tx = false;
        $id = 0;
        try {
            $lock->acquire($this->unit_id, "new:" . substr(hash("sha256", json_encode($prepared, JSON_UNESCAPED_SLASHES)), 0, 32));
            if ($this->db->transBegin() === false) { throw new \RuntimeException("series create transaction"); }
            $in_tx = true;
            $sequence = new SequenceService();
            $sequence->ensure($this->unit_id, "booking_series", "SER-" . gmdate("Y") . "-", 6, true);
            $number = $sequence->next($this->unit_id, "booking_series");
            $data = $this->stamp($prepared["series"] + ["unit_id" => $this->unit_id, "series_number" => $number, "status" => "active", "lock_version" => 1, "deleted" => 0], true);
            $id = (int) $this->series->ci_save($data);
            if ($id <= 0) { throw new \RuntimeException("series insert"); }
            $this->syncResources($id, $prepared["resources"]);
            (new BookingSeriesEventService($this->unit_id, $this->actor_id))->append($id, "created", null, "active", null, ["series_number" => $number]);
            $this->audit_change("booking_series_created", "booking_series", $id, null, $data, ["resources" => $prepared["resources"]]);
            if ($this->db->transCommit() === false) { throw new \RuntimeException("series create commit"); }
            $in_tx = false;
        } catch (\Throwable $e) {
            if ($in_tx) { $this->db->transRollback(); }
            throw $e;
        } finally {
            $lock->release();
        }
        $result = ["id" => $id, "series_number" => $number, "lock_version" => 1];
        if ($generate) { $result["generation"] = (new BookingSeriesOccurrenceService($this->unit_id, $this->actor_id))->generate($id); }
        return $result;
    }

    public function updateEntire(int $id, array $input): array
    {
        $prepared = $this->normalize($input);
        $lock = new BookingSeriesLockService();
        $in_tx = false;
        try {
            $lock->acquire($this->unit_id, (string) $id);
            $before = $this->series->get_scoped($id, $this->unit_id);
            if (!$before) { throw new \DomainException("gd_booking_series_not_found"); }
            if (!in_array((string) $before->status, ["active", "paused"], true)) { throw new \DomainException("gd_booking_series_not_editable"); }
            $expected = (int) ($input["lock_version"] ?? 0);
            if ($expected !== (int) $before->lock_version) { throw new \DomainException("gd_booking_series_edit_conflict"); }
            if ($this->db->transBegin() === false) { throw new \RuntimeException("series update transaction"); }
            $in_tx = true;
            $data = $this->stamp($prepared["series"], false);
            if (!$this->series->optimistic_update($id, $this->unit_id, $expected, $data)) { throw new \DomainException("gd_booking_series_edit_conflict"); }
            $this->syncResources($id, $prepared["resources"]);
            (new BookingSeriesEventService($this->unit_id, $this->actor_id))->append($id, "updated", (string) $before->status, (string) $before->status, null, ["scope" => "entire_series"]);
            $this->audit_change("booking_series_updated", "booking_series", $id, (array) $before, $data, ["scope" => "entire_series", "resources" => $prepared["resources"]]);
            if ($this->db->transCommit() === false) { throw new \RuntimeException("series update commit"); }
            $in_tx = false;
        } catch (\Throwable $e) {
            if ($in_tx) { $this->db->transRollback(); }
            throw $e;
        } finally {
            $lock->release();
        }

        $today = (new \DateTimeImmutable("today", new \DateTimeZone($this->time->timezoneName())))->format("Y-m-d");
        $occurrences = new BookingSeriesOccurrenceService($this->unit_id, $this->actor_id);
        $replaced = $occurrences->cancelFuture($id, $today, "Definição da série atualizada", true);
        $fresh = $this->series->get_scoped($id, $this->unit_id);
        $generation = (string) $fresh->status === "active" ? $occurrences->generate($id) : null;
        return ["id" => $id, "lock_version" => (int) $fresh->lock_version, "replaced_booking_ids" => $replaced, "generation" => $generation];
    }

    public function normalize(array $input): array
    {
        $type = trim((string) ($input["booking_type"] ?? ""));
        if (!in_array($type, Constants::BOOKING_TYPES, true)) { throw new \DomainException("gd_invalid_booking_type"); }
        $title = DataNormalizationService::text(strip_tags((string) ($input["title"] ?? "")));
        if ($title === "" || mb_strlen($title) > 180) { throw new \DomainException("gd_booking_title_required"); }
        $frequency = trim((string) ($input["frequency"] ?? ""));
        if (!in_array($frequency, Constants::BOOKING_SERIES_FREQUENCIES, true)) { throw new \DomainException("gd_invalid_recurrence"); }
        $interval = $this->integer($input["interval_value"] ?? 1, 1, 365, "gd_invalid_recurrence");
        $weekdays = $this->weekdays($input["weekdays"] ?? []);
        if ($frequency === "weekly" && !$weekdays) { throw new \DomainException("gd_booking_series_weekdays_required"); }
        $monthly_day = $frequency === "monthly" ? $this->integer($input["monthly_day"] ?? 0, 1, 31, "gd_booking_series_monthly_day_required") : null;
        $start_time = TemporalService::normalizeTime((string) ($input["local_start_time"] ?? ""));
        $end_time = TemporalService::normalizeTime((string) ($input["local_end_time"] ?? ""));
        $starts_on = $this->valid_date($input["starts_on"] ?? null);
        $mode = trim((string) ($input["ends_mode"] ?? ""));
        if (!in_array($mode, Constants::BOOKING_SERIES_ENDS_MODES, true)) { throw new \DomainException("gd_invalid_booking_series_end"); }
        $ends_on = $mode === "until_date" ? $this->valid_date($input["ends_on"] ?? null) : null;
        if ($ends_on !== null && $ends_on < $starts_on) { throw new \DomainException("gd_invalid_booking_series_end"); }
        $max = $mode === "count" ? $this->integer($input["max_occurrences"] ?? 0, 1, Constants::BOOKING_SERIES_MAX_OCCURRENCES_PER_OPERATION, "gd_booking_series_occurrence_limit") : null;
        $default_status = trim((string) ($input["default_booking_status"] ?? ""));
        if (!in_array($default_status, Constants::BOOKING_SERIES_DEFAULT_STATUSES, true)) { throw new \DomainException("gd_invalid_booking_status"); }
        $policy = trim((string) ($input["conflict_policy"] ?? ""));
        if (!in_array($policy, Constants::BOOKING_SERIES_CONFLICT_POLICIES, true)) { throw new \DomainException("gd_invalid_booking_series_conflict_policy"); }
        $horizon = $this->integer($input["generation_horizon_days"] ?? Constants::BOOKING_SERIES_DEFAULT_HORIZON_DAYS, 1, Constants::BOOKING_SERIES_MAX_HORIZON_DAYS, "gd_invalid_booking_series_horizon");
        $customer = (int) ($input["customer_account_id"] ?? 0);
        $contact = (int) ($input["contact_person_id"] ?? 0);
        $this->assertCustomerAndContact($type, $customer, $contact);
        $notes = trim(strip_tags((string) ($input["notes"] ?? "")));
        if (mb_strlen($notes) > 5000) { throw new \DomainException("gd_booking_notes_too_large"); }
        $metadata = $this->metadata($input["metadata"] ?? null);
        $resources = $this->normalizeResources($input["resources"] ?? []);
        $series = [
            "customer_account_id" => $customer ?: null, "contact_person_id" => $contact ?: null,
            "booking_type" => $type, "title" => $title, "frequency" => $frequency, "interval_value" => $interval,
            "weekdays" => $frequency === "weekly" ? json_encode($weekdays) : null, "monthly_day" => $monthly_day,
            "local_start_time" => $start_time, "local_end_time" => $end_time, "timezone" => $this->time->timezoneName(),
            "starts_on" => $starts_on, "ends_mode" => $mode, "ends_on" => $ends_on, "max_occurrences" => $max,
            "default_booking_status" => $default_status, "conflict_policy" => $policy, "generation_horizon_days" => $horizon,
            "notes" => $notes ?: null, "metadata" => $metadata,
        ];
        (new RecurrenceGeneratorService($this->unit_id))->candidates($series);
        return ["series" => $series, "resources" => $resources];
    }

    public function inputFrom(object $series): array
    {
        $resources = $this->series_resources->for_series((int) $series->id, $this->unit_id);
        return [
            "booking_type" => $series->booking_type, "title" => $series->title, "customer_account_id" => $series->customer_account_id, "contact_person_id" => $series->contact_person_id,
            "frequency" => $series->frequency, "interval_value" => $series->interval_value, "weekdays" => json_decode((string) $series->weekdays, true) ?: [], "monthly_day" => $series->monthly_day,
            "local_start_time" => $series->local_start_time, "local_end_time" => $series->local_end_time, "starts_on" => $series->starts_on,
            "ends_mode" => $series->ends_mode, "ends_on" => $series->ends_on, "max_occurrences" => $series->max_occurrences,
            "default_booking_status" => $series->default_booking_status, "conflict_policy" => $series->conflict_policy, "generation_horizon_days" => $series->generation_horizon_days,
            "notes" => $series->notes, "metadata" => $series->metadata, "lock_version" => $series->lock_version,
            "resources" => array_map(static fn($r): array => ["resource_id" => (int) $r->resource_id, "buffer_before_minutes" => (int) $r->buffer_before_minutes, "buffer_after_minutes" => (int) $r->buffer_after_minutes], $resources),
        ];
    }

    private function syncResources(int $series_id, array $resources): void
    {
        $table = $this->db->prefixTable("gd_booking_series_resources");
        $all = $this->db->table($table)->where("series_id", $series_id)->where("unit_id", $this->unit_id)->get()->getResult();
        $by_resource = []; foreach ($all as $row) { $by_resource[(int) $row->resource_id] = $row; }
        $wanted = [];
        foreach ($resources as $resource) {
            $rid = (int) $resource["resource_id"]; $wanted[$rid] = true;
            $data = $this->stamp(["unit_id" => $this->unit_id, "series_id" => $series_id, "resource_id" => $rid, "buffer_before_minutes" => $resource["buffer_before_minutes"], "buffer_after_minutes" => $resource["buffer_after_minutes"], "deleted" => 0], !isset($by_resource[$rid]));
            if (isset($by_resource[$rid])) { $this->series_resources->ci_save($data, (int) $by_resource[$rid]->id); }
            else { $this->series_resources->ci_save($data); }
        }
        foreach ($by_resource as $rid => $row) {
            if (!isset($wanted[$rid]) && !(int) $row->deleted) { $this->db->table($table)->where("id", (int) $row->id)->where("unit_id", $this->unit_id)->update(["deleted" => 1, "updated_at" => gmdate("Y-m-d H:i:s"), "updated_by" => $this->actor_id ?: null]); }
        }
    }

    private function normalizeResources($raw): array
    {
        if (!is_array($raw) || !$raw) { throw new \DomainException("gd_invalid_booking_resources"); }
        $out = []; $seen = [];
        foreach ($raw as $entry) {
            if (!is_array($entry)) { continue; }
            $rid = (int) ($entry["resource_id"] ?? 0);
            if ($rid <= 0 || isset($seen[$rid])) { throw new \DomainException($rid > 0 ? "gd_duplicate_booking_resource" : "gd_invalid_booking_resources"); }
            $seen[$rid] = true;
            $exists = $this->db->table($this->db->prefixTable("gd_resources"))->where("id", $rid)->where("unit_id", $this->unit_id)->where("deleted", 0)->where("is_active", 1)->where("is_bookable", 1)->countAllResults();
            if ($exists !== 1) { throw new \DomainException("gd_invalid_booking_resource"); }
            $out[] = ["resource_id" => $rid, "buffer_before_minutes" => $this->integer($entry["buffer_before_minutes"] ?? 0, 0, Constants::BOOKING_MAX_BUFFER_MINUTES, "gd_invalid_booking_buffer"), "buffer_after_minutes" => $this->integer($entry["buffer_after_minutes"] ?? 0, 0, Constants::BOOKING_MAX_BUFFER_MINUTES, "gd_invalid_booking_buffer")];
        }
        if (!$out) { throw new \DomainException("gd_invalid_booking_resources"); }
        usort($out, static fn(array $a, array $b): int => $a["resource_id"] <=> $b["resource_id"]);
        return $out;
    }

    private function assertCustomerAndContact(string $type, int $customer, int $contact): void
    {
        if (in_array($type, Constants::BOOKING_COMMERCIAL_TYPES, true) && $customer <= 0) { throw new \DomainException("gd_booking_customer_required"); }
        if ($contact > 0 && $customer <= 0) { throw new \DomainException("gd_booking_contact_requires_customer"); }
        if ($customer > 0 && $this->db->table($this->db->prefixTable("gd_customer_accounts"))->where("id", $customer)->where("unit_id", $this->unit_id)->where("deleted", 0)->where("status", "active")->countAllResults() !== 1) { throw new \DomainException("gd_invalid_booking_customer"); }
        if ($contact > 0) {
            $person = $this->db->table($this->db->prefixTable("gd_people"))->where("id", $contact)->where("unit_id", $this->unit_id)->where("deleted", 0)->countAllResults();
            $link = $this->db->table($this->db->prefixTable("gd_account_people"))->where("unit_id", $this->unit_id)->where("account_id", $customer)->where("person_id", $contact)->where("status", "active")->where("deleted", 0)->countAllResults();
            if ($person !== 1 || $link < 1) { throw new \DomainException("gd_invalid_booking_contact"); }
        }
    }

    private function weekdays($raw): array
    {
        if (is_string($raw)) { $raw = array_filter(explode(",", $raw)); }
        if (!is_array($raw)) { return []; }
        $days = array_values(array_unique(array_map("intval", $raw)));
        foreach ($days as $day) { if ($day < 1 || $day > 7) { throw new \DomainException("gd_booking_series_weekdays_required"); } }
        sort($days, SORT_NUMERIC); return $days;
    }

    private function integer($value, int $min, int $max, string $error): int
    {
        if (!preg_match('/^\d+$/', (string) $value)) { throw new \DomainException($error); }
        $value = (int) $value; if ($value < $min || $value > $max) { throw new \DomainException($error); } return $value;
    }

    private function metadata($value): ?string
    {
        $json = DataNormalizationService::json($value, 16000);
        if ($json === null) { return null; }
        $data = json_decode($json, true);
        $walk = function ($value, string $key = "") use (&$walk): void {
            foreach (["password", "token", "secret", "authorization", "cookie", "price", "amount", "payment", "charge", "billing", "finance"] as $bad) { if (str_contains(mb_strtolower($key), $bad)) { throw new \DomainException("gd_booking_metadata_forbidden"); } }
            if (is_array($value)) { foreach ($value as $child_key => $child) { $walk($child, (string) $child_key); } }
            elseif (is_string($value) && preg_match('/[<>]/', $value)) { throw new \DomainException("gd_booking_metadata_forbidden"); }
        };
        $walk($data); return $json;
    }
}
