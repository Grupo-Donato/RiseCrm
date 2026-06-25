<div id="page-content" class="page-wrapper clearfix">
    <div class="row">
        <div class="col-sm-3 col-lg-2">
            <?php echo view("grupo_donato_gestao\\Views\\components\\settings_nav", ["active_tab" => "audit"]); ?>
        </div>
        <div class="col-sm-9 col-lg-10">
            <div class="card">
                <div class="page-title clearfix">
                    <h4><?php echo app_lang("gd_audit"); ?></h4>
                </div>
                <div class="table-responsive">
                    <table id="gd-audit-table" class="display" cellspacing="0" width="100%"></table>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        $("#gd-audit-table").appTable({
            source: '<?php echo_uri("grupo_donato/audit/list_data"); ?>',
            serverSide: true,
            order: [[0, "desc"]],
            columns: [
                {title: '<?php echo app_lang("id"); ?>', "class": "w80", order_by: "id"},
                {title: '<?php echo app_lang("gd_audit_when"); ?>', order_by: "created_at"},
                {title: '<?php echo app_lang("gd_audit_actor"); ?>'},
                {title: '<?php echo app_lang("gd_audit_action"); ?>', order_by: "action"},
                {title: '<?php echo app_lang("gd_audit_entity"); ?>', order_by: "entity_type"},
                {title: '<?php echo app_lang("gd_audit_request"); ?>'},
                {title: '<i data-feather="menu" class="icon-16"></i>', "class": "text-center option w80"}
            ]
        });
    });
</script>
