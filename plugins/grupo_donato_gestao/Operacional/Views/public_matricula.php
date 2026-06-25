<?php
$turmas = $turmas ?? [];
$melhor_horario_options = $melhor_horario_options ?? [];
$defaults = $defaults ?? [];
$unidade_nome = $unidade->nome_unidade ?? "Grupo Donato";
$unidade_cidade = $unidade->cidade ?? "";
$post_url = $post_url ?? current_url();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Matrícula online - <?php echo esc($unidade_nome); ?></title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f4f7fb;
            --panel: #ffffff;
            --ink: #172033;
            --muted: #607086;
            --line: #d9e1ec;
            --brand: #1967d2;
            --brand-dark: #124b9a;
            --ok: #0f7b4f;
            --error: #b3261e;
        }

        * {
            box-sizing: border-box;
        }

        body {
            background: var(--bg);
            color: var(--ink);
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.45;
            margin: 0;
        }

        .page {
            margin: 0 auto;
            max-width: 980px;
            padding: 24px 16px 40px;
        }

        .topbar {
            align-items: flex-start;
            display: flex;
            gap: 16px;
            justify-content: space-between;
            margin-bottom: 18px;
        }

        .brand h1 {
            font-size: 26px;
            line-height: 1.2;
            margin: 0 0 4px;
        }

        .brand p,
        .summary p {
            color: var(--muted);
            margin: 0;
        }

        .tag {
            background: #e8f0fe;
            border: 1px solid #c8ddff;
            border-radius: 999px;
            color: var(--brand-dark);
            display: inline-flex;
            font-size: 13px;
            font-weight: 700;
            padding: 6px 10px;
            white-space: nowrap;
        }

        form {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: 0 12px 32px rgba(23, 32, 51, 0.08);
            overflow: hidden;
        }

        .section {
            border-top: 1px solid var(--line);
            padding: 22px;
        }

        .section:first-child {
            border-top: 0;
        }

        .section h2 {
            font-size: 18px;
            margin: 0 0 14px;
        }

        .grid {
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(12, 1fr);
        }

        .field {
            grid-column: span 6;
        }

        .field.full {
            grid-column: 1 / -1;
        }

        .field.third {
            grid-column: span 4;
        }

        label {
            color: #34445c;
            display: block;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .form-control,
        select,
        textarea {
            background: #fff;
            border: 1px solid #c8d3e0;
            border-radius: 6px;
            color: var(--ink);
            font: inherit;
            min-height: 42px;
            padding: 9px 10px;
            width: 100%;
        }

        textarea {
            min-height: 88px;
            resize: vertical;
        }

        .summary {
            background: #f8fafc;
            border: 1px solid var(--line);
            border-radius: 8px;
            display: grid;
            gap: 8px;
            grid-template-columns: repeat(3, 1fr);
            padding: 14px;
        }

        .summary strong {
            display: block;
            font-size: 15px;
        }

        .checkline {
            align-items: flex-start;
            display: flex;
            gap: 10px;
        }

        .checkline input {
            margin-top: 4px;
        }

        .actions {
            align-items: center;
            background: #f8fafc;
            border-top: 1px solid var(--line);
            display: flex;
            gap: 14px;
            justify-content: space-between;
            padding: 18px 22px;
        }

        .btn {
            background: var(--brand);
            border: 0;
            border-radius: 6px;
            color: #fff;
            cursor: pointer;
            font-size: 16px;
            font-weight: 700;
            min-height: 44px;
            padding: 10px 18px;
        }

        .btn:disabled {
            cursor: progress;
            opacity: 0.72;
        }

        .message {
            border-radius: 6px;
            display: none;
            font-weight: 700;
            padding: 12px 14px;
        }

        .message.ok {
            background: #e7f5ee;
            color: var(--ok);
            display: block;
        }

        .message.error {
            background: #fdecea;
            color: var(--error);
            display: block;
        }

        @media (max-width: 760px) {
            .topbar,
            .actions {
                display: block;
            }

            .tag,
            .actions .btn {
                margin-top: 12px;
                width: 100%;
            }

            .field,
            .field.third {
                grid-column: 1 / -1;
            }

            .summary {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <main class="page">
        <div class="topbar">
            <div class="brand">
                <h1>Matrícula online</h1>
                <p><?php echo esc($unidade_nome . ($unidade_cidade ? " - " . $unidade_cidade : "")); ?></p>
            </div>
            <span class="tag">Origem: telemarketing</span>
        </div>

        <?php echo form_open($post_url, ["id" => "gd-public-matricula-form"]); ?>
            <input type="hidden" name="origem_matricula" value="telemarketing">
            <input type="hidden" name="curso_nome" value="<?php echo esc($defaults["curso_nome"] ?? "ACADEMIA DE TREINAMENTO MIRIM"); ?>">
            <input type="hidden" name="num_parcelas" value="<?php echo (int) ($defaults["num_parcelas"] ?? 12); ?>">
            <input type="hidden" name="valor_mensalidade" value="<?php echo esc($defaults["valor_mensalidade"] ?? "237.00"); ?>">
            <input type="hidden" name="valor_mensal" value="<?php echo esc($defaults["valor_mensalidade"] ?? "237.00"); ?>">
            <input type="hidden" name="valor_inscricao" value="<?php echo esc($defaults["valor_inscricao"] ?? "100.00"); ?>">
            <input type="hidden" name="data_inscricao" value="<?php echo date("Y-m-d"); ?>">
            <input type="hidden" name="matricula_efetuada" value="0">
            <input type="hidden" name="uniforme_efetuado" value="0">
            <input type="hidden" name="material_efetuado" value="0">

            <section class="section">
                <h2>Responsável</h2>
                <div class="grid">
                    <div class="field full">
                        <label for="responsavel_nome">Nome completo *</label>
                        <input id="responsavel_nome" class="form-control" name="responsavel_nome" required autocomplete="name">
                    </div>
                    <div class="field third">
                        <label for="responsavel_cpf">CPF</label>
                        <input id="responsavel_cpf" class="form-control" name="responsavel_cpf" inputmode="numeric" autocomplete="off">
                    </div>
                    <div class="field third">
                        <label for="responsavel_rg">RG</label>
                        <input id="responsavel_rg" class="form-control" name="responsavel_rg" autocomplete="off">
                    </div>
                    <div class="field third">
                        <label for="responsavel_nascimento">Nascimento</label>
                        <input id="responsavel_nascimento" class="form-control" name="responsavel_nascimento" type="date" min="1900-01-01" max="<?php echo date("Y-m-d"); ?>">
                    </div>
                    <div class="field">
                        <label for="responsavel_whats">WhatsApp *</label>
                        <input id="responsavel_whats" class="form-control" name="responsavel_whats" required inputmode="tel" autocomplete="tel">
                    </div>
                    <div class="field">
                        <label for="responsavel_email">E-mail</label>
                        <input id="responsavel_email" class="form-control" name="responsavel_email" type="email" autocomplete="email">
                    </div>
                    <div class="field full">
                        <label for="responsavel_endereco">Endereço</label>
                        <input id="responsavel_endereco" class="form-control" name="responsavel_endereco" autocomplete="street-address">
                    </div>
                    <div class="field third">
                        <label for="responsavel_numero">Número</label>
                        <input id="responsavel_numero" class="form-control" name="responsavel_numero">
                    </div>
                    <div class="field third">
                        <label for="responsavel_bairro">Bairro</label>
                        <input id="responsavel_bairro" class="form-control" name="responsavel_bairro">
                    </div>
                    <div class="field third">
                        <label for="responsavel_cidade">Cidade</label>
                        <input id="responsavel_cidade" class="form-control" name="responsavel_cidade">
                    </div>
                    <div class="field">
                        <label for="responsavel_cep">CEP</label>
                        <input id="responsavel_cep" class="form-control" name="responsavel_cep" inputmode="numeric" autocomplete="postal-code">
                    </div>
                    <div class="field">
                        <label for="responsavel_complemento">Complemento</label>
                        <input id="responsavel_complemento" class="form-control" name="responsavel_complemento">
                    </div>
                </div>
            </section>

            <section class="section">
                <h2>Aluno</h2>
                <div class="grid">
                    <div class="field full">
                        <label for="nome_aluno">Nome completo *</label>
                        <input id="nome_aluno" class="form-control" name="nome_aluno" required autocomplete="off">
                    </div>
                    <div class="field third">
                        <label for="nascimento_aluno">Nascimento *</label>
                        <input id="nascimento_aluno" class="form-control" name="nascimento_aluno" type="date" required min="1900-01-01" max="<?php echo date("Y-m-d"); ?>">
                    </div>
                    <div class="field third">
                        <label for="cpf_aluno">CPF</label>
                        <input id="cpf_aluno" class="form-control" name="cpf_aluno" inputmode="numeric" autocomplete="off">
                    </div>
                    <div class="field third">
                        <label for="rg_aluno">RG</label>
                        <input id="rg_aluno" class="form-control" name="rg_aluno" autocomplete="off">
                    </div>
                    <div class="field">
                        <label for="horario">Horário da turma</label>
                        <?php echo form_dropdown("horario", $turmas, "", ["id" => "horario", "class" => "form-control"]); ?>
                    </div>
                    <div class="field">
                        <label for="tamanho_camisa">Tamanho da camiseta</label>
                        <input id="tamanho_camisa" class="form-control" name="tamanho_camisa" placeholder="Ex.: 14, P, M, G">
                    </div>
                    <div class="field">
                        <label for="data_inicio">Início do curso</label>
                        <input id="data_inicio" class="form-control" name="data_inicio" type="date" value="<?php echo esc($defaults["data_inicio"] ?? date("Y-m-d")); ?>">
                    </div>
                    <div class="field">
                        <label for="melhor_horario_ligacao">Melhor horário para ligação</label>
                        <?php echo form_dropdown("melhor_horario_ligacao", $melhor_horario_options, "", ["id" => "melhor_horario_ligacao", "class" => "form-control"]); ?>
                    </div>
                </div>
            </section>

            <section class="section">
                <h2>Curso e aceite</h2>
                <div class="summary">
                    <p><strong><?php echo esc($defaults["curso_nome"] ?? "ACADEMIA DE TREINAMENTO MIRIM"); ?></strong> Curso contratado</p>
                    <p><strong>R$ <?php echo number_format((float) ($defaults["valor_mensalidade"] ?? 237), 2, ",", "."); ?></strong> Mensalidade</p>
                    <p><strong>R$ <?php echo number_format((float) ($defaults["valor_inscricao"] ?? 100), 2, ",", "."); ?></strong> Inscrição</p>
                </div>

                <div class="grid" style="margin-top: 16px;">
                    <div class="field">
                        <label for="cidade_assinatura">Cidade da assinatura</label>
                        <input id="cidade_assinatura" class="form-control" name="cidade_assinatura" value="<?php echo esc($unidade_cidade); ?>">
                    </div>
                    <div class="field">
                        <label for="estado_assinatura">UF</label>
                        <input id="estado_assinatura" class="form-control" name="estado_assinatura" maxlength="2">
                    </div>
                    <div class="field full">
                        <label for="assinatura_contratante">Assinatura digital do responsável *</label>
                        <input id="assinatura_contratante" class="form-control" name="assinatura_contratante" required placeholder="Digite seu nome completo">
                    </div>
                    <div class="field full">
                        <label class="checkline">
                            <input type="checkbox" name="li_ciente" value="1" required>
                            <span>Confirmo que os dados informados são verdadeiros e autorizo o cadastro da matrícula online.</span>
                        </label>
                    </div>
                </div>
            </section>

            <div class="actions">
                <div id="gd-public-message" class="message"></div>
                <button id="gd-public-submit" class="btn" type="submit">Concluir matrícula</button>
            </div>
        <?php echo form_close(); ?>
    </main>

    <script>
        (function () {
            var form = document.getElementById("gd-public-matricula-form");
            var button = document.getElementById("gd-public-submit");
            var message = document.getElementById("gd-public-message");

            form.addEventListener("submit", function (event) {
                event.preventDefault();
                button.disabled = true;
                message.className = "message";
                message.textContent = "";

                fetch(form.action, {
                    method: "POST",
                    body: new FormData(form),
                    credentials: "same-origin",
                    headers: {"X-Requested-With": "XMLHttpRequest"}
                }).then(function (response) {
                    return response.json();
                }).then(function (result) {
                    if (result && result.success) {
                        message.className = "message ok";
                        message.textContent = "Matrícula recebida com sucesso. Nossa equipe dará continuidade pelo WhatsApp.";
                        form.reset();
                        return;
                    }

                    message.className = "message error";
                    message.textContent = result && result.message ? result.message : "Não foi possível concluir a matrícula.";
                    button.disabled = false;
                }).catch(function () {
                    message.className = "message error";
                    message.textContent = "Não foi possível concluir a matrícula. Verifique sua conexão e tente novamente.";
                    button.disabled = false;
                });
            });
        })();
    </script>
</body>
</html>
