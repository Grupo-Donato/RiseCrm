<?php
$turmas = [
    "" => "-",
    "08:30-11:00" => "08:30-11:00",
    "13:30-16:00" => "13:30-16:00"
];
$status_options = ["Ativo" => "Ativo", "Pendente" => "Pendente", "Inadimplente" => "Inadimplente", "Concluido" => "Concluído", "Inativo" => "Inativo", "Cancelado" => "Cancelado"];
$melhor_horario_options = ["" => "-", "manha" => "Manhã", "tarde" => "Tarde", "qualquer" => "Qualquer horário"];
?>

<?php echo form_open(get_uri("grupo_donato/operacional/save_aluno"), ["id" => "bombeiros-aluno-form", "class" => "general-form", "role" => "form", "enctype" => "multipart/form-data"]); ?>
<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="id" value="<?php echo (int) $model_info->id; ?>" />
        <input type="hidden" name="responsavel_id" value="<?php echo (int) $model_info->responsavel_id; ?>" />
        <input type="hidden" name="origem_matricula" value="<?php echo esc($model_info->origem_matricula ?: "manual"); ?>" />

        <h5 class="mb15">Responsável</h5>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-aluno-responsavel-nome" class="col-md-3">Nome</label>
                <div class="col-md-9">
                    <?php echo form_input(["id" => "bombeiros-aluno-responsavel-nome", "name" => "responsavel_nome", "value" => $model_info->responsavel_nome, "class" => "form-control", "data-rule-required" => true, "data-msg-required" => app_lang("field_required")]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-aluno-responsavel-nascimento" class="col-md-3">Nascimento</label>
                <div class="col-md-3">
                    <?php echo form_input(["id" => "bombeiros-aluno-responsavel-nascimento", "name" => "responsavel_nascimento", "type" => "date", "value" => $model_info->responsavel_nascimento, "class" => "form-control", "min" => "1900-01-01", "max" => date("Y-m-d")]); ?>
                </div>
                <label for="bombeiros-aluno-responsavel-rg" class="col-md-2">RG</label>
                <div class="col-md-4">
                    <?php echo form_input(["id" => "bombeiros-aluno-responsavel-rg", "name" => "responsavel_rg", "value" => $model_info->responsavel_rg, "class" => "form-control"]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-aluno-responsavel-cpf" class="col-md-3">CPF</label>
                <div class="col-md-3">
                    <?php echo form_input(["id" => "bombeiros-aluno-responsavel-cpf", "name" => "responsavel_cpf", "value" => $model_info->responsavel_cpf, "class" => "form-control"]); ?>
                </div>
                <label for="bombeiros-aluno-responsavel-whats" class="col-md-2">WhatsApp</label>
                <div class="col-md-4">
                    <?php echo form_input(["id" => "bombeiros-aluno-responsavel-whats", "name" => "responsavel_whats", "value" => $model_info->responsavel_whats, "class" => "form-control", "data-rule-required" => true, "data-msg-required" => app_lang("field_required")]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-aluno-responsavel-celular" class="col-md-3">Celular</label>
                <div class="col-md-3">
                    <?php echo form_input(["id" => "bombeiros-aluno-responsavel-celular", "name" => "responsavel_celular", "value" => $model_info->responsavel_celular, "class" => "form-control"]); ?>
                </div>
                <label for="bombeiros-aluno-responsavel-recado" class="col-md-2">Recado</label>
                <div class="col-md-4">
                    <?php echo form_input(["id" => "bombeiros-aluno-responsavel-recado", "name" => "responsavel_recado", "value" => $model_info->responsavel_recado, "class" => "form-control"]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-aluno-responsavel-email" class="col-md-3">E-mail</label>
                <div class="col-md-9">
                    <?php echo form_input(["id" => "bombeiros-aluno-responsavel-email", "name" => "responsavel_email", "type" => "email", "value" => $model_info->responsavel_email, "class" => "form-control"]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-aluno-responsavel-endereco" class="col-md-3">Endereço</label>
                <div class="col-md-6">
                    <?php echo form_input(["id" => "bombeiros-aluno-responsavel-endereco", "name" => "responsavel_endereco", "value" => $model_info->responsavel_endereco, "class" => "form-control"]); ?>
                </div>
                <label for="bombeiros-aluno-responsavel-numero" class="col-md-1">Nº</label>
                <div class="col-md-2">
                    <?php echo form_input(["id" => "bombeiros-aluno-responsavel-numero", "name" => "responsavel_numero", "value" => $model_info->responsavel_numero, "class" => "form-control"]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-aluno-responsavel-bairro" class="col-md-3">Bairro</label>
                <div class="col-md-3">
                    <?php echo form_input(["id" => "bombeiros-aluno-responsavel-bairro", "name" => "responsavel_bairro", "value" => $model_info->responsavel_bairro, "class" => "form-control"]); ?>
                </div>
                <label for="bombeiros-aluno-responsavel-cidade" class="col-md-2">Cidade</label>
                <div class="col-md-4">
                    <?php echo form_input(["id" => "bombeiros-aluno-responsavel-cidade", "name" => "responsavel_cidade", "value" => $model_info->responsavel_cidade, "class" => "form-control"]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-aluno-responsavel-cep" class="col-md-3">CEP</label>
                <div class="col-md-3">
                    <?php echo form_input(["id" => "bombeiros-aluno-responsavel-cep", "name" => "responsavel_cep", "value" => $model_info->responsavel_cep, "class" => "form-control"]); ?>
                </div>
                <label for="bombeiros-aluno-responsavel-complemento" class="col-md-2">Complemento</label>
                <div class="col-md-4">
                    <?php echo form_input(["id" => "bombeiros-aluno-responsavel-complemento", "name" => "responsavel_complemento", "value" => $model_info->responsavel_complemento, "class" => "form-control"]); ?>
                </div>
            </div>
        </div>

        <h5 class="mb15 mt20">Aluno</h5>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-aluno-matricula" class="col-md-3">Matrícula</label>
                <div class="col-md-3">
                    <?php echo form_input(["id" => "bombeiros-aluno-matricula", "name" => "matricula", "value" => $model_info->matricula ?? "", "class" => "form-control", "placeholder" => "Gerada automaticamente", "readonly" => "readonly"]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-aluno-nome" class="col-md-3">Nome</label>
                <div class="col-md-9">
                    <?php echo form_input(["id" => "bombeiros-aluno-nome", "name" => "nome_aluno", "value" => $model_info->nome_aluno, "class" => "form-control", "data-rule-required" => true, "data-msg-required" => app_lang("field_required")]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-aluno-nascimento" class="col-md-3">Nascimento</label>
                <div class="col-md-3">
                    <?php echo form_input(["id" => "bombeiros-aluno-nascimento", "name" => "nascimento_aluno", "type" => "date", "value" => $model_info->nascimento_aluno, "class" => "form-control", "min" => "1900-01-01", "max" => date("Y-m-d"), "data-rule-required" => true, "data-msg-required" => app_lang("field_required")]); ?>
                </div>
                <label for="bombeiros-aluno-rg" class="col-md-2">RG</label>
                <div class="col-md-4">
                    <?php echo form_input(["id" => "bombeiros-aluno-rg", "name" => "rg_aluno", "value" => $model_info->rg_aluno, "class" => "form-control"]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-aluno-cpf" class="col-md-3">CPF</label>
                <div class="col-md-3">
                    <?php echo form_input(["id" => "bombeiros-aluno-cpf", "name" => "cpf_aluno", "value" => $model_info->cpf_aluno, "class" => "form-control"]); ?>
                </div>
                <label for="bombeiros-aluno-unidade" class="col-md-2">Unidade</label>
                <div class="col-md-4">
                    <?php echo form_dropdown("unidade_id", $unidades_dropdown, $model_info->unidade_id, ["id" => "bombeiros-aluno-unidade", "class" => "form-control", "data-rule-required" => true, "data-msg-required" => app_lang("field_required")]); ?>
                </div>
            </div>
        </div>

        <h5 class="mb15 mt20">Curso e pagamento</h5>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-aluno-curso" class="col-md-3">Curso contratado</label>
                <div class="col-md-9">
                    <?php echo form_input(["id" => "bombeiros-aluno-curso", "name" => "curso_nome", "value" => $model_info->curso_nome, "class" => "form-control"]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-aluno-parcelas" class="col-md-3">Nº parcelas</label>
                <div class="col-md-3">
                    <?php echo form_input(["id" => "bombeiros-aluno-parcelas", "name" => "num_parcelas", "type" => "number", "min" => "1", "value" => $model_info->num_parcelas, "class" => "form-control"]); ?>
                </div>
                <label for="bombeiros-aluno-valor" class="col-md-2">Valor da parcela</label>
                <div class="col-md-4">
                    <?php echo form_input(["id" => "bombeiros-aluno-valor", "name" => "valor_mensalidade", "value" => $model_info->valor_mensalidade, "class" => "form-control"]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-aluno-inscricao-valor" class="col-md-3">Valor da inscrição</label>
                <div class="col-md-3">
                    <?php echo form_input(["id" => "bombeiros-aluno-inscricao-valor", "name" => "valor_inscricao", "value" => $model_info->valor_inscricao, "class" => "form-control"]); ?>
                </div>
                <label for="bombeiros-aluno-inscricao-data" class="col-md-2">Data da inscrição</label>
                <div class="col-md-4">
                    <?php echo form_input(["id" => "bombeiros-aluno-inscricao-data", "name" => "data_inscricao", "type" => "date", "value" => $model_info->data_inscricao, "class" => "form-control"]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-aluno-valor-mensal" class="col-md-3">Valor mensal</label>
                <div class="col-md-3">
                    <?php echo form_input(["id" => "bombeiros-aluno-valor-mensal", "name" => "valor_mensal", "value" => $model_info->valor_mensal, "class" => "form-control"]); ?>
                </div>
                <label for="bombeiros-aluno-primeira-parcela" class="col-md-2">1ª parcela</label>
                <div class="col-md-4">
                    <?php echo form_input(["id" => "bombeiros-aluno-primeira-parcela", "name" => "data_primeira_parcela", "type" => "date", "value" => $model_info->data_primeira_parcela, "class" => "form-control"]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-aluno-data-inicio" class="col-md-3">Início do curso</label>
                <div class="col-md-3">
                    <?php echo form_input(["id" => "bombeiros-aluno-data-inicio", "name" => "data_inicio", "type" => "date", "value" => $model_info->data_inicio, "class" => "form-control"]); ?>
                </div>
                <label for="bombeiros-aluno-horario" class="col-md-2">Horário da turma</label>
                <div class="col-md-4">
                    <?php echo form_dropdown("horario", $turmas, $model_info->turma, ["id" => "bombeiros-aluno-horario", "class" => "form-control"]); ?>
                </div>
            </div>
        </div>

        <h5 class="mb15 mt20">Dados adicionais da ficha</h5>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-aluno-camisa" class="col-md-3">Tamanho da camiseta</label>
                <div class="col-md-3">
                    <?php echo form_input(["id" => "bombeiros-aluno-camisa", "name" => "tamanho_camisa", "value" => $model_info->tamanho_camisa, "class" => "form-control", "placeholder" => "Ex.: 14, P, M, G"]); ?>
                </div>
                <label for="bombeiros-aluno-melhor-horario" class="col-md-2">Melhor ligação</label>
                <div class="col-md-4">
                    <?php echo form_dropdown("melhor_horario_ligacao", $melhor_horario_options, $model_info->melhor_horario_ligacao, ["id" => "bombeiros-aluno-melhor-horario", "class" => "form-control"]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3">Efetuado</label>
                <div class="col-md-9">
                    <input type="hidden" name="matricula_efetuada" value="0" />
                    <input type="hidden" name="uniforme_efetuado" value="0" />
                    <input type="hidden" name="material_efetuado" value="0" />
                    <label class="mr15"><?php echo form_checkbox("matricula_efetuada", "1", (int) $model_info->matricula_efetuada === 1, "id='bombeiros-aluno-matricula-efetuada'"); ?> Matrícula</label>
                    <label class="mr15"><?php echo form_checkbox("uniforme_efetuado", "1", (int) $model_info->uniforme_efetuado === 1, "id='bombeiros-aluno-uniforme-efetuado'"); ?> Uniforme</label>
                    <label><?php echo form_checkbox("material_efetuado", "1", (int) $model_info->material_efetuado === 1, "id='bombeiros-aluno-material-efetuado'"); ?> Material</label>
                </div>
            </div>
        </div>

        <h5 class="mb15 mt20">Assinatura</h5>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-aluno-cidade-assinatura" class="col-md-3">Cidade/UF</label>
                <div class="col-md-5">
                    <?php echo form_input(["id" => "bombeiros-aluno-cidade-assinatura", "name" => "cidade_assinatura", "value" => $model_info->cidade_assinatura, "class" => "form-control"]); ?>
                </div>
                <div class="col-md-2">
                    <?php echo form_input(["id" => "bombeiros-aluno-estado-assinatura", "name" => "estado_assinatura", "value" => $model_info->estado_assinatura, "class" => "form-control", "placeholder" => "UF"]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3">Data da assinatura</label>
                <div class="col-md-2">
                    <?php echo form_input(["id" => "bombeiros-aluno-dia-assinatura", "name" => "dia_assinatura", "value" => $model_info->dia_assinatura, "class" => "form-control", "placeholder" => "Dia"]); ?>
                </div>
                <div class="col-md-4">
                    <?php echo form_input(["id" => "bombeiros-aluno-mes-assinatura", "name" => "mes_assinatura", "value" => $model_info->mes_assinatura, "class" => "form-control", "placeholder" => "Mês"]); ?>
                </div>
                <div class="col-md-3">
                    <?php echo form_input(["id" => "bombeiros-aluno-ano-assinatura", "name" => "ano_assinatura", "value" => $model_info->ano_assinatura, "class" => "form-control", "placeholder" => "Ano"]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-aluno-assinatura-contratada" class="col-md-3">Assinatura Grupo Donato</label>
                <div class="col-md-9">
                    <?php echo form_input(["id" => "bombeiros-aluno-assinatura-contratada", "name" => "assinatura_contratada", "value" => $model_info->assinatura_contratada, "class" => "form-control"]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-aluno-assinatura-contratante" class="col-md-3">Assinatura contratante</label>
                <div class="col-md-9">
                    <?php echo form_input(["id" => "bombeiros-aluno-assinatura-contratante", "name" => "assinatura_contratante", "value" => $model_info->assinatura_contratante, "class" => "form-control"]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3">Ciência</label>
                <div class="col-md-3">
                    <input type="hidden" name="li_ciente" value="0" />
                    <label><?php echo form_checkbox("li_ciente", "1", (int) $model_info->li_ciente === 1, "id='bombeiros-aluno-li-ciente'"); ?> Li e estou ciente</label>
                </div>
                <label for="bombeiros-aluno-status" class="col-md-2">Status</label>
                <div class="col-md-4">
                    <?php echo form_dropdown("status", $status_options, $model_info->status ?: "Ativo", ["id" => "bombeiros-aluno-status", "class" => "form-control"]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-aluno-data-cancelamento" class="col-md-3">Data cancelamento</label>
                <div class="col-md-3">
                    <?php echo form_input(["id" => "bombeiros-aluno-data-cancelamento", "name" => "data_cancelamento", "type" => "date", "value" => $model_info->data_cancelamento ?? "", "class" => "form-control"]); ?>
                </div>
                <label for="bombeiros-aluno-motivo-cancelamento" class="col-md-2">Motivo</label>
                <div class="col-md-4">
                    <?php echo form_input(["id" => "bombeiros-aluno-motivo-cancelamento", "name" => "motivo_cancelamento", "value" => $model_info->motivo_cancelamento ?? "", "class" => "form-control"]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-aluno-observacao-cancelamento" class="col-md-3">Observação</label>
                <div class="col-md-9">
                    <?php echo form_textarea(["id" => "bombeiros-aluno-observacao-cancelamento", "name" => "observacao_cancelamento", "value" => $model_info->observacao_cancelamento ?? "", "class" => "form-control", "rows" => 2]); ?>
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
        $("#bombeiros-aluno-form").appForm({
            onSuccess: function (result) {
                if (window.reloadGdOperationalTables) {
                    reloadGdOperationalTables();
                } else if ($("#bombeiros-alunos-table").length && $.fn.DataTable.isDataTable("#bombeiros-alunos-table")) {
                    $("#bombeiros-alunos-table").appTable({newData: result.data, dataId: result.id});
                }
                if (window.reloadBombeirosFinanceiro) {
                    reloadBombeirosFinanceiro();
                }
            }
        });
    });
</script>
