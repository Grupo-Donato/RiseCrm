<div class="card">
    <div class="card-body">
        <h3 class="pl15 pr15"> <?php echo $model_info->form_title; ?></h3>

        <div class="table-responsive mt20 general-form">
            <table id="estimate-request-table" class="display no-thead b-t no-hover border-bottom-0" cellspacing="0" width="100%">
            </table>
        </div>

        <?php
        if ($model_info->files) {
            $files = unserialize($model_info->files);
            $total_files = count($files);
            if (count($files)) {
                echo "<div class='p15 b-t'>";
                echo view("includes/timeline_preview", array("files" => $files));
                echo "</div>";
            }

            if ($total_files && $show_download_option) {
                $download_caption = app_lang('download');
                if ($total_files > 1) {
                    $download_caption = sprintf(app_lang('download_files'), $total_files);
                }

                echo "<i data-feather='paperclip' class='icon-16 float-start'></i>";

                echo anchor(get_uri("estimate_requests/download_estimate_request_files/" . $model_info->id), $download_caption, array("class" => "float-end", "title" => $download_caption));
            }
        }
        ?>

    </div>
</div>
<script type="text/javascript">
    $(document).ready(function() {
        $("#estimate-request-table").appTable({
            source: '<?php echo_uri("estimate_requests/estimate_request_filed_list_data/" . $model_info->id) ?>',
            order: [
                [1, "asc"]
            ],
            hideTools: true,
            displayLength: 100,
            columns: [{
                    title: '<?php echo app_lang("title") ?>'
                },
                {
                    visible: false
                }
            ],
            onInitComplete: function() {
                $(".dataTables_empty").hide();
            }
        });
    });
</script>