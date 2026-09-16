<?php
$turmas = is_array($turmas ?? null) ? $turmas : [];
$melhor_horario_options = is_array($melhor_horario_options ?? null) ? $melhor_horario_options : [];
$defaults = is_array($defaults ?? null) ? $defaults : [];
$unidade_nome = (string) ($unidade->nome_unidade ?? "Grupo Donato");
$unidade_cidade = (string) ($unidade->cidade ?? "");
$post_url = (string) ($post_url ?? current_url());
$asset_url = get_uri("matricula-online/asset/online_enrollment.js");
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Matrícula online - <?php echo esc($unidade_nome); ?></title>
    <style>
        :root { color-scheme: light; --bg:#f4f7fb; --panel:#fff; --ink:#172033; --muted:#64748b; --line:#d9e1ec; --brand:#1557a6; --brand-dark:#0c3c77; --ok:#087443; --error:#b42318; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--ink); font:16px/1.45 Arial,Helvetica,sans-serif; }
        .gd-enrollment-page { width:100%; max-width:980px; margin:0 auto; padding:20px 14px 40px; }
        .gd-enrollment-header { display:flex; align-items:flex-start; justify-content:space-between; gap:14px; margin-bottom:16px; }
        .gd-enrollment-header h1 { margin:0 0 4px; font-size:clamp(23px,6vw,32px); line-height:1.15; }
        .gd-enrollment-header p { margin:0; color:var(--muted); }
        .gd-enrollment-badge { padding:7px 11px; border:1px solid #c7dcfa; border-radius:999px; background:#eaf2ff; color:var(--brand-dark); font-size:12px; font-weight:700; white-space:nowrap; }
        .gd-enrollment-card { overflow:hidden; border:1px solid var(--line); border-radius:14px; background:var(--panel); box-shadow:0 12px 34px rgba(23,32,51,.08); }
        .gd-enrollment-progress { display:grid; grid-template-columns:repeat(3,1fr); gap:6px; padding:13px 14px; border-bottom:1px solid var(--line); background:#f8fafc; }
        .gd-enrollment-progress span { color:#94a3b8; font-size:12px; font-weight:700; text-align:center; }
        .gd-enrollment-progress span.active { color:var(--brand); }
        .gd-enrollment-progress i { display:block; width:100%; height:4px; margin-top:6px; border-radius:999px; background:#e2e8f0; }
        .gd-enrollment-progress span.active i { background:var(--brand); }
        .gd-enrollment-step { padding:20px; }
        .gd-enrollment-step h2 { margin:0 0 5px; font-size:20px; }
        .gd-enrollment-step-intro { margin:0 0 18px; color:var(--muted); }
        .gd-enrollment-grid { display:grid; grid-template-columns:repeat(12,minmax(0,1fr)); gap:13px; }
        .gd-enrollment-field { grid-column:span 6; min-width:0; }
        .gd-enrollment-field.full { grid-column:1/-1; }
        .gd-enrollment-field.third { grid-column:span 4; }
        .gd-enrollment-field label { display:block; margin:0 0 6px; color:#34445c; font-size:13px; font-weight:700; }
        .gd-enrollment-field input,.gd-enrollment-field select { width:100%; min-height:45px; padding:10px 11px; border:1px solid #c8d3e0; border-radius:8px; background:#fff; color:var(--ink); font:inherit; }
        .gd-enrollment-summary { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin:0 0 17px; padding:14px; border:1px solid var(--line); border-radius:10px; background:#f8fafc; }
        .gd-enrollment-summary p { margin:0; color:var(--muted); font-size:13px; }
        .gd-enrollment-summary strong { display:block; color:var(--ink); font-size:16px; }
        .gd-enrollment-actions { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:16px 20px; border-top:1px solid var(--line); background:#f8fafc; }
        .gd-enrollment-button { min-height:48px; padding:11px 18px; border:0; border-radius:9px; background:var(--brand); color:#fff; cursor:pointer; font:700 16px/1.2 Arial,sans-serif; text-decoration:none; text-align:center; }
        .gd-enrollment-button.secondary { border:1px solid #b8c6d8; background:#fff; color:var(--brand-dark); }
        .gd-enrollment-button:disabled { cursor:progress; opacity:.6; }
        .gd-enrollment-check { display:flex; align-items:flex-start; gap:10px; margin-top:18px; font-weight:700; }
        .gd-enrollment-check input { width:21px; height:21px; flex:0 0 auto; margin:1px 0 0; accent-color:var(--brand); }
        .gd-contract-shell { overflow:hidden; max-height:none; margin:0 -4px; border:1px solid var(--line); border-radius:10px; background:#fff; }
        .gd-contract-scroll-note { padding:10px 13px; border-bottom:1px solid var(--line); background:#f8fafc; color:var(--muted); font-size:13px; }
        .gd-signature-wrap { margin-top:17px; }
        .gd-signature-wrap label { display:block; margin-bottom:7px; font-weight:700; }
        .gd-signature-canvas { display:block; width:100%; height:220px; border:2px dashed #9fb2c8; border-radius:10px; background:#fff; cursor:crosshair; touch-action:none; }
        .gd-signature-tools { display:flex; justify-content:flex-end; gap:9px; margin-top:9px; }
        .gd-enrollment-message { display:none; flex:1; padding:11px 13px; border-radius:8px; font-weight:700; }
        .gd-enrollment-message.ok { display:block; background:#e8f7ee; color:var(--ok); }
        .gd-enrollment-message.error { display:block; background:#fdecea; color:var(--error); }
        .gd-enrollment-success { padding:32px 20px 38px; text-align:center; }
        .gd-enrollment-success h2 { margin:0 0 10px; color:var(--ok); font-size:24px; }
        .gd-enrollment-success p { margin:7px 0; }
        .gd-enrollment-success .gd-success-box { max-width:520px; margin:20px auto; padding:15px; border:1px solid #cfe7d8; border-radius:10px; background:#f4fcf7; text-align:left; }
        .gd-enrollment-success .gd-success-box strong { display:inline-block; min-width:112px; }
        .gd-enrollment-success .gd-success-actions { display:flex; justify-content:center; flex-wrap:wrap; gap:10px; margin-top:20px; }
        [hidden] { display:none !important; }
        @media (max-width:700px) {
            .gd-enrollment-page { padding:12px 8px 28px; }
            .gd-enrollment-header { display:block; padding:4px 5px; }
            .gd-enrollment-badge { display:inline-flex; margin-top:10px; }
            .gd-enrollment-step { padding:16px 13px; }
            .gd-enrollment-field,.gd-enrollment-field.third { grid-column:1/-1; }
            .gd-enrollment-summary { grid-template-columns:1fr; }
            .gd-enrollment-actions { align-items:stretch; flex-direction:column; padding:13px; }
            .gd-enrollment-actions .gd-enrollment-button { width:100%; }
            .gd-enrollment-message { width:100%; }
            .gd-signature-canvas { height:205px; }
        }
    </style>
</head>
<body>
<main class="gd-enrollment-page" id="gd-online-enrollment"
      data-create-url="<?php echo esc($post_url); ?>"
      data-state-url="<?php echo esc($state_url); ?>"
      data-accept-url="<?php echo esc($accept_url); ?>"
      data-signature-url="<?php echo esc($signature_url); ?>"
      data-finalize-url="<?php echo esc($finalize_url); ?>"
      data-whatsapp-url="<?php echo esc($whatsapp_url); ?>"
      data-storage-key="gd-online-enrollment-<?php echo esc((string) ($unidade->slug ?? "unidade")); ?>">
    <header class="gd-enrollment-header"><div><h1>Matrícula online</h1><p><?php echo esc($unidade_nome . ($unidade_cidade ? " - " . $unidade_cidade : "")); ?></p></div><span class="gd-enrollment-badge">Escola de Futebol Camisa 9</span></header>
    <section class="gd-enrollment-card">
        <div class="gd-enrollment-progress" aria-label="Etapas da matrícula"><span data-progress="1" class="active">1. Dados<i></i></span><span data-progress="2">2. Contrato<i></i></span><span data-progress="3">3. Assinatura<i></i></span></div>
        <form id="gd-public-matricula-form" method="post" action="<?php echo esc($post_url); ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="idempotency_key" id="gd-idempotency-key">
            <input type="hidden" name="origem_matricula" value="telemarketing"><input type="hidden" name="curso_nome" value="<?php echo esc($defaults["curso_nome"] ?? "ACADEMIA DE TREINAMENTO MIRIM"); ?>"><input type="hidden" name="num_parcelas" value="12"><input type="hidden" name="valor_mensalidade" value="237.00"><input type="hidden" name="valor_mensal" value="237.00"><input type="hidden" name="valor_inscricao" value="100.00"><input type="hidden" name="data_inscricao" value="<?php echo date("Y-m-d"); ?>"><input type="hidden" name="matricula_efetuada" value="0"><input type="hidden" name="uniforme_efetuado" value="0"><input type="hidden" name="material_efetuado" value="0">
            <div class="gd-enrollment-step" data-step="1">
                <h2>Dados da matrícula</h2><p class="gd-enrollment-step-intro">Preencha os dados do responsável e do aluno. Eles serão usados no contrato.</p>
                <div class="gd-enrollment-grid">
                    <div class="gd-enrollment-field full"><label for="responsavel_nome">Nome completo do responsável *</label><input id="responsavel_nome" name="responsavel_nome" required autocomplete="name"></div>
                    <div class="gd-enrollment-field third"><label for="responsavel_cpf">CPF</label><input id="responsavel_cpf" name="responsavel_cpf" inputmode="numeric" autocomplete="off"></div><div class="gd-enrollment-field third"><label for="responsavel_rg">RG</label><input id="responsavel_rg" name="responsavel_rg" autocomplete="off"></div><div class="gd-enrollment-field third"><label for="responsavel_nascimento">Nascimento</label><input id="responsavel_nascimento" name="responsavel_nascimento" type="date" min="1900-01-01" max="<?php echo date("Y-m-d"); ?>"></div>
                    <div class="gd-enrollment-field"><label for="responsavel_whats">WhatsApp *</label><input id="responsavel_whats" name="responsavel_whats" required inputmode="tel" autocomplete="tel"></div><div class="gd-enrollment-field"><label for="responsavel_email">E-mail</label><input id="responsavel_email" name="responsavel_email" type="email" autocomplete="email"></div>
                    <div class="gd-enrollment-field full"><label for="responsavel_endereco">Endereço</label><input id="responsavel_endereco" name="responsavel_endereco" autocomplete="street-address"></div><div class="gd-enrollment-field third"><label for="responsavel_numero">Número</label><input id="responsavel_numero" name="responsavel_numero"></div><div class="gd-enrollment-field third"><label for="responsavel_bairro">Bairro</label><input id="responsavel_bairro" name="responsavel_bairro"></div><div class="gd-enrollment-field third"><label for="responsavel_cidade">Cidade</label><input id="responsavel_cidade" name="responsavel_cidade"></div><div class="gd-enrollment-field"><label for="responsavel_cep">CEP</label><input id="responsavel_cep" name="responsavel_cep" inputmode="numeric" autocomplete="postal-code"></div><div class="gd-enrollment-field"><label for="responsavel_complemento">Complemento</label><input id="responsavel_complemento" name="responsavel_complemento"></div><div class="gd-enrollment-field"><label for="cidade_assinatura">Cidade da assinatura</label><input id="cidade_assinatura" name="cidade_assinatura" value="<?php echo esc($unidade_cidade ?: "São Bernardo do Campo"); ?>"></div><div class="gd-enrollment-field"><label for="estado_assinatura">UF</label><input id="estado_assinatura" name="estado_assinatura" value="SP" maxlength="2" autocapitalize="characters"></div>
                    <div class="gd-enrollment-field full"><hr style="border:0;border-top:1px solid #e2e8f0;margin:4px 0"></div><div class="gd-enrollment-field full"><label for="nome_aluno">Nome completo do aluno *</label><input id="nome_aluno" name="nome_aluno" required autocomplete="off"></div><div class="gd-enrollment-field third"><label for="nascimento_aluno">Nascimento *</label><input id="nascimento_aluno" name="nascimento_aluno" type="date" required min="1900-01-01" max="<?php echo date("Y-m-d"); ?>"></div><div class="gd-enrollment-field third"><label for="cpf_aluno">CPF do aluno</label><input id="cpf_aluno" name="cpf_aluno" inputmode="numeric" autocomplete="off"></div><div class="gd-enrollment-field third"><label for="rg_aluno">RG do aluno</label><input id="rg_aluno" name="rg_aluno" autocomplete="off"></div>
                    <div class="gd-enrollment-field"><label for="horario">Horário da turma</label><select id="horario" name="horario"><option value="">Selecione</option><?php foreach ($turmas as $value => $label) { if (is_array($label)) { ?><optgroup label="<?php echo esc((string) $value); ?>"><?php foreach ($label as $option_value => $option_label) { ?><option value="<?php echo esc((string) $option_value); ?>"><?php echo esc((string) $option_label); ?></option><?php } ?></optgroup><?php } elseif ((string) $value !== "") { ?><option value="<?php echo esc((string) $value); ?>"><?php echo esc((string) $label); ?></option><?php } } ?></select></div><div class="gd-enrollment-field"><label for="tamanho_camisa">Tamanho da camiseta</label><input id="tamanho_camisa" name="tamanho_camisa" placeholder="Ex.: 14, P, M, G"></div><div class="gd-enrollment-field"><label for="data_inicio">Início do curso</label><input id="data_inicio" name="data_inicio" type="date" value="<?php echo esc($defaults["data_inicio"] ?? date("Y-m-d")); ?>"></div><div class="gd-enrollment-field"><label for="melhor_horario_ligacao">Melhor horário para ligação</label><select id="melhor_horario_ligacao" name="melhor_horario_ligacao"><?php foreach ($melhor_horario_options as $value => $label) { ?><option value="<?php echo esc((string) $value); ?>"><?php echo esc((string) $label); ?></option><?php } ?></select></div>
                </div>
            </div>
            <div class="gd-enrollment-step" data-step="contract" hidden><h2>Leia seu contrato</h2><p class="gd-enrollment-step-intro">Confira os dados preenchidos e leia o contrato completo antes de continuar.</p><div class="gd-enrollment-summary"><p><strong>R$ 237,00</strong>Mensalidade</p><p><strong>12 parcelas</strong>Plano contratado</p><p><strong>Camisa 9</strong>Escola de Futebol</p></div><div class="gd-contract-shell"><div class="gd-contract-scroll-note">Role até o final para conferir todas as cláusulas.</div><div id="gd-contract-content"></div></div><label class="gd-enrollment-check"><input type="checkbox" name="li_ciente" value="1" id="gd-contract-accepted"><span>Li integralmente o contrato acima, compreendi suas cláusulas e concordo com os termos apresentados.</span></label></div>
            <div class="gd-enrollment-step" data-step="signature" hidden><h2>Confirme sua matrícula</h2><p class="gd-enrollment-step-intro">Assine com o dedo, mouse ou caneta digital. A assinatura será guardada junto ao contrato.</p><div class="gd-enrollment-summary" id="gd-final-summary"><p><strong data-summary="student_name">-</strong>Aluno</p><p><strong data-summary="responsible_name">-</strong>Responsável</p><p><strong data-summary="class_name">-</strong>Turma</p><p><strong data-summary="monthly_value">R$ 237,00</strong>Mensalidade</p></div><p id="gd-signature-status" hidden>✓ Assinatura registrada. Você já pode finalizar a matrícula.</p><div class="gd-signature-wrap" id="gd-signature-area"><label for="gd-signature-canvas">ASSINATURA DO RESPONSÁVEL</label><canvas class="gd-signature-canvas" id="gd-signature-canvas" width="900" height="410"></canvas><div class="gd-signature-tools"><button class="gd-enrollment-button secondary" type="button" id="gd-signature-clear">Limpar assinatura</button></div></div></div>
            <div class="gd-enrollment-success" data-step="success" hidden><h2>✓ Matrícula realizada com sucesso</h2><p><strong data-success="student_name">O aluno</strong> já está matriculado na Escola de Futebol Camisa 9.</p><div class="gd-success-box"><p><strong>Responsável:</strong> <span data-success="responsible_name">-</span></p><p><strong>WhatsApp:</strong> <span data-success="whatsapp">-</span></p><p><strong>Contrato:</strong> <span data-success="contract_number">-</span></p><p id="gd-delivery-note">✓ Uma cópia do contrato foi enviada para o WhatsApp.</p></div><div class="gd-success-actions"><a class="gd-enrollment-button" id="gd-download-contract" href="#" target="_blank" rel="noopener" hidden>Baixar meu contrato</a><button class="gd-enrollment-button secondary" type="button" id="gd-retry-whatsapp" hidden>Tentar enviar novamente</button><button class="gd-enrollment-button secondary" type="button" id="gd-new-enrollment">Nova matrícula</button></div></div>
            <div class="gd-enrollment-actions" id="gd-enrollment-actions"><div class="gd-enrollment-message" id="gd-enrollment-message" role="alert" aria-live="polite"></div><button class="gd-enrollment-button" type="button" id="gd-step1-submit">Continuar e ver contrato</button><button class="gd-enrollment-button" type="button" id="gd-step2-submit" hidden disabled>Continuar para assinatura</button><button class="gd-enrollment-button" type="button" id="gd-signature-submit" hidden>Confirmar assinatura</button><button class="gd-enrollment-button" type="button" id="gd-finalize-submit" hidden>FINALIZAR MATRÍCULA</button></div>
        </form>
    </section>
</main>
<script src="<?php echo esc($asset_url); ?>" defer></script>
</body>
</html>
