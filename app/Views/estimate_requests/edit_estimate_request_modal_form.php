<?php echo form_open(get_uri("estimate_requests/update_estimate_request"), array("id" => "estimate-request-update-form", "class" => "general-form", "role" => "form")); ?>
<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="id" value="<?php echo $model_info->id; ?>" />

        <div class="form-group">
            <div class="pl15 pr15">
                <label for="assigned_to" class=" col-md-12"><?php echo app_lang('assign_to'); ?></label>
                <div class="col-md-12">
                    <?php
                    echo form_dropdown("assigned_to", $assigned_to_dropdown, $model_info->assigned_to, "class='select2'");
                    ?>
                </div>
            </div>
        </div>

        <div class=" pt10">
            <div class="table-responsive general-form ">
                <table id="estimate-form-table" class="display b-t no-thead no-hover border-bottom-0" cellspacing="0" width="100%">
                </table>
            </div>
        </div>
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><span data-feather="x" class="icon-16"></span> <?php echo app_lang('close'); ?></button>
    <button type="submit" class="btn btn-primary"><span data-feather="check-circle" class="icon-16"></span> <?php echo app_lang('save'); ?></button>
</div>
<?php echo form_close(); ?>

<script type="text/javascript">
    $(document).ready(function() {

        $("#estimate-request-update-form").appForm({
            onSuccess: function(result) {
                appAlert.success(result.message, {
                    duration: 10000
                });
                location.reload();
            }
        });

        $("#estimate-request-update-form .select2").select2();

        appAjaxRequest({
            url: '<?php echo_uri("estimate_requests/estimate_form_filed_list_data/" . $model_info->estimate_form_id . "/" . $model_info->id) ?>',
            type: "POST",
            dataType: "json",
            success: function(response) {
                $("#estimate-form-table").addClass("display no-thead b-t b-b-only no-hover dataTable no-footer").append("<tbody id='estimate-form-table-tbody'></tbody>");

                $.each(response.data, function(key, value) {
                    var row = `<tr><td>${value[0]}</td></tr>`;
                    $("#estimate-form-table-tbody").append(row);
                });

            }
        });

    });
</script>