<div class="table-responsive">
    <table id="estimate-request-table" class="display xs-hide-dtr-control no-title" cellspacing="0" width="100%">
    </table>
</div>
<script type="text/javascript">
    $(document).ready(function () {

        var fieldVisibility = false;
        if ("<?php echo $login_user->user_type; ?>" === "staff") {
            fieldVisibility = true;
        }

        var mobileView = 0;
        if (isMobile()) {
            mobileView = 1;
        }

        $("#estimate-request-table").appTable({
            source: '<?php echo_uri("estimate_requests/estimate_requests_list_data_of_client/" . $client_id . "/") ?>' + mobileView,
            order: [[0, 'desc']],
            columns: [
                {visible: false, searchable: false},
                {visible: false, searchable: false},
                {title: "<?php echo app_lang('id'); ?>", "class": "all", "iDataSort": 1},
                {visible: false, searchable: false},
                {title: "<?php echo app_lang('title'); ?>"},
                {title: "<?php echo app_lang('assigned_to'); ?>", visible: fieldVisibility},
                {visible: false, searchable: false},
                {title: '<?php echo app_lang("created_date") ?>', "iDataSort": 6},
                {title: "<?php echo app_lang('status'); ?>"},
                {title: "<i data-feather='menu' class='icon-16'></i>", "class": "text-center w100", visible: fieldVisibility}
            ],
            rowCallback: function(nRow, aData) {
                if (mobileView) {
                    $("td:eq(0)", nRow).attr("style", "border-left-color:" + aData[0] + " !important;").addClass('list-status-border');
                }
            },
            printColumns: [2, 4, 5, 7, 8]
        });
    });
</script>