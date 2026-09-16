<?php
$turmas = bombeiros_turmas_grouped();
$status_options = ["Ativo" => "Ativo", "Pendente" => "Pendente", "Inadimplente" => "Inadimplente", "Concluido" => "Concluído", "Inativo" => "Inativo", "Cancelado" => "Cancelado"];
$melhor_horario_options = ["" => "-", "manha" => "Manhã", "tarde" => "Tarde", "qualquer" => "Qualquer horário"];
$exame_medico_nome = !empty($model_info->exame_medico_nome) ? $model_info->exame_medico_nome : "Exame médico anexado";
$exame_medico_tamanho = (int) ($model_info->exame_medico_tamanho ?? 0);
$exame_medico_tamanho_label = "";
if ($exame_medico_tamanho > 0) {
    $exame_medico_tamanho_label = $exame_medico_tamanho >= 1048576
        ? number_format($exame_medico_tamanho / 1048576, 1, ",", ".") . " MB"
        : number_format($exame_medico_tamanho / 1024, 0, ",", ".") . " KB";
}
$student_photo_default_url = get_avatar();
$student_photo_has_current = !empty($model_info->id) && !empty($model_info->photo_path);
$student_photo_current_url = $student_photo_has_current
    ? get_uri("grupo_donato/operacional/foto_aluno/" . (int) $model_info->id)
        . "?v=" . rawurlencode(pathinfo((string) $model_info->photo_path, PATHINFO_FILENAME))
    : $student_photo_default_url;
$can_manage_student_photo = !empty($can_manage_student_photo);
$is_new_student = empty($model_info->id);
$cross_unit_units = is_array($cross_unit_units ?? null) ? $cross_unit_units : [];
?>

<style>
    #bombeiros-aluno-form .gd-file-input {
        color: #ffffff;
    }

    #bombeiros-aluno-form .gd-file-input::file-selector-button,
    #bombeiros-aluno-form .gd-file-input::-webkit-file-upload-button {
        background-color: #f8fafc !important;
        border: 0;
        border-right: 1px solid rgba(15, 23, 42, 0.16);
        color: #17365f !important;
        font-weight: 600;
    }

    #bombeiros-aluno-form .gd-file-input:hover::file-selector-button,
    #bombeiros-aluno-form .gd-file-input:hover::-webkit-file-upload-button {
        background-color: #e8eef7 !important;
        color: #17365f !important;
    }

    #bombeiros-aluno-form .gd-student-photo-preview {
        width: 125px;
        height: 125px;
        border-radius: 50%;
        object-fit: cover;
        background: #eef1f5;
    }

    #bombeiros-aluno-form .gd-cross-unit-results .list-group-item {
        cursor: pointer;
    }
</style>

<?php echo form_open_multipart(get_uri("grupo_donato/operacional/save_aluno"), ["id" => "bombeiros-aluno-form", "class" => "general-form", "role" => "form"]); ?>
<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="id" value="<?php echo (int) $model_info->id; ?>" />
        <input type="hidden" name="responsavel_id" value="<?php echo (int) $model_info->responsavel_id; ?>" />
        <input type="hidden" name="origem_matricula" value="<?php echo esc($model_info->origem_matricula ?: "manual"); ?>" />
        <?php if ($is_new_student) { ?>
            <input type="hidden" name="origem_aluno_id" id="bombeiros-aluno-origem-id" class="validate-hidden" value="" />
            <input type="hidden" name="origem_unidade_id" id="bombeiros-aluno-origem-unidade-id" value="" />

            <div class="alert alert-info mb20">
                <div class="form-group mb0">
                    <label for="bombeiros-aluno-origem-tipo"><strong>Como deseja cadastrar este aluno?</strong></label>
                    <select id="bombeiros-aluno-origem-tipo" class="form-control">
                        <option value="novo">Aluno totalmente novo</option>
                        <option value="outra_unidade">Aluno já cadastrado em outra unidade</option>
                    </select>
                </div>

                <div id="bombeiros-aluno-outra-unidade" class="hide mt15">
                    <div class="row">
                        <div class="col-md-5">
                            <label for="bombeiros-aluno-origem-unidade">Unidade onde já está cadastrado</label>
                            <select id="bombeiros-aluno-origem-unidade" class="form-control">
                                <option value="">Selecione a unidade</option>
                                <?php foreach ($cross_unit_units as $cross_unit_id => $cross_unit_label) { ?>
                                    <option value="<?php echo (int) $cross_unit_id; ?>"><?php echo esc($cross_unit_label); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label for="bombeiros-aluno-origem-busca">Buscar aluno</label>
                            <div class="input-group">
                                <input type="search" id="bombeiros-aluno-origem-busca" class="form-control" placeholder="Nome, matrícula, CPF ou responsável" autocomplete="off" />
                                <button type="button" id="bombeiros-aluno-origem-buscar" class="btn btn-default">Buscar</button>
                            </div>
                        </div>
                    </div>
                    <div id="bombeiros-aluno-origem-resultados" class="gd-cross-unit-results list-group mt10"></div>
                    <div id="bombeiros-aluno-origem-selecionado" class="small mt10 hide"></div>
                    <div class="text-off mt5">A turma, cobrança, assinatura e histórico serão preenchidos para a unidade atual.</div>
                </div>
            </div>
        <?php } ?>

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
            <div class="row align-items-center">
                <label for="bombeiros-aluno-foto" class="col-md-3">Foto de perfil</label>
                <div class="col-md-3 text-center">
                    <span class="avatar avatar-lg">
                        <img
                            id="bombeiros-aluno-foto-preview"
                            class="gd-student-photo-preview"
                            src="<?php echo esc($student_photo_current_url); ?>"
                            alt="Foto de <?php echo esc($model_info->nome_aluno ?: "aluno"); ?>"
                            loading="lazy"
                            onerror="this.onerror=null;this.src='<?php echo esc($student_photo_default_url); ?>';"
                        />
                    </span>
                </div>
                <div class="col-md-6">
                    <?php if ($can_manage_student_photo) { ?>
                        <input
                            type="file"
                            name="student_photo"
                            id="bombeiros-aluno-foto"
                            class="form-control gd-file-input"
                            accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                        />
                        <div class="text-off mt5">JPG, JPEG, PNG ou WebP. Tamanho máximo: 10 MB. A foto será otimizada para até 500 × 500 px.</div>
                        <input type="hidden" name="remove_photo" value="0" />
                        <?php if ($student_photo_has_current) { ?>
                            <label class="mt10">
                                <input type="checkbox" name="remove_photo" id="bombeiros-aluno-remover-foto" value="1" />
                                Remover a foto atual
                            </label>
                            <div class="text-off">Selecione uma nova imagem para substituir a foto atual.</div>
                        <?php } ?>
                    <?php } else { ?>
                        <div class="text-off">Você possui acesso de visualização. A alteração da foto exige permissão para gerenciar alunos.</div>
                    <?php } ?>
                </div>
            </div>
        </div>

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

        <h5 class="mb15 mt20">Exame médico</h5>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-aluno-exame-medico" class="col-md-3">Anexar exame</label>
                <div class="col-md-9">
                    <input type="file" name="exame_medico" id="bombeiros-aluno-exame-medico" class="form-control gd-file-input" accept="application/pdf,image/jpeg,image/png,image/webp,.pdf,.jpg,.jpeg,.png,.webp" />
                    <?php if (!empty($model_info->exame_medico) && !empty($model_info->id)) { ?>
                        <div class="mt10">
                            Exame salvo na matrícula:
                            <a href="<?php echo get_uri("grupo_donato/operacional/baixar_exame_medico/" . (int) $model_info->id); ?>" target="_blank" rel="noopener">
                                <span data-feather="file-text" class="icon-16"></span> <?php echo esc($exame_medico_nome); ?>
                            </a>
                            <?php if ($exame_medico_tamanho_label) { ?>
                                <span class="text-off">(<?php echo $exame_medico_tamanho_label; ?>)</span>
                            <?php } ?>
                            <div class="text-off">Selecione um novo arquivo para substituir o exame atual.</div>
                        </div>
                    <?php } else { ?>
                        <div class="text-off mt5">Formatos aceitos: PDF ou imagem (JPG/PNG/WebP). Fotos grandes serão compactadas automaticamente.</div>
                    <?php } ?>
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

        <?php if (!empty($student_sport_history) && !empty($student_sport_history["events"])): ?>
            <div class="border-top pt15 mt20">
                <h5>Desenvolvimento esportivo</h5>
                <?php foreach ($student_sport_history["events"] as $sport_event): ?>
                    <div class="border-bottom py10">
                        <strong><?php echo esc($sport_event->event_name); ?></strong>
                        <span class="text-off"> · <?php echo esc($sport_event->category_name); ?> · <?php echo esc(date("d/m/Y", strtotime((string) $sport_event->starts_on))); ?></span>
                        <div class="small mt5">Escalação: <?php echo esc($sport_event->lineup_status); ?> · <?php echo $sport_event->matches ? count($sport_event->matches) . " partida(s)" : "sem estatísticas de partida"; ?></div>
                        <?php if (!empty($sport_event->scores)): ?><div class="small mt5"><?php foreach ($sport_event->scores as $score): ?><span class="badge bg-secondary me5"><?php echo esc($score->name); ?>: <?php echo esc((string) $score->score); ?></span><?php endforeach; ?></div><?php endif; ?>
                        <?php if (!empty($sport_event->evaluation->strengths)): ?><div class="small mt5"><strong>Pontos fortes:</strong> <?php echo esc($sport_event->evaluation->strengths); ?></div><?php endif; ?>
                        <?php if (!empty($sport_event->evaluation->development_areas)): ?><div class="small"><strong>A desenvolver:</strong> <?php echo esc($sport_event->evaluation->development_areas); ?></div><?php endif; ?>
                        <?php if (!empty($sport_event->evaluation->responsible_feedback)): ?><div class="small"><strong>Feedback:</strong> <?php echo esc($sport_event->evaluation->responsible_feedback); ?></div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php if (!empty($student_sport_report) && !empty($student_sport_report["events"])): ?>
            <div class="border-top pt15 mt20">
                <div class="d-flex justify-content-between align-items-center"><h5 class="mb0">Relatório trimestral</h5><button type="button" class="btn btn-default btn-sm" onclick="window.print();">Imprimir</button></div>
                <div class="small text-off mt5"><?php echo esc($student_sport_report["date_from"]); ?> a <?php echo esc($student_sport_report["date_to"]); ?> · <?php echo (int) $student_sport_report["event_count"]; ?> evento(s)</div>
                <?php if (!empty($student_sport_report["averages"])): ?><div class="small mt8"><strong>Médias por critério:</strong> <?php foreach ($student_sport_report["averages"] as $criterion => $average): ?><span class="badge bg-secondary me5"><?php echo esc($criterion); ?>: <?php echo esc($average); ?></span><?php endforeach; ?></div><?php endif; ?>
                <?php if (!empty($student_sport_report["strengths"])): ?><div class="small mt8"><strong>Pontos fortes:</strong> <?php echo esc(implode(" · ", $student_sport_report["strengths"])); ?></div><?php endif; ?>
                <?php if (!empty($student_sport_report["development_areas"])): ?><div class="small"><strong>Próximos desenvolvimentos:</strong> <?php echo esc(implode(" · ", $student_sport_report["development_areas"])); ?></div><?php endif; ?>
                <?php if (!empty($student_sport_report["comments"])): ?><div class="small"><strong>Comentários:</strong> <?php echo esc(implode(" · ", $student_sport_report["comments"])); ?></div><?php endif; ?>
            </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><span data-feather="x" class="icon-16"></span> <?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary"><span data-feather="check-circle" class="icon-16"></span> <?php echo app_lang("save"); ?></button>
</div>
<?php echo form_close(); ?>

<script type="text/javascript">
    $(document).ready(function () {
        var photoInput = document.getElementById("bombeiros-aluno-foto");
        var photoPreview = document.getElementById("bombeiros-aluno-foto-preview");
        var removePhoto = document.getElementById("bombeiros-aluno-remover-foto");
        var currentPhotoUrl = <?php echo json_encode($student_photo_current_url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        var defaultPhotoUrl = <?php echo json_encode($student_photo_default_url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        var previewObjectUrl = null;

        function clearPreviewObjectUrl() {
            if (previewObjectUrl) {
                URL.revokeObjectURL(previewObjectUrl);
                previewObjectUrl = null;
            }
        }

        if (photoInput && photoPreview) {
            photoInput.addEventListener("change", function () {
                clearPreviewObjectUrl();
                var file = this.files && this.files[0] ? this.files[0] : null;
                if (!file) {
                    photoPreview.src = removePhoto && removePhoto.checked ? defaultPhotoUrl : currentPhotoUrl;
                    return;
                }

                var allowedTypes = ["image/jpeg", "image/png", "image/webp"];
                if ((file.type && allowedTypes.indexOf(file.type) === -1) || file.size > 10 * 1024 * 1024) {
                    this.value = "";
                    photoPreview.src = currentPhotoUrl;
                    appAlert.error(file.size > 10 * 1024 * 1024
                        ? "A foto deve ter no máximo 10 MB."
                        : "Selecione uma imagem JPG, PNG ou WebP.");
                    return;
                }

                if (removePhoto) {
                    removePhoto.checked = false;
                }
                previewObjectUrl = URL.createObjectURL(file);
                photoPreview.src = previewObjectUrl;
            });
        }

        if (removePhoto && photoPreview) {
            removePhoto.addEventListener("change", function () {
                clearPreviewObjectUrl();
                if (this.checked && photoInput) {
                    photoInput.value = "";
                }
                photoPreview.src = this.checked ? defaultPhotoUrl : currentPhotoUrl;
            });
        }

        <?php if ($is_new_student) { ?>
        var $studentForm = $("#bombeiros-aluno-form");
        var $originType = $("#bombeiros-aluno-origem-tipo");
        var $originPanel = $("#bombeiros-aluno-outra-unidade");
        var $originUnit = $("#bombeiros-aluno-origem-unidade");
        var $originSearch = $("#bombeiros-aluno-origem-busca");
        var $originResults = $("#bombeiros-aluno-origem-resultados");
        var $originSelected = $("#bombeiros-aluno-origem-selecionado");
        var crossUnitStudents = {};

        function crossUnitField(name, value) {
            $studentForm.find("[name='" + name + "']").val(value == null ? "" : value);
        }

        function clearCrossUnitSelection(clearPersonalData) {
            crossUnitStudents = {};
            $("#bombeiros-aluno-origem-id, #bombeiros-aluno-origem-unidade-id").val("");
            $originResults.empty();
            $originSelected.addClass("hide").text("");
            if (clearPersonalData) {
                [
                    "nome_aluno", "nascimento_aluno", "rg_aluno", "cpf_aluno",
                    "responsavel_nome", "responsavel_nascimento", "responsavel_rg", "responsavel_cpf",
                    "responsavel_whats", "responsavel_celular", "responsavel_recado", "responsavel_email",
                    "responsavel_endereco", "responsavel_numero", "responsavel_complemento",
                    "responsavel_bairro", "responsavel_cep", "responsavel_cidade"
                ].forEach(function (name) { crossUnitField(name, ""); });
                crossUnitField("responsavel_id", "0");
            }
        }

        function updateCrossUnitSourceOptions() {
            var targetUnit = $("#bombeiros-aluno-unidade").val();
            var sourceUnit = $originUnit.val();
            $originUnit.find("option").each(function () {
                var value = $(this).val();
                $(this).prop("disabled", !!value && value === targetUnit);
            });
            if (sourceUnit && sourceUnit === targetUnit) {
                $originUnit.val("");
                clearCrossUnitSelection(false);
            }
        }

        function applyCrossUnitStudent(student) {
            var studentId = parseInt(student.id || 0, 10);
            if (!studentId) {
                return;
            }
            $("#bombeiros-aluno-origem-id").val(studentId);
            $("#bombeiros-aluno-origem-unidade-id").val(student.unidade_id || $originUnit.val());
            crossUnitField("responsavel_id", student.responsavel_id || 0);
            ["nome_aluno", "nascimento_aluno", "rg_aluno", "cpf_aluno"].forEach(function (name) {
                crossUnitField(name, student[name]);
            });
            [
                "responsavel_nome", "responsavel_nascimento", "responsavel_rg", "responsavel_cpf",
                "responsavel_whats", "responsavel_celular", "responsavel_recado", "responsavel_email",
                "responsavel_endereco", "responsavel_numero", "responsavel_complemento",
                "responsavel_bairro", "responsavel_cep", "responsavel_cidade"
            ].forEach(function (name) {
                crossUnitField(name, student[name]);
            });

            var matricula = student.matricula || student.id;
            var unidade = student.nome_unidade || "outra unidade";
            $originSelected
                .removeClass("hide")
                .text("Aluno selecionado: " + (student.nome_aluno || "") + " · matrícula " + matricula + " · " + unidade);
            $originResults.empty();
        }

        function renderCrossUnitResults(students) {
            crossUnitStudents = {};
            $originResults.empty();
            if (!students || !students.length) {
                $originResults.append($('<div class="text-off">Nenhum aluno encontrado.</div>'));
                return;
            }
            students.forEach(function (student) {
                crossUnitStudents[student.id] = student;
                var label = (student.nome_aluno || "Aluno sem nome") + " · matrícula " + (student.matricula || student.id);
                var details = (student.responsavel_nome || "Sem responsável") + " · " + (student.nome_unidade || "");
                var $item = $("<button>", {
                    type: "button",
                    class: "list-group-item list-group-item-action text-left"
                }).attr("data-cross-unit-student-id", student.id);
                $("<strong>").text(label).appendTo($item);
                $("<div>").addClass("small text-off").text(details).appendTo($item);
                $originResults.append($item);
            });
        }

        function searchCrossUnitStudents() {
            var sourceUnit = $originUnit.val();
            var targetUnit = $("#bombeiros-aluno-unidade").val();
            var query = $.trim($originSearch.val());
            if (!sourceUnit || !targetUnit || sourceUnit === targetUnit) {
                appAlert.error("Selecione uma unidade de origem diferente da unidade de destino.", {container: ".modal-body", animate: false});
                return;
            }
            if (query.length < 2) {
                appAlert.error("Informe pelo menos 2 caracteres para buscar o aluno.", {container: ".modal-body", animate: false});
                return;
            }
            $originResults.html('<div class="text-off">Buscando...</div>');
            appAjaxRequest({
                url: "<?php echo get_uri("grupo_donato/operacional/alunos_outra_unidade_search"); ?>",
                type: "POST",
                dataType: "json",
                data: {source_unit_id: sourceUnit, target_unit_id: targetUnit, query: query},
                success: function (result) {
                    if (!result || !result.success) {
                        $originResults.empty();
                        appAlert.error((result && result.message) || "Não foi possível buscar alunos.", {container: ".modal-body", animate: false});
                        return;
                    }
                    renderCrossUnitResults(result.data || []);
                },
                error: function () {
                    $originResults.empty();
                    appAlert.error("Não foi possível buscar alunos.", {container: ".modal-body", animate: false});
                }
            });
        }

        function setCrossUnitMode() {
            var isCrossUnit = $originType.val() === "outra_unidade";
            $originPanel.toggleClass("hide", !isCrossUnit);
            if (isCrossUnit) {
                $("#bombeiros-aluno-origem-id").attr("data-rule-required", "true");
                updateCrossUnitSourceOptions();
            } else {
                $("#bombeiros-aluno-origem-id").removeAttr("data-rule-required");
                clearCrossUnitSelection(true);
            }
        }

        $originType.on("change", setCrossUnitMode);
        $originUnit.on("change", function () {
            clearCrossUnitSelection(false);
        });
        $("#bombeiros-aluno-unidade").on("change", updateCrossUnitSourceOptions);
        $("#bombeiros-aluno-origem-buscar").on("click", searchCrossUnitStudents);
        $originSearch.on("keydown", function (event) {
            if (event.key === "Enter") {
                event.preventDefault();
                searchCrossUnitStudents();
            }
        });
        $originResults.on("click", "[data-cross-unit-student-id]", function () {
            applyCrossUnitStudent(crossUnitStudents[$(this).attr("data-cross-unit-student-id")]);
        });
        $studentForm.on("submit.gdCrossUnit", function (event) {
            if ($originType.val() === "outra_unidade" && !$("#bombeiros-aluno-origem-id").val()) {
                event.preventDefault();
                event.stopImmediatePropagation();
                appAlert.error("Selecione um aluno da outra unidade antes de salvar.", {container: ".modal-body", animate: false});
                return false;
            }
        });
        setCrossUnitMode();
        <?php } ?>

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
