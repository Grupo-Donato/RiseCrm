<?php echo form_open(get_uri("grupo_donato/operacional/save_responsavel"), ["id" => "bombeiros-responsavel-form", "class" => "general-form", "role" => "form"]); ?>
<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="id" value="<?php echo (int) $model_info->id; ?>" />

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-responsavel-nome" class="col-md-3">Nome</label>
                <div class="col-md-9">
                    <?php echo form_input(["id" => "bombeiros-responsavel-nome", "name" => "nome", "value" => $model_info->nome, "class" => "form-control", "data-rule-required" => true, "data-msg-required" => app_lang("field_required")]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-responsavel-nascimento" class="col-md-3">Nascimento</label>
                <div class="col-md-3">
                    <?php echo form_input(["id" => "bombeiros-responsavel-nascimento", "name" => "nascimento", "type" => "date", "value" => $model_info->nascimento, "class" => "form-control"]); ?>
                </div>
                <label for="bombeiros-responsavel-rg" class="col-md-2">RG</label>
                <div class="col-md-4">
                    <?php echo form_input(["id" => "bombeiros-responsavel-rg", "name" => "rg", "value" => $model_info->rg, "class" => "form-control"]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-responsavel-cpf" class="col-md-3">CPF</label>
                <div class="col-md-3">
                    <?php echo form_input(["id" => "bombeiros-responsavel-cpf", "name" => "cpf", "value" => $model_info->cpf, "class" => "form-control"]); ?>
                </div>
                <label for="bombeiros-responsavel-whats" class="col-md-2">WhatsApp</label>
                <div class="col-md-4">
                    <?php echo form_input(["id" => "bombeiros-responsavel-whats", "name" => "whats", "value" => $model_info->whats, "class" => "form-control", "data-rule-required" => true, "data-msg-required" => app_lang("field_required")]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-responsavel-celular" class="col-md-3">Celular</label>
                <div class="col-md-3">
                    <?php echo form_input(["id" => "bombeiros-responsavel-celular", "name" => "celular", "value" => $model_info->celular, "class" => "form-control"]); ?>
                </div>
                <label for="bombeiros-responsavel-recado" class="col-md-2">Recado</label>
                <div class="col-md-4">
                    <?php echo form_input(["id" => "bombeiros-responsavel-recado", "name" => "recado", "value" => $model_info->recado, "class" => "form-control"]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-responsavel-email" class="col-md-3">E-mail</label>
                <div class="col-md-9">
                    <?php echo form_input(["id" => "bombeiros-responsavel-email", "name" => "email", "type" => "email", "value" => $model_info->email, "class" => "form-control"]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-responsavel-endereco" class="col-md-3">Endereço</label>
                <div class="col-md-6">
                    <?php echo form_input(["id" => "bombeiros-responsavel-endereco", "name" => "endereco", "value" => $model_info->endereco, "class" => "form-control"]); ?>
                </div>
                <label for="bombeiros-responsavel-numero" class="col-md-1">Nº</label>
                <div class="col-md-2">
                    <?php echo form_input(["id" => "bombeiros-responsavel-numero", "name" => "numero", "value" => $model_info->numero, "class" => "form-control"]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-responsavel-bairro" class="col-md-3">Bairro</label>
                <div class="col-md-3">
                    <?php echo form_input(["id" => "bombeiros-responsavel-bairro", "name" => "bairro", "value" => $model_info->bairro, "class" => "form-control"]); ?>
                </div>
                <label for="bombeiros-responsavel-cidade" class="col-md-2">Cidade</label>
                <div class="col-md-4">
                    <?php echo form_input(["id" => "bombeiros-responsavel-cidade", "name" => "cidade", "value" => $model_info->cidade, "class" => "form-control"]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-responsavel-cep" class="col-md-3">CEP</label>
                <div class="col-md-3">
                    <?php echo form_input(["id" => "bombeiros-responsavel-cep", "name" => "cep", "value" => $model_info->cep, "class" => "form-control"]); ?>
                </div>
                <label for="bombeiros-responsavel-complemento" class="col-md-2">Complemento</label>
                <div class="col-md-4">
                    <?php echo form_input(["id" => "bombeiros-responsavel-complemento", "name" => "complemento", "value" => $model_info->complemento, "class" => "form-control"]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-responsavel-status" class="col-md-3">Status</label>
                <div class="col-md-9">
                    <?php echo form_dropdown("status", ["Ativo" => "Ativo", "Inativo" => "Inativo"], $model_info->status ?: "Ativo", ["id" => "bombeiros-responsavel-status", "class" => "form-control"]); ?>
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
        $("#bombeiros-responsavel-form").appForm({
            onSuccess: function (result) {
                if ($("#bombeiros-responsaveis-table").length && $.fn.DataTable.isDataTable("#bombeiros-responsaveis-table")) {
                    $("#bombeiros-responsaveis-table").appTable({newData: result.data, dataId: result.id});
                }
                if (window.reloadBombeirosTable) {
                    reloadBombeirosTable("#bombeiros-alunos-table");
                }
            }
        });
    });
</script>
