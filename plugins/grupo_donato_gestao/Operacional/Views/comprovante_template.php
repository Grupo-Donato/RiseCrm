<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprovante de Pagamento - Grupo Donato</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Source Sans 3", "Inter", Arial, sans-serif; background: #EFE4D6; color: #0F121B; padding: 20px; }
        .comprovante-container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border: 1px solid #C59F70; }
        .header { text-align: center; margin-bottom: 30px; }
        .logo-text { font-size: 24px; font-weight: bold; color: #EFE4D6; background: #21293C; border: 2px solid #C59F70; padding: 15px; border-radius: 5px; display: inline-block; margin: 10px 0; }
        .logo-subtitle { font-size: 12px; color: #5D6677; }
        .titulo { text-align: center; font-size: 18px; font-weight: bold; margin-bottom: 30px; text-transform: uppercase; }
        .info-section { margin-bottom: 20px; }
        .info-row { display: flex; margin-bottom: 10px; padding: 8px 0; border-bottom: 1px solid #eee; }
        .info-label { font-weight: bold; width: 200px; min-width: 200px; }
        .info-value { flex: 1; border-bottom: 1px dotted #999; min-height: 20px; padding-bottom: 3px; }
        .checkbox-group { display: flex; gap: 20px; margin: 15px 0; flex-wrap: wrap; }
        .checkbox-item { display: flex; align-items: center; gap: 5px; }
        .checkbox-item input[type="checkbox"] { width: 18px; height: 18px; }
        .valor-section { background: #EFE4D6; padding: 15px; border: 2px solid #C59F70; margin: 20px 0; text-align: center; }
        .valor-label { font-weight: bold; margin-bottom: 10px; }
        .valor-value { font-size: 24px; font-weight: bold; color: #21293C; }
        .forma-pagamento { margin: 20px 0; }
        .footer-text { text-align: center; font-size: 11px; color: #5D6677; margin: 25px 0 15px; line-height: 1.6; }
        .slogan { text-align: center; font-weight: bold; color: #21293C; margin: 20px 0; }
        .contato { text-align: center; font-size: 12px; color: #5D6677; margin-top: 15px; }
        @media print {
            body { padding: 0; }
            .comprovante-container { border: none; box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="comprovante-container">
        <div class="header">
            <div class="logo-text">GRUPO DONATO</div>
            <div class="logo-subtitle">ACADEMIA DE TREINAMENTO MIRIM</div>
        </div>

        <div class="titulo">COMPROVANTE DE PAGAMENTO - GRUPO DONATO</div>

        <div class="info-section">
            <div class="info-row">
                <span class="info-label">CNPJ:</span>
                <span class="info-value">63.357.041/0001-50</span>
            </div>
            <div class="info-row">
                <span class="info-label">Tel/WhatsApp:</span>
                <span class="info-value">(11) 96399-8061</span>
            </div>
            <div class="info-row">
                <span class="info-label">E-mail:</span>
                <span class="info-value">contato@grupodonato.com.br</span>
            </div>
        </div>

        <div class="info-section">
            <div class="info-row">
                <span class="info-label">Nº do comprovante:</span>
                <span class="info-value"><?php echo esc($numero_comprovante ?? "XXXX"); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Data emissão:</span>
                <span class="info-value"><?php echo esc($data_emissao ?? "XX/XX/XXXX"); ?></span>
            </div>
        </div>

        <div class="info-section">
            <div class="info-row">
                <span class="info-label">Responsável:</span>
                <span class="info-value"><?php echo esc($responsavel_nome ?? ""); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">CPF:</span>
                <span class="info-value"><?php echo esc($responsavel_cpf ?? ""); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Aluno(a):</span>
                <span class="info-value"><?php echo esc($aluno_nome ?? ""); ?></span>
            </div>
            <?php if (!empty($aluno_nome_adicional)): ?>
                <div class="info-row">
                    <span class="info-label">Aluno(a):</span>
                    <span class="info-value"><?php echo esc($aluno_nome_adicional); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <div class="info-section">
            <div class="info-row">
                <span class="info-label">Mensalidade do Grupo Donato, referente à:</span>
                <span class="info-value"></span>
            </div>
            <div class="checkbox-group">
                <?php
                $mensalidade_num = isset($mensalidade_numero) ? (int) $mensalidade_numero : 1;
                for ($i = 1; $i <= 6; $i++):
                    $checked = $i === $mensalidade_num ? "checked" : "";
                    $style = $i === $mensalidade_num ? "color: #B23A3F; font-weight: bold;" : "";
                    ?>
                    <div class="checkbox-item">
                        <input type="checkbox" <?php echo $checked; ?> disabled>
                        <label style="<?php echo esc($style, "attr"); ?>"><?php echo (int) $i; ?>º Mensalidade</label>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <div class="valor-section">
            <div class="valor-label">Valor (R$):</div>
            <div class="valor-value"><?php echo esc("R$ " . number_format($valor ?? 0, 2, ",", ".")); ?></div>
        </div>

        <div class="forma-pagamento">
            <div class="info-row">
                <span class="info-label">Forma de pagamento:</span>
                <span class="info-value"></span>
            </div>
            <div class="checkbox-group">
                <?php
                $formas = ["BOLETO", "CRÉDITO", "DÉBITO", "PIX"];
                $forma_selecionada = $forma_pagamento ?? "";
                foreach ($formas as $forma):
                    $selected = mb_strtoupper($forma) === mb_strtoupper($forma_selecionada);
                    $checked = $selected ? "checked" : "";
                    $style = $selected ? "color: #B23A3F; font-weight: bold;" : "";
                    ?>
                    <div class="checkbox-item">
                        <input type="checkbox" <?php echo $checked; ?> disabled>
                        <label style="<?php echo esc($style, "attr"); ?>"><?php echo esc($forma); ?></label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="info-section">
            <div class="info-row">
                <span class="info-label">Conferido por:</span>
                <span class="info-value"><?php echo esc($conferido_por ?? ""); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Data conferência:</span>
                <span class="info-value"><?php echo esc($data_conferencia ?? ""); ?></span>
            </div>
        </div>

        <div class="footer-text">
            Este comprovante confirma o recebimento do valor referente à mensalidade indicada. Guarde-o para eventuais comprovações junto à instituição.
        </div>

        <div class="slogan">GRUPO DONATO - Formação, Disciplina e Cidadania.</div>

        <div class="contato">
            <strong>www.grupodonato.com.br</strong><br>
            <strong>@grupodonato</strong>
        </div>
    </div>
</body>
</html>
