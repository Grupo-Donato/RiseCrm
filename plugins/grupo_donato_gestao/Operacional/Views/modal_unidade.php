<?php echo form_open(get_uri("grupo_donato/operacional/save_unidade"), ["id" => "bombeiros-unidade-form", "class" => "general-form", "role" => "form"]); ?>
<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="id" value="<?php echo (int) $model_info->id; ?>" />

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-unidade-nome" class="col-md-3">Unidade</label>
                <div class="col-md-9">
                    <?php
                    echo form_input([
                        "id" => "bombeiros-unidade-nome",
                        "name" => "nome_unidade",
                        "value" => $model_info->nome_unidade,
                        "class" => "form-control",
                        "placeholder" => "Nome da unidade",
                        "data-rule-required" => true,
                        "data-msg-required" => app_lang("field_required")
                    ]);
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-unidade-slug" class="col-md-3">Slug</label>
                <div class="col-md-9">
                    <?php
                    echo form_input([
                        "id" => "bombeiros-unidade-slug",
                        "name" => "slug",
                        "value" => $model_info->slug ?? "",
                        "class" => "form-control",
                        "placeholder" => "sao_bernardo_do_campo"
                    ]);
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-unidade-cidade" class="col-md-3">Cidade</label>
                <div class="col-md-9">
                    <?php
                    echo form_input([
                        "id" => "bombeiros-unidade-cidade",
                        "name" => "cidade",
                        "value" => $model_info->cidade,
                        "class" => "form-control",
                        "placeholder" => "Cidade (opcional)"
                    ]);
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-unidade-endereco" class="col-md-3">Endereço</label>
                <div class="col-md-9">
                    <?php
                    echo form_input([
                        "id" => "bombeiros-unidade-endereco",
                        "name" => "endereco",
                        "value" => $model_info->endereco,
                        "class" => "form-control",
                        "placeholder" => "Endereço"
                    ]);
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-unidade-padrao" class="col-md-3">Padrão</label>
                <div class="col-md-9">
                    <?php
                    echo form_checkbox("is_default", "1", !empty($model_info->is_default), "id='bombeiros-unidade-padrao' class='form-check-input'");
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-unidade-status" class="col-md-3">Status</label>
                <div class="col-md-9">
                    <?php echo form_dropdown("status", ["Ativo" => "Ativo", "Inativo" => "Inativo"], $model_info->status ?: "Ativo", ["id" => "bombeiros-unidade-status", "class" => "form-control"]); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><span data-feather="x" class="icon-16"></span> <?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary"><span data-feather="check-circle" class="icon-16"></span> <?php echo app_lang("save"); ?></button>
</div>
<?php echo form_close(); ?>

<script type="text/javascript">
    $(document).ready(function () {
        $("#bombeiros-unidade-form").appForm({
            onSuccess: function (result) {
                if (result.dropdown_option && window.refreshBombeirosUnidadeFilter) {
                    window.refreshBombeirosUnidadeFilter(result.dropdown_option);
                }
                if (window.initBombeirosUnidadesTable) {
                    window.initBombeirosUnidadesTable();
                }
                if ($("#bombeiros-unidades-table").length && $.fn.DataTable.isDataTable("#bombeiros-unidades-table")) {
                    $("#bombeiros-unidades-table").appTable({newData: result.data, dataId: result.id});
                    $("#bombeiros-unidades-table").appTable({reload: true});
                }
                if (window.reloadBombeirosTable) {
                    reloadBombeirosTable("#bombeiros-alunos-table");
                }
            }
        });
    });
</script>
