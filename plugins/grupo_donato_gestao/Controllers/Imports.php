<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Controllers;

use grupo_donato_gestao\Config\Constants;
use grupo_donato_gestao\Services\ImportBatchService;

class Imports extends Gd_Controller
{
    private int $unit_id;
    private ImportBatchService $service;

    /** Permissões de gestão exigidas por tipo (além de gd_imports_manage). */
    private const TYPE_PERMS = [
        "school_payments" => ["gd_students_manage", "gd_enrollments_manage", "gd_receivables_manage", "gd_payments_manage"],
        "cash" => ["gd_expenses_manage"],
        "court_renters" => ["gd_court_rentals_manage", "gd_customer_accounts_manage"],
    ];

    public function __construct()
    {
        parent::__construct();
        $this->access->require("gd_imports_view");
        $this->unit_id = (int) $this->active_unit_id();
        if (!$this->unit_id) { throw new \RuntimeException("No active unit."); }
        $this->service = new ImportBatchService($this->unit_id, $this->user_id(), $this->login_user);
    }

    public function index()
    {
        return $this->gd_render("imports/index", [
            "can_manage" => $this->access->can("gd_imports_manage"),
            "types" => Constants::IMPORT_TYPES, "statuses" => Constants::IMPORT_BATCH_STATUSES,
        ]);
    }

    public function list_data()
    {
        try {
            $result = $this->service->listPage(append_server_side_filtering_commmon_params([
                "import_type" => $this->request->getPost("import_type"), "status" => $this->request->getPost("status"),
            ]));
            $rows = []; foreach ($result["data"] as $row) { $rows[] = $this->row($row); } $result["data"] = $rows;
            return $this->response->setJSON($result);
        } catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function new_batch()
    {
        $this->access->require("gd_imports_manage");
        return $this->gd_render("imports/new", ["types" => Constants::IMPORT_TYPES]);
    }

    public function upload()
    {
        try {
            $type = (string) $this->request->getPost("import_type");
            $this->requireType($type);
            $file = $this->request->getFile("import_file");
            if (!$file || !$file->isValid()) { throw new \DomainException("gd_import_file_required"); }
            $ext = strtolower((string) $file->getExtension());
            if (!in_array($ext, ["xlsx", "xls", "csv"], true)) { throw new \DomainException("gd_import_invalid_file_type"); }
            $result = $this->service->createBatch([
                "import_type" => $type, "file_path" => $file->getTempName(),
                "original_filename" => $file->getClientName(), "override" => $this->request->getPost("override") ? true : false,
            ]);
            $this->json_success(app_lang("record_saved"), $result);
        } catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function preview()
    {
        try { $this->access->require("gd_imports_manage"); $this->json_success("", ["data" => $this->service->preview((int) $this->request->getPost("id"))]); }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function mapping()
    {
        try {
            $batch = $this->batchForWrite();
            $map = $this->request->getPost("mapping");
            $this->json_success(app_lang("record_saved"), $this->service->saveMapping((int) $batch->id, is_array($map) ? $map : []));
        } catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function validate()
    {
        try { $batch = $this->batchForWrite(); $this->json_success(app_lang("record_saved"), $this->service->validate((int) $batch->id)); }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function confirm()
    {
        try { $batch = $this->batchForWrite(); $this->json_success(app_lang("record_saved"), $this->service->confirm((int) $batch->id)); }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function reprocess()
    {
        try { $batch = $this->batchForWrite(); $this->json_success(app_lang("record_saved"), $this->service->reprocess((int) $batch->id)); }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function view($id)
    {
        $batch = $this->service->get((int) $id);
        if (!$batch) { return show_404(); }
        return $this->gd_render("imports/view", [
            "batch" => $batch, "can_manage" => $this->access->can("gd_imports_manage"),
        ]);
    }

    public function issues()
    {
        try {
            $batch = $this->service->get((int) $this->request->getPost("id"));
            if (!$batch) { return show_404(); }
            $this->json_success("", ["data" => $batch->issues]);
        } catch (\Throwable $e) { $this->gd_fail($e); }
    }

    /* ---------------- helpers ---------------- */

    private function requireType(string $type): void
    {
        $this->access->require("gd_imports_manage");
        foreach (self::TYPE_PERMS[$type] ?? [] as $perm) { $this->access->require($perm); }
    }

    /** Resolve o lote, valida unidade (IDOR) e exige as permissões do tipo. */
    private function batchForWrite(): object
    {
        $batch = $this->service->get((int) $this->request->getPost("id"));
        if (!$batch) { throw new \DomainException("gd_import_not_found"); }
        $this->requireType((string) $batch->import_type);
        return $batch;
    }

    private function row(object $row): array
    {
        $actions = anchor(get_uri("grupo_donato/imports/view/" . $row->id), "<i data-feather='eye' class='icon-16'></i>", ["title" => app_lang("gd_view_details")]);
        return [
            $this->escape($row->batch_number), app_lang("gd_import_type_" . $row->import_type), $this->escape($row->original_filename),
            (int) $row->row_count, (int) $row->imported_count, (int) $row->issue_count,
            app_lang("gd_import_status_" . $row->status), $row->created_at ? format_to_datetime($row->created_at) : "", $actions,
        ];
    }
}
